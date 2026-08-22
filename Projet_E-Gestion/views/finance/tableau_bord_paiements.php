<?php
include "./views/include/header.php";

// Ajouter le CSS personnalisé
echo '<link href="assets/css/tableau_bord_paiements.css" rel="stylesheet">';

// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Vérifier si l'utilisateur a des responsabilités dans des sections
$stmt_sections = $connexion->prepare("
    SELECT DISTINCT section_idsection 
    FROM responsable_section 
    WHERE \"idUser\" = :idUser
");
$stmt_sections->bindParam(':idUser', $idUser);
$stmt_sections->execute();
$user_sections = $stmt_sections->fetchAll(PDO::FETCH_COLUMN);
$has_section_responsibility = !empty($user_sections);

// Récupérer les noms des sections
$sections_names = [];
if ($has_section_responsibility) {
    $sections_names_sql = "SELECT idsection, \"designationSection\" FROM section WHERE idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
    $stmt_names = $connexion->prepare($sections_names_sql);
    $stmt_names->execute();
    $sections_data = $stmt_names->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sections_data as $section) {
        $sections_names[$section['idsection']] = $section['designationSection'];
    }
}

// Récupérer l'année académique active
$stmt_active_year = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1");
$stmt_active_year->execute();
$active_year = $stmt_active_year->fetch(PDO::FETCH_ASSOC);

// Filtres
$annee_filtre = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : ($active_year['idannee_acad'] ?? null);
$section_filtre = isset($_GET['section']) ? $_GET['section'] : '';
$promotion_filtre = isset($_GET['promotion']) ? $_GET['promotion'] : '';

// Récupérer toutes les années académiques
$stmt_years = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt_years->execute();
$annees = $stmt_years->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions filtrées par sections de l'utilisateur
$sql_promotions = "
    SELECT DISTINCT p.idpromotion, p.\"designationPromotion\", s.idsection, s.\"designationSection\"
    FROM promotion p
    INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
    INNER JOIN section s ON o.section_idsection = s.idsection
    WHERE 1=1";

if ($has_section_responsibility) {
    $sql_promotions .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
}

if (!empty($section_filtre)) {
    $sql_promotions .= " AND s.idsection = :section_filtre";
}

$sql_promotions .= " ORDER BY s.\"designationSection\", p.\"designationPromotion\"";

$stmt_promotions = $connexion->prepare($sql_promotions);
if (!empty($section_filtre)) {
    $stmt_promotions->bindParam(':section_filtre', $section_filtre);
}
$stmt_promotions->execute();
$promotions = $stmt_promotions->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales par devise
$sql_stats_global = "
    SELECT 
        COUNT(DISTINCT e.idetudiant) as total_etudiants,
        COUNT(DISTINCT af.id) as total_affectations,
        f.devise,
        COALESCE(SUM(
            CASE 
                WHEN af.promotion_id IS NOT NULL THEN 
                    (CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END)
                WHEN af.matricule_etudiant IS NOT NULL THEN 
                    (CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END)
                ELSE 0
            END
        ), 0) as montant_total_attendu,
        COALESCE(SUM(pf.montant), 0) as montant_total_paye,
        COUNT(DISTINCT pf.id) as nombre_paiements
    FROM etudiant e
    INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
    INNER JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN affectation_frais af ON (e.matricule = af.matricule_etudiant OR p.idpromotion = af.promotion_id)
    LEFT JOIN frais f ON af.frais_id = f.id AND f.annee_acad_id = :annee_acad
    LEFT JOIN paiements_frais pf ON af.id = pf.affectation_id AND pf.matricule_etudiant = e.matricule
    WHERE e.annee_acad_idannee_acad = :annee_acad";

if ($has_section_responsibility) {
    $sql_stats_global .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
}

if (!empty($section_filtre)) {
    $sql_stats_global .= " AND s.idsection = :section_filtre";
}

if (!empty($promotion_filtre)) {
    $sql_stats_global .= " AND p.idpromotion = :promotion_filtre";
}

$sql_stats_global .= " GROUP BY f.devise";

$stmt_stats_global = $connexion->prepare($sql_stats_global);
$stmt_stats_global->bindParam(':annee_acad', $annee_filtre);
if (!empty($section_filtre)) {
    $stmt_stats_global->bindParam(':section_filtre', $section_filtre);
}
if (!empty($promotion_filtre)) {
    $stmt_stats_global->bindParam(':promotion_filtre', $promotion_filtre);
}
$stmt_stats_global->execute();
$stats_global_all = $stmt_stats_global->fetchAll(PDO::FETCH_ASSOC);

// Organiser les stats par devise
$stats_by_devise = ['USD' => [], 'CDF' => []];
$total_etudiants = 0;
foreach ($stats_global_all as $stat) {
    $devise = $stat['devise'] ?? 'USD';
    $stats_by_devise[$devise] = $stat;
    if ($stat['total_etudiants'] > $total_etudiants) {
        $total_etudiants = $stat['total_etudiants'];
    }
}

// Statistiques par promotion et devise - Montants attendus
$sql_montants_attendus = "
    SELECT 
        p.idpromotion,
        p.\"designationPromotion\",
        s.\"designationSection\",
        frais_devise as devise,
        COUNT(DISTINCT e.idetudiant) as nombre_etudiants,
        SUM(montant_frais) as montant_attendu
    FROM promotion p
    INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
    INNER JOIN section s ON o.section_idsection = s.idsection
    INNER JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion AND e.annee_acad_idannee_acad = :annee_acad
    LEFT JOIN (
        SELECT DISTINCT
            af.id,
            af.matricule_etudiant,
            af.promotion_id,
            f.devise as frais_devise,
            CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END as montant_frais
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id AND f.annee_acad_id = :annee_acad
    ) af_unique ON (e.matricule = af_unique.matricule_etudiant OR af_unique.promotion_id = p.idpromotion)
    WHERE af_unique.frais_devise IS NOT NULL";

if ($has_section_responsibility) {
    $sql_montants_attendus .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
}

if (!empty($section_filtre)) {
    $sql_montants_attendus .= " AND s.idsection = :section_filtre";
}

if (!empty($promotion_filtre)) {
    $sql_montants_attendus .= " AND p.idpromotion = :promotion_filtre";
}

$sql_montants_attendus .= " GROUP BY p.idpromotion, p.\"designationPromotion\", s.\"designationSection\", frais_devise
    HAVING nombre_etudiants > 0
    ORDER BY s.\"designationSection\", p.\"designationPromotion\", frais_devise";

$stmt_montants_attendus = $connexion->prepare($sql_montants_attendus);
$stmt_montants_attendus->bindParam(':annee_acad', $annee_filtre);
if (!empty($section_filtre)) {
    $stmt_montants_attendus->bindParam(':section_filtre', $section_filtre);
}
if (!empty($promotion_filtre)) {
    $stmt_montants_attendus->bindParam(':promotion_filtre', $promotion_filtre);
}
$stmt_montants_attendus->execute();
$montants_attendus = $stmt_montants_attendus->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les montants payés par promotion et devise
$sql_montants_payes = "
    SELECT 
        p.idpromotion,
        f.devise,
        COALESCE(SUM(pf.montant), 0) as montant_paye,
        COUNT(DISTINCT pf.id) as nombre_paiements
    FROM promotion p
    INNER JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion AND e.annee_acad_idannee_acad = :annee_acad
    INNER JOIN paiements_frais pf ON e.matricule = pf.matricule_etudiant
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id AND f.annee_acad_id = :annee_acad
    WHERE 1=1
    GROUP BY p.idpromotion, f.devise
";

$stmt_montants_payes = $connexion->prepare($sql_montants_payes);
$stmt_montants_payes->bindParam(':annee_acad', $annee_filtre);
$stmt_montants_payes->execute();
$montants_payes = $stmt_montants_payes->fetchAll(PDO::FETCH_ASSOC);

// Mapper les montants payés
$montants_payes_map = [];
foreach ($montants_payes as $mp) {
    $key = $mp['idpromotion'] . '_' . $mp['devise'];
    $montants_payes_map[$key] = [
        'montant_paye' => $mp['montant_paye'],
        'nombre_paiements' => $mp['nombre_paiements']
    ];
}

// Combiner les résultats
$stats_promotions = [];
foreach ($montants_attendus as $stat) {
    $devise = $stat['devise'] ?? 'USD';
    $key = $stat['idpromotion'] . '_' . $devise;

    $stat['montant_paye'] = $montants_payes_map[$key]['montant_paye'] ?? 0;
    $stat['nombre_paiements'] = $montants_payes_map[$key]['nombre_paiements'] ?? 0;

    $stats_promotions[] = $stat;
}

// Statistiques par frais et promotion
$sql_stats_frais = "
    SELECT 
        f.id,
        f.designation as frais_designation,
        f.devise,
        p.idpromotion,
        p.\"designationPromotion\",
        s.\"designationSection\",
        (
            -- Nombre d'affectations individuelles
            COUNT(DISTINCT CASE WHEN af.matricule_etudiant IS NOT NULL THEN af.id END) +
            -- Nombre d'affectations par promotion
            COUNT(DISTINCT CASE WHEN af.promotion_id IS NOT NULL THEN af.id END)
        ) as nombre_affectations,
        (
            -- Montant attendu des affectations individuelles
            COALESCE(SUM(
                CASE 
                    WHEN af.matricule_etudiant IS NOT NULL THEN
                        CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END
                    ELSE 0
                END
            ), 0) +
            -- Montant attendu des affectations par promotion (multiplié par le nombre d'étudiants)
            COALESCE((
                SELECT SUM(
                    (CASE WHEN af2.montant_specifique > 0 THEN af2.montant_specifique ELSE f.montant END) *
                    (SELECT COUNT(*) FROM etudiant e2 WHERE e2.promotion_idpromotion = af2.promotion_id AND e2.annee_acad_idannee_acad = :annee_acad)
                )
                FROM affectation_frais af2
                WHERE af2.frais_id = f.id AND af2.promotion_id = p.idpromotion
            ), 0)
        ) as montant_attendu,
        COALESCE(SUM(pf.montant), 0) as montant_paye,
        COUNT(DISTINCT pf.id) as nombre_paiements,
        (
            -- Nombre d'étudiants concernés par affectations individuelles
            COUNT(DISTINCT CASE WHEN af.matricule_etudiant IS NOT NULL THEN e.idetudiant END) +
            -- Nombre d'étudiants concernés par affectations de promotion
            COALESCE((
                SELECT COUNT(DISTINCT e3.idetudiant)
                FROM affectation_frais af3
                INNER JOIN etudiant e3 ON e3.promotion_idpromotion = af3.promotion_id
                WHERE af3.frais_id = f.id AND af3.promotion_id = p.idpromotion
                AND e3.annee_acad_idannee_acad = :annee_acad
            ), 0)
        ) as nombre_etudiants_concernes
    FROM frais f
    LEFT JOIN affectation_frais af ON f.id = af.frais_id
    LEFT JOIN etudiant e ON (af.matricule_etudiant = e.matricule OR af.promotion_id = e.promotion_idpromotion) AND e.annee_acad_idannee_acad = :annee_acad
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN paiements_frais pf ON af.id = pf.affectation_id AND pf.matricule_etudiant = e.matricule
    WHERE f.annee_acad_id = :annee_acad";

if ($has_section_responsibility) {
    $sql_stats_frais .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
}

if (!empty($section_filtre)) {
    $sql_stats_frais .= " AND s.idsection = :section_filtre";
}

if (!empty($promotion_filtre)) {
    $sql_stats_frais .= " AND p.idpromotion = :promotion_filtre";
}

$sql_stats_frais .= " GROUP BY f.id, f.designation, f.devise, p.idpromotion, p.\"designationPromotion\", s.\"designationSection\"
    HAVING nombre_affectations > 0 OR nombre_etudiants_concernes > 0
    ORDER BY f.designation, s.\"designationSection\", p.\"designationPromotion\"";

$stmt_stats_frais = $connexion->prepare($sql_stats_frais);
$stmt_stats_frais->bindParam(':annee_acad', $annee_filtre);
if (!empty($section_filtre)) {
    $stmt_stats_frais->bindParam(':section_filtre', $section_filtre);
}
if (!empty($promotion_filtre)) {
    $stmt_stats_frais->bindParam(':promotion_filtre', $promotion_filtre);
}
$stmt_stats_frais->execute();
$stats_frais = $stmt_stats_frais->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-graph-up-arrow me-2"></i>Tableau de Bord des Paiements</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Tableau de Bord</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <?php if ($has_section_responsibility): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Filtrage actif:</strong> Vous visualisez uniquement les données des sections suivantes :
                <strong><?= implode(', ', $sections_names) ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-funnel me-2"></i>Filtres</h5>
                        <form action="" method="GET" class="row g-3">
                            <input type="hidden" name="view" value="finance/tableau_bord_paiements">

                            <div class="col-md-4">
                                <label for="annee_acad" class="form-label">Année Académique</label>
                                <select class="form-select" id="annee_acad" name="annee_acad" onchange="this.form.submit()">
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_filtre == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                            <?= $active_year && $annee['idannee_acad'] == $active_year['idannee_acad'] ? ' (Active)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($has_section_responsibility && count($sections_names) > 1): ?>
                                <div class="col-md-4">
                                    <label for="section" class="form-label">Section</label>
                                    <select class="form-select" id="section" name="section" onchange="this.form.submit()">
                                        <option value="">Toutes mes sections</option>
                                        <?php foreach ($sections_names as $id => $name): ?>
                                            <option value="<?= $id ?>" <?= $section_filtre == $id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select class="form-select" id="promotion" name="promotion" onchange="this.form.submit()">
                                    <option value="">Toutes les promotions</option>
                                    <?php
                                    $current_section = '';
                                    foreach ($promotions as $promo):
                                        if ($current_section != $promo['designationSection']) {
                                            if ($current_section != '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($promo['designationSection']) . '">';
                                            $current_section = $promo['designationSection'];
                                        }
                                    ?>
                                        <option value="<?= $promo['idpromotion'] ?>" <?= $promotion_filtre == $promo['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                        </option>
                                    <?php
                                    endforeach;
                                    if ($current_section != '') echo '</optgroup>';
                                    ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques Globales -->
        <div class="row mb-4">
            <div class="col-lg-12 col-md-12">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Étudiants</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #e3f2fd; color: #2196f3;">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($total_etudiants) ?></h6>
                                <span class="text-muted small pt-1">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques par Devise -->
        <?php foreach (['USD', 'CDF'] as $devise):
            if (empty($stats_by_devise[$devise])) continue;
            $stats = $stats_by_devise[$devise];
            $montant_restant = $stats['montant_total_attendu'] - $stats['montant_total_paye'];
            $taux_recouvrement = $stats['montant_total_attendu'] > 0
                ? ($stats['montant_total_paye'] / $stats['montant_total_attendu']) * 100
                : 0;
        ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary"><i class="bi bi-cash me-2"></i>Statistiques en <?= $devise ?></h5>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card info-card revenue-card">
                        <div class="card-body">
                            <h5 class="card-title">Montant Attendu</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #fff3e0; color: #ff9800;">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= number_format($stats['montant_total_attendu'], 2) ?></h6>
                                    <span class="text-muted small pt-1"><?= $devise ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card info-card customers-card">
                        <div class="card-body">
                            <h5 class="card-title">Montant Payé</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #e8f5e9; color: #4caf50;">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= number_format($stats['montant_total_paye'], 2) ?></h6>
                                    <span class="text-success small pt-1">
                                        <i class="bi bi-arrow-up"></i> <?= number_format($taux_recouvrement, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card info-card">
                        <div class="card-body">
                            <h5 class="card-title">Reste à Payer</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #ffebee; color: #f44336;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= number_format($montant_restant, 2) ?></h6>
                                    <span class="text-danger small pt-1">
                                        <?= number_format(100 - $taux_recouvrement, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique de recouvrement par devise -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-pie-chart me-2"></i>Taux de Recouvrement en <?= $devise ?></h5>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                    style="width: <?= $taux_recouvrement ?>%;"
                                    aria-valuenow="<?= $taux_recouvrement ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                    <?= number_format($taux_recouvrement, 1) ?>% Payé
                                </div>
                                <div class="progress-bar bg-danger" role="progressbar"
                                    style="width: <?= 100 - $taux_recouvrement ?>%;"
                                    aria-valuenow="<?= 100 - $taux_recouvrement ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                    <?= number_format(100 - $taux_recouvrement, 1) ?>% Impayé
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Nombre de paiements effectués:</small>
                                    <strong class="d-block"><?= number_format($stats['nombre_paiements']) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Frais affectés:</small>
                                    <strong class="d-block"><?= number_format($stats['total_affectations']) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Montant moyen par étudiant:</small>
                                    <strong class="d-block">
                                        <?= $total_etudiants > 0
                                            ? number_format($stats['montant_total_attendu'] / $total_etudiants, 2)
                                            : '0.00' ?> <?= $devise ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>



        <!-- Statistiques par Promotion -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-mortarboard me-2"></i>Situation par Promotion</h5>

                        <?php if (empty($stats_promotions)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucune donnée disponible pour les critères sélectionnés.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Section</th>
                                            <th>Promotion</th>
                                            <th>Devise</th>
                                            <th class="text-center">Étudiants</th>
                                            <th class="text-end">Montant Attendu</th>
                                            <th class="text-end">Montant Payé</th>
                                            <th class="text-end">Reste</th>
                                            <th class="text-center">Taux</th>
                                            <th class="text-center">Paiements</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $current_section = '';
                                        foreach ($stats_promotions as $promo):
                                            $reste = $promo['montant_attendu'] - $promo['montant_paye'];
                                            $taux = $promo['montant_attendu'] > 0
                                                ? ($promo['montant_paye'] / $promo['montant_attendu']) * 100
                                                : 0;
                                            $devise = $promo['devise'] ?? 'USD';

                                            // Afficher le nom de la section si elle change
                                            if ($current_section != $promo['designationSection']) {
                                                if ($current_section != '') {
                                                    echo '<tr class="table-secondary"><td colspan="9"></td></tr>';
                                                }
                                                $current_section = $promo['designationSection'];
                                            }
                                        ?>
                                            <tr>
                                                <td><span class="badge bg-primary"><?= htmlspecialchars($promo['designationSection']) ?></span></td>
                                                <td><strong><?= htmlspecialchars($promo['designationPromotion']) ?></strong></td>
                                                <td><span class="badge bg-info"><?= htmlspecialchars($devise) ?></span></td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $promo['nombre_etudiants'] ?></span>
                                                </td>
                                                <td class="text-end"><?= number_format($promo['montant_attendu'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                <td class="text-end text-success"><strong><?= number_format($promo['montant_paye'], 2) ?> <?= htmlspecialchars($devise) ?></strong></td>
                                                <td class="text-end text-danger"><?= number_format($reste, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px; min-width: 100px;">
                                                        <div class="progress-bar <?= $taux >= 75 ? 'bg-success' : ($taux >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                                            role="progressbar"
                                                            style="width: <?= $taux ?>%;"
                                                            aria-valuenow="<?= $taux ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100">
                                                            <?= number_format($taux, 0) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= $promo['nombre_paiements'] ?></td>
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

        <!-- Statistiques par Frais et Promotion -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-receipt me-2"></i>Situation par Type de Frais et Promotion</h5>

                        <?php if (empty($stats_frais)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun frais affecté pour les critères sélectionnés.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Frais</th>
                                            <th>Section</th>
                                            <th>Promotion</th>
                                            <th class="text-center">Étudiants</th>
                                            <th class="text-center">Affectations</th>
                                            <th class="text-end">Attendu</th>
                                            <th class="text-end">Payé</th>
                                            <th class="text-end">Reste</th>
                                            <th class="text-center">Taux</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $current_frais = '';
                                        $current_devise = '';
                                        $totals_by_frais_devise = [];

                                        foreach ($stats_frais as $frais):
                                            $reste = $frais['montant_attendu'] - $frais['montant_paye'];
                                            $taux = $frais['montant_attendu'] > 0
                                                ? ($frais['montant_paye'] / $frais['montant_attendu']) * 100
                                                : 0;

                                            // Identifier le changement de frais
                                            $frais_key = $frais['frais_designation'] . '_' . $frais['devise'];
                                            if ($current_frais != $frais_key) {
                                                // Afficher le total du frais précédent
                                                if ($current_frais != '' && isset($totals_by_frais_devise[$current_frais])) {
                                                    $total = $totals_by_frais_devise[$current_frais];
                                                    $taux_frais = $total['attendu'] > 0 ? ($total['paye'] / $total['attendu']) * 100 : 0;
                                        ?>
                                                    <tr class="table-secondary fw-bold">
                                                        <td colspan="5" class="text-end">Sous-total <?= htmlspecialchars($total['designation']) ?>:</td>
                                                        <td class="text-end"><?= number_format($total['attendu'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                        <td class="text-end text-success"><?= number_format($total['paye'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                        <td class="text-end text-danger"><?= number_format($total['attendu'] - $total['paye'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                        <td class="text-center"><?= number_format($taux_frais, 1) ?>%</td>
                                                    </tr>
                                                    <tr class="table-light">
                                                        <td colspan="9"></td>
                                                    </tr>
                                            <?php
                                                }
                                                $current_frais = $frais_key;
                                                $current_devise = $frais['devise'];
                                            }

                                            // Accumuler les totaux par frais
                                            if (!isset($totals_by_frais_devise[$frais_key])) {
                                                $totals_by_frais_devise[$frais_key] = [
                                                    'designation' => $frais['frais_designation'],
                                                    'devise' => $frais['devise'],
                                                    'attendu' => 0,
                                                    'paye' => 0
                                                ];
                                            }
                                            $totals_by_frais_devise[$frais_key]['attendu'] += $frais['montant_attendu'];
                                            $totals_by_frais_devise[$frais_key]['paye'] += $frais['montant_paye'];
                                            ?>
                                            <tr>
                                                 <td><strong><?= htmlspecialchars($frais['frais_designation']) ?></strong></td>
                                                 <td><span class="badge bg-primary"><?= htmlspecialchars($frais['designationSection'] ?? '') ?></span></td>
                                                 <td><?= htmlspecialchars($frais['designationPromotion'] ?? '') ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $frais['nombre_etudiants_concernes'] ?></span>
                                                </td>
                                                <td class="text-center"><?= $frais['nombre_affectations'] ?></td>
                                                <td class="text-end"><?= number_format($frais['montant_attendu'], 2) ?> <?= htmlspecialchars($frais['devise']) ?></td>
                                                <td class="text-end text-success"><strong><?= number_format($frais['montant_paye'], 2) ?> <?= htmlspecialchars($frais['devise']) ?></strong></td>
                                                <td class="text-end text-danger"><?= number_format($reste, 2) ?> <?= htmlspecialchars($frais['devise']) ?></td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px; min-width: 80px;">
                                                        <div class="progress-bar <?= $taux >= 75 ? 'bg-success' : ($taux >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                                            role="progressbar"
                                                            style="width: <?= $taux ?>%;"
                                                            aria-valuenow="<?= $taux ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100">
                                                            <?= number_format($taux, 0) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                        endforeach;

                                        // Afficher le dernier total
                                        if ($current_frais != '' && isset($totals_by_frais_devise[$current_frais])) {
                                            $total = $totals_by_frais_devise[$current_frais];
                                            $taux_frais = $total['attendu'] > 0 ? ($total['paye'] / $total['attendu']) * 100 : 0;
                                        ?>
                                            <tr class="table-secondary fw-bold">
                                                <td colspan="5" class="text-end">Sous-total <?= htmlspecialchars($total['designation']) ?>:</td>
                                                <td class="text-end"><?= number_format($total['attendu'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                <td class="text-end text-success"><?= number_format($total['paye'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                <td class="text-end text-danger"><?= number_format($total['attendu'] - $total['paye'], 2) ?> <?= htmlspecialchars($current_devise) ?></td>
                                                <td class="text-center"><?= number_format($taux_frais, 1) ?>%</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        
    </section>
</main>

<style>
    @media print {

        .sidebar,
        .header,
        .pagetitle nav,
        .card-title,
        .btn,
        .alert-dismissible .btn-close {
            display: none !important;
        }

        .card {
            border: 1px solid #000 !important;
            page-break-inside: avoid;
        }

        .main {
            margin: 0 !important;
            padding: 0 !important;
        }
    }

    .card-icon {
        width: 64px;
        height: 64px;
        font-size: 32px;
    }

    .info-card h6 {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
        padding: 0;
    }

    .progress {
        border-radius: 5px;
    }

    .table th {
        font-weight: 600;
        background-color: #f8f9fa;
    }

    .badge {
        font-size: 0.85rem;
        padding: 0.35em 0.65em;
    }
</style>

<script>
    function exportToExcel() {
        alert('Fonctionnalité d\'export Excel en cours de développement');
        // TODO: Implémenter l'export Excel
    }
</script>

<?php include "./views/include/footer.php"; ?>