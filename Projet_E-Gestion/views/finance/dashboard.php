<?php
include "./views/include/header.php";

// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupération de l'année académique en cours ou sélectionnée
$annee_acad_id = isset($_GET['annee_acad_id']) ? intval($_GET['annee_acad_id']) : null;

// Si aucune année n'est sélectionnée, on prend l'année en cours
if (empty($annee_acad_id)) {
    $stmt = $connexion->prepare("SELECT idannee_acad FROM annee_acad ORDER BY designation DESC LIMIT 1");
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    $annee_acad_id = $current_year['idannee_acad'] ?? 0;
}

// Récupérer les années académiques pour le filtre
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails de l'année académique sélectionnée
$stmt = $connexion->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = :id");
$stmt->bindParam(':id', $annee_acad_id);
$stmt->execute();
$annee_info = $stmt->fetch(PDO::FETCH_ASSOC);
$annee_designation = $annee_info['designation'] ?? "Toutes les années";

// Période pour les statistiques (par défaut: année en cours)
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-01-01');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');

// === INDICATEURS FINANCIERS CLÉS ===

// 1. Total des recettes (frais académiques perçus)
$stmt = $connexion->prepare("
    SELECT SUM(pf.montant) as total_recettes
    FROM paiements_frais pf
    JOIN transactions t ON pf.transaction_id = t.id
    WHERE t.date_transaction BETWEEN :date_debut AND :date_fin
    AND t.type = 'Recette'
");
$stmt->bindParam(':date_debut', $date_debut);
$stmt->bindParam(':date_fin', $date_fin);
$stmt->execute();
$total_recettes = $stmt->fetch(PDO::FETCH_ASSOC)['total_recettes'] ?? 0;

// 2. Total des dépenses
$stmt = $connexion->prepare("
    SELECT SUM(t.montant) as total_depenses
    FROM transactions t
    WHERE t.date_transaction BETWEEN :date_debut AND :date_fin
    AND t.type = 'Dépense'
");
$stmt->bindParam(':date_debut', $date_debut);
$stmt->bindParam(':date_fin', $date_fin);
$stmt->execute();
$total_depenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_depenses'] ?? 0;

// 3. Solde net (recettes - dépenses)
$solde_net = $total_recettes - $total_depenses;

// 4. Total des frais facturés (attendus)
$stmt = $connexion->prepare("
    SELECT SUM(montant_total_attendu) as total_facture
    FROM (
        -- Frais affectés individuellement aux étudiants
        SELECT SUM(CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END) as montant_total_attendu
        FROM affectation_frais af
        JOIN frais f ON af.frais_id = f.id
        JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        WHERE af.promotion_id IS NULL 
        AND af.etudiant_id IS NOT NULL
        AND af.est_exempte = 0 
        AND (aa.idannee_acad = :annee_acad_id1 OR :annee_acad_id1 = 0)
        
        UNION ALL
        
        -- Frais affectés aux promotions (multipliés par le nombre d'étudiants actifs)
        SELECT SUM(
            (CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END) 
            * (
                SELECT COUNT(*) 
                FROM etudiant e 
                WHERE e.promotion_idpromotion = af.promotion_id 
                AND e.est_actif = 1
            )
        ) as montant_total_attendu
        FROM affectation_frais af
        JOIN frais f ON af.frais_id = f.id
        JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        WHERE af.promotion_id IS NOT NULL 
        AND af.est_exempte = 0
        AND (aa.idannee_acad = :annee_acad_id2 OR :annee_acad_id2 = 0)
    ) as totaux
");
$stmt->bindParam(':annee_acad_id1', $annee_acad_id);
$stmt->bindParam(':annee_acad_id2', $annee_acad_id);
$stmt->execute();
$total_frais_factures = $stmt->fetch(PDO::FETCH_ASSOC)['total_facture'] ?? 0;

// 5. Total des frais payés
$stmt = $connexion->prepare("
    SELECT SUM(pf.montant) as total_paye
    FROM paiements_frais pf
    JOIN affectation_frais af ON pf.affectation_id = af.id
    JOIN frais f ON af.frais_id = f.id
    JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    WHERE (aa.idannee_acad = :annee_acad_id OR :annee_acad_id = 0)
");
$stmt->bindParam(':annee_acad_id', $annee_acad_id);
$stmt->execute();
$total_frais_payes = $stmt->fetch(PDO::FETCH_ASSOC)['total_paye'] ?? 0;

// 6. Taux de recouvrement
$taux_recouvrement = ($total_frais_factures > 0) ? ($total_frais_payes / $total_frais_factures * 100) : 0;

// 7. Soldes disponibles (caisses et comptes bancaires) - Groupé par devise
$stmt = $connexion->prepare("SELECT id, designation, solde_actuel, devise FROM caisses WHERE est_actif = 1");
$stmt->execute();
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $connexion->prepare("SELECT id, intitule_compte as designation, solde_actuel, devise FROM comptes_bancaires WHERE est_actif = 1");
$stmt->execute();
$comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Regrouper les soldes par devise
function groupBalancesByCurrency($accounts) {
    $balances = [];
    foreach ($accounts as $account) {
        $currency = $account['devise'];
        if (!isset($balances[$currency])) {
            $balances[$currency] = 0;
        }
        $balances[$currency] += $account['solde_actuel'];
    }
    return $balances;
}

$soldes_caisses_par_devise = groupBalancesByCurrency($caisses);
$soldes_banques_par_devise = groupBalancesByCurrency($comptes_bancaires);

// Combiner les soldes des caisses et banques par devise
// Calcul correct des liquidités par devise
$liquidites_par_devise = [];

// D'abord, ajouter tous les soldes de caisses par devise
foreach ($soldes_caisses_par_devise as $devise => $montant) {
    if (!isset($liquidites_par_devise[$devise])) {
        $liquidites_par_devise[$devise] = 0;
    }
    $liquidites_par_devise[$devise] += $montant;
}

// Ensuite, ajouter tous les soldes de banques par devise
foreach ($soldes_banques_par_devise as $devise => $montant) {
    if (!isset($liquidites_par_devise[$devise])) {
        $liquidites_par_devise[$devise] = 0;
    }
    $liquidites_par_devise[$devise] += $montant;
}

// Pour vérification, recalculer les totaux pour USD spécifiquement
$solde_caisses_usd = isset($soldes_caisses_par_devise['USD']) ? $soldes_caisses_par_devise['USD'] : 0;
$solde_banques_usd = isset($soldes_banques_par_devise['USD']) ? $soldes_banques_par_devise['USD'] : 0;
$liquidites_usd = $solde_caisses_usd + $solde_banques_usd;

// Vérification de cohérence
$solde_caisses = array_sum(array_values($soldes_caisses_par_devise));
$solde_banques = array_sum(array_values($soldes_banques_par_devise));
$liquidites_disponibles = $solde_caisses + $solde_banques;


// 8. Frais impayés (créances)
$creances = $total_frais_factures - $total_frais_payes;

// Récupérer les 5 dernières transactions
$stmt = $connexion->prepare("
    SELECT t.id, t.date_transaction, t.montant, t.type, t.description
    FROM transactions t
    ORDER BY t.date_transaction DESC
    LIMIT 5
");
$stmt->execute();
$dernieres_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul simple des données mensuelles de l'année en cours (évite la boucle infinie)
$mois_actuels = [];
$recettes_mensuelles = [];
$depenses_mensuelles = [];

// Prépare les 12 derniers mois
for ($i = 0; $i < 12; $i++) {
    $mois = date('Y-m', strtotime("-$i month"));
    $mois_actuels[] = date('M Y', strtotime("-$i month"));
    
    // Initialise à zéro
    $recettes_mensuelles[$mois] = 0;
    $depenses_mensuelles[$mois] = 0;
}

// Récupère les recettes des 12 derniers mois
$stmt = $connexion->prepare("
    SELECT TO_CHAR(date_transaction, 'YYYY-MM') as mois, SUM(montant) as total
    FROM transactions
    WHERE type = 'Recette' 
    AND date_transaction >= (CURRENT_DATE - INTERVAL '12 months')
    GROUP BY TO_CHAR(date_transaction, 'YYYY-MM')
");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($recettes_mensuelles[$row['mois']])) {
        $recettes_mensuelles[$row['mois']] = floatval($row['total']);
    }
}

// Récupère les dépenses des 12 derniers mois
$stmt = $connexion->prepare("
    SELECT TO_CHAR(date_transaction, 'YYYY-MM') as mois, SUM(montant) as total
    FROM transactions
    WHERE type = 'Dépense'
    AND date_transaction >= (CURRENT_DATE - INTERVAL '12 months')
    GROUP BY TO_CHAR(date_transaction, 'YYYY-MM')
");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($depenses_mensuelles[$row['mois']])) {
        $depenses_mensuelles[$row['mois']] = floatval($row['total']);
    }
}

// On inverse les tableaux pour avoir les données du plus ancien au plus récent
$mois_actuels = array_reverse($mois_actuels);
$mois_donnees = array_reverse(array_keys($recettes_mensuelles));
$recettes_data = [];
$depenses_data = [];

foreach ($mois_donnees as $mois) {
    $recettes_data[] = $recettes_mensuelles[$mois];
    $depenses_data[] = $depenses_mensuelles[$mois];
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Tableau de Bord Financier</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Tableau de Bord</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Filtres -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <form action="" method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="view" value="finance/dashboard">
                            
                            <div class="col-md-3">
                                <label for="annee_acad_id" class="form-label">Année académique</label>
                                <select class="form-select" id="annee_acad_id" name="annee_acad_id">
                                    <?php foreach ($annees_academiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $annee['idannee_acad'] == $annee_acad_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $date_debut ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_fin" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $date_fin ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Appliquer les filtres</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Récapitulatif des indicateurs financiers -->
        <div class="row">
            <!-- Recettes vs Dépenses -->
            <div class="col-lg-8">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Récapitulatif Financier - <?= htmlspecialchars($annee_designation) ?></h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center mb-4">
                                    <h6>Recettes</h6>
                                    <h2 class="text-primary"><?= number_format($total_recettes, 2) ?> USD</h2>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center mb-4">
                                    <h6>Dépenses</h6>
                                    <h2 class="text-danger"><?= number_format($total_depenses, 2) ?> USD</h2>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center mb-4">
                                    <h6>Solde Net</h6>
                                    <h2 class="<?= $solde_net >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($solde_net, 2) ?> USD
                                    </h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-center mt-2">
                            <div class="btn-group" role="group">
                                <a href="" class="btn btn-outline-primary">Voir recettes</a>
                                <a href="" class="btn btn-outline-danger">Voir dépenses</a>
                                <button type="button" class="btn btn-outline-success" onclick="exportDashboardData()">
                                    <i class="bi bi-file-excel me-1"></i> Exporter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liquidités disponibles -->
            <div class="col-lg-4">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Liquidités Disponibles</h5>
                        
                        <?php foreach ($liquidites_par_devise as $devise => $montant): ?>
                            <div class="text-center mb-3">
                                <h2 class="text-success"><?= number_format($montant, 2) ?> <?= htmlspecialchars($devise) ?></h2>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3">
                            <h6><i class="bi bi-cash me-1"></i> Caisses:</h6>
                            <?php foreach ($soldes_caisses_par_devise as $devise => $montant): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Solde en <?= htmlspecialchars($devise) ?>:</span>
                                    <span class="fw-bold"><?= number_format($montant, 2) ?> <?= htmlspecialchars($devise) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-3">
                            <h6><i class="bi bi-bank me-1"></i> Banques:</h6>
                            <?php foreach ($soldes_banques_par_devise as $devise => $montant): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Solde en <?= htmlspecialchars($devise) ?>:</span>
                                    <span class="fw-bold"><?= number_format($montant, 2) ?> <?= htmlspecialchars($devise) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <a href="?view=finance/config_caisses" class="btn btn-sm btn-outline-primary me-2">Gérer caisses</a>
                            <a href="?view=finance/config_comptes_bancaires" class="btn btn-sm btn-outline-primary">Gérer comptes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Autres indicateurs clés -->
        <div class="row">
            <!-- Frais académiques -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Frais Académiques</h5>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Montant facturé:</span>
                            <span class="fw-bold"><?= number_format($total_frais_factures, 2) ?> USD</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Montant perçu:</span>
                            <span class="fw-bold"><?= number_format($total_frais_payes, 2) ?> USD</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Créances (impayés):</span>
                            <span class="fw-bold text-danger"><?= number_format($creances, 2) ?> USD</span>
                        </div>
                        
                        <div class="progress mt-3" style="height: 20px;">
                            <div class="progress-bar bg-<?= $taux_recouvrement < 50 ? 'danger' : ($taux_recouvrement < 80 ? 'warning' : 'success') ?>" 
                                role="progressbar" 
                                style="width: <?= min(100, $taux_recouvrement) ?>%" 
                                aria-valuenow="<?= $taux_recouvrement ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <?= number_format($taux_recouvrement, 1) ?>%
                            </div>
                        </div>
                        <p class="text-center mt-2 mb-0 small">Taux de recouvrement</p>
                    </div>
                </div>
            </div>

            <!-- Détail des liquidités -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Situation des Comptes</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6><i class="bi bi-cash"></i> Caisses</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Désignation</th>
                                                <th>Devise</th>
                                                <th class="text-end">Solde</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($caisses)): ?>
                                                <tr><td colspan="3" class="text-center">Aucune caisse active</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($caisses as $caisse): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($caisse['designation']) ?></td>
                                                        <td><?= htmlspecialchars($caisse['devise']) ?></td>
                                                        <td class="text-end"><?= number_format($caisse['solde_actuel'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6><i class="bi bi-bank"></i> Comptes Bancaires</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Désignation</th>
                                                <th>Devise</th>
                                                <th class="text-end">Solde</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($comptes_bancaires)): ?>
                                                <tr><td colspan="3" class="text-center">Aucun compte bancaire actif</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($comptes_bancaires as $compte): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($compte['designation']) ?></td>
                                                        <td><?= htmlspecialchars($compte['devise']) ?></td>
                                                        <td class="text-end"><?= number_format($compte['solde_actuel'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières transactions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Dernières transactions</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-end">Montant (USD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dernieres_transactions)): ?>
                                        <tr><td colspan="4" class="text-center">Aucune transaction récente</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($dernieres_transactions as $transaction): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($transaction['date_transaction'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $transaction['type'] == 'Recette' ? 'success' : 'danger' ?>">
                                                        <?= $transaction['type'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                <td class="text-end"><?= number_format($transaction['montant'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="?view=finance/transactions" class="btn btn-sm btn-primary">Voir toutes les transactions</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>


// Fonction pour exporter les données du tableau de bord
function exportDashboardData() {
    // Redirection vers un contrôleur d'exportation (à implémenter)
    window.location.href = '?view=finance/export_dashboard&annee_acad_id=<?= $annee_acad_id ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>';
}
</script>

<?php include "./views/include/footer.php"; ?>


