<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); 

// Récupérer l'ID de la sortie
$id_sortie = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations de la sortie
$query = "SELECT * FROM sortie_stock WHERE id_sortie = :id_sortie AND etat = 'En cours'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Sortie de stock non trouvée ou non modifiable',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = 'stock/stock.sortie.list';
        });
    </script>";
    exit;
}

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

// Vérifier si l'utilisateur a accès au dépôt de la sortie
$hasAccess = false;
foreach ($depots as $depot) {
    if ($depot['id_depot'] == $sortie['id_depot']) {
        $hasAccess = true;
        break;
    }
}

// Si l'utilisateur n'a pas accès au dépôt de cette sortie, rediriger
if (!$hasAccess) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation de modifier cette sortie de stock. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'stock/stock.sortie.list';
        });
    </script>";
    exit;
}

// Récupérer les détails de la sortie
$query = "SELECT d.*, p.code_produit, p.libelle_produit 
          FROM detail_sortie_stock d
          JOIN produit p ON d.id_produit = p.id_produit 
          WHERE d.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails par lot
$query = "SELECT dl.*, l.numero_lot, l.quantite_disponible, l.date_peremption
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
    $sortedLotDetails[$lot['id_detail_sortie']] = $lot;
}
?>


<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFICATION D'UNE SORTIE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.sortie.list">Sorties</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Formulaire de modification</h5>

                        <form id="sortieForm" method="POST" action="controller/update_sortie_stock.php" class="needs-validation" novalidate>
                            <input type="hidden" name="id_sortie" value="<?= $id_sortie ?>">

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label for="numero_sortie" class="form-label">Numéro de sortie <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="numero_sortie" name="numero_sortie" value="<?= htmlspecialchars($sortie['numero_sortie']) ?>" required readonly>
                                    <div class="invalid-feedback">Veuillez saisir un numéro de sortie.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="date_sortie" class="form-label">Date de sortie <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_sortie" name="date_sortie" value="<?= $sortie['date_sortie'] ?>" required>
                                    <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="id_depot" class="form-label">Dépôt <span class="text-danger">*</span></label>
                                    <select class="form-select" id="id_depot" name="id_depot" required>
                                        <option value="">Sélectionner un dépôt</option>
                                        <?php foreach ($depots as $depot): ?>
                                            <option value="<?= $depot['id_depot'] ?>" <?= ($depot['id_depot'] == $sortie['id_depot']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($depot['libelle_depot']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un dépôt.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="type_sortie" class="form-label">Type de sortie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type_sortie" name="type_sortie" required>
                                        <option value="">Sélectionner un type</option>
                                        <option value="Vente" <?= ($sortie['type_sortie'] == 'Vente') ? 'selected' : '' ?>>Vente</option>
                                        <option value="Transfert" <?= ($sortie['type_sortie'] == 'Transfert') ? 'selected' : '' ?>>Transfert</option>
                                        <option value="Inventaire" <?= ($sortie['type_sortie'] == 'Inventaire') ? 'selected' : '' ?>>Inventaire</option>
                                        <option value="Perte" <?= ($sortie['type_sortie'] == 'Perte') ? 'selected' : '' ?>>Perte</option>
                                        <option value="Autre" <?= ($sortie['type_sortie'] == 'Autre') ? 'selected' : '' ?>>Autre</option>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un type de sortie.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="reference_document" class="form-label">Référence document</label>
                                    <input type="text" class="form-control" id="reference_document" name="reference_document" value="<?= htmlspecialchars($sortie['reference_document'] ?? '') ?>">
                                    <div class="form-text">Facultatif - Numéro de la facture, bon de livraison, etc.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="observation" class="form-label">Observation</label>
                                    <textarea class="form-control" id="observation" name="observation" rows="1"><?= htmlspecialchars($sortie['observation'] ?? '') ?></textarea>
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
                                            <?php $rowCount = 0; ?>
                                            <?php foreach ($details as $index => $detail): ?>
                                                <?php $rowCount++; ?>
                                                <?php $lotDetail = isset($sortedLotDetails[$detail['id_detail_sortie']]) ? $sortedLotDetails[$detail['id_detail_sortie']] : null; ?>
                                                <tr id="row_<?= $rowCount ?>">
                                                    <td>
                                                        <input type="hidden" name="products[<?= $rowCount ?>][id_detail_sortie]" value="<?= $detail['id_detail_sortie'] ?>">
                                                        <input type="hidden" name="products[<?= $rowCount ?>][id_produit]" value="<?= $detail['id_produit'] ?>">
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($detail['code_produit'] . ' - ' . $detail['libelle_produit']) ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="hidden" name="products[<?= $rowCount ?>][id_lot]" value="<?= $lotDetail ? $lotDetail['id_lot'] : '' ?>">
                                                        <input type="text" class="form-control" value="<?= $lotDetail ? htmlspecialchars($lotDetail['numero_lot']) : '' ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control stock-dispo" value="<?= $lotDetail ? number_format($lotDetail['quantite_disponible'] + $lotDetail['quantite'], 2) : 0 ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control quantity" name="products[<?= $rowCount ?>][quantite]" step="0.01" min="0.01" value="<?= number_format($detail['quantite'], 2, '.', '') ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control price" name="products[<?= $rowCount ?>][prix_unitaire]" step="0.01" min="0.01" value="<?= number_format($detail['prix_unitaire'], 2, '.', '') ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control total" name="products[<?= $rowCount ?>][montant_total]" step="0.01" value="<?= number_format($detail['montant_total'], 2, '.', '') ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm removeRow">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
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
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/stock.sortie.view&id=<?= $id_sortie ?>'">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer
                                    </button>
                                </div>
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
    let rowCount = <?= $rowCount ?>;
    
    // Fonction pour charger les produits depuis l'API
    function loadProducts(selectElement, depotId) {
        if (!depotId) {
            selectElement.html('<option value="" selected disabled>Sélectionnez d\'abord un dépôt</option>');
            return;
        }
        
        selectElement.html('<option value="" selected disabled>Chargement...</option>');
        
        $.ajax({
            url: 'controller/get_products_in_stock.php',
            type: 'GET',
            data: { depot_id: depotId },
            dataType: 'json',
            success: function(data) {
                selectElement.empty();
                selectElement.append('<option value="" selected disabled>Sélectionner un produit</option>');
                
                if (data.error) {
                    console.error('Erreur API:', data.error);
                    return;
                }
                
                if (data.length === 0) {
                    selectElement.append('<option value="" disabled>Aucun produit en stock</option>');
                    return;
                }
                
                $.each(data, function(index, product) {
                    selectElement.append('<option value="' + product.id_produit + '">' + product.code_produit + ' - ' + product.libelle_produit + '</option>');
                });
                
                // Déclencher change pour Select2
                selectElement.trigger('change');
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des produits:', error);
                selectElement.html('<option value="" selected disabled>Erreur de chargement</option>');
                selectElement.trigger('change');
            }
        });
    }
    
    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    // Fonction pour charger les lots d'un produit
    function loadLots(lotSelect, depotId, productId, rowElement) {
        if (!depotId || !productId) {
            lotSelect.html('<option value="" selected disabled>Sélectionnez d\'abord un produit</option>');
            lotSelect.trigger('change'); // Important pour Select2
            return;
        }
        
        lotSelect.html('<option value="" selected disabled>Chargement...</option>');
        lotSelect.trigger('change'); // Important pour Select2
        
        $.ajax({
            url: 'controller/get_product_lots.php',
            type: 'GET',
            data: { 
                depot_id: depotId,
                product_id: productId
            },
            dataType: 'json',
            success: function(data) {
                lotSelect.empty();
                
                if (data.error) {
                    console.error('Erreur API:', data.error);
                    lotSelect.html('<option value="">Erreur: ' + data.error + '</option>');
                    lotSelect.trigger('change');
                    return;
                }
                
                if (!Array.isArray(data) || data.length === 0) {
                    lotSelect.html('<option value="">Aucun lot disponible</option>');
                    rowElement.find('.stock-dispo').val('0');
                } else {
                    lotSelect.append('<option value="">Sélectionner un lot</option>');
                    
                    data.forEach(function(lot) {
                        const expiryInfo = lot.date_peremption ? ' (Exp: ' + formatDate(lot.date_peremption) + ')' : '';
                        const optionText = lot.numero_lot + expiryInfo + ' - Stock: ' + lot.quantite_disponible;
                        
                        const $option = $('<option>', {
                            value: lot.id_lot,
                            text: optionText
                        }).data('stock', lot.quantite_disponible).data('price', lot.prix_unitaire_vente);
                        
                        lotSelect.append($option);
                    });
                }
                
                lotSelect.trigger('change'); // Important pour Select2
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des lots:', error);
                lotSelect.html('<option value="">Erreur de chargement</option>');
                lotSelect.trigger('change'); // Important pour Select2
            }
        });
    }
    
    // Fonction pour calculer les montants
    function calculateTotal(row) {
        const quantity = parseFloat($(row).find('.quantity').val()) || 0;
        const price = parseFloat($(row).find('.price').val()) || 0;
        const total = quantity * price;
        
        $(row).find('.total').val(total.toFixed(2));
    }
    
    // Bouton pour ajouter une nouvelle ligne
    $('#addRowBtn').on('click', function() {
        const depotId = $('#id_depot').val();
        if (!depotId) {
            Swal.fire({
                title: 'Attention',
                text: 'Veuillez d\'abord sélectionner un dépôt',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        rowCount++;
        const newRow = `
            <tr id="row_${rowCount}">
                <td>
                    <select class="form-select product-select" id="product_${rowCount}" name="products[${rowCount}][id_produit]" required>
                        <option value="" selected disabled>Sélectionner un produit</option>
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
        
        // Charger les produits pour cette nouvelle ligne
        const productSelect = $(`#product_${rowCount}`);
        loadProducts(productSelect, depotId);
        
        // Initialiser Select2 sur les nouveaux selects
        try {
            $(`#product_${rowCount}, #lot_${rowCount}`).select2({
                width: '100%',
                placeholder: 'Sélectionner une option',
                allowClear: true
            });
        } catch (e) {
            console.warn("Select2 n'est pas disponible:", e);
        }
        
        // Ajouter les gestionnaires d'événements pour cette ligne
        addRowEventHandlers(rowCount);
    });
    
    // Fonction pour ajouter les gestionnaires d'événements à une ligne
    function addRowEventHandlers(rowId) {
        const row = $(`#row_${rowId}`);
        
        // Gestionnaire d'événement pour la sélection de produit
        $(`#product_${rowId}`).on('change', function() {
            const productId = $(this).val();
            const depotId = $('#id_depot').val();
            const lotSelect = $(`#lot_${rowId}`);
            
            // Réinitialiser les champs dépendants
            row.find('.stock-dispo').val('');
            row.find('.quantity').val('');
            row.find('.price').val('');
            row.find('.total').val('');
            
            // Charger les lots disponibles
            if (productId) {
                loadLots(lotSelect, depotId, productId, row);
            } else {
                lotSelect.html('<option value="">Sélectionner d\'abord un produit</option>');
                lotSelect.trigger('change'); // Important pour Select2
            }
        });
        
        // Gestionnaire d'événement pour la sélection de lot
        $(`#lot_${rowId}`).on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const stockDispo = selectedOption.data('stock') || 0;
            const price = selectedOption.data('price') || 0;
            
            row.find('.stock-dispo').val(stockDispo);
            row.find('.price').val(parseFloat(price).toFixed(2));
            
            // Vider et recalculer les champs dépendants
            row.find('.quantity').val('');
            row.find('.total').val('');
        });
        
        // Validation de la quantité par rapport au stock disponible
        row.find('.quantity').on('input', function() {
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
        row.find('.price').on('input', function() {
            calculateTotal(row);
        });
        
        // Supprimer une ligne
        row.find('.removeRow').on('click', function() {
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Voulez-vous supprimer cette ligne?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                }
            });
        });
    }
    
    // Initialiser les événements pour les lignes existantes
    function initExistingRows() {
        // Gérer le changement de dépôt
        $('#id_depot').on('change', function() {
            const depotId = $(this).val();
            
            // Supprimer toutes les lignes sauf celles qui contiennent des id_detail_sortie
            $('#productTable tbody tr').each(function() {
                const detailSortieId = $(this).find('input[name$="[id_detail_sortie]"]').val();
                if (!detailSortieId) {
                    $(this).remove();
                }
            });
            
            // Réinitialiser le compteur de lignes
            rowCount = $('#productTable tbody tr').length;
        });
        
        // Initialiser les événements pour les lignes existantes
        $('#productTable tbody tr').each(function() {
            // Événements pour le calcul des totaux
            $(this).find('.quantity, .price').on('input', function() {
                calculateTotal($(this).closest('tr'));
                
                // Vérifier que la quantité ne dépasse pas le stock disponible
                const row = $(this).closest('tr');
                const quantity = parseFloat(row.find('.quantity').val()) || 0;
                const stockDispo = parseFloat(row.find('.stock-dispo').val()) || 0;
                
                if (quantity > stockDispo) {
                    Swal.fire({
                        title: 'Attention',
                        text: 'La quantité saisie dépasse le stock disponible',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    row.find('.quantity').val(stockDispo.toFixed(2));
                    calculateTotal(row); 
                }
            });
            
            // Supprimer une ligne
            $(this).find('.removeRow').on('click', function() {
                const row = $(this).closest('tr');
                const detailSortieId = row.find('input[name$="[id_detail_sortie]"]').val();
                
                if (detailSortieId) {
                    Swal.fire({
                        title: 'Êtes-vous sûr?',
                        text: "Voulez-vous supprimer cette ligne? Cette action sera définitive lors de l'enregistrement.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Oui, supprimer',
                        cancelButtonText: 'Annuler'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Ajouter un champ caché pour indiquer que cette ligne doit être supprimée
                            $('#sortieForm').append(`<input type="hidden" name="delete_details[]" value="${detailSortieId}">`);
                            row.remove();
                        }
                    });
                } else {
                    row.remove();
                }
            });
        });
    }
    
    // Validation du formulaire
    $('#sortieForm').on('submit', function(event) {
        // Validation personnalisée
        let isValid = true;
        const depotId = $('#id_depot').val();
        
        if (!depotId) {
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez sélectionner un dépôt.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            isValid = false;
        }
        
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
        
        // Vérifier que tous les produits ont une quantité
        $('#productTable tbody tr').each(function() {
            const quantity = $(this).find('.quantity').val();
            const price = $(this).find('.price').val();
            const productId = $(this).find('.product-select').val() || $(this).find('input[name$="[id_produit]"]').val();
            const lotId = $(this).find('.lot-select').val() || $(this).find('input[name$="[id_lot]"]').val();
            
            if (!quantity || !price || !productId || !lotId) {
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
    
    // Initialiser les lignes existantes
    initExistingRows();
    
    // Initialiser Select2 pour les éléments existants
    try {
        $('.product-select, .lot-select').select2({
            width: '100%',
            placeholder: 'Sélectionner une option',
            allowClear: true
        });
    } catch (e) {
        console.warn("Select2 n'est pas disponible:", e);
    }
});
</script>

<?php include "./views/include/footer.php"; ?>