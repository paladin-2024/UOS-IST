<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Paramètres de pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$fournisseur = isset($_GET['fournisseur']) ? intval($_GET['fournisseur']) : 0;
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$etat = isset($_GET['etat']) ? $_GET['etat'] : '';

// Construction de la requête avec filtres
$whereClause = [];
$params = [];

if ($fournisseur > 0) {
    $whereClause[] = "rf.id_fournisseur = :fournisseur";
    $params[':fournisseur'] = $fournisseur;
}

if (!empty($dateDebut)) {
    $whereClause[] = "rf.date_reception >= :date_debut";
    $params[':date_debut'] = $dateDebut;
}

if (!empty($dateFin)) {
    $whereClause[] = "rf.date_reception <= :date_fin";
    $params[':date_fin'] = $dateFin;
}

if (!empty($etat)) {
    $whereClause[] = "rf.etat = :etat";
    $params[':etat'] = $etat;
}

$where = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

// Requête pour compter le nombre total de réceptions
$queryCount = "SELECT COUNT(*) as total FROM reception_fournisseur rf $where";
$stmtCount = $db->prepare($queryCount);
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}
$stmtCount->execute();
$totalReceptions = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalReceptions / $limit);

// Requête pour récupérer les réceptions avec pagination
$query = "SELECT rf.*, f.nom_fournisseur, 
          CASE WHEN rf.id_commande IS NULL THEN 'Sans commande' ELSE cf.numero_commande END as numero_commande 
          FROM reception_fournisseur rf 
          JOIN fournisseur f ON rf.id_fournisseur = f.id_fournisseur 
          LEFT JOIN commande_fournisseur cf ON rf.id_commande = cf.id_commande 
          $where 
          ORDER BY rf.date_reception DESC, rf.id_reception DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$receptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des fournisseurs pour le filtre
$queryFournisseurs = "SELECT id_fournisseur, code_fournisseur, nom_fournisseur 
                     FROM fournisseur 
                     WHERE actif = 1 
                     ORDER BY nom_fournisseur";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES RÉCEPTIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item active">Réceptions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Réceptions de commandes fournisseurs</h5>
                            
                            <!-- Ajout des boutons pour créer une nouvelle réception -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-plus-circle"></i> Nouvelle réception
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="achats/receptions/receptions.add"><i class="bi bi-file-earmark-plus me-2"></i>Réception directe</a></li>
                                    <li><a class="dropdown-item" href="achats/commandes/commandes.list?reception=1"><i class="bi bi-file-earmark-check me-2"></i>À partir d'une commande</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Filtres -->
                        <form action="" method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="p" value="achats/receptions/receptions.list">
                            
                            <div class="col-md-3">
                                <label for="fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="fournisseur" name="fournisseur">
                                    <option value="">Tous les fournisseurs</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['id_fournisseur'] ?>" <?= $fournisseur == $f['id_fournisseur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['code_fournisseur'] . ' - ' . $f['nom_fournisseur']) ?>
                                        </option>
                                    <?php endforeach; ?>
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
                            
                            <div class="col-md-2">
                                <label for="etat" class="form-label">État</label>
                                <select class="form-select" id="etat" name="etat">
                                    <option value="">Tous les états</option>
                                    <option value="Validé" <?= $etat == 'Validé' ? 'selected' : '' ?>>Validé</option>
                                    <option value="Annulé" <?= $etat == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                                <a href="?p=achats/receptions/receptions.list" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                </a>
                            </div>
                        </form>

                        <!-- Tableau des réceptions -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                            <thead>
    <tr>
        <th>N° Réception</th>
        <th>Date</th>
        <th>Commande</th>
        <th>Fournisseur</th>
        <th>Référence BL</th>
        <th>Montant</th>
        <th>État</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php if (empty($receptions)): ?>
        <tr>
            <td colspan="8" class="text-center">Aucune réception trouvée</td>
        </tr>
    <?php else: ?>
        <?php foreach ($receptions as $reception): ?>
            <tr>
                <td><?= htmlspecialchars($reception['numero_reception']) ?></td>
                <td><?= date('d/m/Y', strtotime($reception['date_reception'])) ?></td>
                <td>
                    <?php if ($reception['numero_commande'] === 'Sans commande'): ?>
                        <span class="badge bg-secondary">Sans commande</span>
                    <?php else: ?>
                        <?= htmlspecialchars($reception['numero_commande']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($reception['nom_fournisseur']) ?></td>
                <td><?= htmlspecialchars($reception['reference_bl']) ?></td>
                <td class="text-end"><?= number_format($reception['montant_total'] ?? 0, 2, ',', ' ') ?> USD</td>
                <td>
                    <span class="badge bg-<?= $reception['etat'] == 'Validé' ? 'success' : 'danger' ?>">
                        <?= $reception['etat'] ?>
                    </span>
                </td>
                <td>
                    <a href="achats/receptions/receptions.view&id=<?= $reception['id_reception'] ?>" class="btn btn-sm btn-info">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a onclick="printReception(<?= $reception['id_reception'] ?>)" class="btn btn-sm btn-secondary">
                        <i class="bi bi-printer"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>

                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-3">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?p=achats/receptions/receptions.list&page=<?= $page - 1 ?>&fournisseur=<?= $fournisseur ?>&date_debut=<?= $dateDebut ?>&date_fin=<?= $dateFin ?>&etat=<?= $etat ?>">Précédent</a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?p=achats/receptions/receptions.list&page=<?= $i ?>&fournisseur=<?= $fournisseur ?>&date_debut=<?= $dateDebut ?>&date_fin=<?= $dateFin ?>&etat=<?= $etat ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?p=achats/receptions/receptions.list&page=<?= $page + 1 ?>&fournisseur=<?= $fournisseur ?>&date_debut=<?= $dateDebut ?>&date_fin=<?= $dateFin ?>&etat=<?= $etat ?>">Suivant</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function printReception(id) {
        window.open('controller/reception_pdf.php?id=' + id, '_blank');
    }
    </script>

<?php include "./views/include/footer.php"; ?>
