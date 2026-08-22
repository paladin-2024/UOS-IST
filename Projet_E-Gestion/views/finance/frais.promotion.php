<?php 
include "./views/include/header.php"; ?>

<?php
// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer l'idAgent de l'utilisateur connecté
$stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

// Récupération des filtres depuis l'URL
$anneeAcad = isset($_GET['anneeAcad']) ? $_GET['anneeAcad'] : '';
$promotion = isset($_GET['promotion']) ? $_GET['promotion'] : '';
$categoriesFrais = isset($_GET['categoriesFrais']) ? $_GET['categoriesFrais'] : '';

// Récupération des années académiques
$annees = $connexion->query("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$categories = $connexion->query("SELECT id, designation FROM categories_frais ORDER BY designation");

// Si une année est sélectionnée, charger les promotions correspondantes
$promotions = [];
if (!empty($anneeAcad)) {
    $stmtPromotions = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", s.\"designationSection\" 
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        WHERE p.annee_acad_idannee_acad = :anneeId
        ORDER BY s.\"designationSection\", p.\"designationPromotion\"
    ");
    $stmtPromotions->bindParam(':anneeId', $anneeAcad, PDO::PARAM_INT);
    $stmtPromotions->execute();
    $promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);
}

// Construction de la requête pour récupérer les frais par promotion
$sql = "
    SELECT 
        f.id, f.designation, f.montant, f.devise, f.est_obligatoire,
        cf.designation as categorie_frais,
        p.\"designationPromotion\" as promotion,
        p.idpromotion,
        s.\"designationSection\" as section,
        a.designation as annee_academique,
        COUNT(DISTINCT e.idetudiant) as nb_etudiants,
        COUNT(DISTINCT pf.id) as nb_paiements,
        COALESCE(SUM(CASE WHEN pf.est_confirme = 1 THEN pf.montant ELSE 0 END), 0) as montant_percu
    FROM frais f
    JOIN categories_frais cf ON f.categorie_id = cf.id
    JOIN affectation_frais af ON f.id = af.frais_id
    JOIN promotion p ON af.promotion_id = p.idpromotion
    JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion AND e.est_actif = 1
    LEFT JOIN paiements_frais pf ON af.id = pf.affectation_id AND pf.etudiant_id = e.idetudiant
    WHERE 1=1
";


// Ajout des conditions de filtrage
$params = [];
if (!empty($anneeAcad)) {
    $sql .= " AND p.annee_acad_idannee_acad = :anneeAcad";
    $params[':anneeAcad'] = $anneeAcad;
}
if (!empty($promotion)) {
    $sql .= " AND p.idpromotion = :promotion";
    $params[':promotion'] = $promotion;
}
if (!empty($categoriesFrais)) {
    $sql .= " AND f.categorie_id = :categoriesFrais";
    $params[':categoriesFrais'] = $categoriesFrais;
}

// Grouper par frais et promotion
$sql .= " GROUP BY f.id, p.idpromotion ORDER BY s.\"designationSection\", p.\"designationPromotion\", cf.designation, f.designation";

// Exécution de la requête
$stmt = $connexion->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$fraisPromotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques par devise
$totalFrais = count($fraisPromotions);
$totaux_par_devise = [];

foreach ($fraisPromotions as $frais) {
    // Déterminer la devise avec une logique plus robuste
    $devise = !empty($frais['devise']) ? trim($frais['devise']) : 'USD';
    if (empty($devise)) {
        $devise = 'USD'; // Devise par défaut
    }
    
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'montant_attendu' => 0,
            'montant_percu' => 0,
            'nb_frais' => 0
        ];
    }
    
    // Le montant attendu est calculé en multipliant le montant du frais par le nombre d'étudiants dans la promotion
    $montantAttendu = $frais['montant'] * $frais['nb_etudiants'];
    $totaux_par_devise[$devise]['montant_attendu'] += $montantAttendu;
    $totaux_par_devise[$devise]['montant_percu'] += $frais['montant_percu'];
    $totaux_par_devise[$devise]['nb_frais']++;
}

// Maintenir la compatibilité avec l'ancien code (pour USD par défaut)
$totalMontantAttendu = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['montant_attendu'] : 0;
$totalMontantPercu = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['montant_percu'] : 0;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Rapport des frais par promotion</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Frais par promotion</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Rapport des frais par promotion</h5>
                            <div class="export-buttons">
                                <a href="controller/export_frais_promotion.php?format=pdf&<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> PDF
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Imprimer
                                </button>
                            </div>
                        </div>

                        <!-- Filtres -->
                        <div class="row mb-4 mt-3">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body pt-3 pb-3">
                                        <h5 class="card-title pb-0 mb-2">Filtres</h5>
                                        <form id="filterForm" method="GET" action="" class="row g-3">
                                            <input type="hidden" name="page" value="finance/frais.promotion">
                                            <div class="col-md-4">
                                                <label for=anneeAcad class="form-label">Année académique</label>
                                                <select class="form-select" id=anneeAcad name=anneeAcad onchange="this.form.submit()">
                                                    <option value="">Toutes</option>
                                                    <?php
                                                    while ($annee = $annees->fetch()) {
                                                        $selected = $anneeAcad == $annee['idannee_acad'] ? 'selected' : '';
                                                        echo "<option value=\"{$annee['idannee_acad']}\" {$selected}>{$annee['designation']}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="promotion" class="form-label">Promotion</label>
                                                <select class="form-select" id="promotion" name="promotion">
                                                    <option value="">Toutes</option>
                                                    <?php
                                                    foreach ($promotions as $promo) {
                                                        $selected = $promotion == $promo['idpromotion'] ? 'selected' : '';
                                                        echo "<option value=\"{$promo['idpromotion']}\" {$selected}>{$promo['designationSection']} - {$promo['designationPromotion']}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="categoriesFrais" class="form-label">Catégorie de frais</label>
                                                <select class="form-select" id="categoriesFrais" name="categoriesFrais">
                                                    <option value="">Toutes</option>
                                                    <?php
                                                    while ($categorie = $categories->fetch()) {
                                                        $selected = $categoriesFrais == $categorie['id'] ? 'selected' : '';
                                                        echo "<option value=\"{$categorie['id']}\" {$selected}>{$categorie['designation']}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-12 text-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-filter"></i> Filtrer
                                                </button>
                                                <a href="finance/frais.promotion" class="btn btn-secondary">
                                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Résumé -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar avatar-lg bg-primary bg-gradient rounded-3">
                                                    <i class="bi bi-list-check text-white fs-4"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <h6 class="text-muted mb-1">Total des frais configurés</h6>
                                                <h3 class="mb-0 fw-bold text-primary"><?php echo $totalFrais; ?></h3>
                                                <small class="text-muted">frais actifs dans le système</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Résumé par devise -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <?php if (!empty($totaux_par_devise)): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="bi bi-currency-exchange text-primary me-2"></i>
                                            Situation financière par devise
                                        </h5>
                                        <span class="badge bg-light text-dark fs-6"><?= count($totaux_par_devise) ?> devise(s)</span>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <?php foreach ($totaux_par_devise as $devise_courante => $totaux_devise): ?>
                                            <?php 
                                            $taux_recouvrement = $totaux_devise['montant_attendu'] > 0 ? ($totaux_devise['montant_percu'] / $totaux_devise['montant_attendu']) * 100 : 0;
                                            $couleur_taux = $taux_recouvrement >= 90 ? 'success' : ($taux_recouvrement >= 50 ? 'warning' : 'danger');
                                            $couleur_devise = ['USD' => 'success', 'EUR' => 'primary', 'CDF' => 'info', 'GBP' => 'warning'];
                                            $bg_devise = $couleur_devise[$devise_courante] ?? 'secondary';
                                            ?>
                                            <div class="col-lg-6 col-xl-4">
                                                <div class="card border-0 shadow-sm h-100 devise-card">
                                                    <div class="card-header bg-<?= $bg_devise ?> bg-gradient text-white border-0">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <h6 class="mb-0 fw-bold">
                                                                <i class="bi bi-currency-dollar me-2"></i>
                                                                <?= htmlspecialchars($devise_courante) ?>
                                                            </h6>
                                                            <span class="badge bg-white bg-opacity-25">
                                                                <?= $totaux_devise['nb_frais'] ?> frais
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row g-3">
                                                            <div class="col-6">
                                                                <div class="text-center">
                                                                    <div class="text-muted small mb-1">Montant attendu</div>
                                                                    <div class="fw-bold text-dark fs-6">
                                                                        <?= number_format($totaux_devise['montant_attendu'], 0, ',', ' ') ?>
                                                                    </div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($devise_courante) ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="text-center">
                                                                    <div class="text-muted small mb-1">Montant perçu</div>
                                                                    <div class="fw-bold text-<?= $couleur_taux ?> fs-6">
                                                                        <?= number_format($totaux_devise['montant_percu'], 0, ',', ' ') ?>
                                                                    </div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($devise_courante) ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Barre de progression -->
                                                        <div class="mt-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="small text-muted">Taux de recouvrement</span>
                                                                <span class="badge bg-<?= $couleur_taux ?> fs-6">
                                                                    <?= number_format($taux_recouvrement, 1) ?>%
                                                                </span>
                                                            </div>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-<?= $couleur_taux ?> progress-bar-striped progress-bar-animated" 
                                                                     role="progressbar" 
                                                                     style="width: <?= min($taux_recouvrement, 100) ?>%"
                                                                     aria-valuenow="<?= $taux_recouvrement ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Solde restant -->
                                                        <?php $solde_restant = $totaux_devise['montant_attendu'] - $totaux_devise['montant_percu']; ?>
                                                        <?php if ($solde_restant > 0): ?>
                                                            <div class="mt-3 p-2 bg-light rounded">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <span class="small text-muted">
                                                                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                                                        Reste à recouvrer
                                                                    </span>
                                                                    <span class="fw-bold text-danger">
                                                                        <?= number_format($solde_restant, 0, ',', ' ') ?> <?= htmlspecialchars($devise_courante) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="mt-3 p-2 bg-success bg-opacity-10 rounded">
                                                                <div class="text-center">
                                                                    <span class="small text-success fw-bold">
                                                                        <i class="bi bi-check-circle me-1"></i>
                                                                        Recouvrement complet
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="avatar avatar-xl bg-light rounded-circle mx-auto mb-3">
                                            <i class="bi bi-info-circle text-muted fs-1"></i>
                                        </div>
                                        <h5 class="text-muted mb-2">Aucun frais trouvé</h5>
                                        <p class="text-muted mb-0">Aucun frais ne correspond aux critères de recherche sélectionnés.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tableau des frais par promotion -->
                        <div class="table-responsive">
                            <?php if (count($fraisPromotions) > 0): ?>
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th scope="col">Section</th>
                                            <th scope="col">Promotion</th>
                                            <th scope="col">Catégorie</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Obligatoire</th>
                                            <th scope="col">Nb. Étudiants</th>
                                            <th scope="col">Montant attendu</th>
                                            <th scope="col">Montant perçu</th>
                                            <th scope="col">Taux</th>
                                            <th scope="col">Détails</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fraisPromotions as $frais): ?>
                                            <?php 
                                            // Calcul du montant attendu pour cette ligne
                                            $montantAttendu = $frais['montant'] * $frais['nb_etudiants'];
                                            $taux = $montantAttendu > 0 ? ($frais['montant_percu'] / $montantAttendu) * 100 : 0;
                                            $badgeClass = $taux >= 90 ? 'bg-success' : ($taux >= 50 ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($frais['section']); ?></td>
                                                <td><?php echo htmlspecialchars($frais['promotion']); ?></td>
                                                <td><?php echo htmlspecialchars($frais['categorie_frais']); ?></td>
                                                <td><?php echo htmlspecialchars($frais['designation']); ?></td>
                                                <td class="text-end"><?php echo number_format($frais['montant'], 2, ',', ' ') . ' ' . $frais['devise']; ?></td>
                                                <td>
                                                    <?php if ($frais['est_obligatoire']): ?>
                                                        <span class="badge bg-success">Oui</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Non</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?php echo $frais['nb_etudiants']; ?></td>
                                                <td class="text-end"><?php echo number_format($montantAttendu, 2, ',', ' ') . ' ' . $frais['devise']; ?></td>
                                                <td class="text-end"><?php echo number_format($frais['montant_percu'], 2, ',', ' ') . ' ' . $frais['devise']; ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($taux, 1); ?>%</span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $frais['id']; ?>, <?php echo $frais['idpromotion']; ?>)">
                                                        <i class="bi bi-eye"></i> Détails
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info mt-3 text-center">
                                    <i class="bi bi-info-circle me-2"></i> Aucun frais trouvé avec les critères sélectionnés.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour voir les détails des paiements -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDetailsModalLabel">Détails des paiements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailsModalContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" id="exportDetailsBtn" class="btn btn-success" target="_blank">
                    <i class="bi bi-file-excel"></i> Exporter
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour voir les détails des paiements
    function viewDetails(fraisId, promotionId) {
        const modalContent = document.getElementById('detailsModalContent');
        const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
        const exportBtn = document.getElementById('exportDetailsBtn');
        
        // Mettre à jour le lien d'exportation
        exportBtn.href = `controller/export_details_paiements.php?fraisId=${fraisId}&promotionId=${promotionId}`;
        
        // Afficher le spinner de chargement
        modalContent.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des détails...</p>
            </div>
        `;
        
        // Afficher le modal
        modal.show();
        
        // Charger les détails depuis le serveur
        fetch(`controller/get_details_paiements.php?fraisId=${fraisId}&promotionId=${promotionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ${data.error}
                        </div>
                    `;
                    return;
                }
                
                // Afficher les informations du frais
                let html = `
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Informations du frais</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Désignation:</strong> ${data.frais.designation}</p>
                                            <p><strong>Catégorie:</strong> ${data.frais.categorie}</p>
                                            <p><strong>Montant:</strong> ${parseFloat(data.frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${data.frais.devise}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Promotion:</strong> ${data.frais.promotion}</p>
                                            <p><strong>Section:</strong> ${data.frais.section}</p>
                                            <p><strong>Année académique:</strong> ${data.frais.annee_academique}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Afficher les statistiques
                html += `
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card info-card">
                                <div class="card-body">
                                    <h5 class="card-title">Nombre d'étudiants</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                                            <i class="bi bi-people text-white"></i>
                                        </div>
                                        <div class="ps-3">
                                            <h6>${data.stats.nb_etudiants}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card info-card">
                                <div class="card-body">
                                    <h5 class="card-title">Montant attendu</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                                            <i class="bi bi-cash-stack text-white"></i>
                                        </div>
                                        <div class="ps-3">
                                            <h6>${parseFloat(data.stats.montant_attendu).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${data.frais.devise}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card info-card">
                                <div class="card-body">
                                    <h5 class="card-title">Montant perçu</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                                            <i class="bi bi-currency-dollar text-white"></i>
                                        </div>
                                        <div class="ps-3">
                                            <h6>${parseFloat(data.stats.montant_percu).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${data.frais.devise}</h6>
                                            <span class="text-success small pt-1 fw-bold">
                                                ${parseFloat(data.stats.taux_paiement).toFixed(1)}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Afficher les paiements
                if (data.paiements && data.paiements.length > 0) {
                    html += `
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom de l'étudiant</th>
                                        <th>Montant payé</th>
                                        <th>Date de paiement</th>
                                        <th>Référence</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.paiements.forEach(paiement => {
                        const montantPaye = parseFloat(paiement.montant || 0);
                        const montantTotal = parseFloat(data.frais.montant);
                        const pourcentage = montantTotal > 0 ? (montantPaye / montantTotal) * 100 : 0;
                        
                        let statutBadge;
                        if (pourcentage >= 100) {
                            statutBadge = '<span class="badge bg-success">Payé</span>';
                        } else if (pourcentage > 0) {
                            statutBadge = '<span class="badge bg-warning">Partiel</span>';
                        } else {
                            statutBadge = '<span class="badge bg-danger">Non payé</span>';
                        }
                        
                        html += `
                            <tr>
                                <td>${paiement.matricule || '-'}</td>
                                <td>${paiement.nom_etudiant || '-'}</td>
                                <td class="text-end">${montantPaye.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${data.frais.devise}</td>
                                <td>${paiement.date_paiement ? new Date(paiement.date_paiement).toLocaleDateString('fr-FR') : '-'}</td>
                                <td>${paiement.reference || '-'}</td>
                                <td>${statutBadge}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun paiement trouvé pour ce frais.
                        </div>
                    `;
                }
                
                // Afficher les étudiants sans paiement
                if (data.etudiants_sans_paiement && data.etudiants_sans_paiement.length > 0) {
                    html += `
                        <h5 class="mt-4">Étudiants n'ayant pas effectué de paiement (${data.etudiants_sans_paiement.length})</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom de l'étudiant</th>
                                        <th>Montant dû</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.etudiants_sans_paiement.forEach(etudiant => {
                        html += `
                            <tr>
                                <td>${etudiant.matricule || '-'}</td>
                                <td>${etudiant.nom_etudiant || '-'}</td>
                                <td class="text-end">${parseFloat(data.frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${data.frais.devise}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                
                modalContent.innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Une erreur est survenue lors du chargement des détails.
                    </div>
                `;
            });
    }
    
    
</script>

<style>
/* Styles personnalisés pour les avatars */
.avatar {
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-lg {
    width: 4rem;
    height: 4rem;
}

.avatar-xl {
    width: 5rem;
    height: 5rem;
}

/* Styles pour les cartes de devise */
.devise-card {
    transition: all 0.3s ease;
    border-radius: 12px !important;
    overflow: hidden;
}

.devise-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

.devise-card .card-header {
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.25rem;
}

.devise-card .card-body {
    padding: 1.25rem;
}

/* Animation pour les barres de progression */
.progress-bar {
    transition: width 1s ease-in-out;
}

/* Styles pour les badges améliorés */
.badge {
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
}


/* Styles pour les boutons */
.btn {
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Styles pour les cartes principales */
.card {
    border-radius: 12px;
    border: none;
    transition: all 0.3s ease;
}

.shadow-sm {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
}

/* Styles pour les alertes d'état vide */
.avatar.bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
}

/* Styles pour les filtres */
.form-select, .form-control {
    border-radius: 8px;
    border: 1px solid #e0e6ed;
    transition: all 0.2s ease;
}

.form-select:focus, .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* Styles pour les icônes */
.bi {
    vertical-align: -0.125em;
}

/* Styles pour les modals */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    border: none;
}

.modal-body .table {
    font-size: 14px;
    margin-bottom: 0;
}

.modal-body th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-weight: 600;
}

/* Responsive design amélioré */
@media (max-width: 768px) {
    .devise-card {
        margin-bottom: 1rem;
    }
    
    .d-flex.gap-4 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}

/* Styles d'impression */
@media print {
    .pagetitle, .export-buttons, .card-header, .card-footer, .breadcrumb, 
    #main-navbar, #sidebar, .footer, #filterForm, .modal-footer, 
    .btn, .actions-column {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
    
    body {
        padding: 0;
        background: white;
    }
    
    .main {
        padding: 0;
        margin: 0;
    }
    
    table {
        width: 100% !important;
    }
    
    .devise-card {
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    @page {
        size: landscape;
        margin: 1cm;
    }
}

/* Animation d'entrée pour les cartes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.devise-card {
    animation: fadeInUp 0.6s ease-out;
}

.devise-card:nth-child(2) {
    animation-delay: 0.1s;
}

.devise-card:nth-child(3) {
    animation-delay: 0.2s;
}

.devise-card:nth-child(4) {
    animation-delay: 0.3s;
}

/* Styles pour les états de chargement */
.spinner-border {
    color: #667eea;
}

/* Amélioration des couleurs de fond pour les devises */
.bg-gradient {
    background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-primary-dark, #5a67d8) 100%) !important;
}

.bg-success.bg-gradient {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.bg-info.bg-gradient {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%) !important;
}

.bg-warning.bg-gradient {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
}

.bg-secondary.bg-gradient {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
}
</style>

<?php include "./views/include/footer.php"; ?>


