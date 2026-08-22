<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); 

// Récupérer l'ID de l'inventaire à modifier
$inventaireId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($inventaireId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID d\'inventaire invalide.'
        }).then(() => {
            window.location.href = 'stock/inventaire.list';
        });
    </script>";
    exit;
}

// Récupérer les informations de l'inventaire
$stmt = $db->prepare("SELECT * FROM inventaire WHERE id_inventaire = :id_inventaire");
$stmt->bindParam(':id_inventaire', $inventaireId, PDO::PARAM_INT);
$stmt->execute();
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'L\'inventaire spécifié n\'existe pas.'
        }).then(() => {
            window.location.href = 'stock/inventaire.list';
        });
    </script>";
    exit;
}

// Vérifier si l'inventaire est encore modifiable
if ($inventaire['etat'] != 'En cours') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Action impossible',
            text: 'Cet inventaire ne peut plus être modifié car il n\'est plus en cours.'
        }).then(() => {
            window.location.href = 'stock/inventaire.view&id=" . $inventaireId . "';
        });
    </script>";
    exit;
}

// Récupérer les détails de l'inventaire
$stmt = $db->prepare("SELECT di.*, p.code_produit, p.libelle_produit, l.numero_lot, l.date_peremption 
                     FROM detail_inventaire di
                     INNER JOIN produit p ON di.id_produit = p.id_produit
                     INNER JOIN lot_produit l ON di.id_lot = l.id_lot
                     WHERE di.id_inventaire = :id_inventaire
                     ORDER BY di.id_detail_inventaire");
$stmt->bindParam(':id_inventaire', $inventaireId, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Vérifier si l'utilisateur a accès au dépôt de cet inventaire
$hasAccess = false;
if ($isAdmin) {
    $hasAccess = true;
} else {
    foreach ($depots as $depot) {
        if ($depot['id_depot'] == $inventaire['id_depot']) {
            $hasAccess = true;
            break;
        }
    }
}

if (!$hasAccess) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation de modifier cet inventaire.'
        }).then(() => {
            window.location.href = 'stock/inventaire.list';
        });
    </script>";
    exit;
}

// Récupérer le nom du dépôt
$stmt = $db->prepare("SELECT libelle_depot FROM depot WHERE id_depot = :id_depot");
$stmt->bindParam(':id_depot', $inventaire['id_depot'], PDO::PARAM_INT);
$stmt->execute();
$depotInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$depotName = $depotInfo ? $depotInfo['libelle_depot'] : 'Inconnu';

// Récupérer les produits disponibles en stock pour le dépôt sélectionné
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
$stmtProducts->bindParam(':depot_id', $inventaire['id_depot'], PDO::PARAM_INT);
$stmtProducts->execute();
$products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER L'INVENTAIRE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/inventaire.list">Inventaires</a></li>
                <li class="breadcrumb-item active">Modifier l'inventaire</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de l'inventaire</h5>

                        <!-- Formulaire de modification d'inventaire -->
                        <form id="inventaireForm" action="controller/edit_inventaire.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" name="id_inventaire" value="<?= $inventaireId ?>">
                            <input type="hidden" name="id_depot" value="<?= $inventaire['id_depot'] ?>">
                            
                            <div class="col-md-4">
                                <label for="numero_inventaire" class="form-label">Numéro d'inventaire</label>
                                <input type="text" class="form-control" id="numero_inventaire" value="<?= htmlspecialchars($inventaire['numero_inventaire']) ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_inventaire" class="form-label">Date d'inventaire</label>
                                <input type="date" class="form-control" id="date_inventaire" name="date_inventaire" value="<?= $inventaire['date_inventaire'] ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="depot_info" class="form-label">Dépôt</label>
                                <input type="text" class="form-control" id="depot_info" value="<?= htmlspecialchars($depotName) ?>" readonly>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="2"><?= htmlspecialchars($inventaire['observation']) ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits à inventorier</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="25%">Produit</th>
                                            <th width="15%">Lot</th>
                                            <th width="10%">Stock théorique</th>
                                            <th width="10%">Stock physique</th>
                                            <th width="10%">Écart</th>
                                            <th width="15%">Prix unitaire</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($details)): ?>
                                        <tr id="row_1">
                                            <td>
                                                <select class="form-select product-select" name="produits[]" required>
                                                    <option value="" selected disabled>Sélectionner un produit</option>
                                                    <?php foreach ($products as $product): ?>
                                                    <option value="<?= $product['id_produit'] ?>"><?= htmlspecialchars($product['code_produit'] . ' - ' . $product['libelle_produit']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select lot-select" name="lots[]" required>
                                                    <option value="" selected disabled>Sélectionner d'abord un produit</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control stock-theorique" name="stock_theorique[]" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control stock-physique" name="stock_physique[]" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control ecart" name="ecart[]" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control prix-unitaire" name="prix_unitaire[]" step="0.01" readonly>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm removeRow">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($details as $index => $detail): ?>
                                            <tr id="row_<?= $index + 1 ?>">
                                                <input type="hidden" name="detail_id[]" value="<?= $detail['id_detail_inventaire'] ?>">
                                                <td>
                                                    <select class="form-select product-select" name="produits[]" required>
                                                        <option value="" disabled>Sélectionner un produit</option>
                                                        <?php foreach ($products as $product): ?>
                                                        <option value="<?= $product['id_produit'] ?>" <?= $product['id_produit'] == $detail['id_produit'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($product['code_produit'] . ' - ' . $product['libelle_produit']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-select lot-select" name="lots[]" required data-selected-lot="<?= $detail['id_lot'] ?>">
                                                        <option value="<?= $detail['id_lot'] ?>" selected>
                                                            <?= htmlspecialchars($detail['numero_lot']) ?>
                                                            <?= $detail['date_peremption'] ? ' (Exp: ' . date('d/m/Y', strtotime($detail['date_peremption'])) . ')' : '' ?>
                                                        </option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control stock-theorique" name="stock_theorique[]" value="<?= $detail['stock_theorique'] ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control stock-physique" name="stock_physique[]" step="0.01" min="0" value="<?= $detail['stock_physique'] ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control ecart" name="ecart[]" value="<?= $detail['ecart'] ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control prix-unitaire" name="prix_unitaire[]" step="0.01" value="<?= $detail['prix_unitaire'] ?>" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/inventaire.view&id=<?= $inventaireId ?>'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variable pour suivre le nombre de lignes
    let rowCount = <?= empty($details) ? 1 : count($details) ?>;
    
    // Initialiser Select2 pour tous les selects existants
    $('.product-select, .lot-select').select2({
        width: '100%',
        placeholder: 'Sélectionner une option',
        allowClear: true
    });
    
    // Charger les lots pour les produits déjà sélectionnés
    $('.lot-select').each(function() {
        const row = $(this).closest('tr');
        const productId = row.find('.product-select').val();
        const lotId = $(this).data('selected-lot');
        
        if (productId) {
            loadLotsForExistingProduct(productId, row, lotId);
        }
    });
    
    // Bouton pour ajouter une nouvelle ligne
    $('#addRowBtn').on('click', function() {
        rowCount++;
        const newRow = `
            <tr id="row_${rowCount}">
                <td>
                    <select class="form-select product-select" id="product_${rowCount}" name="produits[]" required>
                        <option value="" selected disabled>Sélectionner un produit</option>
                        <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id_produit'] ?>"><?= htmlspecialchars($product['code_produit'] . ' - ' . $product['libelle_produit']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select class="form-select lot-select" id="lot_${rowCount}" name="lots[]" required>
                        <option value="" selected disabled>Sélectionner d'abord un produit</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control stock-theorique" name="stock_theorique[]" readonly>
                </td>
                <td>
                    <input type="number" class="form-control stock-physique" name="stock_physique[]" step="0.01" min="0" required>
                </td>
                <td>
                    <input type="number" class="form-control ecart" name="ecart[]" readonly>
                </td>
                <td>
                    <input type="number" class="form-control prix-unitaire" name="prix_unitaire[]" step="0.01" readonly>
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
        
        loadLotsWithjQuery(productId, rowId);
    });
    
    // Fonction pour charger les lots avec jQuery
    function loadLotsWithjQuery(productId, rowId) {
        const row = $('#' + rowId);
        const $lotSelect = row.find('.lot-select');
        const depotId = <?= $inventaire['id_depot'] ?>;
        
        console.log("Chargement des lots pour produit ID:", productId, "et dépôt ID:", depotId);
        
        // Réinitialiser les champs dépendants
        row.find('.stock-theorique').val('');
        row.find('.stock-physique').val('');
        row.find('.ecart').val('');
        row.find('.prix-unitaire').val('');
        
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
                    row.find('.stock-theorique').val('0');
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
    
    // Fonction pour charger les lots pour les produits déjà sélectionnés
    function loadLotsForExistingProduct(productId, row, selectedLotId) {
        const $lotSelect = row.find('.lot-select');
        const depotId = <?= $inventaire['id_depot'] ?>;
        
        $.ajax({
            url: 'controller/get_product_lots.php',
            type: 'GET',
            data: { 
                depot_id: depotId,
                product_id: productId
            },
            dataType: 'json',
            success: function(data) {
                if (!data.error && Array.isArray(data) && data.length > 0) {
                    // Conserver l'option sélectionnée actuelle
                    const selectedOption = $lotSelect.find('option:selected').clone();
                    
                    // Vider et reconstruire la liste des lots
                    $lotSelect.empty();
                    $lotSelect.append(selectedOption);
                    
                    // Ajouter les autres lots disponibles
                    data.forEach(function(lot) {
                        // Ne pas ajouter de doublon pour le lot déjà sélectionné
                        if (lot.id_lot != selectedLotId) {
                            const expiryInfo = lot.date_peremption ? ' (Exp: ' + formatDate(lot.date_peremption) + ')' : '';
                            const optionText = lot.numero_lot + expiryInfo + ' - Stock: ' + lot.quantite_disponible;
                            
                            const $option = $('<option>', {
                                value: lot.id_lot,
                                text: optionText
                            }).data('stock', lot.quantite_disponible).data('price', lot.prix_unitaire_vente);
                            
                            $lotSelect.append($option);
                        }
                    });
                    
                    $lotSelect.trigger('change'); // Important pour Select2
                }
            }
        });
    }
    
    // Gestionnaire d'événement pour la sélection de lot
    $(document).on('change', '.lot-select', function() {
        const row = $(this).closest('tr');
        const selectedOption = $(this).find('option:selected');
        const stockTheorique = selectedOption.data('stock') || 0;
        const price = selectedOption.data('price') || 0;
        
        // Ne mettre à jour le stock théorique que si c'est une nouvelle ligne
        if (!row.find('input[name="detail_id[]"]').length) {
            row.find('.stock-theorique').val(stockTheorique);
            row.find('.prix-unitaire').val(parseFloat(price).toFixed(2));
            
            // Vider et recalculer les champs dépendants
            row.find('.stock-physique').val('');
            row.find('.ecart').val('');
        }
    });
    
    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    // Calculer l'écart lorsque le stock physique est saisi
    $(document).on('input', '.stock-physique', function() {
        const row = $(this).closest('tr');
        const stockTheorique = parseFloat(row.find('.stock-theorique').val()) || 0;
        const stockPhysique = parseFloat($(this).val()) || 0;
        const ecart = stockPhysique - stockTheorique;
        
        row.find('.ecart').val(ecart.toFixed(2));
    });
    
    // Validation du formulaire
    $('#inventaireForm').on('submit', function(event) {
        // Validation personnalisée
        let isValid = true;
        
        // Vérifier qu'au moins un produit est sélectionné
        if ($('#productTable tbody tr').length < 1) {
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez ajouter au moins un produit à l\'inventaire.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            isValid = false;
        }
        
        // Vérifier que tous les produits ont un lot sélectionné et un stock physique saisi
        $('#productTable tbody tr').each(function() {
            const productId = $(this).find('.product-select').val();
            const lotId = $(this).find('.lot-select').val();
            const stockPhysique = $(this).find('.stock-physique').val();
            
            if (!productId || !lotId || stockPhysique === '') {
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
});
</script>

<?php include "./views/include/footer.php"; ?>
