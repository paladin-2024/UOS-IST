<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du dépôt
$depotId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($depotId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Dépôt non trouvé'
        }).then(() => {
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}

// Récupération des détails du dépôt
$query = "SELECT d.*, u.\"nomUser\" as nom_createur 
          FROM depot d 
          LEFT JOIN t_users u ON d.id_user_creation = u.\"idUser\" 
          WHERE d.id_depot = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $depotId, PDO::PARAM_INT);
$stmt->execute();
$depot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$depot) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Dépôt non trouvé'
        }).then(() => {
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}

// Récupération du stock total dans ce dépôt
$queryStock = "SELECT SUM(l.quantite_disponible) as total_stock
               FROM lot_produit l
               JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
               JOIN entree_stock es ON des.id_entree = es.id_entree
               WHERE es.id_depot = :id_depot AND l.quantite_disponible > 0";
$stmtStock = $db->prepare($queryStock);
$stmtStock->bindParam(':id_depot', $depotId, PDO::PARAM_INT);
$stmtStock->execute();
$stockInfo = $stmtStock->fetch(PDO::FETCH_ASSOC);
$totalStock = $stockInfo ? $stockInfo['total_stock'] : 0;

// Récupération des derniers mouvements de stock (entrées et sorties)
$queryMouvements = "SELECT 'Entrée' as type, e.numero_entree as numero, e.date_entree as date, SUM(de.quantite) as quantite
                    FROM entree_stock e
                    JOIN detail_entree_stock de ON e.id_entree = de.id_entree
                    WHERE e.id_depot = :id_depot AND e.etat = 'Validé'
                    GROUP BY e.id_entree
                    UNION ALL
                    SELECT 'Sortie' as type, s.numero_sortie as numero, s.date_sortie as date, SUM(ds.quantite) as quantite
                    FROM sortie_stock s
                    JOIN detail_sortie_stock ds ON s.id_sortie = ds.id_sortie
                    WHERE s.id_depot = :id_depot AND s.etat = 'Validé'
                    GROUP BY s.id_sortie
                    ORDER BY date DESC
                    LIMIT 10";
$stmtMouvements = $db->prepare($queryMouvements);
$stmtMouvements->bindParam(':id_depot', $depotId, PDO::PARAM_INT);
$stmtMouvements->execute();
$mouvements = $stmtMouvements->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU DÉPÔT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="depots/depots.list">Dépôts</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <h2><?= htmlspecialchars($depot['libelle_depot']) ?></h2>
                        <h3>Code: <?= htmlspecialchars($depot['code_depot']) ?></h3>
                        <div class="social-links mt-2">
                            <?= $depot['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title">Informations détaillées</h5>
                        
                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Code</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($depot['code_depot']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Libellé</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($depot['libelle_depot']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Adresse</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($depot['adresse'] ?? 'Non spécifiée') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Responsable</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($depot['responsable'] ?? 'Non spécifié') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Statut</div>
                            <div class="col-lg-9 col-md-8"><?= $depot['actif'] ? 'Actif' : 'Inactif' ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Créé par</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($depot['nom_createur'] ?? 'Inconnu') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Date de création</div>
                            <div class="col-lg-9 col-md-8"><?= date('d/m/Y H:i', strtotime($depot['date_creation'])) ?></div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <a href="depots/depots.edit&id=<?= $depot['id_depot'] ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>
                                <a href="depots/depots.list" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title">Statistiques du dépôt</h5>
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Stock total <span>| Unités</span></h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-box"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= number_format($totalStock, 0, ',', ' ') ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title">Derniers mouvements de stock</h5>
                        
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Numéro</th>
                                    <th>Date</th>
                                    <th>Quantité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($mouvements) > 0): ?>
                                    <?php foreach ($mouvements as $mouvement): ?>
                                        <tr>
                                            <td>
                                                <?php if ($mouvement['type'] == 'Entrée'): ?>
                                                    <span class="badge bg-success">Entrée</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Sortie</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($mouvement['numero']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($mouvement['date'])) ?></td>
                                            <td><?= number_format($mouvement['quantite'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Aucun mouvement enregistré</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
