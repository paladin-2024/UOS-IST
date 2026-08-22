<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupérer l'ID de la sortie
$id_sortie = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations de la sortie
$query = "SELECT s.*, d.libelle_depot, 
          u1.nomUser as user_creation, 
          u2.nomUser as user_validation
          FROM sortie_stock s
          LEFT JOIN depot d ON s.id_depot = d.id_depot
          LEFT JOIN t_users u1 ON s.id_user_creation = u1.idUser
          LEFT JOIN t_users u2 ON s.id_user_validation = u2.idUser
          WHERE s.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Sortie de stock non trouvée',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = 'stock/stock.sortie.list';
        });
    </script>";
    exit;
}

// Récupérer les détails de la sortie
$query = "SELECT d.*, p.code_produit, p.libelle_produit, u.symbole_unite
          FROM detail_sortie_stock d
          JOIN produit p ON d.id_produit = p.id_produit 
          LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
          WHERE d.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails par lot
$query = "SELECT dl.*, l.numero_lot, l.date_peremption
          FROM detail_sortie_lot dl
          JOIN lot_produit l ON dl.id_lot = l.id_lot
          JOIN detail_sortie_stock d ON dl.id_detail_sortie = d.id_detail_sortie
          WHERE d.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$lotDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les détails de lot par id_detail_sortie
$sortedLotDetails = [];
foreach ($lotDetails as $lot) {
    $sortedLotDetails[$lot['id_detail_sortie']][] = $lot;
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DE LA SORTIE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.sortie.list">Sorties</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Informations de la sortie
                            <div class="float-end">
                                <?php if ($sortie['etat'] == 'En cours'): ?>
                                <a href="stock/stock.sortie.edit&id=<?= $id_sortie ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil-square"></i> Modifier
                                </a>
                                <button onclick="confirmValidate(<?= $id_sortie ?>)" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg"></i> Valider
                                </button>
                                <button onclick="confirmCancel(<?= $id_sortie ?>)" class="btn btn-danger btn-sm">
                                    <i class="bi bi-x-lg"></i> Annuler
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#printOptionsModal">
                                    <i class="bi bi-printer"></i> Imprimer
                                </button>

                            </div>
                        </h5>

                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">Numéro de sortie</th>
                                        <td><?= htmlspecialchars($sortie['numero_sortie']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date de sortie</th>
                                        <td><?= date('d/m/Y', strtotime($sortie['date_sortie'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dépôt</th>
                                        <td><?= htmlspecialchars($sortie['libelle_depot']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type de sortie</th>
                                        <td><?= htmlspecialchars($sortie['type_sortie']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">Référence document</th>
                                        <td><?= htmlspecialchars($sortie['reference_document'] ?: '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>État</th>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            switch ($sortie['etat']) {
                                                case 'En cours': $badge_class = 'bg-warning'; break;
                                                case 'Validé': $badge_class = 'bg-success'; break;
                                                case 'Annulé': $badge_class = 'bg-danger'; break;
                                            }
                                            echo "<span class='badge {$badge_class}'>{$sortie['etat']}</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Créé par</th>
                                        <td><?= htmlspecialchars($sortie['user_creation']) ?> le <?= date('d/m/Y H:i', strtotime($sortie['date_creation'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Validé par</th>
                                        <td>
                                            <?php if ($sortie['id_user_validation']): ?>
                                                <?= htmlspecialchars($sortie['user_validation']) ?> le <?= date('d/m/Y H:i', strtotime($sortie['date_validation'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($sortie['observation']): ?>
                        <div class="alert alert-info mt-3">
                            <strong>Observation:</strong> <?= nl2br(htmlspecialchars($sortie['observation'])) ?>
                        </div>
                        <?php endif; ?>

                        <h5 class="card-title mt-4">Détails des produits</h5>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Prix unitaire</th>
                                        <th>Montant total</th>
                                        <th>Détails des lots</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_general = 0;
                                    foreach ($details as $detail): 
                                        $total_general += $detail['montant_total'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detail['code_produit']) ?></td>
                                        <td><?= htmlspecialchars($detail['libelle_produit']) ?></td>
                                        <td><?= number_format($detail['quantite'], 2) ?> <?= htmlspecialchars($detail['symbole_unite'] ?? '') ?></td>
                                        <td><?= number_format($detail['prix_unitaire'], 2) ?> $</td>
                                        <td><?= number_format($detail['montant_total'], 2) ?> $</td>
                                        <td>
                                            <?php if (isset($sortedLotDetails[$detail['id_detail_sortie']])): ?>
                                                <ul class="list-unstyled">
                                                <?php foreach ($sortedLotDetails[$detail['id_detail_sortie']] as $lot): ?>
                                                    <li>
                                                        <strong>Lot:</strong> <?= htmlspecialchars($lot['numero_lot']) ?>
                                                        <?php if ($lot['date_peremption']): ?>
                                                            (Exp: <?= date('d/m/Y', strtotime($lot['date_peremption'])) ?>)
                                                        <?php endif; ?>
                                                        - <strong>Qté:</strong> <?= number_format($lot['quantite'], 2) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total général:</th>
                                        <th><?= number_format($total_general, 2) ?> $</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="text-center mt-4">
                            <a href="stock/stock.sortie.list" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour à la liste
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<!-- Modal pour les options d'impression -->
<div class="modal fade" id="printOptionsModal" tabindex="-1" aria-labelledby="printOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printOptionsModalLabel">Options d'impression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Disposition:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="printLayout" id="layoutFull" value="full" checked>
                        <label class="form-check-label" for="layoutFull">
                            1 bon par page (standard)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="printLayout" id="layoutCompact" value="compact">
                        <label class="form-check-label" for="layoutCompact">
                            2 bons par page (si possible)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="printBtn">Imprimer</button>
            </div>
        </div>
    </div>
</div>


<script>
    // Ajouter après vos scripts existants
document.getElementById('printBtn').addEventListener('click', function() {
    const layout = document.querySelector('input[name="printLayout"]:checked').value;
    const layoutParam = layout === 'compact' ? '&format=compact' : '';
    const printUrl = 'controller/stock.sortie.print.php?id=<?= $id_sortie ?>' + layoutParam;
    
    window.open(printUrl, '_blank');
    $('#printOptionsModal').modal('hide');
});

    function confirmValidate(idSortie) {
        Swal.fire({
            title: 'Confirmer la validation?',
            text: "Cette action va valider définitivement la sortie de stock et mettre à jour les quantités disponibles des lots.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_sortie_stock.php?id=' + idSortie;
            }
        });
    }
    
    function confirmCancel(idSortie) {
        Swal.fire({
            title: 'Confirmer l\'annulation?',
            text: "Cette action va annuler la sortie de stock.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Retour'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_sortie_stock.php?id=' + idSortie;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>

