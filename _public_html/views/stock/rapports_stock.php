<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des dépôts
$query = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmt = $db->prepare($query);
$stmt->execute();
$depots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories
$queryCategories = "SELECT * FROM categorie_produit ORDER BY libelle_categorie";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
?>

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
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Générateur de rapports de stock</h5>

                        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] == 'mouvements') ? 'active' : '' ?>" id="mouvements-tab" data-bs-toggle="tab" data-bs-target="#mouvements" type="button" role="tab" aria-controls="mouvements" aria-selected="<?= (!isset($_GET['tab']) || $_GET['tab'] == 'mouvements') ? 'true' : 'false' ?>">Mouvements de stock</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] == 'etat-stock') ? 'active' : '' ?>" id="etat-stock-tab" data-bs-toggle="tab" data-bs-target="#etat-stock" type="button" role="tab" aria-controls="etat-stock" aria-selected="<?= (isset($_GET['tab']) && $_GET['tab'] == 'etat-stock') ? 'true' : 'false' ?>">État du stock</button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="valorisation-tab" data-bs-toggle="tab" data-bs-target="#valorisation" type="button" role="tab" aria-controls="valorisation" aria-selected="false">Valorisation</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="lots-tab" data-bs-toggle="tab" data-bs-target="#lots" type="button" role="tab" aria-controls="lots" aria-selected="false">Gestion des lots</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analyse-tab" data-bs-toggle="tab" data-bs-target="#analyse" type="button" role="tab" aria-controls="analyse" aria-selected="false">Analyse</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="alertes-tab" data-bs-toggle="tab" data-bs-target="#alertes" type="button" role="tab" aria-controls="alertes" aria-selected="false">Alertes</button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3" id="reportTabsContent">
                            <div class="tab-content pt-3" id="reportTabsContent">
                                <!-- Mouvements de stock - Maintenant premier onglet -->
                                <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] == 'mouvements') ? 'show active' : '' ?>" id="mouvements" role="tabpanel" aria-labelledby="mouvements-tab">
                                    <form action="" method="GET" id="depotSelectionForm" class="row mb-3">
                                        <input type="hidden" name="page" value="stock/rapports_stock">
                                        <input type="hidden" name="tab" value="mouvements">

                                        <div class="col-md-4">
                                            <label for="mouvement_type" class="form-label">Type de mouvement</label>
                                            <select class="form-select" id="mouvement_type" name="mouvement_type">
                                                <option value="all" <?= (isset($_GET['mouvement_type']) && $_GET['mouvement_type'] == 'all') ? 'selected' : '' ?>>Tous les mouvements</option>
                                                <option value="entree" <?= (isset($_GET['mouvement_type']) && $_GET['mouvement_type'] == 'entree') ? 'selected' : '' ?>>Entrées de stock</option>
                                                <option value="sortie" <?= (isset($_GET['mouvement_type']) && $_GET['mouvement_type'] == 'sortie') ? 'selected' : '' ?>>Sorties de stock</option>
                                                <option value="transfert" <?= (isset($_GET['mouvement_type']) && $_GET['mouvement_type'] == 'transfert') ? 'selected' : '' ?>>Transferts</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label for="depot_mouvement" class="form-label">Dépôt</label>
                                            <select class="form-select" id="depot_mouvement" name="depot_id" onchange="this.form.submit()">
                                                <option value="all">Tous les dépôts</option>
                                                <?php foreach ($depots as $depot): ?>
                                                    <option value="<?= $depot['id_depot'] ?>" <?= (isset($_GET['depot_id']) && $_GET['depot_id'] == $depot['id_depot']) ? 'selected' : '' ?>><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label for="produit_id" class="form-label">Produit (optionnel)</label>
                                            <select class="form-select" id="produit_id" name="produit_id">
                                                <option value="all">Tous les produits</option>
                                                <?php
                                                // Si un dépôt est sélectionné, charger les produits correspondants
                                                if (isset($_GET['depot_id']) && $_GET['depot_id'] != 'all') {
                                                    $depotId = $_GET['depot_id'];

                                                    // Requête pour obtenir les produits du dépôt sélectionné
                                                    $queryProducts = "SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit 
                                          FROM produit p
                                          INNER JOIN lot_produit lp ON p.id_produit = lp.id_produit
                                          INNER JOIN detail_entree_stock des ON lp.id_detail_entree = des.id_detail_entree
                                          INNER JOIN entree_stock es ON des.id_entree = es.id_entree
                                          WHERE p.actif = 1 AND es.id_depot = :depot_id AND es.etat = 'Validé'
                                          ORDER BY p.code_produit";
                                                    $stmtProducts = $db->prepare($queryProducts);
                                                    $stmtProducts->bindParam(':depot_id', $depotId, PDO::PARAM_INT);
                                                    $stmtProducts->execute();
                                                    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

                                                    foreach ($products as $product) {
                                                        $selected = (isset($_GET['produit_id']) && $_GET['produit_id'] == $product['id_produit']) ? 'selected' : '';
                                                        echo "<option value='{$product['id_produit']}' {$selected}>{$product['code_produit']} - {$product['libelle_produit']}</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </form>

                                    <!-- Formulaire modifié pour pointer vers un contrôleur spécifique -->
                                    <form action="controller/rapports/mouvements_stock.php" method="post" target="_blank" class="mt-3">
                                        <input type="hidden" name="depot_id" value="<?= isset($_GET['depot_id']) ? $_GET['depot_id'] : 'all' ?>">
                                        <input type="hidden" name="mouvement_type" value="<?= isset($_GET['mouvement_type']) ? $_GET['mouvement_type'] : 'all' ?>">
                                        <input type="hidden" name="produit_id" value="<?= isset($_GET['produit_id']) ? $_GET['produit_id'] : 'all' ?>">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="date_debut" class="form-label">Date de début</label>
                                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= date('Y-m-01') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_fin" class="form-label">Date de fin</label>
                                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- État du stock -->
                                <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] == 'etat-stock') ? 'show active' : '' ?>" id="etat-stock" role="tabpanel" aria-labelledby="etat-stock-tab">
                                    <form action="controller/rapports/etat_stock.php" method="post" target="_blank">
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="depot_id" class="form-label">Dépôt</label>
                                                <select class="form-select" id="depot_id" name="depot_id">
                                                    <option value="all">Tous les dépôts</option>
                                                    <?php foreach ($depots as $depot): ?>
                                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="categorie_id" class="form-label">Catégorie</label>
                                                <select class="form-select" id="categorie_id" name="categorie_id">
                                                    <option value="all">Toutes les catégories</option>
                                                    <?php foreach ($categories as $categorie): ?>
                                                        <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="sort_by" class="form-label">Trier par</label>
                                                <select class="form-select" id="sort_by" name="sort_by">
                                                    <option value="code">Code produit</option>
                                                    <option value="libelle">Libellé</option>
                                                    <option value="categorie">Catégorie</option>
                                                    <option value="quantite">Quantité (décroissant)</option>
                                                    <option value="valeur">Valeur (décroissant)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="zero_stock" name="zero_stock" value="1">
                                                    <label class="form-check-label" for="zero_stock">
                                                        Inclure les produits avec un stock à zéro
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="with_image" name="with_image" value="1">
                                                    <label class="form-check-label" for="with_image">
                                                        Inclure les images des produits
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>



                                <!-- Valorisation -->
                                <div class="tab-pane fade" id="valorisation" role="tabpanel" aria-labelledby="valorisation-tab">
                                    <form action="controller/rapports/valorisation_stock.php" method="post" target="_blank">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="date_valorisation" class="form-label">Date de valorisation</label>
                                                <input type="date" class="form-control" id="date_valorisation" name="date_valorisation" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="depot_valorisation" class="form-label">Dépôt</label>
                                                <select class="form-select" id="depot_valorisation" name="depot_id">
                                                    <option value="all">Tous les dépôts</option>
                                                    <?php foreach ($depots as $depot): ?>
                                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="categorie_valorisation" class="form-label">Catégorie</label>
                                                <select class="form-select" id="categorie_valorisation" name="categorie_id">
                                                    <option value="all">Toutes les catégories</option>
                                                    <?php foreach ($categories as $categorie): ?>
                                                        <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="type_valorisation" class="form-label">Type de valorisation</label>
                                                <select class="form-select" id="type_valorisation" name="type_valorisation">
                                                    <option value="achat">Prix d'achat</option>
                                                    <option value="vente">Prix de vente</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Gestion des lots -->
                                <div class="tab-pane fade" id="lots" role="tabpanel" aria-labelledby="lots-tab">
                                    <form action="controller/rapports/lots_stock.php" method="post" target="_blank">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="depot_lots" class="form-label">Dépôt</label>
                                                <select class="form-select" id="depot_lots" name="depot_id">
                                                    <option value="all">Tous les dépôts</option>
                                                    <?php foreach ($depots as $depot): ?>
                                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="type_rapport_lots" class="form-label">Type de rapport</label>
                                                <select class="form-select" id="type_rapport_lots" name="type_rapport_lots">
                                                    <option value="par_produit">Lots par produit</option>
                                                    <option value="par_peremption">Lots par date de péremption</option>
                                                    <option value="tracabilite">Traçabilité des lots</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="produit_lots" class="form-label">Produit (optionnel)</label>
                                                <select class="form-select" id="produit_lots" name="produit_id">
                                                    <option value="all">Tous les produits</option>
                                                    <!-- Cette liste sera remplie par AJAX -->
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="peremption_avant" class="form-label">Péremption avant (optionnel)</label>
                                                <input type="date" class="form-control" id="peremption_avant" name="peremption_avant">
                                            </div>
                                        </div>

                                        <div class="row mb-3 lot-tracabilite" style="display: none;">
                                            <div class="col-md-6">
                                                <label for="numero_lot" class="form-label">Numéro de lot</label>
                                                <input type="text" class="form-control" id="numero_lot" name="numero_lot">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="periode_tracabilite" class="form-label">Période</label>
                                                <select class="form-select" id="periode_tracabilite" name="periode_tracabilite">
                                                    <option value="30">Dernier mois</option>
                                                    <option value="90">Dernier trimestre</option>
                                                    <option value="180">Dernier semestre</option>
                                                    <option value="365">Dernière année</option>
                                                    <option value="custom">Période personnalisée</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3 periode-custom" style="display: none;">
                                            <div class="col-md-6">
                                                <label for="date_debut_tracabilite" class="form-label">Date de début</label>
                                                <input type="date" class="form-control" id="date_debut_tracabilite" name="date_debut_tracabilite">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_fin_tracabilite" class="form-label">Date de fin</label>
                                                <input type="date" class="form-control" id="date_fin_tracabilite" name="date_fin_tracabilite">
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Analyse -->
                                <div class="tab-pane fade" id="analyse" role="tabpanel" aria-labelledby="analyse-tab">
                                    <form action="controller/rapports/analyse_stock.php" method="post" target="_blank">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="type_analyse" class="form-label">Type d'analyse</label>
                                                <select class="form-select" id="type_analyse" name="type_analyse">
                                                    <option value="faible_rotation">Produits à faible rotation</option>
                                                    <option value="plus_vendus">Produits les plus vendus</option>
                                                    <option value="ecarts_inventaire">Écarts d'inventaire</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="depot_analyse" class="form-label">Dépôt</label>
                                                <select class="form-select" id="depot_analyse" name="depot_id">
                                                    <option value="all">Tous les dépôts</option>
                                                    <?php foreach ($depots as $depot): ?>
                                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="date_debut_analyse" class="form-label">Date de début</label>
                                                <input type="date" class="form-control" id="date_debut_analyse" name="date_debut" 
                                                       value="<?= date('Y-m-d', strtotime('-3 months')) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_fin_analyse" class="form-label">Date de fin</label>
                                                <input type="date" class="form-control" id="date_fin_analyse" name="date_fin" 
                                                       value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3 limite-produits">
                                            <div class="col-md-6">
                                                <label for="limite_produits" class="form-label">Nombre de produits à afficher</label>
                                                <select class="form-select" id="limite_produits" name="limite_produits">
                                                    <option value="10">10 produits</option>
                                                    <option value="20">20 produits</option>
                                                    <option value="50">50 produits</option>
                                                    <option value="100">100 produits</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="categorie_analyse" class="form-label">Catégorie</label>
                                                <select class="form-select" id="categorie_analyse" name="categorie_id">
                                                    <option value="all">Toutes les catégories</option>
                                                    <?php foreach ($categories as $categorie): ?>
                                                        <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Alertes -->
                                <div class="tab-pane fade" id="alertes" role="tabpanel" aria-labelledby="alertes-tab">
                                    <form action="controller/rapports/alertes_stock.php" method="post" target="_blank">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="type_alerte" class="form-label">Type d'alerte</label>
                                                <select class="form-select" id="type_alerte" name="type_alerte">
                                                    <option value="rupture">Produits en rupture de stock</option>
                                                    <option value="seuil_min">Produits sous le seuil minimal</option>
                                                    <option value="peremption">Produits proches de la péremption</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="depot_alerte" class="form-label">Dépôt</label>
                                                <select class="form-select" id="depot_alerte" name="depot_id">
                                                    <option value="all">Tous les dépôts</option>
                                                    <?php foreach ($depots as $depot): ?>
                                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="categorie_alerte" class="form-label">Catégorie</label>
                                                <select class="form-select" id="categorie_alerte" name="categorie_id">
                                                    <option value="all">Toutes les catégories</option>
                                                    <?php foreach ($categories as $categorie): ?>
                                                        <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 jours-peremption" style="display: none;">
                                                <label for="jours_peremption" class="form-label">Jours avant péremption</label>
                                                <input type="number" class="form-control" id="jours_peremption" name="jours_peremption" value="30">
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" name="format" value="html" class="btn btn-primary">
                                                <i class="bi bi-display"></i> Prévisualiser
                                            </button>
                                            <button type="submit" name="format" value="pdf" class="btn btn-danger">
                                                <i class="bi bi-file-pdf"></i> Exporter en PDF
                                            </button>
                                            <button type="submit" name="format" value="excel" class="btn btn-success">
                                                <i class="bi bi-file-excel"></i> Exporter en Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
$(document).ready(function() {
    // Initialiser Select2 pour les sélecteurs statiques
    $('.form-select').select2({
        width: '100%',
        placeholder: 'Sélectionner une option',
        allowClear: true
    });
    
    // Conserver vos fonctions existantes pour les onglets
    $('#type_rapport_lots').change(function() {
        if ($(this).val() === 'tracabilite') {
            $('.lot-tracabilite').show();
        } else {
            $('.lot-tracabilite').hide();
        }
    });
    
    $('#periode_tracabilite').change(function() {
        if ($(this).val() === 'custom') {
            $('.periode-custom').show();
        } else {
            $('.periode-custom').hide();
        }
    });
    
    $('#type_alerte').change(function() {
        if ($(this).val() === 'peremption') {
            $('.jours-peremption').show();
        } else {
            $('.jours-peremption').hide();
        }
    });
    
    // IMPORTANT: Utilisation de la délégation d'événements comme dans inventaire.add.php
    $(document).on('change', '#depot_mouvement', function() {
        const depotId = $(this).val() || 'all';
        console.log("Changement de dépôt détecté:", depotId);
        loadProductsForMovement(depotId);
    });
    
    // Fonction spécifique pour charger les produits de l'onglet Mouvement
    function loadProductsForMovement(depotId) {
        const $produitSelect = $('#produit_id');
        
        // Mettre à jour l'UI pendant le chargement
        $produitSelect.empty();
        $produitSelect.append('<option value="">Chargement...</option>');
        $produitSelect.prop('disabled', true);
        $produitSelect.trigger('change.select2'); // Important pour Select2!
        
        console.log("Chargement des produits pour dépôt ID:", depotId);
        
        $.ajax({
            url: 'controller/get_products.php',
            type: 'POST',
            data: {
                depot_id: depotId,
                category_id: 'all'
            },
            dataType: 'json',
            success: function(data) {
                console.log("Données reçues:", data);
                
                // Vider le select
                $produitSelect.empty();
                
                // Ajouter l'option par défaut
                $produitSelect.append('<option value="all">Tous les produits</option>');
                
                // Ajouter les produits
                if (Array.isArray(data) && data.length > 0) {
                    $.each(data, function(index, product) {
                        $produitSelect.append(`<option value="${product.id_produit}">${product.code_produit} - ${product.libelle_produit}</option>`);
                    });
                } else {
                    $produitSelect.append('<option value="" disabled>Aucun produit disponible</option>');
                }
                
                // Réactiver le select et déclencher l'événement change
                $produitSelect.prop('disabled', false);
                $produitSelect.trigger('change.select2'); // Important pour Select2!
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des produits:', error);
                $produitSelect.empty();
                $produitSelect.append('<option value="all">Tous les produits</option>');
                $produitSelect.append('<option value="" disabled>Erreur de chargement</option>');
                $produitSelect.prop('disabled', false);
                $produitSelect.trigger('change.select2'); // Important pour Select2!
            }
        });
    }
    
    // Gérer les autres onglets comme avant
    $('#categorie_id').change(function() {
        // code existant pour l'onglet État du stock
    });
    
    $('#categorie_valorisation').change(function() {
        // code existant pour l'onglet Valorisation
    });
    
    // Cet événement est crucial - il charge les produits quand on change d'onglet
    $('#mouvements-tab').on('shown.bs.tab', function() {
        console.log("Onglet Mouvements activé");
        const depotId = $('#depot_mouvement').val() || 'all';
        loadProductsForMovement(depotId);
    });
    
    // Chargement initial pour l'onglet actif au démarrage
    if ($('#mouvements').hasClass('active')) {
        const depotId = $('#depot_mouvement').val() || 'all';
        loadProductsForMovement(depotId);
    }
});
</script>

<?php include "./views/include/footer_file.php"; ?>
