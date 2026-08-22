<?php
require_once "head_student.php";

// Set page title for mobile header
$pageTitle = 'Frais Académiques';
$currentPage = 'frais_academiques';

// Récupérer les informations de l'étudiant
$studentId = $_SESSION['student_id'] ?? 0;
$studentMatricule = $_SESSION['student_matricule'] ?? '';
$promotionId = $_SESSION['promotion_id'] ?? 0;
$currentYear = $universite->getAnneeAcademiqueById($_SESSION['annee_acad']);

// Connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer la configuration FlexPay
$stmt_config = $connexion->query("SELECT flexpay_actif FROM configuration_universite LIMIT 1");
$config_universite = $stmt_config->fetch(PDO::FETCH_ASSOC);
$flexpay_actif = !empty($config_universite['flexpay_actif']);

// Récupérer les frais individuels de l'étudiant
$sql_individuels = "
    SELECT 
        af.id, 
        af.frais_id,
        af.montant_specifique,
        af.date_affectation,
        af.devise,
        af.statut_paiement,
        af.est_exempte,
        f.designation AS frais_designation, 
        f.est_echelonnable,
        f.montant AS montant_frais,
        f.devise AS devise_frais,
        f.lieu_paiement,
        cf.designation AS categorie_nom,
        aa.designation AS annee_academique,
        (SELECT COALESCE(SUM(pf.montant), 0) 
         FROM paiements_frais pf 
         WHERE pf.affectation_id = af.id 
         AND pf.matricule_etudiant = :matricule) AS montant_paye
    FROM affectation_frais af
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    WHERE af.matricule_etudiant = :matricule2
    AND af.est_exempte = 0
    AND f.annee_acad_id = :annee_acad_id
    ORDER BY f.designation";

$stmt_individuels = $connexion->prepare($sql_individuels);
$stmt_individuels->bindParam(':matricule', $studentMatricule);
$stmt_individuels->bindParam(':matricule2', $studentMatricule);
$stmt_individuels->bindParam(':annee_acad_id', $currentYear['idannee_acad']);
$stmt_individuels->execute();
$frais_individuels = $stmt_individuels->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les frais de promotion
$sql_promotion = "
    SELECT 
        af.id,
        af.frais_id,
        af.montant_specifique,
        af.date_affectation,
        af.devise,
        af.statut_paiement,
        af.est_exempte, 
        f.designation AS frais_designation, 
        f.est_echelonnable,
        f.montant AS montant_frais,
        f.devise AS devise_frais,
        f.lieu_paiement,
        cf.designation AS categorie_nom,
        aa.designation AS annee_academique,
        (SELECT COALESCE(SUM(pf.montant), 0) 
         FROM paiements_frais pf 
         WHERE pf.affectation_id = af.id 
         AND pf.matricule_etudiant = :matricule) AS montant_paye
    FROM affectation_frais af
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    WHERE af.promotion_id = :promotion_id
    AND af.matricule_etudiant IS NULL
    AND af.est_exempte = 0
    AND f.annee_acad_id = :annee_acad_id
    AND NOT EXISTS (
        SELECT 1 FROM affectation_frais af2 
        WHERE af2.frais_id = af.frais_id 
        AND af2.matricule_etudiant = :matricule2
    )
    ORDER BY f.designation";

$stmt_promotion = $connexion->prepare($sql_promotion);
$stmt_promotion->bindParam(':matricule', $studentMatricule);
$stmt_promotion->bindParam(':matricule2', $studentMatricule);
$stmt_promotion->bindParam(':promotion_id', $promotionId);
$stmt_promotion->bindParam(':annee_acad_id', $currentYear['idannee_acad']);
$stmt_promotion->execute();
$frais_promotion = $stmt_promotion->fetchAll(PDO::FETCH_ASSOC);

// Traitement des frais pour calculer les montants restants
function processFrais(&$frais) {
    foreach ($frais as &$affectation) {
        $montant_total = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
        $affectation['montant_total'] = $montant_total;
        $affectation['montant_restant'] = $montant_total - $affectation['montant_paye'];

        if ($affectation['montant_paye'] >= $montant_total) {
            $affectation['statut_paiement_etudiant'] = 'Complet';
        } elseif ($affectation['montant_paye'] > 0) {
            $affectation['statut_paiement_etudiant'] = 'Partiel';
        } else {
            $affectation['statut_paiement_etudiant'] = 'Non payé';
        }
    }
}

processFrais($frais_individuels);
processFrais($frais_promotion);

// Récupérer les affectations ayant déjà une déclaration en attente ou validée
$stmt_pending = $connexion->prepare("
    SELECT DISTINCT affectation_id FROM declarations_paiement 
    WHERE matricule_etudiant = :matricule AND statut_validation IN ('en_attente', 'validé')
");
$stmt_pending->bindParam(':matricule', $studentMatricule);
$stmt_pending->execute();
$pending_affectations = $stmt_pending->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les déclarations de paiement de l'étudiant
$sql_declarations = "
    SELECT dp.*, 
           f.designation AS frais_designation,
           af.id AS affectation_id,
           aa.designation AS annee_academique,
           CASE 
               WHEN dp.statut_validation = 'validé' THEN 'success'
               WHEN dp.statut_validation = 'rejeté' THEN 'danger'
               ELSE 'warning'
           END AS badge_color
    FROM declarations_paiement dp
    INNER JOIN affectation_frais af ON dp.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    WHERE dp.matricule_etudiant = :matricule
    ORDER BY dp.date_declaration DESC";

$stmt_declarations = $connexion->prepare($sql_declarations);
$stmt_declarations->bindParam(':matricule', $studentMatricule);
$stmt_declarations->execute();
$declarations = $stmt_declarations->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// CORRECTION: Calcul des totaux par devise
// ========================================
$totaux_par_devise = [];
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Fonction pour normaliser la devise
function normaliserDevise($devise) {
    if (empty($devise)) {
        return 'USD'; // Devise par défaut
    }
    // Nettoyer et convertir en majuscules
    $devise = strtoupper(trim($devise));
    
    // Normaliser les variantes courantes
    $normalisation = [
        'DOLLAR' => 'USD',
        'DOLLARS' => 'USD',
        '$' => 'USD',
        'FRANC' => 'CDF',
        'FRANCS' => 'CDF',
        'FC' => 'CDF',
    ];
    
    return $normalisation[$devise] ?? $devise;
}

if (!empty($frais_individuels) || !empty($frais_promotion)) {
    // Calculer les totaux à partir des frais individuels
    foreach ($frais_individuels as $frais) {
        // Déterminer la devise - PRIORITÉ à af.devise, puis f.devise
        $devise_brute = !empty($frais['devise']) ? $frais['devise'] : 
                        (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
        
        // Normaliser la devise
        $devise = normaliserDevise($devise_brute);
        
        // DEBUG
        if ($debug_mode) {
            echo "<!-- DEBUG Frais Individuel: " . htmlspecialchars($frais['frais_designation']) . 
                 " | Devise AF: " . ($frais['devise'] ?? 'NULL') . 
                 " | Devise Frais: " . ($frais['devise_frais'] ?? 'NULL') . 
                 " | Devise Brute: " . $devise_brute .
                 " | Devise Normalisée: " . $devise . 
                 " | Montant: " . $frais['montant_total'] . " -->\n";
        }
        
        // Initialiser le tableau pour cette devise si nécessaire
        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }
        
        // Additionner les montants
        $totaux_par_devise[$devise]['total_du'] += floatval($frais['montant_total']);
        $totaux_par_devise[$devise]['total_paye'] += floatval($frais['montant_paye']);
    }
    
    // Calculer les totaux à partir des frais de promotion
    foreach ($frais_promotion as $frais) {
        // Déterminer la devise - PRIORITÉ à af.devise, puis f.devise
        $devise_brute = !empty($frais['devise']) ? $frais['devise'] : 
                        (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
        
        // Normaliser la devise
        $devise = normaliserDevise($devise_brute);
        
        // DEBUG
        if ($debug_mode) {
            echo "<!-- DEBUG Frais Promotion: " . htmlspecialchars($frais['frais_designation']) . 
                 " | Devise AF: " . ($frais['devise'] ?? 'NULL') . 
                 " | Devise Frais: " . ($frais['devise_frais'] ?? 'NULL') . 
                 " | Devise Brute: " . $devise_brute .
                 " | Devise Normalisée: " . $devise . 
                 " | Montant: " . $frais['montant_total'] . " -->\n";
        }
        
        // Initialiser le tableau pour cette devise si nécessaire
        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }
        
        // Additionner les montants
        $totaux_par_devise[$devise]['total_du'] += floatval($frais['montant_total']);
        $totaux_par_devise[$devise]['total_paye'] += floatval($frais['montant_paye']);
    }
    
    // Calculer le solde restant pour chaque devise
    foreach ($totaux_par_devise as $devise => &$totaux) {
        $totaux['solde_restant'] = $totaux['total_du'] - $totaux['total_paye'];
        
        // DEBUG
        if ($debug_mode) {
            echo "<!-- DEBUG Total " . $devise . ": Dû=" . $totaux['total_du'] . 
                 " | Payé=" . $totaux['total_paye'] . 
                 " | Reste=" . $totaux['solde_restant'] . " -->\n";
        }
    }
    unset($totaux); // Libérer la référence
    
    // Trier les devises (USD en premier, puis CDF, puis les autres)
    uksort($totaux_par_devise, function($a, $b) {
        if ($a === 'USD') return -1;
        if ($b === 'USD') return 1;
        if ($a === 'CDF') return -1;
        if ($b === 'CDF') return 1;
        return strcmp($a, $b);
    });
}
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<div class="content-area">
    <div class="pagetitle">
        <h1><i class="fas fa-money-check-alt me-2"></i>Frais Académiques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="student">Accueil</a></li>
                <li class="breadcrumb-item active">Frais Académiques</li>
            </ol>
        </nav>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Résumé financier -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Situation Financière - <?= htmlspecialchars($currentYear['designation']) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($totaux_par_devise)): ?>
                        <div class="row">
                            <?php foreach ($totaux_par_devise as $devise => $totaux): 
                                $pourcentage = $totaux['total_du'] > 0 ? ($totaux['total_paye'] / $totaux['total_du']) * 100 : 0;
                                if ($pourcentage >= 100) {
                                    $badgeColor = 'success';
                                    $badgeText = 'Soldé';
                                } elseif ($pourcentage > 0) {
                                    $badgeColor = 'warning';
                                    $badgeText = 'En cours';
                                } else {
                                    $badgeColor = 'danger';
                                    $badgeText = 'Impayé';
                                }
                            ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-left-primary shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-2">
                                                        <i class="fas fa-<?= $devise === 'USD' ? 'dollar-sign' : ($devise === 'CDF' ? 'coins' : 'money-bill-wave') ?> me-1"></i>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </div>
                                                    <span class="badge bg-<?= $badgeColor ?> fs-6">
                                                        <i class="fas fa-<?= $badgeColor === 'success' ? 'check-circle' : ($badgeColor === 'warning' ? 'clock' : 'exclamation-circle') ?> me-1"></i>
                                                        <?= $badgeText ?>
                                                    </span>
                                                    <?php if ($totaux['total_du'] > 0): ?>
                                                    <small class="text-muted d-block mt-2">
                                                        <i class="fas fa-percentage me-1"></i><?= number_format($pourcentage, 0) ?>% payé
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($pourcentage < 100): ?>
                                                    <a href="#frais" class="btn btn-sm btn-outline-primary" data-bs-toggle="tab">
                                                        <i class="fas fa-eye me-1"></i> Voir détails
                                                    </a>
                                                    <?php else: ?>
                                                    <i class="fas fa-check-circle text-success fs-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun frais assigné pour cette année académique.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs pour les différentes sections -->
    <ul class="nav nav-pills mb-4" id="fraisTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="frais-tab" data-bs-toggle="tab" 
                    data-bs-target="#frais" type="button">
                <i class="fas fa-list me-1"></i> Mes Frais
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="declarations-tab" data-bs-toggle="tab" 
                    data-bs-target="#declarations" type="button">
                <i class="fas fa-file-invoice me-1"></i> Mes Déclarations
                <?php if (count($declarations) > 0): ?>
                    <span class="badge bg-danger ms-1"><?= count($declarations) ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="fraisTabContent">
        <!-- Tab Frais -->
        <div class="tab-pane fade show active" id="frais" role="tabpanel">
            <!-- Frais individuels -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Frais Individuels</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($frais_individuels)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun frais individuel assigné.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Frais</th>
                                        <th>Catégorie</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($frais_individuels as $frais): 
                                        $devise_brute = !empty($frais['devise']) ? $frais['devise'] : $frais['devise_frais'];
                                        $devise = normaliserDevise($devise_brute);
                                    ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($frais['frais_designation']) ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($devise) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($frais['categorie_nom'] ?? 'Non spécifiée') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $frais['statut_paiement_etudiant'] === 'Complet' ? 'success' : 
                                                ($frais['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                <i class="fas fa-<?= $frais['statut_paiement_etudiant'] === 'Complet' ? 'check-circle' : 
                                                    ($frais['statut_paiement_etudiant'] === 'Partiel' ? 'clock' : 'exclamation-circle') ?> me-1"></i>
                                                <?= htmlspecialchars($frais['statut_paiement_etudiant']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($frais['montant_restant'] > 0): ?>
                                                <?php if (in_array($frais['id'], $pending_affectations)): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-clock me-1"></i> Déclaration en attente
                                                    </span>
                                                <?php else: ?>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#declarerPaiementModal"
                                                            data-affectation-id="<?= $frais['id'] ?>"
                                                            data-frais-designation="<?= htmlspecialchars($frais['frais_designation']) ?>"
                                                            data-montant-restant="<?= $frais['montant_restant'] ?>"
                                                            data-devise="<?= htmlspecialchars($devise) ?>">
                                                        <i class="fas fa-plus-circle me-1"></i> Déclarer
                                                    </button>
                                                    <?php if ($flexpay_actif && isset($frais['lieu_paiement']) && $frais['lieu_paiement'] === 'Faculté'): ?>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                            onclick="ouvrirPaiementFlexPay(<?= $frais['id'] ?>, '<?= htmlspecialchars(addslashes($frais['frais_designation'])) ?>', <?= $frais['montant_restant'] ?>, '<?= htmlspecialchars($devise) ?>')">
                                                        <i class="fas fa-mobile-alt me-1"></i> Mobile Money
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Soldé
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Frais de promotion -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Frais de Promotion</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($frais_promotion)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun frais de promotion applicable.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Frais</th>
                                        <th>Catégorie</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($frais_promotion as $frais): 
                                        $devise_brute = !empty($frais['devise']) ? $frais['devise'] : $frais['devise_frais'];
                                        $devise = normaliserDevise($devise_brute);
                                    ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($frais['frais_designation']) ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($devise) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($frais['categorie_nom'] ?? 'Non spécifiée') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $frais['statut_paiement_etudiant'] === 'Complet' ? 'success' : 
                                                ($frais['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                <i class="fas fa-<?= $frais['statut_paiement_etudiant'] === 'Complet' ? 'check-circle' : 
                                                    ($frais['statut_paiement_etudiant'] === 'Partiel' ? 'clock' : 'exclamation-circle') ?> me-1"></i>
                                                <?= htmlspecialchars($frais['statut_paiement_etudiant']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($frais['montant_restant'] > 0): ?>
                                                <?php if (in_array($frais['id'], $pending_affectations)): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-clock me-1"></i> Déclaration en attente
                                                    </span>
                                                <?php else: ?>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#declarerPaiementModal"
                                                            data-affectation-id="<?= $frais['id'] ?>"
                                                            data-frais-designation="<?= htmlspecialchars($frais['frais_designation']) ?>"
                                                            data-montant-restant="<?= $frais['montant_restant'] ?>"
                                                            data-devise="<?= htmlspecialchars($devise) ?>">
                                                        <i class="fas fa-plus-circle me-1"></i> Déclarer
                                                    </button>
                                                    <?php if ($flexpay_actif && isset($frais['lieu_paiement']) && $frais['lieu_paiement'] === 'Faculté'): ?>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                            onclick="ouvrirPaiementFlexPay(<?= $frais['id'] ?>, '<?= htmlspecialchars(addslashes($frais['frais_designation'])) ?>', <?= $frais['montant_restant'] ?>, '<?= htmlspecialchars($devise) ?>')">
                                                        <i class="fas fa-mobile-alt me-1"></i> Mobile Money
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Soldé
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Déclarations -->
        <div class="tab-pane fade" id="declarations" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique des Déclarations</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($declarations)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Vous n'avez pas encore déclaré de paiement.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Frais</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                        <th>Référence</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($declarations as $declaration): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($declaration['date_declaration'])) ?></td>
                                        <td><?= htmlspecialchars($declaration['frais_designation']) ?></td>
                                        <td><?= number_format($declaration['montant'], 2, '.', ' ') ?> <?= htmlspecialchars($declaration['devise']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($declaration['mode_paiement']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($declaration['reference_paiement']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $declaration['badge_color'] ?>">
                                                <?= $declaration['statut_validation'] === 'en_attente' ? 'En attente' : 
                                                    ($declaration['statut_validation'] === 'validé' ? 'Validé' : 'Rejeté') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($declaration['preuve_paiement']): ?>
                                                <a href="../uploads/preuves_paiement/<?= htmlspecialchars($declaration['preuve_paiement']) ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-file-image me-1"></i> Voir preuve
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($declaration['commentaire_validation']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                        data-bs-toggle="popover" 
                                                        data-bs-trigger="hover"
                                                        data-bs-content="<?= htmlspecialchars($declaration['commentaire_validation']) ?>">
                                                    <i class="fas fa-comment"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour déclarer un paiement -->
<div class="modal fade" id="declarerPaiementModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-body p-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Déclarer un Paiement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controller/declarer_paiement_etudiant.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="affectation_id" id="declaration_affectation_id">
                    <input type="hidden" name="matricule_etudiant" value="<?= htmlspecialchars($studentMatricule) ?>">
                    
                    <!-- Info du frais -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Frais:</strong> <span id="declaration_frais_designation"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Montant restant:</strong> 
                                <span id="declaration_montant_restant"></span> 
                                <span id="declaration_devise"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Montant -->
                        <div class="col-md-6">
                            <label for="montant" class="form-label required">
                                <i class="fas fa-money-bill-wave me-1"></i>Montant payé
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" 
                                       id="declaration_montant" name="montant" required>
                                <span class="input-group-text" id="declaration_devise_input"></span>
                            </div>
                            <div class="invalid-feedback">Veuillez saisir le montant payé.</div>
                        </div>

                        <!-- Date de paiement -->
                        <div class="col-md-6">
                            <label for="date_paiement" class="form-label required">
                                <i class="fas fa-calendar-alt me-1"></i>Date du paiement
                            </label>
                            <input type="date" class="form-control" id="date_paiement" 
                                   name="date_paiement" max="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Veuillez sélectionner la date du paiement.</div>
                        </div>

                        <!-- Mode de paiement -->
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label required">
                                <i class="fas fa-credit-card me-1"></i>Mode de paiement
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner...</option>
                                <option value="Virement bancaire">Virement bancaire</option>
                                <option value="Dépôt bancaire">Dépôt bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Espèces">Espèces (autre caisse)</option>
                                <option value="Chèque">Chèque</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le mode de paiement.</div>
                        </div>

                        <!-- Lieu de paiement -->
                        <div class="col-md-6">
                            <label for="lieu_paiement" class="form-label required">
                                <i class="fas fa-map-marker-alt me-1"></i>Lieu de paiement
                            </label>
                            <input type="text" class="form-control" id="lieu_paiement" 
                                   name="lieu_paiement" placeholder="Ex: Equity Bank, Orange Money..." required>
                            <div class="invalid-feedback">Veuillez indiquer où le paiement a été effectué.</div>
                        </div>

                        <!-- Référence de paiement -->
                        <div class="col-md-12">
                            <label for="reference_paiement" class="form-label required">
                                <i class="fas fa-hashtag me-1"></i>Référence de paiement
                            </label>
                            <input type="text" class="form-control" id="reference_paiement" 
                                   name="reference_paiement" 
                                   placeholder="N° de transaction, N° de bordereau, N° de reçu..." required>
                            <div class="invalid-feedback">Veuillez saisir la référence du paiement.</div>
                        </div>

                        <!-- Preuve de paiement -->
                        <div class="col-md-12">
                            <label for="preuve_paiement" class="form-label required">
                                <i class="fas fa-file-upload me-1"></i>Preuve de paiement
                            </label>
                            <input type="file" class="form-control" id="preuve_paiement" 
                                   name="preuve_paiement" accept="image/*,.pdf" required>
                            <div class="form-text">
                                Formats acceptés: Images (JPG, PNG) ou PDF. Taille max: 5 Mo.
                            </div>
                            <div class="invalid-feedback">Veuillez joindre une preuve de paiement.</div>
                        </div>

                        <!-- Commentaire -->
                        <div class="col-md-12">
                            <label for="commentaire" class="form-label">
                                <i class="fas fa-comment me-1"></i>Commentaire (optionnel)
                            </label>
                            <textarea class="form-control" id="commentaire" name="commentaire" 
                                      rows="2" placeholder="Informations supplémentaires..."></textarea>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Votre déclaration sera vérifiée par l'administration avant validation. 
                        Assurez-vous que la preuve de paiement est lisible et correspond aux informations saisies.
                    </div>
                </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Soumettre la déclaration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal FlexPay Payment -->
<div class="modal fade" id="flexPayModal" tabindex="-1" aria-labelledby="flexPayModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="flexPayModalLabel">
                    <i class="fas fa-mobile-alt me-2"></i>Paiement Mobile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="fp_btn_close_header"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fp_affectation_id">
                <input type="hidden" id="fp_montant">
                <input type="hidden" id="fp_devise">

                <div class="text-center mb-4">
                    <h6 class="text-muted" id="fp_frais_nom"></h6>
                    <h3 class="fw-bold text-primary" id="fp_montant_display"></h3>
                </div>

                <div id="fp_zone_formulaire">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mode de paiement</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fp_type_paiement"
                                    id="fp_type_mobile" value="mobile_money" checked>
                                <label class="form-check-label" for="fp_type_mobile">
                                    <i class="fas fa-mobile-alt me-1"></i> Mobile Money
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fp_type_paiement"
                                    id="fp_type_carte" value="carte_bancaire">
                                <label class="form-check-label" for="fp_type_carte">
                                    <i class="fas fa-credit-card me-1"></i> Carte Bancaire
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="fp_phone_group">
                        <label for="fp_telephone" class="form-label fw-bold">Numéro de téléphone</label>
                        <input type="tel" class="form-control" id="fp_telephone"
                            placeholder="+243..." maxlength="15">
                        <div class="form-text">Entrez le numéro associé à votre compte Mobile Money.</div>
                    </div>
                </div>

                <div id="fp_zone_attente" class="text-center py-4" style="display: none;">
                    <div class="mb-3">
                        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                    <h5 class="text-primary">En attente de confirmation...</h5>
                    <p class="text-muted small">
                        Veuillez confirmer le paiement sur votre téléphone.<br>
                        Entrez votre mot de passe M-Pesa ou Airtel Money.<br>
                        Cette fenêtre se fermera automatiquement une fois le paiement confirmé.
                    </p>
                    <div class="progress mb-3" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" style="width: 100%"></div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="fp_btn_annuler_attente">
                        <i class="fas fa-times me-1"></i>Annuler et revenir
                    </button>
                </div>

                <div id="fp_zone_resultat" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer" id="fp_footer_initial">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fp_btn_fermer_initial">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" id="fp_btn_confirmer"
                    onclick="confirmerPaiementFlexPay()">
                    <i class="fas fa-check me-2"></i>Confirmer le paiement
                </button>
            </div>
            <div class="modal-footer" id="fp_footer_attente" style="display: none;">
                <button type="button" class="btn btn-secondary" id="fp_btn_fermer_resultat" style="display: none;">
                    <i class="fas fa-times me-2"></i>Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<?php include "includes/bottom_nav.php"; ?>

<script>
// Gestion du modal de déclaration
document.addEventListener('DOMContentLoaded', function() {
    const declarerPaiementModal = document.getElementById('declarerPaiementModal');
    if (declarerPaiementModal) {
        declarerPaiementModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const affectationId = button.getAttribute('data-affectation-id');
            const fraisDesignation = button.getAttribute('data-frais-designation');
            const montantRestant = parseFloat(button.getAttribute('data-montant-restant'));
            const devise = button.getAttribute('data-devise');

            document.getElementById('declaration_affectation_id').value = affectationId;
            document.getElementById('declaration_frais_designation').textContent = fraisDesignation;
            document.getElementById('declaration_montant_restant').textContent = montantRestant.toFixed(2);
            document.getElementById('declaration_devise').textContent = devise;
            document.getElementById('declaration_devise_input').textContent = devise;
            
            // Définir le montant maximum
            document.getElementById('declaration_montant').setAttribute('max', montantRestant);
            document.getElementById('declaration_montant').value = '';
        });
    }

    // Initialiser les popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    // Validation du formulaire + anti double-clic
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi...';
                }
            }
            form.classList.add('was-validated');
        }, false);
    });

});

// ===== FlexPay Payment Modal =====
let fpIntervalCheckStatut = null;
let fpCurrentOrderNumber = null;
let fpCurrentReference = null;

function ouvrirPaiementFlexPay(affectationId, fraisNom, montant, devise) {
    document.getElementById('fp_affectation_id').value = affectationId;
    document.getElementById('fp_montant_display').textContent = new Intl.NumberFormat('fr-FR').format(montant) + ' ' + devise;
    document.getElementById('fp_frais_nom').textContent = fraisNom;
    document.getElementById('fp_montant').value = montant;
    document.getElementById('fp_devise').value = devise;
    document.getElementById('fp_telephone').value = '';
    document.getElementById('fp_type_mobile').checked = true;
    document.getElementById('fp_phone_group').style.display = 'block';
    
    document.getElementById('fp_zone_formulaire').style.display = 'block';
    document.getElementById('fp_zone_attente').style.display = 'none';
    document.getElementById('fp_zone_resultat').style.display = 'none';
    document.getElementById('fp_footer_initial').style.display = 'flex';
    document.getElementById('fp_footer_attente').style.display = 'none';
    document.getElementById('fp_btn_confirmer').disabled = false;
    document.getElementById('fp_btn_fermer_resultat').style.display = 'none';
    
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('flexPayModal'));
    modal.show();
}

document.querySelectorAll('input[name="fp_type_paiement"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('fp_phone_group').style.display =
            this.value === 'mobile_money' ? 'block' : 'none';
    });
});

function confirmerPaiementFlexPay() {
    const affectationId = document.getElementById('fp_affectation_id').value;
    const typePaiement = document.querySelector('input[name="fp_type_paiement"]:checked').value;
    const telephone = document.getElementById('fp_telephone').value;

    if (typePaiement === 'mobile_money' && !telephone) {
        Swal.fire('Erreur', 'Veuillez entrer votre numéro de téléphone.', 'error');
        return;
    }

    const btn = document.getElementById('fp_btn_confirmer');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';

    fetch('../controller/flexpay_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            affectation_id: affectationId,
            telephone: telephone,
            type_paiement: typePaiement
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fpCurrentOrderNumber = data.order_number;
            fpCurrentReference = data.reference;
            
            document.getElementById('fp_zone_formulaire').style.display = 'none';
            document.getElementById('fp_zone_resultat').style.display = 'none';
            document.getElementById('fp_footer_initial').style.display = 'none';
            document.getElementById('fp_zone_attente').style.display = 'block';
            document.getElementById('fp_footer_attente').style.display = 'flex';
            
            let attempts = 0;
            const maxAttempts = 60; // 60 secondes
            
            fpIntervalCheckStatut = setInterval(async function() {
                attempts++;
                
                console.log('FlexPay check response - attempts:', attempts);
                
                try {
                    const response = await fetch(`../controller/flexpay_check.php?order_number=${encodeURIComponent(fpCurrentOrderNumber)}`);
                    const statutResult = await response.json();
                    
                    console.log('FlexPay check response:', statutResult);
                    
                    if (statutResult && statutResult.success) {
                        console.log('Statut du paiement:', statutResult.statut);
                        if (statutResult.statut === 'reussi') {
                            clearInterval(fpIntervalCheckStatut);
                            fpIntervalCheckStatut = null;
                            
                            document.getElementById('fp_zone_attente').style.display = 'none';
                            const resultatDiv = document.getElementById('fp_zone_resultat');
                            resultatDiv.style.display = 'block';
                            resultatDiv.className = 'alert alert-success';
                            resultatDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + 
                                '<strong>Paiement confirmé avec succès!</strong><br>' +
                                '<small>Référence: ' + fpCurrentReference + '</small>';
                            
                            document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                            document.getElementById('fp_btn_fermer_resultat').onclick = function() {
                                window.location.reload();
                            };
                            document.getElementById('fp_btn_fermer_resultat').innerHTML = '<i class="fas fa-check me-2"></i>Fermer et actualiser';
                        } else if (statutResult.statut === 'echoue') {
                            clearInterval(fpIntervalCheckStatut);
                            fpIntervalCheckStatut = null;
                            
                            document.getElementById('fp_zone_attente').style.display = 'none';
                            const resultatDiv = document.getElementById('fp_zone_resultat');
                            resultatDiv.style.display = 'block';
                            const messageErreur = statutResult.message_detaille || 'Le paiement a échoué.';
                            resultatDiv.className = 'alert alert-danger';
                            resultatDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i>' + 
                                '<strong>Paiement échoué</strong><br>' +
                                '<small>' + messageErreur + '</small>';
                            
                            document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                        }
                    }
                    
                    if (attempts >= maxAttempts) {
                        clearInterval(fpIntervalCheckStatut);
                        fpIntervalCheckStatut = null;
                        
                        document.getElementById('fp_zone_attente').style.display = 'none';
                        const resultatDiv = document.getElementById('fp_zone_resultat');
                        resultatDiv.style.display = 'block';
                        resultatDiv.className = 'alert alert-warning';
                        resultatDiv.innerHTML = '<i class="fas fa-clock me-2"></i>' + 
                            '<strong>Délai d\'attente dépassé</strong><br>' +
                            '<small>Le paiement n\'a pas été confirmé. Veuillez vérifier votre téléphone.</small><br>' +
                            '<button class="btn btn-outline-primary btn-sm mt-2" id="fp_btn_verifier_timeout">' +
                            '<i class="fas fa-sync-alt me-1"></i>Vérifier le statut</button>';
                        
                        document.getElementById('fp_btn_verifier_timeout').onclick = async function() {
                            const finalResult = await fetch(`../controller/flexpay_check.php?order_number=${encodeURIComponent(fpCurrentOrderNumber)}`).then(r => r.json());
                            if (finalResult && finalResult.success && finalResult.statut === 'reussi') {
                                resultatDiv.className = 'alert alert-success';
                                resultatDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Paiement confirmé!</strong>';
                                setTimeout(() => window.location.reload(), 2000);
                            } else if (finalResult && finalResult.success && finalResult.statut === 'echoue') {
                                resultatDiv.className = 'alert alert-danger';
                                resultatDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i><strong>Paiement échoué</strong>';
                            }
                        };
                        
                        document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                    }
                } catch (error) {
                    console.error('Erreur vérification:', error);
                }
            }, 1000);
            
        } else {
            Swal.fire('Erreur', data.message || 'Une erreur est survenue.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        Swal.fire('Erreur', 'Erreur de connexion au serveur.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

document.getElementById('fp_btn_annuler_attente')?.addEventListener('click', function() {
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
    document.getElementById('fp_zone_attente').style.display = 'none';
    document.getElementById('fp_zone_formulaire').style.display = 'block';
    document.getElementById('fp_footer_initial').style.display = 'flex';
    document.getElementById('fp_btn_confirmer').disabled = false;
});

document.getElementById('flexPayModal')?.addEventListener('hide.bs.modal', function() {
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
});
</script>

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<?php require_once "footer_student.php"; ?>