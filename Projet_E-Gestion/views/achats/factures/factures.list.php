<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des paramètres de filtrage
$fournisseurId = isset($_GET['fournisseur']) ? intval($_GET['fournisseur']) : 0;
$etat = isset($_GET['etat']) ? $_GET['etat'] : '';
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construction de la requête SQL avec filtres
$sql = "SELECT ff.*, f.nom_fournisseur 
        FROM facture_fournisseur ff
        JOIN fournisseur f ON ff.id_fournisseur = f.id_fournisseur
        WHERE 1=1";

$params = [];

if ($fournisseurId > 0) {
    $sql .= " AND ff.id_fournisseur = :fournisseur_id";
    $params[':fournisseur_id'] = $fournisseurId;
}

if (!empty($etat)) {
    $sql .= " AND ff.etat = :etat";
    $params[':etat'] = $etat;
}

if (!empty($dateDebut)) {
    $sql .= " AND ff.date_facture >= :date_debut";
    $params[':date_debut'] = $dateDebut;
}

if (!empty($dateFin)) {
    $sql .= " AND ff.date_facture <= :date_fin";
    $params[':date_fin'] = $dateFin;
}

$sql .= " ORDER BY ff.date_facture DESC, ff.numero_facture DESC";

// Exécution de la requête
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des fournisseurs pour le filtre
$stmtFournisseurs = $db->prepare("SELECT id_fournisseur, code_fournisseur, nom_fournisseur FROM fournisseur WHERE actif = 1 ORDER BY nom_fournisseur");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$totalTTC = 0;
$totalPaye = 0;
$totalSolde = 0;

foreach ($factures as $facture) {
    if ($facture['etat'] != 'Annulé') {
        $totalTTC += $facture['montant_ttc'];
        $totalPaye += $facture['montant_paye'];
        $totalSolde += $facture['solde'];
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES FACTURES FOURNISSEURS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item active">Factures fournisseurs</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Factures fournisseurs</h5>
                            <a href="achats/factures/factures.add" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nouvelle facture
                            </a>
                        </div>

                        <!-- Filtres -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Filtres</h5>
                                <form method="GET" action="achats/factures/factures.list" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="fournisseur" class="form-label">Fournisseur</label>
                                        <select class="form-select" id="fournisseur" name="fournisseur">
                                            <option value="">Tous les fournisseurs</option>
                                            <?php foreach ($fournisseurs as $f): ?>
                                                <option value="<?= $f['id_fournisseur'] ?>" <?= $fournisseurId == $f['id_fournisseur'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($f['code_fournisseur'] . ' - ' . $f['nom_fournisseur']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="etat" class="form-label">État</label>
                                        <select class="form-select" id="etat" name="etat">
                                            <option value="">Tous les états</option>
                                            <option value="En cours" <?= $etat == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                            <option value="Validé" <?= $etat == 'Validé' ? 'selected' : '' ?>>Validé</option>
                                            <option value="Payé partiellement" <?= $etat == 'Payé partiellement' ? 'selected' : '' ?>>Payé partiellement</option>
                                            <option value="Payé" <?= $etat == 'Payé' ? 'selected' : '' ?>>Payé</option>
                                            <option value="Annulé" <?= $etat == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_debut" class="form-label">Date début</label>
                                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $dateDebut ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_fin" class="form-label">Date fin</label>
                                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $dateFin ?>">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="bi bi-search"></i> Filtrer
                                        </button>
                                        <a href="achats/factures/factures.list" class="btn btn-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Tableau des factures -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th>N° Facture</th>
                                        <th>Réf. Fournisseur</th>
                                        <th>Date</th>
                                        <th>Fournisseur</th>
                                        <th>Montant TTC</th>
                                        <th>Montant payé</th>
                                        <th>Solde</th>
                                        <th>Échéance</th>
                                        <th>État</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($factures)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">Aucune facture trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($factures as $facture): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($facture['numero_facture']) ?></td>
                                                <td><?= htmlspecialchars($facture['reference_fournisseur'] ?? 'N/A') ?></td>
                                                <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                                                <td><?= htmlspecialchars($facture['nom_fournisseur']) ?></td>
                                                <td class="text-end"><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                <td class="text-end"><?= number_format($facture['montant_paye'], 2, ',', ' ') ?> USD</td>
                                                <td class="text-end"><?= number_format($facture['solde'], 2, ',', ' ') ?> USD</td>
                                                <td><?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></td>
                                                <td>
                                                    <?php
                                                    switch ($facture['etat']) {
                                                        case 'En cours':
                                                            echo '<span class="badge bg-warning">En cours</span>';
                                                            break;
                                                        case 'Validé':
                                                            echo '<span class="badge bg-success">Validé</span>';
                                                            break;
                                                        case 'Payé partiellement':
                                                            echo '<span class="badge bg-info">Payé partiellement</span>';
                                                            break;
                                                        case 'Payé':
                                                            echo '<span class="badge bg-primary">Payé</span>';
                                                            break;
                                                        case 'Annulé':
                                                            echo '<span class="badge bg-danger">Annulé</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="achats/factures/factures.view&id=<?= $facture['id_facture'] ?>" class="btn btn-sm btn-info" title="Voir">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($facture['etat'] == 'En cours'): ?>
                                                            <a href="achats/factures/factures.edit&id=<?= $facture['id_facture'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($facture['etat'] == 'Validé' || $facture['etat'] == 'Payé partiellement'): ?>
                                                            <a href="achats/paiements/paiements.add&facture=<?= $facture['id_facture'] ?>" class="btn btn-sm btn-success" title="Payer">
                                                                <i class="bi bi-cash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="4">Total</th>
                                        <th class="text-end"><?= number_format($totalTTC, 2, ',', ' ') ?> USD</th>
                                        <th class="text-end"><?= number_format($totalPaye, 2, ',', ' ') ?> USD</th>
                                        <th class="text-end"><?= number_format($totalSolde, 2, ',', ' ') ?> USD</th>
                                        <th colspan="3"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    $(document).ready(function() {
        // Initialisation de DataTables
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            "pageLength": 25,
            "order": [[2, 'desc']], // Tri par date décroissante
            "columnDefs": [
                { "orderable": false, "targets": 9 } // Désactiver le tri sur la colonne Actions
            ]
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
