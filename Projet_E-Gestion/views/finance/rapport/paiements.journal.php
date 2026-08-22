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
$dateDebut = isset($_GET['dateDebut']) ? $_GET['dateDebut'] : '';
$dateFin = isset($_GET['dateFin']) ? $_GET['dateFin'] : '';
$modePaiement = isset($_GET['modePaiement']) ? $_GET['modePaiement'] : '';
$statutConfirmation = isset($_GET['statutConfirmation']) ? $_GET['statutConfirmation'] : '';
$anneeAcad = isset($_GET['anneeAcad']) ? $_GET['anneeAcad'] : '';
$promotion = isset($_GET['promotion']) ? $_GET['promotion'] : '';
$categoriesFrais = isset($_GET['categoriesFrais']) ? $_GET['categoriesFrais'] : '';

// Pour les requêtes qui utilisent $bdd, remplacer par $connexion
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

// Construction de la requête de paiements avec les filtres
$sql = "
    SELECT 
        pf.id, pf.recu_numero, pf.date_valeur, 
        pf.montant, pf.devise, pf.mode_paiement, pf.reference_externe,
        pf.est_confirme, pf.date_confirmation,
        e.matricule, e.noms as nom_etudiant,
        p.\"designationPromotion\" as promotion,
        s.\"designationSection\" as section,
        f.designation as designation_frais,
        cf.designation as categorie_frais
    FROM paiements_frais pf
    JOIN etudiant e ON pf.etudiant_id = e.idetudiant
    JOIN affectation_frais af ON pf.affectation_id = af.id
    JOIN frais f ON af.frais_id = f.id
    JOIN categories_frais cf ON f.categorie_id = cf.id
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    WHERE 1=1
";


// Ajout des conditions de filtrage
$params = [];
if (!empty($dateDebut)) {
    $sql .= " AND pf.date_valeur >= :dateDebut";
    $params[':dateDebut'] = $dateDebut;
}
if (!empty($dateFin)) {
    $sql .= " AND pf.date_valeur <= :dateFin";
    $params[':dateFin'] = $dateFin;
}

if (!empty($modePaiement)) {
    $sql .= " AND pf.mode_paiement = :modePaiement";
    $params[':modePaiement'] = $modePaiement;
}
if ($statutConfirmation !== '') {
    $sql .= " AND pf.est_confirme = :statutConfirmation";
    $params[':statutConfirmation'] = $statutConfirmation;
}
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

// Tri par date (la plus récente d'abord)
$sql .= " ORDER BY pf.date_valeur DESC LIMIT 500";

// Exécution de la requête
$stmt = $connexion->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques par devise
$totaux_par_devise = [];
$dernierePaiementDate = null;

foreach ($paiements as $paiement) {
    // Déterminer la devise avec une logique plus robuste
    $devise = !empty($paiement['devise']) ? trim($paiement['devise']) : 'USD';
    if (empty($devise)) {
        $devise = 'USD'; // Devise par défaut
    }
    
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'total_montant' => 0,
            'nombre_paiements' => 0,
            'montant_moyen' => 0
        ];
    }
    
    $totaux_par_devise[$devise]['total_montant'] += $paiement['montant'];
    $totaux_par_devise[$devise]['nombre_paiements']++;
    
    if ($dernierePaiementDate === null || 
        ($paiement['date_valeur'] && strtotime($paiement['date_valeur']) > strtotime($dernierePaiementDate))) {
        $dernierePaiementDate = $paiement['date_valeur'];
    }
}

// Calculer les montants moyens pour chaque devise
foreach ($totaux_par_devise as $devise => &$totaux) {
    $totaux['montant_moyen'] = $totaux['nombre_paiements'] > 0 ? $totaux['total_montant'] / $totaux['nombre_paiements'] : 0;
}

// Maintenir la compatibilité avec l'ancien code (pour USD par défaut)
$nombrePaiements = count($paiements);
$totalMontant = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_montant'] : 0;
$montantMoyen = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['montant_moyen'] : 0;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Journal des paiements</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item"><a href="finance/rapport">Rapports</a></li>
                <li class="breadcrumb-item active">Journal des paiements</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Historique complet des paiements reçus</h5>
                            <div class="export-buttons">
                                <a href="controller/finance/export_paiements.php?format=pdf&<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> PDF
                                </a>
                                <a href="controller/finance/export_paiements.php?format=excel&<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-excel"></i> Excel
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
                                            <input type="hidden" name="page" value="finance/rapport/paiements.journal">
                                            <div class="col-md-3">
                                                <label for=dateDebut class="form-label">Date début</label>
                                                <input type="date" class="form-control" id=dateDebut name=dateDebut value="<?php echo $dateDebut; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label for=dateFin class="form-label">Date fin</label>
                                                <input type="date" class="form-control" id=dateFin name=dateFin value="<?php echo $dateFin; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label for=modePaiement class="form-label">Mode de paiement</label>
                                                <select class="form-select" id=modePaiement name=modePaiement>
                                                    <option value="">Tous</option>
                                                    <option value="Espèces" <?php echo $modePaiement === 'Espèces' ? 'selected' : ''; ?>>Espèces</option>
                                                    <option value="Chèque" <?php echo $modePaiement === 'Chèque' ? 'selected' : ''; ?>>Chèque</option>
                                                    <option value="Virement" <?php echo $modePaiement === 'Virement' ? 'selected' : ''; ?>>Virement</option>
                                                    <option value="Mobile Money" <?php echo $modePaiement === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                                    <option value="Carte bancaire" <?php echo $modePaiement === 'Carte bancaire' ? 'selected' : ''; ?>>Carte bancaire</option>
                                                    <option value="Autre" <?php echo $modePaiement === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="statutConfirmation" class="form-label">Statut</label>
                                                <select class="form-select" id="statutConfirmation" name="statutConfirmation">
                                                    <option value="">Tous</option>
                                                    <option value="1" <?php echo $statutConfirmation === '1' ? 'selected' : ''; ?>>Confirmés</option>
                                                    <option value="0" <?php echo $statutConfirmation === '0' ? 'selected' : ''; ?>>Non confirmés</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
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
                                            <div class="col-md-3">
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
                                            <div class="col-md-3">
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
                                                <a href="finance/rapport/paiements.journal" class="btn btn-secondary">
                                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Résumé général -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar avatar-lg bg-primary bg-gradient rounded-3">
                                                    <i class="bi bi-receipt text-white fs-4"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <h6 class="text-muted mb-1">Total des paiements</h6>
                                                <h3 class="mb-0 fw-bold text-primary"><?php echo $nombrePaiements; ?></h3>
                                                <small class="text-muted">paiements enregistrés</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar avatar-lg bg-success bg-gradient rounded-3">
                                                    <i class="bi bi-calendar-event text-white fs-4"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <h6 class="text-muted mb-1">Dernier paiement</h6>
                                                <h3 class="mb-0 fw-bold text-success"><?php echo $dernierePaiementDate ? date('d/m/Y', strtotime($dernierePaiementDate)) : '--/--/----'; ?></h3>
                                                <small class="text-muted">date la plus récente</small>
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
                                            Paiements par devise
                                        </h5>
                                        <span class="badge bg-light text-dark fs-6"><?= count($totaux_par_devise) ?> devise(s)</span>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <?php foreach ($totaux_par_devise as $devise_courante => $totaux_devise): ?>
                                            <?php 
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
                                                                <?= $totaux_devise['nombre_paiements'] ?> paiements
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row g-3">
                                                            <div class="col-6">
                                                                <div class="text-center">
                                                                    <div class="text-muted small mb-1">Total encaissé</div>
                                                                    <div class="fw-bold text-dark fs-6">
                                                                        <?= number_format($totaux_devise['total_montant'], 0, ',', ' ') ?>
                                                                    </div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($devise_courante) ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="text-center">
                                                                    <div class="text-muted small mb-1">Montant moyen</div>
                                                                    <div class="fw-bold text-<?= $bg_devise ?> fs-6">
                                                                        <?= number_format($totaux_devise['montant_moyen'], 0, ',', ' ') ?>
                                                                    </div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($devise_courante) ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Barre de progression basée sur le pourcentage de paiements -->
                                                        <div class="mt-3">
                                                            <?php 
                                                            $pourcentage_paiements = $nombrePaiements > 0 ? ($totaux_devise['nombre_paiements'] / $nombrePaiements) * 100 : 0;
                                                            ?>
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="small text-muted">Part des paiements</span>
                                                                <span class="badge bg-<?= $bg_devise ?> fs-6">
                                                                    <?= number_format($pourcentage_paiements, 1) ?>%
                                                                </span>
                                                            </div>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-<?= $bg_devise ?> progress-bar-striped progress-bar-animated" 
                                                                     role="progressbar" 
                                                                     style="width: <?= min($pourcentage_paiements, 100) ?>%"
                                                                     aria-valuenow="<?= $pourcentage_paiements ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Informations supplémentaires -->
                                                        <div class="mt-3 p-2 bg-light rounded">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="small text-muted">
                                                                    <i class="bi bi-info-circle text-info me-1"></i>
                                                                    Nombre de paiements
                                                                </span>
                                                                <span class="fw-bold text-dark">
                                                                    <?= $totaux_devise['nombre_paiements'] ?>
                                                                </span>
                                                            </div>
                                                        </div>
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
                                        <h5 class="text-muted mb-2">Aucun paiement trouvé</h5>
                                        <p class="text-muted mb-0">Aucun paiement ne correspond aux critères de recherche sélectionnés.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tableau des paiements -->
                        <div class="table-responsive">
                            <?php if (count($paiements) > 0): ?>
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th scope="col">Reçu N°</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Matricule</th>
                                            <th scope="col">Étudiant</th>
                                            <th scope="col">Promotion</th>
                                            <th scope="col">Frais</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Devise</th>
                                            <th scope="col">Mode</th>
                                            <th scope="col">Référence</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements as $paiement): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($paiement['recu_numero'] ?? '-'); ?></td>
                                                <td><?php echo $paiement['date_valeur'] ? date('d/m/Y', strtotime($paiement['date_valeur'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($paiement['matricule']); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['nom_etudiant']); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['section'] . ' - ' . $paiement['promotion']); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['designation_frais']); ?></td>
                                                <td class="text-end"><?php echo number_format($paiement['montant'], 2, ',', ' '); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['devise']); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['mode_paiement']); ?></td>
                                                <td><?php echo htmlspecialchars($paiement['reference_externe'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($paiement['est_confirme']): ?>
                                                        <span class="badge bg-success">Confirmé</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Non confirmé</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a onclick="showPaiementDetails(<?php echo $paiement['id']; ?>)" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="controller/generer_recu.php?id=<?php echo $paiement['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimer reçu">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info mt-3 text-center">
                                    <i class="bi bi-info-circle me-2"></i> Aucun paiement trouvé avec les critères sélectionnés.
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (count($paiements) == 500): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle me-2"></i> Seuls les 500 premiers résultats sont affichés. Veuillez utiliser les filtres pour affiner votre recherche.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal détails paiement -->
<div class="modal fade" id="paiementDetailsModal" tabindex="-1" aria-labelledby="paiementDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paiementDetailsModalLabel">Détails du paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" id="printReceiptBtn" target="_blank" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Imprimer le reçu
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour afficher les détails d'un paiement
    function showPaiementDetails(paiementId) {
        const modal = new bootstrap.Modal(document.getElementById('paiementDetailsModal'));
        const modalContent = document.getElementById('modalContent');
        const printReceiptBtn = document.getElementById('printReceiptBtn');
        
        // Définir le lien d'impression
        printReceiptBtn.href = `controller/generer_recu.php?id=${paiementId}`;
        
        // Charger les détails du paiement
        modalContent.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des détails...</p>
            </div>
        `;
        
        fetch(`controller/get_paiement_details.php?id=${paiementId}`)
            .then(response => response.json())
            .then(data => {
                // Formatage des données pour affichage
                // Dans le script JavaScript
                const dateAffichage = formatDate(data.date_valeur);

                const statutBadge = data.est_confirme == 1 
                    ? '<span class="badge bg-success">Confirmé</span>' 
                    : '<span class="badge bg-warning">Non confirmé</span>';
                
                modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informations du paiement</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Reçu N°</th>
                                    <td>${data.recu_numero || '-'}</td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td>${dateAffichage}</td>
                                </tr>
                                <tr>
                                    <th>Montant</th>
                                    <td>${formatMontant(data.montant)} ${data.devise}</td>
                                </tr>
                                <tr>
                                    <th>Mode de paiement</th>
                                    <td>${data.mode_paiement}</td>
                                </tr>
                                <tr>
                                    <th>Référence externe</th>
                                    <td>${data.reference_externe || '-'}</td>
                                </tr>
                                <tr>
                                    <th>Statut</th>
                                    <td>${statutBadge}</td>
                                </tr>
                                <tr>
                                    <th>Date de confirmation</th>
                                    <td>${data.date_confirmation ? formatDate(data.date_confirmation) : '-'}</td>
                                </tr>
                                <tr>
                                    <th>Commentaire</th>
                                    <td>${data.commentaire || '-'}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informations de l'étudiant</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Matricule</th>
                                    <td>${data.matricule_etudiant}</td>
                                </tr>
                                <tr>
                                    <th>Nom complet</th>
                                    <td>${data.nom_etudiant}</td>
                                </tr>
                                <tr>
                                    <th>Promotion</th>
                                    <td>${data.promotion || '-'}</td>
                                </tr>
                                <tr>
                                    <th>Section</th>
                                    <td>${data.section || '-'}</td>
                                </tr>
                            </table>
                            
                            <h6 class="fw-bold mt-3">Frais concerné</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Catégorie</th>
                                    <td>${data.categorie_frais || '-'}</td>
                                </tr>
                                <tr>
                                    <th>Désignation</th>
                                    <td>${data.designation_frais || '-'}</td>
                                </tr>
                                <tr>
                                    <th>Montant total</th>
                                    <td>${formatMontant(data.montant_total)} ${data.devise}</td>
                                </tr>
                                <tr>
                                    <th>Solde restant</th>
                                    <td>${formatMontant(data.montant_restant)} ${data.devise}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                `;
                
                                // Afficher les tranches si disponibles
                                if (data.tranches && data.tranches.length > 0) {
                    let htmlTranches = `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="fw-bold">Détails des tranches</h6>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>N° Tranche</th>
                                            <th>Désignation</th>
                                            <th>Montant</th>
                                            <th>Échéance</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    data.tranches.forEach(tranche => {
                        const statut = tranche.statut_paiement === 'Complet' 
                            ? '<span class="badge bg-success">Payé</span>' 
                            : tranche.statut_paiement === 'Partiel'
                                ? '<span class="badge bg-warning">Partiel</span>'
                                : '<span class="badge bg-danger">Non payé</span>';
                        
                        htmlTranches += `
                            <tr>
                                <td>${tranche.numero_tranche}</td>
                                <td>${tranche.designation}</td>
                                <td class="text-end">${formatMontant(tranche.montant)} ${data.devise}</td>
                                <td>${formatDate(tranche.date_echeance)}</td>
                                <td>${statut}</td>
                            </tr>
                        `;
                    });
                    
                    htmlTranches += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    modalContent.innerHTML += htmlTranches;
                }
                
                // Ajouter des liens vers d'autres actions
                modalContent.innerHTML += `
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="finance/rapport/etudiants.situation?id=${data.etudiant_id}" class="btn btn-outline-info">
                                    <i class="bi bi-clock-history"></i> Historique de l'étudiant
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails du paiement:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Une erreur s'est produite lors du chargement des détails du paiement.
                    </div>
                `;
            });
        
        modal.show();
    }
    
    // Fonctions utilitaires
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    function formatMontant(montant) {
        if (!montant) return '0,00';
        return parseFloat(montant).toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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
    width: 40%;
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
    
    .modal {
        position: relative;
        display: block;
    }
    
    .modal-dialog {
        margin: 0;
        max-width: 100%;
    }
    
    .modal-content {
        border: none;
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

/* Styles pour les cartes d'information */
.info-card h6 {
    font-size: 20px;
    font-weight: bold;
}
</style>

<?php include "./views/include/footer.php"; ?>

