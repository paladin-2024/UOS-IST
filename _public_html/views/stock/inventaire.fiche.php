<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); 

// Récupération des dépôts auxquels l'utilisateur a accès
if ($isAdmin) {
    $queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->execute();
} else {
    $queryDepots = "SELECT d.* 
                    FROM depot d
                    INNER JOIN autorisation_depot ad ON d.id_depot = ad.id_depot
                    WHERE ad.id_user = :user_id AND ad.peut_consulter = 1 AND d.actif = 1
                    ORDER BY d.libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtDepots->execute();
}
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si l'utilisateur a accès à au moins un dépôt
if (empty($depots)) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation d\'accéder aux dépôts. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'dashboard';
        });
    </script>";
    exit;
}

// Récupérer l'ID de dépôt depuis le paramètre GET s'il existe
$selectedDepotId = isset($_GET['depot_id']) ? intval($_GET['depot_id']) : null;

// Vérifier si le dépôt sélectionné fait partie des dépôts autorisés
$depotAuthorized = false;
if ($selectedDepotId) {
    foreach ($depots as $depot) {
        if ($depot['id_depot'] == $selectedDepotId) {
            $depotAuthorized = true;
            break;
        }
    }
    
    // Si le dépôt n'est pas autorisé, réinitialiser la sélection
    if (!$depotAuthorized) {
        $selectedDepotId = null;
    }
}

// Récupérer les produits disponibles en stock pour le dépôt sélectionné
$products = [];
if ($selectedDepotId) {
    $queryProducts = "
        SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit,
                        um.symbole_unite, 
                        STRING_AGG(DISTINCT l.id_lot::text, ',') as lot_ids,
                        STRING_AGG(DISTINCT l.numero_lot, ',') as lot_numeros,
                        SUM(l.quantite_disponible) as quantite_totale
        FROM produit p
        INNER JOIN lot_produit l ON p.id_produit = l.id_produit
        INNER JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
        WHERE l.quantite_disponible > 0
        AND EXISTS (
            SELECT 1 FROM detail_entree_stock de
            INNER JOIN entree_stock e ON de.id_entree = e.id_entree
            WHERE de.id_detail_entree = l.id_detail_entree
            AND e.id_depot = :depot_id
            AND e.etat = 'Validé'
        )
        AND p.actif = 1
        GROUP BY p.id_produit, p.code_produit, p.libelle_produit, um.symbole_unite
        ORDER BY p.libelle_produit
    ";
    $stmtProducts = $db->prepare($queryProducts);
    $stmtProducts->bindParam(':depot_id', $selectedDepotId, PDO::PARAM_INT);
    $stmtProducts->execute();
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GÉNÉRER UNE FICHE D'INVENTAIRE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/inventaire.list">Inventaires</a></li>
                <li class="breadcrumb-item active">Fiche d'inventaire</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Générer une fiche d'inventaire vierge</h5>
                        <p class="text-muted">Cette fiche vous permettra de compléter manuellement les quantités physiques lors de votre inventaire.</p>

                        <!-- Formulaire de sélection du dépôt -->
                        <form id="depotForm" action="" method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="page" value="stock/inventaire.fiche">
                            <div class="col-md-8">
                                <label for="depot_id" class="form-label">Sélectionner un dépôt</label>
                                <select class="form-select" id="depot_id" name="depot_id" required onchange="this.form.submit()">
                                    <option value="" disabled <?= !$selectedDepotId ? 'selected' : '' ?>>Sélectionner un dépôt</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>" <?= $selectedDepotId == $depot['id_depot'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($depot['libelle_depot']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Sélectionner ce dépôt
                                </button>
                            </div>
                        </form>

                        

                        <?php if ($selectedDepotId): ?>
                        <!-- Formulaire de sélection des produits -->
                        <form id="ficheForm" action="controller/generate_fiche_inventaire.php" method="POST" target="_blank" class="mt-4">
                            <input type="hidden" name="depot_id" value="<?= $selectedDepotId ?>">
                            
                            <div class="mb-3">
                                <label for="observation" class="form-label">Objet de l'inventaire</label>
                                <textarea class="form-control" id="observation" name="observation" rows="2" placeholder="Précisez la raison de cet inventaire..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                    <label class="form-check-label" for="selectAll"><strong>Sélectionner tous les produits</strong></label>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchProduct" placeholder="Rechercher un produit...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="badge bg-primary" id="selectedCount">0 produit(s) sélectionné(s)</span>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="productsTable">
                                    <thead>
                                        <tr>
                                            <th width="5%"></th>
                                            <th width="15%">Code</th>
                                            <th width="45%">Produit</th>
                                            <th width="15%">Stock Théorique</th>
                                            <th width="20%">Lots disponibles</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($products)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Aucun produit disponible dans ce dépôt</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($products as $product): ?>
                                                <tr class="product-row">
                                                    <td class="text-center">
                                                        <input class="form-check-input product-checkbox" type="checkbox" name="produits[]" value="<?= $product['id_produit'] ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars($product['code_produit']) ?></td>
                                                    <td><?= htmlspecialchars($product['libelle_produit']) ?></td>
                                                    <td><?= number_format($product['quantite_totale'], 2) ?> <?= htmlspecialchars($product['symbole_unite']) ?></td>
                                                    <td>
                                                        <?php 
                                                            $lotNums = explode(',', $product['lot_numeros']);
                                                            foreach ($lotNums as $index => $lotNum) {
                                                                echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($lotNum) . '</span>';
                                                                if ($index >= 2 && count($lotNums) > 3) {
                                                                    echo '<span class="badge bg-light text-dark">+' . (count($lotNums) - 3) . ' autre(s)</span>';
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <a href="stock/inventaire.list" class="btn btn-secondary me-2">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-primary" id="generateButton" disabled>
                                    <i class="bi bi-file-earmark-text"></i> Générer la fiche d'inventaire
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Veuillez sélectionner un dépôt pour continuer.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche de produits dans le tableau
    $(document).on("keyup", "#searchProduct", function() {
        var value = $(this).val().toLowerCase();
        $("#productsTable .product-row").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Sélectionner/désélectionner tous les produits
    $(document).on("change", "#selectAll", function() {
        let isChecked = $(this).prop("checked");
        $(".product-checkbox:visible").prop("checked", isChecked);
        updateSelectedCount();
        updateGenerateButton();
    });
    
    // Mettre à jour le compteur lorsqu'une case est cochée/décochée
    $(document).on("change", ".product-checkbox", function() {
        updateSelectedCount();
        updateGenerateButton();
    });
    
    // Fonction pour mettre à jour le compteur de produits sélectionnés
    function updateSelectedCount() {
        let count = $(".product-checkbox:checked").length;
        $("#selectedCount").text(count + " produit(s) sélectionné(s)");
    }
    
    // Fonction pour activer/désactiver le bouton de génération
    function updateGenerateButton() {
        let count = $(".product-checkbox:checked").length;
        $("#generateButton").prop("disabled", count === 0);
    }
    
    // Validation du formulaire
    $(document).on("submit", "#ficheForm", function(e) {
        if ($(".product-checkbox:checked").length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Sélection requise',
                text: 'Veuillez sélectionner au moins un produit pour générer la fiche d\'inventaire.'
            });
        }
    });
});
</script>


<?php include "./views/include/footer_file.php"; ?>
