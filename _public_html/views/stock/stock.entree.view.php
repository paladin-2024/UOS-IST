<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupérer l'ID de l'entrée
$id_entree = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_entree <= 0) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant d\'entrée invalide',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Récupération des informations de l'entrée
$query = "SELECT e.*, d.libelle_depot, 
                 u1.nomUser as user_creation, 
                 u2.nomUser as user_validation
          FROM entree_stock e 
          LEFT JOIN depot d ON e.id_depot = d.id_depot
          LEFT JOIN t_users u1 ON e.id_user_creation = u1.idUser
          LEFT JOIN t_users u2 ON e.id_user_validation = u2.idUser
          WHERE e.id_entree = :id_entree";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
$stmt->execute();
$entree = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entree) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Entrée de stock non trouvée',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Récupération des détails de l'entrée
$query = "SELECT d.*, p.code_produit, p.libelle_produit, u.symbole_unite,
                 l.numero_lot, l.date_peremption
          FROM detail_entree_stock d
          LEFT JOIN produit p ON d.id_produit = p.id_produit
          LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
          LEFT JOIN lot_produit l ON d.id_detail_entree = l.id_detail_entree
          WHERE d.id_entree = :id_entree
          ORDER BY d.id_detail_entree";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS ENTRÉE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.entree.list">Entrées</a></li>
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
                            Informations de l'entrée
                            <div class="float-end">
                                <?php if ($entree['etat'] == 'En cours'): ?>
                                <a href="stock/stock.entree.edit&id=<?= $id_entree ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil-square"></i> Modifier
                                </a>
                                <button onclick="confirmValidate(<?= $id_entree ?>)" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg"></i> Valider
                                </button>
                                <button onclick="confirmCancel(<?= $id_entree ?>)" class="btn btn-danger btn-sm">
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
                                        <th width="40%">Numéro d'entrée</th>
                                        <td><?= htmlspecialchars($entree['numero_entree']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date d'entrée</th>
                                        <td><?= date('d/m/Y', strtotime($entree['date_entree'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dépôt</th>
                                        <td><?= htmlspecialchars($entree['libelle_depot']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type d'entrée</th>
                                        <td><?= htmlspecialchars($entree['type_entree']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">Référence document</th>
                                        <td><?= htmlspecialchars($entree['reference_document'] ?: '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>État</th>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            switch ($entree['etat']) {
                                                case 'En cours': $badge_class = 'bg-warning'; break;
                                                case 'Validé': $badge_class = 'bg-success'; break;
                                                case 'Annulé': $badge_class = 'bg-danger'; break;
                                            }
                                            echo "<span class='badge {$badge_class}'>{$entree['etat']}</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Créé par</th>
                                        <td><?= htmlspecialchars($entree['user_creation']) ?> le <?= date('d/m/Y H:i', strtotime($entree['date_creation'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Validé par</th>
                                        <td>
                                            <?php if ($entree['id_user_validation']): ?>
                                                <?= htmlspecialchars($entree['user_validation']) ?> le <?= date('d/m/Y H:i', strtotime($entree['date_validation'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($entree['observation']): ?>
                        <div class="alert alert-info mt-3">
                            <strong>Observation:</strong> <?= nl2br(htmlspecialchars($entree['observation'])) ?>
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
                                        <th>N° Lot</th>
                                        <th>Date péremption</th>
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
                                        <td><?= htmlspecialchars($detail['numero_lot']) ?></td>
                                        <td>
                                            <?= $detail['date_peremption'] ? date('d/m/Y', strtotime($detail['date_peremption'])) : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total général:</th>
                                        <th><?= number_format($total_general, 2) ?> $</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="text-center mt-4">
                            <a href="stock/stock.entree.list" class="btn btn-secondary">
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
        const printUrl = 'controller/stock.entree.print.php?id=<?= $id_entree ?>' + layoutParam;
        
        window.open(printUrl, '_blank');
        $('#printOptionsModal').modal('hide');
    });
</script>


<script>
    function confirmValidate(idEntree) {
        Swal.fire({
            title: 'Confirmer la validation?',
            text: "Cette action va valider définitivement l'entrée de stock.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_entree_stock.php?id=' + idEntree;
            }
        });
    }
    
    function confirmCancel(idEntree) {
        Swal.fire({
            title: 'Confirmer l\'annulation?',
            text: "Cette action va annuler l'entrée de stock.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Retour'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_entree_stock.php?id=' + idEntree;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>

