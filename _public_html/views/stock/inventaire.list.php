<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des dépôts pour le filtre
$queryDepots = "SELECT id_depot, libelle_depot FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmtDepots = $db->prepare($queryDepots);
$stmtDepots->execute();
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Récupération des 50 derniers inventaires
$query = "SELECT i.*, d.libelle_depot, 
           u1.\"nomUser\" as user_creation_nom, 
           u2.\"nomUser\" as user_validation_nom
           FROM inventaire i
           LEFT JOIN depot d ON i.id_depot = d.id_depot
           LEFT JOIN t_users u1 ON i.id_user_creation = u1.\"idUser\"
           LEFT JOIN t_users u2 ON i.id_user_validation = u2.\"idUser\"
           ORDER BY i.date_inventaire DESC, i.id_inventaire DESC
           LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$inventaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES INVENTAIRES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Inventaires</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Inventaires
                            <a href="stock/inventaire.add" class="btn btn-primary btn-sm float-end">
                                <i class="bi bi-plus-circle"></i> Nouvel inventaire
                            </a>
                        </h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    <button class="btn btn-primary" type="button" id="searchButton">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="filterDepot" class="form-select">
                                    <option value="">Tous les dépôts</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['libelle_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="filterEtat" class="form-select">
                                    <option value="">Tous les états</option>
                                    <option value="En cours">En cours</option>
                                    <option value="Validé">Validé</option>
                                    <option value="Annulé">Annulé</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button id="resetFilters" class="btn btn-secondary w-100">
                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th>N° Inventaire</th>
                                        <th>Date</th>
                                        <th>Dépôt</th>
                                        <th>Motif</th>
                                        <th>État</th>
                                        <th>Créé par</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                <tbody>
                                    <?php foreach ($inventaires as $inventaire): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($inventaire['numero_inventaire']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($inventaire['date_inventaire'])) ?></td>
                                        <td><?= htmlspecialchars($inventaire['libelle_depot']) ?></td>
                                        <td><?= htmlspecialchars($inventaire['observation']) ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = '';
                                            switch ($inventaire['etat']) {
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
                                            <span class="badge <?= $badgeClass ?>"><?= $inventaire['etat'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($inventaire['user_creation_nom']) ?></td>
                                        <td>
                                            <a href="stock/inventaire.view&id=<?= $inventaire['id_inventaire'] ?>" class="btn btn-info btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($inventaire['etat'] == 'En cours'): ?>
                                            <a href="stock/inventaire.edit&id=<?= $inventaire['id_inventaire'] ?>" class="btn btn-warning btn-sm">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <button type="button" class="btn btn-success btn-sm" onclick="validateInventory(<?= $inventaire['id_inventaire'] ?>)">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" onclick="cancelInventory(<?= $inventaire['id_inventaire'] ?>)">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <a href="controller/inventaire.print.php?id=<?= $inventaire['id_inventaire'] ?>" target="_blank" class="btn btn-primary">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($inventaires) == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Aucun inventaire trouvé</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <p><small>Affichage des 50 derniers inventaires</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
    function validateInventory(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: 'Êtes-vous sûr de vouloir valider cet inventaire ? Cette action est irréversible et ajustera les stocks en fonction des écarts constatés.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_inventaire.php?id=' + id;
            }
        });
    }
    
    function cancelInventory(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: 'Êtes-vous sûr de vouloir annuler cet inventaire ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_inventaire.php?id=' + id;
            }
        });
    }

    // Initialisation de DataTables avec filtres
    $(document).ready(function() {
        const dataTable = $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            }
        });

        // Recherche générale
        $('#searchButton').on('click', function() {
            dataTable.search($('#searchInput').val()).draw();
        });

        // Recherche sur la touche Entrée
        $('#searchInput').on('keyup', function(e) {
            if (e.key === 'Enter') {
                dataTable.search(this.value).draw();
            }
        });

        // Filtres
        $('#filterDepot').on('change', function() {
            dataTable.column(2).search(this.value).draw();
        });

        $('#filterEtat').on('change', function() {
            dataTable.column(4).search(this.value).draw();
        });

        // Réinitialiser les filtres
        $('#resetFilters').on('click', function() {
            $('#searchInput').val('');
            $('#filterDepot').val('');
            $('#filterEtat').val('');
            dataTable.search('').columns().search('').draw();
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
