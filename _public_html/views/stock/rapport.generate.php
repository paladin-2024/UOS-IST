<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des dépôts
$queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmtDepots = $db->prepare($queryDepots);
$stmtDepots->execute();
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories de produits
$queryCategories = "SELECT * FROM categorie_produit WHERE actif = 1 ORDER BY libelle_categorie";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>RAPPORTS DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Rapports</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Générer un rapport de stock</h5>
                        
                        <ul class="nav nav-tabs" id="reportTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">
                                    État du stock
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="movement-tab" data-bs-toggle="tab" data-bs-target="#movement" type="button" role="tab">
                                    Mouvements de stock
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="expiry-tab" data-bs-toggle="tab" data-bs-target="#expiry" type="button" role="tab">
                                    Produits à péremption
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="lowstock-tab" data-bs-toggle="tab" data-bs-target="#lowstock" type="button" role="tab">
                                    Produits en rupture
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-4" id="reportTabContent">
                            <!-- Rapport État du stock -->
                            <div class="tab-pane fade show active" id="inventory" role="tabpanel">
                                <form id="inventoryReportForm" action="controller/generate_inventory_report.php" method="POST" target="_blank" class="row g-3">
                                    <input type="hidden" name="report_type" value="inventory">
                                    
                                    <div class="col-md-4">
                                        <label for="inv_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="inv_depot" name="depot">
                                            <option value="all">Tous les dépôts</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="inv_categorie" class="form-label">Catégorie</label>
                                        <select class="form-select" id="inv_categorie" name="categorie">
                                            <option value="all">Toutes les catégories</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="inv_format" class="form-label">Format</label>
                                        <select class="form-select" id="inv_format" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inv_with_value" name="with_value" value="1">
                                            <label class="form-check-label" for="inv_with_value">
                                                Inclure la valorisation du stock
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inv_with_lots" name="with_lots" value="1">
                                            <label class="form-check-label" for="inv_with_lots">
                                                Détailler par lot
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> Générer le rapport
                                        </button>
                                        </div>
                                </form>
                            </div>
                            
                            <!-- Rapport Mouvements de stock -->
                            <div class="tab-pane fade" id="movement" role="tabpanel">
                                <form id="movementReportForm" action="controller/generate_movement_report.php" method="POST" target="_blank" class="row g-3">
                                    <input type="hidden" name="report_type" value="movement">
                                    
                                    <div class="col-md-4">
                                        <label for="mov_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="mov_depot" name="depot">
                                            <option value="all">Tous les dépôts</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="mov_type" class="form-label">Type de mouvement</label>
                                        <select class="form-select" id="mov_type" name="type_mouvement">
                                            <option value="all">Tous les mouvements</option>
                                            <option value="in">Entrées</option>
                                            <option value="out">Sorties</option>
                                            <option value="transfer">Transferts</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="mov_produit" class="form-label">Produit</label>
                                        <select class="form-select" id="mov_produit" name="produit">
                                            <option value="all">Tous les produits</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="mov_date_debut" class="form-label">Date de début</label>
                                        <input type="date" class="form-control" id="mov_date_debut" name="date_debut" value="<?= date('Y-m-01') ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="mov_date_fin" class="form-label">Date de fin</label>
                                        <input type="date" class="form-control" id="mov_date_fin" name="date_fin" value="<?= date('Y-m-d') ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="mov_format" class="form-label">Format</label>
                                        <select class="form-select" id="mov_format" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> Générer le rapport
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Rapport Produits à péremption -->
                            <div class="tab-pane fade" id="expiry" role="tabpanel">
                                <form id="expiryReportForm" action="controller/generate_expiry_report.php" method="POST" target="_blank" class="row g-3">
                                    <input type="hidden" name="report_type" value="expiry">
                                    
                                    <div class="col-md-4">
                                        <label for="exp_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="exp_depot" name="depot">
                                            <option value="all">Tous les dépôts</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="exp_days" class="form-label">Jours avant péremption</label>
                                        <select class="form-select" id="exp_days" name="days">
                                            <option value="30">30 jours</option>
                                            <option value="60">60 jours</option>
                                            <option value="90">90 jours</option>
                                            <option value="180">180 jours</option>
                                            <option value="365">365 jours</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="exp_format" class="form-label">Format</label>
                                        <select class="form-select" id="exp_format" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="exp_include_expired" name="include_expired" value="1" checked>
                                            <label class="form-check-label" for="exp_include_expired">
                                                Inclure les produits déjà périmés
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> Générer le rapport
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Rapport Produits en rupture -->
                            <div class="tab-pane fade" id="lowstock" role="tabpanel">
                                <form id="lowstockReportForm" action="controller/generate_lowstock_report.php" method="POST" target="_blank" class="row g-3">
                                    <input type="hidden" name="report_type" value="lowstock">
                                    
                                    <div class="col-md-4">
                                        <label for="low_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="low_depot" name="depot">
                                            <option value="all">Tous les dépôts</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="low_categorie" class="form-label">Catégorie</label>
                                        <select class="form-select" id="low_categorie" name="categorie">
                                            <option value="all">Toutes les catégories</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="low_format" class="form-label">Format</label>
                                        <select class="form-select" id="low_format" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="low_include_zero" name="include_zero" value="1" checked>
                                            <label class="form-check-label" for="low_include_zero">
                                                Inclure les produits en rupture (stock = 0)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="low_include_below_min" name="include_below_min" value="1" checked>
                                            <label class="form-check-label" for="low_include_below_min">
                                                Inclure les produits en dessous du stock minimum
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> Générer le rapport
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
    // Fonction pour charger les produits
    function loadProducts(selectElement, depotId = null) {
        selectElement.html('<option value="all">Chargement...</option>');
        
        let url = 'controller/get_products_api.php';
        let data = {};
        
        if (depotId && depotId !== 'all') {
            url = 'controller/get_depot_products_api.php';
            data = { depotId: depotId };
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            dataType: 'json',
            success: function(data) {
                selectElement.empty();
                selectElement.append('<option value="all">Tous les produits</option>');
                
                $.each(data, function(index, product) {
                    selectElement.append('<option value="' + product.id_produit + '">' + product.code_produit + ' - ' + product.libelle_produit + '</option>');
                });
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des produits:', error);
                selectElement.html('<option value="all">Tous les produits</option>');
            }
        });
    }
    
    // Gérer le changement de dépôt pour les mouvements
    $('#mov_depot').on('change', function() {
        const depotId = $(this).val();
        loadProducts($('#mov_produit'), depotId);
    });
    
    // Validation des dates pour le rapport de mouvements
    $('#movementReportForm').on('submit', function(event) {
        const dateDebut = new Date($('#mov_date_debut').val());
        const dateFin = new Date($('#mov_date_fin').val());
        
        if (dateFin < dateDebut) {
            event.preventDefault();
            Swal.fire({
                title: 'Erreur',
                text: 'La date de fin doit être postérieure à la date de début.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
    
    // Initialisation au chargement de la page
    $(document).ready(function() {
        // Charger les produits pour le rapport de mouvements
        loadProducts($('#mov_produit'));
    });
</script>

<?php include "./views/include/footer.php"; ?>
