<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'année académique active
$stmt_active_year = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1");
$stmt_active_year->execute();
$active_year = $stmt_active_year->fetch(PDO::FETCH_ASSOC);

// Filtres de période
$date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== ''
    ? $_GET['date_debut']
    : date('Y-m-01');
$date_fin = isset($_GET['date_fin']) && $_GET['date_fin'] !== ''
    ? $_GET['date_fin']
    : date('Y-m-d');
$devise_filtre   = isset($_GET['devise'])      ? $_GET['devise']      : '';
$frais_filtre    = isset($_GET['frais_id'])    ? (int)$_GET['frais_id'] : 0;
$categorie_filtre = isset($_GET['categorie_id']) ? (int)$_GET['categorie_id'] : 0;
$confirme_filtre = isset($_GET['confirme'])    ? $_GET['confirme']    : '';

// ------------------------------------------------------------------
// Liste des frais et catégories pour les filtres
// ------------------------------------------------------------------
$stmt_frais_list = $connexion->prepare("
    SELECT f.id, f.designation, f.devise, cf.designation as categorie
    FROM frais f
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    ORDER BY cf.designation, f.designation
");
$stmt_frais_list->execute();
$frais_list = $stmt_frais_list->fetchAll(PDO::FETCH_ASSOC);

$stmt_cat_list = $connexion->prepare("SELECT id, designation FROM categories_frais ORDER BY designation");
$stmt_cat_list->execute();
$categories_list = $stmt_cat_list->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------------
// Construction de la clause WHERE commune
// ------------------------------------------------------------------
$where_parts   = ["DATE(pf.date_valeur) BETWEEN :date_debut AND :date_fin"];
$bind_params   = [':date_debut' => $date_debut, ':date_fin' => $date_fin];

if ($devise_filtre !== '') {
    $where_parts[]              = "pf.devise = :devise";
    $bind_params[':devise']     = $devise_filtre;
}
if ($frais_filtre > 0) {
    $where_parts[]              = "f.id = :frais_id";
    $bind_params[':frais_id']   = $frais_filtre;
}
if ($categorie_filtre > 0) {
    $where_parts[]              = "f.categorie_id = :categorie_id";
    $bind_params[':categorie_id'] = $categorie_filtre;
}
if ($confirme_filtre === '1') {
    $where_parts[] = "pf.est_confirme = 1";
} elseif ($confirme_filtre === '0') {
    $where_parts[] = "pf.est_confirme = 0";
}

$where_sql = implode(" AND ", $where_parts);

// ------------------------------------------------------------------
// Totaux globaux par devise
// ------------------------------------------------------------------
$sql_totaux = "
    SELECT
        pf.devise,
        COUNT(DISTINCT pf.id)           AS nb_paiements,
        COUNT(DISTINCT pf.matricule_etudiant) AS nb_etudiants,
        SUM(pf.montant)                 AS total_encaisse,
        SUM(CASE WHEN pf.est_confirme = 1 THEN pf.montant ELSE 0 END) AS total_confirme,
        SUM(CASE WHEN pf.est_confirme = 0 THEN pf.montant ELSE 0 END) AS total_en_attente
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    WHERE $where_sql
    GROUP BY pf.devise
    ORDER BY pf.devise
";
$stmt_totaux = $connexion->prepare($sql_totaux);
foreach ($bind_params as $k => $v) { $stmt_totaux->bindValue($k, $v); }
$stmt_totaux->execute();
$totaux_devises = $stmt_totaux->fetchAll(PDO::FETCH_ASSOC);

$totaux_map = [];
foreach ($totaux_devises as $t) {
    $totaux_map[$t['devise']] = $t;
}

// ------------------------------------------------------------------
// Encaissements par jour et par devise
// ------------------------------------------------------------------
$sql_par_jour = "
    SELECT
        DATE(pf.date_valeur) AS jour,
        pf.devise,
        COUNT(pf.id)         AS nb_paiements,
        SUM(pf.montant)      AS montant_jour
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    WHERE $where_sql
    GROUP BY DATE(pf.date_valeur), pf.devise
    ORDER BY jour ASC, pf.devise
";
$stmt_jour = $connexion->prepare($sql_par_jour);
foreach ($bind_params as $k => $v) { $stmt_jour->bindValue($k, $v); }
$stmt_jour->execute();
$rows_par_jour = $stmt_jour->fetchAll(PDO::FETCH_ASSOC);

// Structurer les données par jour
$jours_set   = [];
$data_par_jour = [];  // [jour][devise] => montant
foreach ($rows_par_jour as $r) {
    $jours_set[$r['jour']] = true;
    $data_par_jour[$r['jour']][$r['devise']] = [
        'montant'      => $r['montant_jour'],
        'nb_paiements' => $r['nb_paiements'],
    ];
}
$jours_liste = array_keys($jours_set);
sort($jours_liste);

// Préparer les séries pour ApexCharts (USD + CDF)
$series_usd = [];
$series_cdf = [];
$labels_jours = [];
foreach ($jours_liste as $jour) {
    $labels_jours[] = date('d/m', strtotime($jour));
    $series_usd[]   = (float)($data_par_jour[$jour]['USD']['montant'] ?? 0);
    $series_cdf[]   = (float)($data_par_jour[$jour]['CDF']['montant'] ?? 0);
}

// ------------------------------------------------------------------
// Encaissements par type de frais
// ------------------------------------------------------------------
$sql_par_frais = "
    SELECT
        f.id                            AS frais_id,
        f.designation                   AS frais_nom,
        cf.designation                  AS categorie_nom,
        pf.devise,
        COUNT(DISTINCT pf.id)           AS nb_paiements,
        COUNT(DISTINCT pf.matricule_etudiant) AS nb_etudiants,
        SUM(pf.montant)                 AS montant_total
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    WHERE $where_sql
    GROUP BY f.id, f.designation, cf.designation, pf.devise
    ORDER BY cf.designation, f.designation, pf.devise
";
$stmt_par_frais = $connexion->prepare($sql_par_frais);
foreach ($bind_params as $k => $v) { $stmt_par_frais->bindValue($k, $v); }
$stmt_par_frais->execute();
$rows_par_frais = $stmt_par_frais->fetchAll(PDO::FETCH_ASSOC);

// Grouper par frais pour les graphiques camembert
$frais_chart_usd = [];
$frais_chart_cdf = [];
foreach ($rows_par_frais as $r) {
    if ($r['devise'] === 'USD') {
        $frais_chart_usd[$r['frais_nom']] = (float)$r['montant_total'];
    } elseif ($r['devise'] === 'CDF') {
        $frais_chart_cdf[$r['frais_nom']] = (float)$r['montant_total'];
    }
}

// ------------------------------------------------------------------
// Encaissements par mode de paiement
// ------------------------------------------------------------------
$sql_par_mode = "
    SELECT
        pf.mode_paiement,
        pf.devise,
        COUNT(pf.id)    AS nb_paiements,
        SUM(pf.montant) AS montant_total
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    WHERE $where_sql
    GROUP BY pf.mode_paiement, pf.devise
    ORDER BY pf.devise, montant_total DESC
";
$stmt_par_mode = $connexion->prepare($sql_par_mode);
foreach ($bind_params as $k => $v) { $stmt_par_mode->bindValue($k, $v); }
$stmt_par_mode->execute();
$rows_par_mode = $stmt_par_mode->fetchAll(PDO::FETCH_ASSOC);

$mode_chart_labels_usd = $mode_chart_data_usd = [];
$mode_chart_labels_cdf = $mode_chart_data_cdf = [];
foreach ($rows_par_mode as $r) {
    if ($r['devise'] === 'USD') {
        $mode_chart_labels_usd[] = $r['mode_paiement'];
        $mode_chart_data_usd[]   = (float)$r['montant_total'];
    } elseif ($r['devise'] === 'CDF') {
        $mode_chart_labels_cdf[] = $r['mode_paiement'];
        $mode_chart_data_cdf[]   = (float)$r['montant_total'];
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-cash-stack me-2"></i>Tableau de Bord des Encaissements</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Encaissements</li>
            </ol>
        </nav>
    </div>

    <section class="section">

        <!-- ==================== FILTRES ==================== -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title"><i class="bi bi-funnel me-2"></i>Filtres</h5>
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="view" value="finance/tableau_bord_encaissements">

                            <div class="col-md-2">
                                <label class="form-label">Date début</label>
                                <input type="date" class="form-control" name="date_debut"
                                       value="<?= htmlspecialchars($date_debut) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date fin</label>
                                <input type="date" class="form-control" name="date_fin"
                                       value="<?= htmlspecialchars($date_fin) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Devise</label>
                                <select class="form-select" name="devise">
                                    <option value="">Toutes</option>
                                    <option value="USD" <?= $devise_filtre === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= $devise_filtre === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Catégorie de frais</label>
                                <select class="form-select" name="categorie_id">
                                    <option value="0">Toutes</option>
                                    <?php foreach ($categories_list as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                            <?= $categorie_filtre == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type de frais</label>
                                <select class="form-select" name="frais_id">
                                    <option value="0">Tous</option>
                                    <?php foreach ($frais_list as $fl): ?>
                                        <option value="<?= $fl['id'] ?>"
                                            <?= $frais_filtre == $fl['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($fl['designation']) ?>
                                            (<?= htmlspecialchars($fl['devise']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="confirme">
                                    <option value="">Tous</option>
                                    <option value="1" <?= $confirme_filtre === '1' ? 'selected' : '' ?>>Confirmé</option>
                                    <option value="0" <?= $confirme_filtre === '0' ? 'selected' : '' ?>>En attente</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                                <a href="?view=finance/tableau_bord_encaissements" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </form>

                        <!-- Raccourcis de période -->
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <small class="text-muted me-1">Période rapide :</small>
                            <?php
                            $shortcuts = [
                                "Aujourd'hui"     => [date('Y-m-d'), date('Y-m-d')],
                                'Cette semaine'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
                                'Ce mois'         => [date('Y-m-01'), date('Y-m-d')],
                                'Mois précédent'  => [date('Y-m-01', strtotime('first day of last month')), date('Y-t', strtotime('last month'))],
                                'Cette année'     => [date('Y-01-01'), date('Y-m-d')],
                            ];
                            foreach ($shortcuts as $label => [$d1, $d2]):
                                $extra = $devise_filtre ? "&devise=$devise_filtre" : '';
                            ?>
                                <a href="?view=finance/tableau_bord_encaissements&date_debut=<?= $d1 ?>&date_fin=<?= $d2 ?><?= $extra ?>"
                                   class="badge <?= ($date_debut === $d1 && $date_fin === $d2) ? 'bg-primary' : 'bg-light text-dark border' ?> text-decoration-none">
                                    <?= $label ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CARTES TOTAUX ==================== -->
        <?php if (empty($totaux_devises)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Aucun encaissement trouvé pour la période du
                <strong><?= date('d/m/Y', strtotime($date_debut)) ?></strong> au
                <strong><?= date('d/m/Y', strtotime($date_fin)) ?></strong>.
            </div>
        <?php else: ?>

        <div class="row mb-3">
            <?php foreach ($totaux_devises as $t): ?>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card h-100 border-start border-4 <?= $t['devise'] === 'USD' ? 'border-success' : 'border-warning' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1 small text-uppercase fw-bold">Total encaissé</p>
                                <h3 class="fw-bold <?= $t['devise'] === 'USD' ? 'text-success' : 'text-warning' ?>">
                                    <?= number_format($t['total_encaisse'], 2, ',', '.') ?>
                                    <small class="fs-6"><?= htmlspecialchars($t['devise']) ?></small>
                                </h3>
                                <div class="mt-2 d-flex gap-3">
                                    <span class="text-success small">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Confirmé : <?= number_format($t['total_confirme'], 2, ',', '.') ?>
                                    </span>
                                    <span class="text-warning small">
                                        <i class="bi bi-clock me-1"></i>
                                        En attente : <?= number_format($t['total_en_attente'], 2, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:56px;height:56px;font-size:24px;
                                        background:<?= $t['devise'] === 'USD' ? '#e8f5e9;color:#4caf50' : '#fff8e1;color:#ff9800' ?>">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1 small text-uppercase fw-bold">Transactions <?= htmlspecialchars($t['devise']) ?></p>
                        <h3 class="fw-bold text-primary"><?= number_format($t['nb_paiements']) ?></h3>
                        <p class="mb-0 text-muted small">
                            <i class="bi bi-people me-1"></i>
                            <?= number_format($t['nb_etudiants']) ?> étudiants concernés
                        </p>
                        <?php if ($t['nb_paiements'] > 0): ?>
                        <p class="mb-0 text-muted small">
                            Moy. par transaction :
                            <strong><?= number_format($t['total_encaisse'] / $t['nb_paiements'], 2, ',', '.') ?> <?= htmlspecialchars($t['devise']) ?></strong>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ==================== GRAPHIQUE ÉVOLUTION PAR JOUR ==================== -->
        <?php if (!empty($jours_liste)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bar-chart-line me-2"></i>
                            Évolution journalière des encaissements
                            <small class="text-muted fs-6 ms-2">
                                <?= date('d/m/Y', strtotime($date_debut)) ?> → <?= date('d/m/Y', strtotime($date_fin)) ?>
                            </small>
                        </h5>
                        <div id="chartEvolutionJour" style="min-height:320px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================== TABLEAU PAR JOUR ==================== -->
        <?php if (!empty($jours_liste)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-table me-2"></i>Détail par jour</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-sm" id="tableParJour">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-center">Devise</th>
                                        <th class="text-end">Montant encaissé</th>
                                        <th class="text-center">Nb transactions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_print = [];
                                    foreach ($jours_liste as $jour):
                                        foreach (['USD', 'CDF'] as $dev):
                                            if (!isset($data_par_jour[$jour][$dev])) continue;
                                            $d = $data_par_jour[$jour][$dev];
                                            $total_print[$dev] = ($total_print[$dev] ?? 0) + $d['montant'];
                                    ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($jour)) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $dev === 'USD' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= $dev ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= number_format($d['montant'], 2, ',', '.') ?> <?= $dev ?>
                                        </td>
                                        <td class="text-center"><?= $d['nb_paiements'] ?></td>
                                    </tr>
                                    <?php endforeach; endforeach; ?>
                                </tbody>
                                <?php if (!empty($total_print)): ?>
                                <tfoot class="table-secondary fw-bold">
                                    <?php foreach ($total_print as $dev => $tot): ?>
                                    <tr>
                                        <td colspan="2" class="text-end">Total <?= $dev ?> :</td>
                                        <td class="text-end"><?= number_format($tot, 2, ',', '.') ?> <?= $dev ?></td>
                                        <td></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================== PAR TYPE DE FRAIS ==================== -->
        <?php if (!empty($rows_par_frais)): ?>
        <div class="row mb-3">

            <!-- Graphiques camembert USD + CDF -->
            <?php if (!empty($frais_chart_usd)): ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-pie-chart me-2"></i>Répartition USD par type de frais</h5>
                        <div id="chartFraisUSD" style="min-height:300px;"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($frais_chart_cdf)): ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-pie-chart me-2"></i>Répartition CDF par type de frais</h5>
                        <div id="chartFraisCDF" style="min-height:300px;"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tableau par type de frais -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-receipt me-2"></i>Encaissements par type de frais</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-sm" id="tableParFrais">
                                <thead class="table-light">
                                    <tr>
                                        <th>Catégorie</th>
                                        <th>Type de frais</th>
                                        <th class="text-center">Devise</th>
                                        <th class="text-end">Montant encaissé</th>
                                        <th class="text-center">Transactions</th>
                                        <th class="text-center">Étudiants</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sous_totaux_frais = [];
                                    $current_frais_id  = null;
                                    $current_frais_nom = '';

                                    foreach ($rows_par_frais as $rf):
                                        $fkey = $rf['frais_id'] . '_' . $rf['devise'];

                                        if ($current_frais_id !== $rf['frais_id']) {
                                            // Afficher le sous-total du frais précédent
                                            if ($current_frais_id !== null && isset($sous_totaux_frais[$current_frais_id])) {
                                                foreach ($sous_totaux_frais[$current_frais_id] as $dev => $st):
                                                    if (count($sous_totaux_frais[$current_frais_id]) > 1 || true):
                                    ?>
                                                        <tr class="table-secondary small fw-semibold">
                                                            <td colspan="3" class="text-end">
                                                                Sous-total <?= htmlspecialchars($current_frais_nom) ?> (<?= $dev ?>) :
                                                            </td>
                                                            <td class="text-end"><?= number_format($st['montant'], 2, ',', '.') ?> <?= $dev ?></td>
                                                            <td class="text-center"><?= $st['nb'] ?></td>
                                                            <td class="text-center"><?= $st['etu'] ?></td>
                                                        </tr>
                                    <?php           endif; endforeach;
                                                    echo '<tr class="table-light"><td colspan="6"></td></tr>';
                                                }
                                                $current_frais_id  = $rf['frais_id'];
                                                $current_frais_nom = $rf['frais_nom'];
                                        }

                                        // Accumuler sous-totaux
                                        if (!isset($sous_totaux_frais[$rf['frais_id']][$rf['devise']])) {
                                            $sous_totaux_frais[$rf['frais_id']][$rf['devise']] = ['montant' => 0, 'nb' => 0, 'etu' => 0];
                                        }
                                        $sous_totaux_frais[$rf['frais_id']][$rf['devise']]['montant'] += $rf['montant_total'];
                                        $sous_totaux_frais[$rf['frais_id']][$rf['devise']]['nb']      += $rf['nb_paiements'];
                                        $sous_totaux_frais[$rf['frais_id']][$rf['devise']]['etu']     += $rf['nb_etudiants'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($rf['categorie_nom'] ?? 'Non catégorisé') ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($rf['frais_nom']) ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge <?= $rf['devise'] === 'USD' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= htmlspecialchars($rf['devise']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= number_format($rf['montant_total'], 2, ',', '.') ?>
                                            <?= htmlspecialchars($rf['devise']) ?>
                                        </td>
                                        <td class="text-center"><?= $rf['nb_paiements'] ?></td>
                                        <td class="text-center"><?= $rf['nb_etudiants'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php // Dernier sous-total
                                    if ($current_frais_id !== null && isset($sous_totaux_frais[$current_frais_id])) {
                                        foreach ($sous_totaux_frais[$current_frais_id] as $dev => $st): ?>
                                        <tr class="table-secondary small fw-semibold">
                                            <td colspan="3" class="text-end">
                                                Sous-total <?= htmlspecialchars($current_frais_nom) ?> (<?= $dev ?>) :
                                            </td>
                                            <td class="text-end"><?= number_format($st['montant'], 2, ',', '.') ?> <?= $dev ?></td>
                                            <td class="text-center"><?= $st['nb'] ?></td>
                                            <td class="text-center"><?= $st['etu'] ?></td>
                                        </tr>
                                    <?php endforeach; } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================== PAR MODE DE PAIEMENT ==================== -->
        <?php if (!empty($rows_par_mode)): ?>
        <div class="row mb-3">
            <?php
            $modes_par_devise = [];
            foreach ($rows_par_mode as $m) {
                $modes_par_devise[$m['devise']][] = $m;
            }
            foreach ($modes_par_devise as $dev => $modes):
            ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-credit-card me-2"></i>
                            Modes de paiement — <?= $dev ?>
                        </h5>
                        <div id="chartMode_<?= $dev ?>" style="min-height:260px;"></div>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light"><tr>
                                    <th>Mode</th>
                                    <th class="text-end">Montant <?= $dev ?></th>
                                    <th class="text-center">Nb</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($modes as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['mode_paiement']) ?></td>
                                    <td class="text-end"><?= number_format($m['montant_total'], 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $m['nb_paiements'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // fin if (!empty($totaux_devises)) ?>

    </section>
</main>

<style>
.border-start { border-left-width: 4px !important; }
.card { box-shadow: 0 1px 4px rgba(0,0,0,.08); }
@media print {
    .sidebar, .header, form, .pagetitle nav, .btn { display: none !important; }
    .card { border: 1px solid #ccc !important; page-break-inside: avoid; }
}
</style>

<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---- Graphique évolution journalière ----
    <?php if (!empty($jours_liste)): ?>
    var optEvol = {
        series: [
            <?php if (!empty(array_filter($series_usd))): ?>
            { name: 'USD', data: <?= json_encode($series_usd) ?> },
            <?php endif; ?>
            <?php if (!empty(array_filter($series_cdf))): ?>
            { name: 'CDF', data: <?= json_encode($series_cdf) ?> },
            <?php endif; ?>
        ],
        chart: { type: 'bar', height: 320, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        colors: ['#4caf50', '#ff9800'],
        xaxis: {
            categories: <?= json_encode($labels_jours) ?>,
            labels: { rotate: -45 }
        },
        yaxis: { labels: { formatter: v => v.toLocaleString('fr-FR') } },
        tooltip: {
            y: { formatter: (v, { seriesIndex, w }) =>
                v.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' ' +
                w.globals.seriesNames[seriesIndex]
            }
        },
        legend: { position: 'top' }
    };
    new ApexCharts(document.querySelector('#chartEvolutionJour'), optEvol).render();
    <?php endif; ?>

    // ---- Camembert USD par type de frais ----
    <?php if (!empty($frais_chart_usd)): ?>
    new ApexCharts(document.querySelector('#chartFraisUSD'), {
        series: <?= json_encode(array_values($frais_chart_usd)) ?>,
        labels: <?= json_encode(array_keys($frais_chart_usd)) ?>,
        chart: { type: 'donut', height: 300 },
        colors: ['#4caf50','#2196f3','#9c27b0','#00bcd4','#ff5722','#607d8b','#795548'],
        legend: { position: 'bottom' },
        tooltip: { y: { formatter: v => v.toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' USD' } }
    }).render();
    <?php endif; ?>

    // ---- Camembert CDF par type de frais ----
    <?php if (!empty($frais_chart_cdf)): ?>
    new ApexCharts(document.querySelector('#chartFraisCDF'), {
        series: <?= json_encode(array_values($frais_chart_cdf)) ?>,
        labels: <?= json_encode(array_keys($frais_chart_cdf)) ?>,
        chart: { type: 'donut', height: 300 },
        colors: ['#ff9800','#f44336','#3f51b5','#009688','#cddc39','#ff5722','#9e9e9e'],
        legend: { position: 'bottom' },
        tooltip: { y: { formatter: v => v.toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' CDF' } }
    }).render();
    <?php endif; ?>

    // ---- Camemberts modes de paiement ----
    <?php foreach ($modes_par_devise ?? [] as $dev => $modes): ?>
    new ApexCharts(document.querySelector('#chartMode_<?= $dev ?>'), {
        series: <?= json_encode(array_column($modes, 'montant_total', null)) ?>,
        labels: <?= json_encode(array_column($modes, 'mode_paiement')) ?>,
        chart: { type: 'pie', height: 260 },
        legend: { position: 'bottom' },
        tooltip: { y: { formatter: v => v.toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' <?= $dev ?>' } }
    }).render();
    <?php endforeach; ?>

});
</script>

<?php include "./views/include/footer.php"; ?>
