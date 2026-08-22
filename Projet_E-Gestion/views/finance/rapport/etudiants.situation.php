<?php include "./views/include/header.php"; ?>

<?php
// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer l'ID de l'étudiant depuis l'URL
$etudiantId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$etudiantId) {
    echo '<div class="alert alert-danger">ID étudiant non spécifié</div>';
    exit;
}

// Récupérer les informations de base de l'étudiant
$sqlEtudiant = "SELECT 
                    e.*, 
                    p.\"designationPromotion\", 
                    s.\"designationSection\",
                    a.designation as annee_academique,
                    p.idpromotion as promotion_id
                FROM etudiant e
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                JOIN section s ON o.section_idsection = s.idsection
                JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
                WHERE e.idetudiant = :etudiantId";

$stmtEtudiant = $connexion->prepare($sqlEtudiant);
$stmtEtudiant->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
$stmtEtudiant->execute();
$etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    echo '<div class="alert alert-danger">Étudiant non trouvé</div>';
    exit;
}

// Récupérer les caisses actives
$stmt = $connexion->prepare("SELECT id, designation, devise FROM caisses WHERE est_actif = 1 ORDER BY designation");
$stmt->execute();
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les comptes bancaires actifs
$stmt = $connexion->prepare("SELECT id, nom_banque, intitule_compte, numero_compte, devise FROM comptes_bancaires WHERE est_actif = 1 ORDER BY nom_banque, intitule_compte");
$stmt->execute();
$comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

$matricule = $etudiant['matricule'];
$promotion_id = $etudiant['promotion_id'];
$frais_individuels = [];
$frais_promotion = [];

// 1. Récupérer les frais individuels de l'étudiant
$stmt_individuels = $connexion->prepare("
    SELECT 
        af.id, 
        af.frais_id,
        af.promotion_id,
        af.matricule_etudiant,
        af.montant_specifique,
        af.date_affectation,
        af.devise,
        af.statut_paiement,
        af.est_exempte,
        f.designation AS frais_designation, 
        f.est_echelonnable,
        f.montant AS montant_frais,
        f.devise AS devise_frais,
        cf.designation AS categorie_nom,
        aa.designation AS annee_academique,
        p.\"designationPromotion\" AS promotion_nom,
        (SELECT COALESCE(SUM(pf.montant), 0) 
         FROM paiements_frais pf 
         WHERE pf.affectation_id = af.id 
         AND pf.matricule_etudiant = :matricule) AS montant_paye
    FROM affectation_frais af
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
    WHERE af.matricule_etudiant = :matricule
    AND af.est_exempte = 0
");

$stmt_individuels->bindParam(':matricule', $matricule);
$stmt_individuels->execute();
$frais_individuels = $stmt_individuels->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupérer les frais de promotion (sans les frais déjà affectés individuellement)
$stmt_promotion = $connexion->prepare("
    SELECT 
        af.id,
        af.frais_id,
        af.promotion_id,
        af.matricule_etudiant,
        af.montant_specifique,
        af.date_affectation,
        af.devise,
        af.statut_paiement,
        af.est_exempte, 
        f.designation AS frais_designation, 
        f.est_echelonnable,
        f.montant AS montant_frais,
        f.devise AS devise_frais,
        cf.designation AS categorie_nom,
        aa.designation AS annee_academique,
        p.\"designationPromotion\" AS promotion_nom,
        (SELECT COALESCE(SUM(pf.montant), 0) 
         FROM paiements_frais pf 
         WHERE pf.affectation_id = af.id 
         AND pf.matricule_etudiant = :matricule) AS montant_paye
    FROM affectation_frais af
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
    WHERE af.promotion_id = :promotion_id
    AND af.matricule_etudiant IS NULL
    AND af.est_exempte = 0
    AND NOT EXISTS (
        SELECT 1 FROM affectation_frais af2 
        WHERE af2.frais_id = af.frais_id 
        AND af2.matricule_etudiant = :matricule2
    )
");

$stmt_promotion->bindParam(':matricule', $matricule);
$stmt_promotion->bindParam(':matricule2', $matricule);
$stmt_promotion->bindParam(':promotion_id', $promotion_id);
$stmt_promotion->execute();
$frais_promotion = $stmt_promotion->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour calculer les montants restants et statuts pour un tableau de frais
function processFrais(&$frais) {
    foreach ($frais as &$affectation) {
        // Déterminer le montant total du frais
        $montant_total = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
        $affectation['montant_total'] = $montant_total;
        $affectation['montant_restant'] = $montant_total - $affectation['montant_paye'];

        // Mise à jour du statut de paiement basé sur les paiements réels de l'étudiant
        if ($affectation['montant_paye'] >= $montant_total) {
            $affectation['statut_paiement_etudiant'] = 'Complet';
        } elseif ($affectation['montant_paye'] > 0) {
            $affectation['statut_paiement_etudiant'] = 'Partiel';
        } else {
            $affectation['statut_paiement_etudiant'] = 'Non payé';
        }

        // Initialiser un tableau vide pour les tranches
        $affectation['tranches'] = [];
    }
}

// Traitement des frais individuels et des frais de promotion
processFrais($frais_individuels);
processFrais($frais_promotion);

// Récupérer les tranches pour les frais échelonnables
function loadTranches(&$frais, $connexion, $matricule) {
    foreach ($frais as &$affectation) {
        // Ne traiter que les frais échelonnables
        if ($affectation['est_echelonnable'] == 1) {
            $stmt = $connexion->prepare("
                SELECT ep.*, 
                       (SELECT COALESCE(SUM(pt.montant), 0) 
                        FROM paiements_tranches pt
                        JOIN paiements_frais pf ON pt.paiement_id = pf.id
                        WHERE pt.echelonnement_id = ep.id 
                        AND pf.matricule_etudiant = :matricule) AS montant_paye
                FROM echelonnement_paiement ep
                WHERE ep.affectation_id = :affectation_id
                ORDER BY ep.numero_tranche
            ");
            $stmt->bindParam(':affectation_id', $affectation['id']);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->execute();
            $affectation['tranches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer le montant restant pour chaque tranche
            foreach ($affectation['tranches'] as &$tranche) {
                $tranche['montant_restant'] = $tranche['montant'] - $tranche['montant_paye'];

                // Mettre à jour le statut de la tranche
                if ($tranche['montant_paye'] >= $tranche['montant']) {
                    $tranche['statut_paiement'] = 'Complet';
                } elseif ($tranche['montant_paye'] > 0) {
                    $tranche['statut_paiement'] = 'Partiel';
                } else {
                    $tranche['statut_paiement'] = 'Non payé';
                }
            }
        }
    }
}

// Chargement des tranches
loadTranches($frais_individuels, $connexion, $matricule);
loadTranches($frais_promotion, $connexion, $matricule);

// Récupérer l'historique des paiements
$sqlPaiements = "
    SELECT pf.*, 
           af.id AS affectation_id,
           f.designation AS frais_designation,
           t.reference AS transaction_reference,
           t.date_transaction,
           t.source,
           t.source_id,
           u.\"nomUser\" AS agent_nom,
           CASE 
               WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
               WHEN t.source = 'Banque' THEN (SELECT CONCAT(nom_banque, ' - ', intitule_compte) FROM comptes_bancaires WHERE id = t.source_id)
               ELSE 'Non spécifié'
           END AS source_nom
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN t_users u ON t.\"idUser\" = u.\"idUser\"
    WHERE pf.matricule_etudiant = :matricule
    ORDER BY t.date_transaction DESC
";
$stmtPaiements = $connexion->prepare($sqlPaiements);
$stmtPaiements->bindParam(':matricule', $matricule);
$stmtPaiements->execute();
$historique_paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

// Calcul du solde total de l'étudiant par devise
$totaux_par_devise = [];

// Calculer les totaux à partir des frais individuels
foreach ($frais_individuels as $frais) {
    // Déterminer la devise avec une logique plus robuste
    $devise = !empty($frais['devise']) ? $frais['devise'] : 
             (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
    
    // Nettoyer la devise (enlever les espaces)
    $devise = trim($devise);
    if (empty($devise)) {
        $devise = 'USD'; // Devise par défaut
    }
    
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'total_du' => 0,
            'total_paye' => 0,
            'solde_restant' => 0
        ];
    }
    
    $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
    $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
}

// Calculer les totaux à partir des frais de promotion
foreach ($frais_promotion as $frais) {
    // Déterminer la devise avec une logique plus robuste
    $devise = !empty($frais['devise']) ? $frais['devise'] : 
             (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
    
    // Nettoyer la devise (enlever les espaces)
    $devise = trim($devise);
    if (empty($devise)) {
        $devise = 'USD'; // Devise par défaut
    }
    
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'total_du' => 0,
            'total_paye' => 0,
            'solde_restant' => 0
        ];
    }
    
    $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
    $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
}

// Calculer le solde restant pour chaque devise
foreach ($totaux_par_devise as $devise => &$totaux) {
    $totaux['solde_restant'] = $totaux['total_du'] - $totaux['total_paye'];
}

// Maintenir la compatibilité avec l'ancien code (pour USD par défaut)
$total_du = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_du'] : 0;
$total_paye = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_paye'] : 0;
$solde_total = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['solde_restant'] : 0;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Situation financière de l'étudiant</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item"><a href="finance/rapport/index">Rapports</a></li>
                <li class="breadcrumb-item active">Situation étudiant</li>
            </ol>
        </nav>
    </div>

    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <?php if (!empty($etudiant['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($etudiant['photo']); ?>" alt="Photo" class="rounded-circle">
                        <?php else: ?>
                            <img src="uploads/user.png" alt="Photo" class="rounded-circle">
                        <?php endif; ?>
                        <h2><?php echo htmlspecialchars($etudiant['noms']); ?></h2>
                        <h3><?php echo htmlspecialchars($etudiant['matricule']); ?></h3>
                        <div class="social-links mt-2">
                            <a href="" class="twitter"><i class="bi bi-twitter"></i></a>
                            <a href="" class="facebook"><i class="bi bi-facebook"></i></a>
                            <a href="" class="instagram"><i class="bi bi-instagram"></i></a>
                            <a href="" class="linkedin"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de l'étudiant</h5>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Nom complet</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['noms']); ?></div>
                            </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Matricule</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['matricule']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Promotion</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['designationPromotion']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Section</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['designationSection']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Année académique</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['annee_academique']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Date de naissance</div>
                            <div class="col-lg-8 col-md-8"><?php echo $etudiant['dateNaissance'] ? date('d/m/Y', strtotime($etudiant['dateNaissance'])) : '-'; ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Lieu de naissance</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['lieuNaissance'] ?? '-'); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Téléphone</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['telephone'] ?? '-'); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-md-4 label">Email</div>
                            <div class="col-lg-8 col-md-8"><?php echo htmlspecialchars($etudiant['adressemail'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-currency-exchange me-2"></i>
                            Résumé financier par devise
                        </h5>
                        
                        <?php if (!empty($totaux_par_devise)): ?>
                            <?php foreach ($totaux_par_devise as $devise_courante => $totaux_devise): ?>
                                <div class="mb-3 p-2 border rounded">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-cash-coin me-1"></i>
                                        <?= htmlspecialchars($devise_courante) ?>
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-lg-6 col-md-6 label">Total dû</div>
                                        <div class="col-lg-6 col-md-6 fw-bold"><?= number_format($totaux_devise['total_du'], 2, ',', ' ') ?> <?= htmlspecialchars($devise_courante) ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-6 col-md-6 label">Total payé</div>
                                        <div class="col-lg-6 col-md-6 fw-bold text-success"><?= number_format($totaux_devise['total_paye'], 2, ',', ' ') ?> <?= htmlspecialchars($devise_courante) ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-6 col-md-6 label">Solde restant</div>
                                        <div class="col-lg-6 col-md-6 fw-bold <?= $totaux_devise['solde_restant'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= number_format($totaux_devise['solde_restant'], 2, ',', ' ') ?> <?= htmlspecialchars($devise_courante) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($totaux_devise['total_du'] > 0): ?>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <div class="progress" style="height: 8px;">
                                                    <?php 
                                                    $pourcentage = ($totaux_devise['total_paye'] / $totaux_devise['total_du']) * 100;
                                                    $pourcentage = min($pourcentage, 100); // Limiter à 100% maximum
                                                    ?>
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pourcentage ?>%" 
                                                         aria-valuenow="<?= $pourcentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <div class="text-center mt-1">
                                                    <small class="text-muted"><?= round($pourcentage) ?>% payé</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun frais assigné à cet étudiant.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="controller/situation_etudiant.php?id=<?php echo $etudiantId; ?>" target="_blank" class="btn btn-primary">
                                <i class="bi bi-printer"></i> Imprimer la situation
                            </a>
                            <a href="finance/paiements_etudiants?type_recherche=matricule&matricule=<?php echo $matricule; ?>" class="btn btn-success">
                                <i class="bi bi-cash-coin"></i> Effectuer un paiement
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <!-- Tabs navs -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="frais-tab" data-bs-toggle="tab" data-bs-target="#frais" 
                                        type="button" role="tab" aria-controls="frais" aria-selected="true">
                                    Frais affectés
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="paiements-tab" data-bs-toggle="tab" data-bs-target="#paiements" 
                                        type="button" role="tab" aria-controls="paiements" aria-selected="false">
                                    Historique des paiements
                                </button>
                            </li>
                        </ul>
                        <!-- Tabs content -->
                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="frais" role="tabpanel" aria-labelledby="frais-tab">
                                <!-- Frais individuels -->
                                <div class="mb-4">
                                    <h5 class="card-title">Frais individuels</h5>
                                    
                                    <?php if (empty($frais_individuels)): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Aucun frais individuel assigné à cet étudiant.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Frais</th>
                                                        <th>Catégorie</th>
                                                        <th>Année académique</th>
                                                        <th>Montant total</th>
                                                        <th>Montant payé</th>
                                                        <th>Reste à payer</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($frais_individuels as $affectation): 
                                                        $devise = $affectation['devise'] ?: $affectation['devise_frais'];
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($affectation['frais_designation']) ?></td>
                                                        <td><?= htmlspecialchars($affectation['categorie_nom'] ?? 'Non spécifiée') ?></td>
                                                        <td><?= htmlspecialchars($affectation['annee_academique'] ?? 'Non spécifiée') ?></td>
                                                        <td>
                                                            <?= number_format($affectation['montant_total'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($affectation['montant_paye'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($affectation['montant_restant'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $affectation['statut_paiement_etudiant'] === 'Complet' ? 'success' : ($affectation['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                <?= htmlspecialchars($affectation['statut_paiement_etudiant']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($affectation['est_echelonnable'] == 1): ?>
                                                                <!-- Bouton pour afficher les tranches -->
                                                                <button type="button" class="btn btn-sm btn-info mb-1" data-bs-toggle="collapse" data-bs-target="#tranches_ind_<?= $affectation['id'] ?>">
                                                                    <i class="bi bi-list-check"></i> Voir tranches
                                                                    <?php if (empty($affectation['tranches'])): ?>
                                                                        <span class="badge bg-warning">0</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-dark"><?= count($affectation['tranches']) ?></span>
                                                                    <?php endif; ?>
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="finance/paiements_etudiants?type_recherche=matricule&matricule=<?= $matricule ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-cash"></i> Payer
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php if ($affectation['est_echelonnable'] == 1 && !empty($affectation['tranches'])): ?>
                                                    <!-- Ligne pour afficher les tranches (cachée par défaut) -->
                                                    <tr class="collapse" id="tranches_ind_<?= $affectation['id'] ?>">
                                                        <td colspan="8" class="p-0">
                                                            <div class="p-3 border-top border-bottom bg-light">
                                                                <h6 class="mb-3">Tranches de paiement pour <?= htmlspecialchars($affectation['frais_designation']) ?></h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered">
                                                                        <thead>
                                                                            <tr class="table-secondary">
                                                                                <th>N°</th>
                                                                                <th>Désignation</th>
                                                                                <th>Échéance</th>
                                                                                <th>Montant</th>
                                                                                <th>Payé</th>
                                                                                <th>Reste</th>
                                                                                <th>Statut</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($affectation['tranches'] as $tranche): 
                                                                                $tranche_restant = $tranche['montant'] - $tranche['montant_paye'];
                                                                            ?>
                                                                            <tr>
                                                                                <td><?= $tranche['numero_tranche'] ?></td>
                                                                                <td><?= htmlspecialchars($tranche['designation']) ?></td>
                                                                                <td><?= isset($tranche['date_echeance']) ? date('d/m/Y', strtotime($tranche['date_echeance'])) : 'N/A' ?></td>
                                                                                <td><?= number_format($tranche['montant'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td><?= number_format($tranche['montant_paye'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td><?= number_format($tranche_restant, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?= $tranche['statut_paiement'] === 'Complet' ? 'success' : ($tranche['statut_paiement'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                                        <?= htmlspecialchars($tranche['statut_paiement']) ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if ($tranche_restant > 0): ?>
                                                                                        <a href="finance/paiements_etudiants?type_recherche=matricule&matricule=<?= $matricule ?>" class="btn btn-sm btn-primary">
                                                                                            <i class="bi bi-cash"></i> Payer                                                                                        </a>
                                                                                    <?php else: ?>
                                                                                        <button type="button" class="btn btn-sm btn-success" disabled>
                                                                                            <i class="bi bi-check-circle"></i> Payé
                                                                                        </button>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Frais de promotion -->
                                <div class="mb-4">
                                    <h5 class="card-title">Frais de promotion</h5>
                                    
                                    <?php if (empty($frais_promotion)): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Aucun frais de promotion applicable à cet étudiant.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Frais</th>
                                                        <th>Catégorie</th>
                                                        <th>Année académique</th>
                                                        <th>Promotion</th>
                                                        <th>Montant total</th>
                                                        <th>Montant payé</th>
                                                        <th>Reste à payer</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($frais_promotion as $affectation): 
                                                        $devise = $affectation['devise'] ?: $affectation['devise_frais'];
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($affectation['frais_designation']) ?></td>
                                                        <td><?= htmlspecialchars($affectation['categorie_nom'] ?? 'Non spécifiée') ?></td>
                                                        <td><?= htmlspecialchars($affectation['annee_academique'] ?? 'Non spécifiée') ?></td>
                                                        <td><?= htmlspecialchars($affectation['promotion_nom'] ?? 'Non spécifiée') ?></td>
                                                        <td>
                                                            <?= number_format($affectation['montant_total'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($affectation['montant_paye'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($affectation['montant_restant'], 2) ?> 
                                                            <?= htmlspecialchars($devise) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $affectation['statut_paiement_etudiant'] === 'Complet' ? 'success' : ($affectation['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                <?= htmlspecialchars($affectation['statut_paiement_etudiant']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($affectation['est_echelonnable'] == 1): ?>
                                                                <!-- Bouton pour afficher les tranches -->
                                                                <button type="button" class="btn btn-sm btn-info mb-1" data-bs-toggle="collapse" data-bs-target="#tranches_prom_<?= $affectation['id'] ?>">
                                                                    <i class="bi bi-list-check"></i> Voir tranches
                                                                    <?php if (empty($affectation['tranches'])): ?>
                                                                        <span class="badge bg-warning">0</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-dark"><?= count($affectation['tranches']) ?></span>
                                                                    <?php endif; ?>
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="finance/paiements_etudiants?type_recherche=matricule&matricule=<?= $matricule ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-cash"></i> Payer
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php if ($affectation['est_echelonnable'] == 1 && !empty($affectation['tranches'])): ?>
                                                    <!-- Ligne pour afficher les tranches (cachée par défaut) -->
                                                    <tr class="collapse" id="tranches_prom_<?= $affectation['id'] ?>">
                                                        <td colspan="9" class="p-0">
                                                            <div class="p-3 border-top border-bottom bg-light">
                                                                <h6 class="mb-3">Tranches de paiement pour <?= htmlspecialchars($affectation['frais_designation']) ?></h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered">
                                                                        <thead>
                                                                            <tr class="table-secondary">
                                                                                <th>N°</th>
                                                                                <th>Désignation</th>
                                                                                <th>Échéance</th>
                                                                                <th>Montant</th>
                                                                                <th>Payé</th>
                                                                                <th>Reste</th>
                                                                                <th>Statut</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($affectation['tranches'] as $tranche): 
                                                                                $tranche_restant = $tranche['montant'] - $tranche['montant_paye'];
                                                                            ?>
                                                                            <tr>
                                                                                <td><?= $tranche['numero_tranche'] ?></td>
                                                                                <td><?= htmlspecialchars($tranche['designation']) ?></td>
                                                                                <td><?= isset($tranche['date_echeance']) ? date('d/m/Y', strtotime($tranche['date_echeance'])) : 'N/A' ?></td>
                                                                                <td><?= number_format($tranche['montant'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td><?= number_format($tranche['montant_paye'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td><?= number_format($tranche_restant, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?= $tranche['statut_paiement'] === 'Complet' ? 'success' : ($tranche['statut_paiement'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                                        <?= htmlspecialchars($tranche['statut_paiement']) ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if ($tranche_restant > 0): ?>
                                                                                        <a href="finance/paiements_etudiants?type_recherche=matricule&matricule=<?= $matricule ?>" class="btn btn-sm btn-primary">
                                                                                            <i class="bi bi-cash"></i> Payer
                                                                                        </a>
                                                                                    <?php else: ?>
                                                                                        <button type="button" class="btn btn-sm btn-success" disabled>
                                                                                            <i class="bi bi-check-circle"></i> Payé
                                                                                        </button>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="paiements" role="tabpanel" aria-labelledby="paiements-tab">
                                <?php if (count($historique_paiements) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover datatable">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Frais</th>
                                                    <th>Montant</th>
                                                    <th>Mode</th>
                                                    <th>Source</th>
                                                    <th>Référence</th>
                                                    <th>Agent</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historique_paiements as $paiement): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y H:i', strtotime($paiement['date_transaction'])) ?></td>
                                                        <td><?= htmlspecialchars($paiement['frais_designation']) ?></td>
                                                        <td>
                                                            <?= number_format($paiement['montant'], 2) ?>
                                                            <?= htmlspecialchars($paiement['devise']) ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($paiement['mode_paiement']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $paiement['source'] === 'Caisse' ? 'success' : 'primary' ?>">
                                                                <?= htmlspecialchars($paiement['source']) ?>
                                                            </span>
                                                            <?= htmlspecialchars($paiement['source_nom']) ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($paiement['reference_externe'] ?: $paiement['transaction_reference']) ?></td>
                                                        <td><?= htmlspecialchars($paiement['agent_nom'] ?? 'Non spécifié') ?></td>
                                                        <td>
                                                            <a href="controller/generer_recu.php?id=<?= $paiement['id'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                                <i class="bi bi-printer"></i> Reçu
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i> Aucun paiement n'a été enregistré pour cet étudiant.
                                    </div>
                                <?php endif; ?>
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
        // Initialiser DataTable pour une meilleure visualisation du tableau
        if (typeof $.fn.DataTable !== 'undefined') {
            $('.datatable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json'
                },
                pageLength: 10,
                ordering: true,
                responsive: true
            });
        }
    });
</script>

<style>
    /* Style pour les cartes d'information */
    .profile-card img {
        width: 120px;
        height: 120px;
        object-fit: cover;
    }
    
    /* Style pour les tableaux */
    .table th {
        background-color: #f8f9fa;
    }
    
    /* Style pour les badges */
    .badge {
        font-weight: normal;
        padding: 5px 8px;
    }
    
    /* Style pour l'accordéon */
    .accordion-button:not(.collapsed) {
        background-color: #e7f1ff;
        color: #0d6efd;
    }
    
    /* Style pour les onglets */
    .nav-tabs .nav-link.active {
        font-weight: bold;
    }
    
    /* Style pour l'impression */
    @media print {
        .pagetitle, .breadcrumb, .nav-tabs, .card-title, .btn,
        #main-navbar, #sidebar, .footer {
            display: none !important;
        }
        
        .card {
            box-shadow: none !important;
            border: none !important;
        }
        
        .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        body {
            padding: 0;
            background: white;
        }
        
        .main {
            padding: 0;
            margin: 0;
        }
    }
</style>

<?php include "./views/include/footer.php"; ?>


