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
            text: 'Vous n\'avez pas l\'autorisation de créer des transferts pour aucun dépôt. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'stock/transfert.list';
        });
    </script>";
    exit;
}

// Génération d'un numéro de transfert automatique
function generateTransferNumber($db) {
    $query = "SELECT MAX(CAST(SUBSTRING(numero_transfert, 4) AS UNSIGNED)) as max_num 
              FROM transfert_stock 
              WHERE numero_transfert LIKE 'TR-%' 
              AND YEAR(date_transfert) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'TR-' . date('y') . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextTransferNumber = generateTransferNumber($db);
?>


<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVEAU TRANSFERT DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/transfert.list">Transferts</a></li>
                <li class="breadcrumb-item active">Nouveau transfert</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du transfert</h5>

                        <form id="transfertForm" action="controller/create_transfert_stock.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-4">
                                <label for="numero_transfert" class="form-label">Numéro de transfert</label>
                                <input type="text" class="form-control" id="numero_transfert" name="numero_transfert" value="<?= $nextTransferNumber ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_transfert" class="form-label">Date du transfert</label>
                                <input type="date" class="form-control" id="date_transfert" name="date_transfert" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_depot_source" class="form-label">Dépôt source</label>
                                <select class="form-select" id="id_depot_source" name="id_depot_source" required>
                                    <option value="" selected disabled>Sélectionner un dépôt</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un dépôt source.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_depot_destination" class="form-label">Dépôt destination</label>
                                <select class="form-select" id="id_depot_destination" name="id_depot_destination" required>
                                    <option value="" selected disabled>Sélectionner un dépôt</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un dépôt destination.</div>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Articles à transférer</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="25%">Produit</th>
                                            <th width="55%">Lot</th>
                                            <th width="15%">Quantité</th>
                                            <th width="5%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr id="row_1">
                                            <td>1</td>
                                            <td>
                                                <select class="form-select product-select" name="products[1][id_produit]" required>
                                                    <option value="" selected disabled>Sélectionner un produit</option>
                                                </select>
                                                <div class="invalid-feedback">Sélectionnez un produit</div>
                                            </td>
                                            <td>
                                                <select class="form-select lot-select" name="products[1][id_lot]" required>
                                                    <option value="" selected disabled>Sélectionnez d'abord un produit</option>
                                                </select>
                                                <div class="invalid-feedback">Sélectionnez un lot</div>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="products[1][quantite]" min="0.01" step="0.01" required>
                                                    <span class="input-group-text unite-text">-</span>
                                                </div>
                                                <div class="invalid-feedback">Entrez une quantité valide</div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm remove-row" disabled>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-center mb-3">
                                <button type="button" id="addRowBtn" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Ajouter un article
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/transfert.list'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
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
    let rowCount = 1;
    
    // Bouton pour ajouter une nouvelle ligne
    $('#addRowBtn').on('click', function() {
        rowCount++;
        const newRow = `
            <tr id="row_${rowCount}">
                <td>${rowCount}</td>
                <td>
                    <select class="form-select product-select" id="product_${rowCount}" name="products[${rowCount}][id_produit]" required>
                        <option value="" selected disabled>Sélectionner un produit</option>
                    </select>
                    <div class="invalid-feedback">Sélectionnez un produit</div>
                </td>
                <td>
                    <select class="form-select lot-select" id="lot_${rowCount}" name="products[${rowCount}][id_lot]" required>
                        <option value="" selected disabled>Sélectionnez d'abord un produit</option>
                    </select>
                    <div class="invalid-feedback">Sélectionnez un lot</div>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" class="form-control quantity" name="products[${rowCount}][quantite]" min="0.01" step="0.01" required>
                        <span class="input-group-text unite-text">-</span>
                    </div>
                    <div class="invalid-feedback">Entrez une quantité valide</div>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-row">
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
        
        // Charger les produits pour la nouvelle ligne
        const depotId = $('#id_depot_source').val();
        if (depotId) {
            loadProductsWithjQuery($(`#product_${rowCount}`), depotId);
        }
    });
    
    // Supprimer une ligne
    $(document).on('click', '.remove-row', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            // Réorganiser les numéros
            $('#productTable tbody tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
            rowCount--;
        } else {
            Swal.fire({
                title: 'Impossible',
                text: 'Vous devez avoir au moins une ligne de produit.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }
    });
    
    // Fonction pour charger les produits avec jQuery
    function loadProductsWithjQuery(selectElement, depotId) {
        selectElement.html('<option value="" selected disabled>Chargement...</option>');
        
        if (!depotId) {
            selectElement.html('<option value="" selected disabled>Sélectionnez d\'abord un dépôt source</option>');
            return;
        }
        
        $.ajax({
            url: 'controller/get_depot_products.php',
            type: 'GET',
            data: { depot_id: depotId },
            dataType: 'json',
            success: function(data) {
                console.log("Données de produits reçues:", data);
                selectElement.empty();
                
                if (data.error) {
                    console.error('Erreur API:', data.error);
                    selectElement.html('<option value="">Erreur: ' + data.error + '</option>');
                    return;
                }
                
                selectElement.append('<option value="" selected disabled>Sélectionner un produit</option>');
                
                if (Array.isArray(data) && data.length > 0) {
                    $.each(data, function(index, product) {
                        selectElement.append('<option value="' + product.id_produit + '">' + product.code_produit + ' - ' + product.libelle_produit + '</option>');
                    });
                } else {
                    selectElement.append('<option value="" disabled>Aucun produit disponible</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors du chargement des produits:', error);
                selectElement.html('<option value="" selected disabled>Erreur de chargement</option>');
            }
        });
    }
    
    // Remplacer la fonction loadLotsWithjQuery par celle-ci:
function loadLotsWithjQuery(productId, rowId) {
    const row = $('#' + rowId);
    const $lotSelect = row.find('.lot-select');
    const depotId = $('#id_depot_source').val();
    
    console.log("Chargement des lots pour produit ID:", productId, "et dépôt ID:", depotId);
    
    // Réinitialiser les champs dépendants
    row.find('.unite-text').text('-');
    row.find('.quantity').val('');
    
    if (!productId || !depotId) {
        $lotSelect.html('<option value="" selected disabled>Sélectionner d\'abord un produit</option>');
        $lotSelect.trigger('change'); // Important pour Select2
        return;
    }
    
    $lotSelect.html('<option value="" selected disabled>Chargement...</option>');
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
                row.find('.unite-text').text('-');
            } else {
                $lotSelect.append('<option value="">Sélectionner un lot</option>');
                
                data.forEach(function(lot) {
                    const expiryInfo = lot.date_peremption ? ' (Exp: ' + formatDate(lot.date_peremption) + ')' : '';
                    const optionText = lot.numero_lot + expiryInfo + ' - Stock: ' + lot.quantite_disponible;
                    
                    $lotSelect.append(
                        $('<option>', {
                            value: lot.id_lot,
                            text: optionText,
                            'data-stock': lot.quantite_disponible,
                            'data-unite': lot.symbole_unite
                        })
                    );
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

    
    // Gérer le changement de dépôt source
    $('#id_depot_source').on('change', function() {
        const depotId = $(this).val();
        
        // Réinitialiser tous les sélecteurs de produits
        $('.product-select').each(function() {
            loadProductsWithjQuery($(this), depotId);
        });
        
        // Réinitialiser tous les sélecteurs de lots
        $('.lot-select').html('<option value="" selected disabled>Sélectionnez d\'abord un produit</option>');
        
        // Réinitialiser les unités
        $('.unite-text').text('-');
        
        // Activer le bouton de suppression de la première ligne si dépôt sélectionné
        if (depotId) {
            $('#row_1 .remove-row').prop('disabled', rowCount <= 1);
        }
    });
    
    // Vérification des dépôts source et destination
    $('#id_depot_destination').on('change', function() {
        checkDepots();
    });
    
    function checkDepots() {
        const sourceId = $('#id_depot_source').val();
        const destId = $('#id_depot_destination').val();
        
        if (sourceId && destId && sourceId === destId) {
            Swal.fire({
                title: 'Attention',
                text: 'Les dépôts source et destination ne peuvent pas être identiques.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            $('#id_depot_destination').val('');
        }
    }
    
    // Gestionnaire d'événement pour la sélection de produit
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const productId = $(this).val();
        const rowId = row.attr('id');
        
        loadLotsWithjQuery(productId, rowId);
    });
    
    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    // Gestionnaire d'événement pour la sélection de lot
    $(document).on('change', '.lot-select', function() {
        const row = $(this).closest('tr');
        const selectedOption = $(this).find('option:selected');
        const stockDispo = selectedOption.data('stock') || 0;
        const unite = selectedOption.data('unite') || '-';
        
        row.find('.unite-text').text(unite);
        row.find('.quantity').attr('max', stockDispo);
    });
    
    // Validation de la quantité par rapport au stock disponible
    $(document).on('input', '.quantity', function() {
        const row = $(this).closest('tr');
        const lotSelect = row.find('.lot-select');
        const selectedOption = lotSelect.find('option:selected');
        const quantity = parseFloat($(this).val()) || 0;
        const stockDispo = parseFloat(selectedOption.data('stock')) || 0;
        
        if (quantity > stockDispo) {
            Swal.fire({
                title: 'Erreur',
                text: 'La quantité ne peut pas dépasser le stock disponible (' + stockDispo + ').',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            $(this).val('');
        }
    });
    
    // Validation du formulaire
    $('#transfertForm').on('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Vérifications supplémentaires
        const sourceId = $('#id_depot_source').val();
        const destId = $('#id_depot_destination').val();
        
        if (!sourceId || !destId) {
            event.preventDefault();
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez sélectionner les dépôts source et destination.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        if (sourceId === destId) {
            event.preventDefault();
            Swal.fire({
                title: 'Erreur',
                text: 'Les dépôts source et destination ne peuvent pas être identiques.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Vérifier si au moins un produit a été sélectionné
        const hasProduct = $('.product-select').filter(function() {
            return $(this).val() !== null && $(this).val() !== '';
        }).length > 0;
        
        if (!hasProduct) {
            event.preventDefault();
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez sélectionner au moins un produit à transférer.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Vérifier les quantités
        let quantiteInvalide = false;
        $('.lot-select').each(function() {
            if ($(this).val()) {
                const row = $(this).closest('tr');
                const quantiteInput = row.find('.quantity');
                const quantiteSaisie = parseFloat(quantiteInput.val());
                const selectedOption = $(this).find('option:selected');
                const quantiteMax = parseFloat(selectedOption.data('stock'));
                
                console.log('Quantité saisie:', quantiteSaisie, 'Quantité max:', quantiteMax, 'Data stock:', selectedOption.data('stock'));
                
                // Vérification plus souple qui évite les problèmes de précision des nombres à virgule
                if (isNaN(quantiteSaisie) || quantiteSaisie <= 0 || (quantiteSaisie > (quantiteMax + 0.001))) {
                    //quantiteInvalide = true;
                    quantiteInput.addClass('is-invalid');
                } else {
                    quantiteInput.removeClass('is-invalid');
                }
            }
        });

        
        if (quantiteInvalide) {
            event.preventDefault();
            Swal.fire({
                title: 'Erreur',
                text: 'Une ou plusieurs quantités sont invalides ou dépassent le stock disponible.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        $(this).addClass('was-validated');
    });
    
    // Initialisation au chargement de la page
    $(document).ready(function() {
        // Désactiver initialement le bouton de suppression de la première ligne
        $('#row_1 .remove-row').prop('disabled', true);
    });
});
</script>


<?php include "./views/include/footer.php"; ?>
