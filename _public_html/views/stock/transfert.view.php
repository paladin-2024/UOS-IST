<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupérer l'ID du transfert
$idTransfert = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idTransfert <= 0) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant de transfert invalide',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'stock/transfert.list';
        });
    </script>";
    exit;
}

// Récupérer les informations du transfert
$query = "SELECT t.*, 
           d1.libelle_depot as depot_source_nom,
           d2.libelle_depot as depot_destination_nom,
           u1.\"nomUser\" as user_creation_nom, 
           u2.\"nomUser\" as user_validation_nom
           FROM transfert_stock t
           LEFT JOIN depot d1 ON t.id_depot_source = d1.id_depot
           LEFT JOIN depot d2 ON t.id_depot_destination = d2.id_depot
           LEFT JOIN t_users u1 ON t.id_user_creation = u1.\"idUser\"
           LEFT JOIN t_users u2 ON t.id_user_validation = u2.\"idUser\"
           WHERE t.id_transfert = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $idTransfert, PDO::PARAM_INT);
$stmt->execute();
$transfert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transfert) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Transfert non trouvé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'stock/transfert.list';
        });
    </script>";
    exit;
}

// Récupérer les détails du transfert
$query = "SELECT dt.*, p.code_produit, p.libelle_produit, u.libelle_unite, l.numero_lot, l.date_peremption
           FROM detail_transfert_stock dt
           JOIN produit p ON dt.id_produit = p.id_produit
           JOIN lot_produit l ON dt.id_lot = l.id_lot
           JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
           WHERE dt.id_transfert = :id
           ORDER BY p.libelle_produit";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $idTransfert, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAIL DU TRANSFERT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/transfert.list">Transferts</a></li>
                <li class="breadcrumb-item active">Détail du transfert</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Informations du transfert
                            <div class="float-end">
                                <a href="stock/transfert.list" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                                <a href="controller/generate_transfert_document.php?id=<?= $idTransfert ?>" target="_blank" class="btn btn-primary btn-sm">
                                    <i class="bi bi-file-pdf"></i> Imprimer
                                </a>
                            </div>
                        </h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">N° Transfert</th>
                                        <td><?= htmlspecialchars($transfert['numero_transfert']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date</th>
                                        <td><?= date('d/m/Y', strtotime($transfert['date_transfert'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dépôt source</th>
                                        <td><?= htmlspecialchars($transfert['depot_source_nom']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dépôt destination</th>
                                        <td><?= htmlspecialchars($transfert['depot_destination_nom']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">État</th>
                                        <td>
                                            <?php 
                                            $badgeClass = '';
                                            switch ($transfert['etat']) {
                                                case 'En cours':
                                                    $badgeClass = 'bg-warning';
                                                    break;
                                                case 'Validé':
                                                    $badgeClass = 'bg-success';
                                                    break;
                                                case 'Annulé':
                                                    $badgeClass = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= $transfert['etat'] ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Créé par</th>
                                        <td><?= htmlspecialchars($transfert['user_creation_nom']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date création</th>
                                        <td><?= date('d/m/Y H:i', strtotime($transfert['date_creation'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Validé par</th>
                                        <td><?= $transfert['user_validation_nom'] ? htmlspecialchars($transfert['user_validation_nom']) : 'Non validé' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($transfert['observation'])): ?>
                        <div class="alert alert-info">
                            <strong>Observation:</strong> <?= nl2br(htmlspecialchars($transfert['observation'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <h5 class="card-title">Articles transférés</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Produit</th>
                                        <th>Lot</th>
                                        <th>Date péremption</th>
                                        <th>Quantité</th>
                                        <th>Unité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $detail): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detail['code_produit']) ?></td>
                                        <td><?= htmlspecialchars($detail['libelle_produit']) ?></td>
                                        <td><?= htmlspecialchars($detail['numero_lot']) ?></td>
                                        <td>
                                            <?= $detail['date_peremption'] ? date('d/m/Y', strtotime($detail['date_peremption'])) : 'N/A' ?>
                                        </td>
                                        <td class="text-end"><?= number_format($detail['quantite'], 2, ',', ' ') ?></td>
                                        <td><?= htmlspecialchars($detail['libelle_unite']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($details) == 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun article dans ce transfert</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($transfert['etat'] == 'En cours'): ?>
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-success" onclick="validateTransfer(<?= $idTransfert ?>)">
                                <i class="bi bi-check-circle"></i> Valider le transfert
                            </button>
                            <button type="button" class="btn btn-danger" onclick="cancelTransfer(<?= $idTransfert ?>)">
                                <i class="bi bi-x-circle"></i> Annuler le transfert
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
    function validateTransfer(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: 'Êtes-vous sûr de vouloir valider ce transfert ? Cette action est irréversible et déplacera définitivement les stocks entre les dépôts.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_transfert.php?id=' + id;
            }
        });
    }
    
    function cancelTransfer(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: 'Êtes-vous sûr de vouloir annuler ce transfert ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_transfert.php?id=' + id;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
