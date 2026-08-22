<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Paramètres pour le filtrage
$etat = isset($_GET['etat']) ? $_GET['etat'] : 'all';
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');

// Construction de la requête avec filtres
$query = "SELECT t.*, 
           d1.libelle_depot as depot_source_nom,
           d2.libelle_depot as depot_destination_nom,
           u1.nomUser as user_creation_nom
           FROM transfert_stock t
           LEFT JOIN depot d1 ON t.id_depot_source = d1.id_depot
           LEFT JOIN depot d2 ON t.id_depot_destination = d2.id_depot
           LEFT JOIN t_users u1 ON t.id_user_creation = u1.idUser
           WHERE t.date_transfert BETWEEN :date_debut AND :date_fin";

if ($etat !== 'all') {
    $query .= " AND t.etat = :etat";
}

$query .= " ORDER BY t.date_transfert DESC, t.id_transfert DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->bindParam(':date_debut', $dateDebut);
$stmt->bindParam(':date_fin', $dateFin);

if ($etat !== 'all') {
    $stmt->bindParam(':etat', $etat);
}

$stmt->execute();
$transferts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES TRANSFERTS DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Transferts</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="stock/transfert.list">
                            
                            <div class="col-md-3">
                                <label for="etat" class="form-label">État</label>
                                <select class="form-select" id="etat" name="etat">
                                    <option value="all" <?= $etat === 'all' ? 'selected' : '' ?>>Tous</option>
                                    <option value="En cours" <?= $etat === 'En cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="Validé" <?= $etat === 'Validé' ? 'selected' : '' ?>>Validé</option>
                                    <option value="Annulé" <?= $etat === 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_debut" class="form-label">Du</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $dateDebut ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_fin" class="form-label">Au</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $dateFin ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label"> </label>
                                <div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Filtrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Liste des transferts -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Transferts de stock
                            <a href="stock/stock.transfert.add" class="btn btn-primary btn-sm float-end">
                                <i class="bi bi-plus-circle"></i> Nouveau transfert
                            </a>
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>N° Transfert</th>
                                        <th>Date</th>
                                        <th>Dépôt source</th>
                                        <th>Dépôt destination</th>
                                        <th>État</th>
                                        <th>Créé par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transferts as $transfert): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transfert['numero_transfert']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($transfert['date_transfert'])) ?></td>
                                        <td><?= htmlspecialchars($transfert['depot_source_nom']) ?></td>
                                        <td><?= htmlspecialchars($transfert['depot_destination_nom']) ?></td>
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
                                        <td><?= htmlspecialchars($transfert['user_creation_nom']) ?></td>
                                        <td>
                                            <a href="stock/transfert.view&id=<?= $transfert['id_transfert'] ?>" class="btn btn-info btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($transfert['etat'] == 'En cours'): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="validateTransfer(<?= $transfert['id_transfert'] ?>)">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" onclick="cancelTransfer(<?= $transfert['id_transfert'] ?>)">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <a href="controller/generate_transfert_document.php?id=<?= $transfert['id_transfert'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($transferts) == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Aucun transfert trouvé pour la période sélectionnée</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <p><small>Affichage limité à 100 transferts maximum</small></p>
                        </div>
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
