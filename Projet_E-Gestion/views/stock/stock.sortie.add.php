<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); 

// Récupération des dépôts auxquels l'utilisateur a accès avec droits de modification
if ($isAdmin) {
    $queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->execute();
} else {
    $queryDepots = "SELECT d.* 
                    FROM depot d
                    INNER JOIN autorisation_depot ad ON d.id_depot = ad.id_depot
                    WHERE ad.id_user = :user_id AND ad.peut_modifier = 1 AND d.actif = 1
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
            text: 'Vous n\'avez pas l\'autorisation d\'ajouter des sorties de stock pour aucun dépôt. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'stock/stock.sortie.list';
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

// Génération d'un numéro de sortie automatique
function generateExitNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_sortie, 6) AS UNSIGNED)) as max_num 
              FROM sortie_stock 
              WHERE numero_sortie LIKE 'SOR" . $year . "%' 
              AND YEAR(date_sortie) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'SOR' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextExitNumber = generateExitNumber($db);

// Récupérer les produits disponibles en stock pour le dépôt sélectionné
$products = [];
if ($selectedDepotId) {
    $queryProducts = "
        SELECT DISTINCT p.id_produit, p.code_produit, p.libelle_produit
        FROM produit p
        INNER JOIN lot_produit l ON p.id_produit = l.id_produit
        WHERE l.quantite_disponible > 0
        AND EXISTS (
            SELECT 1 FROM detail_entree_stock de
            INNER JOIN entree_stock e ON de.id_entree = e.id_entree
            WHERE de.id_detail_entree = l.id_detail_entree
            AND e.id_depot = :depot_id
            AND e.etat = 'Validé'
        )
        AND p.actif = 1
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
        <h1>NOUVELLE SORTIE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.sortie.list">Sorties</a></li>
                <li class="breadcrumb-item active">Nouvelle sortie</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la sortie</h5>

                        <!-- Formulaire de sélection du dépôt -->
                        <form id="depotForm" action="" method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="page" value="stock/stock.sortie.add">
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
                        <!-- Formulaire d'ajout de sortie - visible seulement si un dépôt est sélectionné -->
                        <form id="sortieForm" action="controller/create_sortie_stock.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" name="id_depot" value="<?= $selectedDepotId ?>">
                            
                            <div class="col-md-4">
                                <label for="numero_sortie" class="form-label">Numéro de sortie</label>
                                <input type="text" class="form-control" id="numero_sortie" name="numero_sortie" value="<?= $nextExitNumber ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_sortie" class="form-label">Date de sortie</label>
                                <input type="date" class="form-control" id="date_sortie" name="date_sortie" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="depot_info" class="form-label">Dépôt sélectionné</label>
                                <?php 
                                $depotName = "";
                                foreach ($depots as $depot) {
                                    if ($depot['id_depot'] == $selectedDepotId) {
                                        $depotName = $depot['libelle_depot'];
                                        break;
                                    }
                                }
                                ?>
                                <input type="text" class="form-control" id="depot_info" value="<?= htmlspecialchars($depotName) ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="type_sortie" class="form-label">Type de sortie</label>
                                <select class="form-select" id="type_sortie" name="type_sortie" required>
                                    <option value="" selected disabled>Sélectionner un type</option>
                                    <option value="Vente">Vente</option>
                                    <option value="Transfert">Transfert</option>
                                    <option value="Inventaire">Inventaire</option>
                                    <option value="Perte">Perte</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un type de sortie.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="reference_document" class="form-label">Référence document</label>
                                <input type="text" class="form-control" id="reference_document" name="reference_document">
                                <div class="form-text">Facultatif - Numéro de la facture, bon de livraison, etc.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="25%">Produit</th>
                                            <th width="15%">Lot</th>
                                            <th width="10%">Stock dispo</th>
                                            <th width="10%">Quantité</th>
                                            <th width="15%">Prix unitaire</th>
                                            <th width="15%">Montant total</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr id="row_1">
                                            <td>
                                                <select class="form-select product-select" name="products[1][id_produit]" required>
                                                    <option value="" selected disabled>Sélectionner un produit</option>
                                                    <?php foreach ($products as $product): ?>
                                                    <option value="<?= $product['id_produit'] ?>"><?= htmlspecialchars($product['code_produit'] . ' - ' . $product['libelle_produit']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select lot-select" name="products[1][id_lot]" required>
                                                    <option value="" selected disabled>Sélectionner d'abord un produit</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control stock-dispo" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control quantity" name="products[1][quantite]" step="0.01" min="0.01" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control price" name="products[1][prix_unitaire]" step="0.01" min="0.01" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control total" name="products[1][montant_total]" step="0.01" readonly>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm removeRow">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" id="addRowBtn" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Ajouter un produit
                                </button>
                            </div>
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/stock.sortie.list'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
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
<!-- À la fin du fichier, remplacez tout le script JavaScript par cette version jQuery -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variable pour suivre le nombre de lignes
    let rowCount = 1;
    
    // Bouton pour ajouter une nouvelle ligne
    $('#addRowBtn').on('click', function() {
        rowCount++;
        const newRow = `
            <tr id="row_${rowCount}">
                <td>
                    <select class="form-select product-select" id="product_${rowCount}" name="products[${rowCount}][id_produit]" required>
                        <option value="" selected disabled>Sélectionner un produit</option>
                        <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id_produit'] ?>"><?= htmlspecialchars($product['code_produit'] . ' - ' . $product['libelle_produit']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select class="form-select lot-select" id="lot_${rowCount}" name="products[${rowCount}][id_lot]" required>
                        <option value="" selected disabled>Sélectionner d'abord un produit</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control stock-dispo" readonly>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="products[${rowCount}][quantite]" step="0.01" min="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control price" name="products[${rowCount}][prix_unitaire]" step="0.01" min="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control total" name="products[${rowCount}][montant_total]" step="0.01" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm removeRow">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#productTable tbody').append(newRow);
        
        // Initialiser Select2 sur les nouveaux selects
        $(`#product_${rowCount}, #lot_${rowCount}`).select2({
            width: '100%',
            placeholder: 'Sélectionner une option',
            allowClear: true
        });
    });
    
    // Supprimer une ligne
    $(document).on('click', '.removeRow', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            Swal.fire({
                title: 'Impossible',
                text: 'Vous devez avoir au moins une ligne de produit.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }
    });
    
    // Gestionnaire d'événement pour la sélection de produit
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const productId = $(this).val();
        const rowId = row.attr('id');
        const lotSelectId = row.find('.lot-select').attr('id');
        
        loadLotsWithjQuery(productId, rowId, lotSelectId);
    });
    
    // Fonction pour charger les lots avec jQuery
    function loadLotsWithjQuery(productId, rowId, lotSelectId) {
        const row = $('#' + rowId);
        const $lotSelect = row.find('.lot-select');
        const depotId = <?= $selectedDepotId ?? 0 ?>;
        
        console.log("Chargement des lots pour produit ID:", productId, "et dépôt ID:", depotId);
        
        // Réinitialiser les champs dépendants
        row.find('.stock-dispo').val('');
        row.find('.quantity').val('');
        row.find('.price').val('');
        row.find('.total').val('');
        
        if (!productId || !depotId) {
            $lotSelect.html('<option value="">Sélectionner d\'abord un produit</option>');
            $lotSelect.trigger('change'); // Important pour Select2
            return;
        }
        
        $lotSelect.html('<option value="">Chargement...</option>');
        $lotSelect.trigger('change'); // Important pour Select2
        
        $.ajax({
            url: 'controller/get_product_lots.php',
            type: 'GET',
            data: { 
                depot_id: depotId,
                product_id: productId
            },
            dataType: 'json',
            success: function(data) {
                console.log("Données de lots reçues:", data);
                $lotSelect.empty();
                
                if (data.error) {
                    console.error('Erreur API:', data.error);
                    $lotSelect.html('<option value="">Erreur: ' + data.error + '</option>');
                    $lotSelect.trigger('change');
                    return;
                }
                
                if (!Array.isArray(data) || data.length === 0) {
                    $lotSelect.html('<option value="">Aucun lot disponible</option>');
                    row.find('.stock-dispo').val('0');
                } else {
                    $lotSelect.append('<option value="">Sélectionner un lot</option>');
                    
                    data.forEach(function(lot) {
                        const expiryInfo = lot.date_peremption ? ' (Exp: ' + formatDate(lot.date_peremption) + ')' : '';
                        const optionText = lot.numero_lot + expiryInfo + ' - Stock: ' + lot.quantite_disponible;
                        
                        const $option = $('<option>', {
                            value: lot.id_lot,
                            text: optionText
                        }).data('stock', lot.quantite_disponible).data('price', lot.prix_unitaire_vente);
                        
                        $lotSelect.append($option);
                    });
                }
                
                $lotSelect.trigger('change'); // Important pour Select2
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des lots:', error);
                $lotSelect.html('<option value="">Erreur de chargement</option>');
                $lotSelect.trigger('change'); // Important pour Select2
            }
        });
    }
    
    // Gestionnaire d'événement pour la sélection de lot
    $(document).on('change', '.lot-select', function() {
        const row = $(this).closest('tr');
        const selectedOption = $(this).find('option:selected');
        const stockDispo = selectedOption.data('stock') || 0;
        const price = selectedOption.data('price') || 0;
        
        row.find('.stock-dispo').val(stockDispo);
        row.find('.price').val(parseFloat(price).toFixed(2));
        
        // Vider et recalculer les champs dépendants
        row.find('.quantity').val('');
        row.find('.total').val('');
    });
    
    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    // Fonction pour calculer les montants
    function calculateTotal(row) {
        const quantity = parseFloat($(row).find('.quantity').val()) || 0;
        const price = parseFloat($(row).find('.price').val()) || 0;
        const total = quantity * price;
        
        $(row).find('.total').val(total.toFixed(2));
    }
    
    // Validation de la quantité par rapport au stock disponible
    $(document).on('input', '.quantity', function() {
        const row = $(this).closest('tr');
        const quantity = parseFloat($(this).val()) || 0;
        const stockDispo = parseFloat(row.find('.stock-dispo').val()) || 0;
        
        if (quantity > stockDispo) {
            Swal.fire({
                title: 'Erreur',
                text: 'La quantité ne peut pas dépasser le stock disponible (' + stockDispo + ').',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            $(this).val('');
            row.find('.total').val('');
        } else {
            calculateTotal(row);
        }
    });
    
    // Calculer le total lors de la modification des prix
    $(document).on('input', '.price', function() {
        calculateTotal($(this).closest('tr'));
    });
    
    // Validation du formulaire
    $('#sortieForm').on('submit', function(event) {
        // Validation personnalisée
        let isValid = true;
        
        // Vérifier qu'au moins un produit est sélectionné
        if ($('#productTable tbody tr').length < 1) {
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez ajouter au moins un produit.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            isValid = false;
        }
        
        // Vérifier que tous les produits ont une quantité et un lot sélectionné
        $('#productTable tbody tr').each(function() {
            const productId = $(this).find('.product-select').val();
            const lotId = $(this).find('.lot-select').val();
            const quantity = $(this).find('.quantity').val();
            
            if (!productId || !lotId || !quantity) {
                Swal.fire({
                    title: 'Erreur',
                    text: 'Veuillez compléter tous les champs pour chaque produit.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                isValid = false;
                return false; // Sortir de la boucle each
            }
        });
        
        if (!isValid) {
            event.preventDefault();
        }
    });
    
    // Initialiser Select2 pour tous les selects existants
    $('.product-select, .lot-select').select2({
        width: '100%',
        placeholder: 'Sélectionner une option',
        allowClear: true
    });
});




</script>


<?php include "./views/include/footer.php"; ?>

