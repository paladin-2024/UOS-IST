<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

// Récupérer l'ID de l'entrée à modifier
$id_entree = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_entree <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Entrée de stock non spécifiée'
        }).then(() => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Récupérer les informations de l'entrée de stock
$query = "SELECT * FROM entree_stock WHERE id_entree = :id_entree";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
$stmt->execute();
$entree = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entree) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Entrée de stock introuvable'
        }).then(() => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Vérifier si l'entrée est encore modifiable (en cours)
if ($entree['etat'] !== 'En cours') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Modification impossible',
            text: 'Cette entrée de stock ne peut plus être modifiée car elle est déjà " . strtolower($entree['etat']) . ".'
        }).then(() => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Vérifier les permissions de l'utilisateur pour ce dépôt
if (!$isAdmin) {
    $permQuery = "SELECT peut_modifier 
                 FROM autorisation_depot 
                 WHERE id_user = :user_id AND id_depot = :depot_id";
    $permStmt = $db->prepare($permQuery);
    $permStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $permStmt->bindParam(':depot_id', $entree['id_depot'], PDO::PARAM_INT);
    $permStmt->execute();
    $permission = $permStmt->fetch(PDO::FETCH_ASSOC);

    if (!$permission || $permission['peut_modifier'] != 1) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'avez pas l\'autorisation de modifier les entrées pour ce dépôt'
            }).then(() => {
                window.location.href = 'stock/stock.entree.list';
            });
        </script>";
        exit;
    }
}

// Récupérer les détails des produits de cette entrée
$queryDetails = "SELECT d.*, l.numero_lot, l.date_peremption 
                FROM detail_entree_stock d
                LEFT JOIN lot_produit l ON d.id_detail_entree = l.id_detail_entree
                WHERE d.id_entree = :id_entree";
$stmtDetails = $db->prepare($queryDetails);
$stmtDetails->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
$stmtDetails->execute();
$details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dépôts
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

// Récupérer le nom du dépôt actuel
$queryDepotName = "SELECT libelle_depot FROM depot WHERE id_depot = :id_depot";
$stmtDepotName = $db->prepare($queryDepotName);
$stmtDepotName->bindParam(':id_depot', $entree['id_depot'], PDO::PARAM_INT);
$stmtDepotName->execute();
$depotName = $stmtDepotName->fetchColumn();
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER ENTRÉE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.entree.list">Entrées</a></li>
                <li class="breadcrumb-item active">Modifier entrée</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de l'entrée</h5>

                        <form id="entreeForm" action="controller/update_entree_stock.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" name="id_entree" value="<?= $entree['id_entree'] ?>">
                            
                            <div class="col-md-4">
                                <label for="numero_entree" class="form-label">Numéro d'entrée</label>
                                <input type="text" class="form-control" id="numero_entree" name="numero_entree" value="<?= $entree['numero_entree'] ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_entree" class="form-label">Date d'entrée</label>
                                <input type="date" class="form-control" id="date_entree" name="date_entree" value="<?= $entree['date_entree'] ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_depot" class="form-label">Dépôt</label>
                                <select class="form-select" id="id_depot" name="id_depot" required>
                                    <option value="" disabled>Sélectionner un dépôt</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>" <?= ($depot['id_depot'] == $entree['id_depot']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($depot['libelle_depot']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un dépôt.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="type_entree" class="form-label">Type d'entrée</label>
                                <select class="form-select" id="type_entree" name="type_entree" required>
                                    <option value="" disabled>Sélectionner un type</option>
                                    <option value="Achat" <?= ($entree['type_entree'] == 'Achat') ? 'selected' : '' ?>>Achat</option>
                                    <option value="Transfert" <?= ($entree['type_entree'] == 'Transfert') ? 'selected' : '' ?>>Transfert</option>
                                    <option value="Inventaire" <?= ($entree['type_entree'] == 'Inventaire') ? 'selected' : '' ?>>Inventaire</option>
                                    <option value="Autre" <?= ($entree['type_entree'] == 'Autre') ? 'selected' : '' ?>>Autre</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un type d'entrée.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="reference_document" class="form-label">Référence document</label>
                                <input type="text" class="form-control" id="reference_document" name="reference_document" value="<?= $entree['reference_document'] ?>">
                                <div class="form-text">Facultatif - Numéro de la facture, bon de livraison, etc.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"><?= $entree['observation'] ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="30%">Produit</th>
                                            <th width="15%">Quantité</th>
                                            <th width="15%">Prix unitaire</th>
                                            <th width="15%">Montant total</th>
                                            <th width="15%">N° Lot</th>
                                            <th width="15%">Date péremption</th>
                                            <th width="5%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rowCount = 0;
                                        foreach ($details as $index => $detail): 
                                            $rowCount++;
                                        ?>
                                        <tr id="row_<?= $rowCount ?>">
                                            <input type="hidden" name="products[<?= $rowCount ?>][id_detail_entree]" value="<?= $detail['id_detail_entree'] ?>">
                                            <td>
                                                <select class="form-select product-select" name="products[<?= $rowCount ?>][id_produit]" required>
                                                    <option value="<?= $detail['id_produit'] ?>" selected data-product-id="<?= $detail['id_produit'] ?>">
                                                        Chargement...
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control quantity" name="products[<?= $rowCount ?>][quantite]" value="<?= $detail['quantite'] ?>" step="0.01" min="0.01" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control price" name="products[<?= $rowCount ?>][prix_unitaire]" value="<?= $detail['prix_unitaire'] ?>" step="0.01" min="0.01" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control total" name="products[<?= $rowCount ?>][montant_total]" value="<?= $detail['montant_total'] ?>" step="0.01" readonly>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" name="products[<?= $rowCount ?>][numero_lot]" value="<?= $detail['numero_lot'] ?? '' ?>" required>
                                            </td>
                                            <td>
                                                <input type="date" class="form-control" name="products[<?= $rowCount ?>][date_peremption]" value="<?= $detail['date_peremption'] ?? '' ?>">
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
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/stock.entree.list'">
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
// Fonction pour charger les produits depuis l'API avec Fetch
function loadProducts(selectElement, selectedProductId = null) {
    // Afficher l'état de chargement
    if (!selectedProductId) {
        selectElement.innerHTML = '<option value="" selected disabled>Chargement...</option>';
    }
    
    fetch('controller/get_products_api.php', {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur réseau: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        // Sauvegarder le produit sélectionné actuel s'il y en a un
        let selectedOption = null;
        if (selectedProductId) {
            selectedOption = selectElement.querySelector('option:checked');
        }
        
        // Vider puis remplir le select
        selectElement.innerHTML = '<option value="" disabled>Sélectionner un produit</option>';
        
        // Ajouter chaque produit comme option
        data.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id_produit;
            option.textContent = product.code_produit + ' - ' + product.libelle_produit;
            
            // Si c'est le produit actuellement sélectionné ou le produit à sélectionner
            if ((selectedOption && selectedOption.value == product.id_produit) || 
                (selectedProductId && product.id_produit == selectedProductId)) {
                option.selected = true;
            }
            
            selectElement.appendChild(option);
        });
    })
    .catch(error => {
        console.error('Erreur lors du chargement des produits:', error);
        selectElement.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
    });
}

// Fonction pour calculer les montants
function calculateTotal(row) {
    const quantityInput = row.querySelector('.quantity');
    const priceInput = row.querySelector('.price');
    const totalInput = row.querySelector('.total');
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const total = quantity * price;
    
    totalInput.value = total.toFixed(2);
}

let rowCount = <?= $rowCount ?>;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Charger les produits pour chaque ligne existante
    document.querySelectorAll('.product-select').forEach(select => {
        const productId = select.querySelector('option').dataset.productId;
        loadProducts(select, productId);
    });
    
    // Ajouter une nouvelle ligne
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            rowCount++;
            const newRow = `
                <tr id="row_${rowCount}">
                    <td>
                        <select class="form-select product-select" name="products[${rowCount}][id_produit]" required>
                            <option value="" selected disabled>Sélectionner un produit</option>
                        </select>
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
                        <input type="text" class="form-control" name="products[${rowCount}][numero_lot]" required>
                    </td>
                    <td>
                        <input type="date" class="form-control" name="products[${rowCount}][date_peremption]">
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm removeRow">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            const tbody = document.querySelector('#productTable tbody');
            tbody.insertAdjacentHTML('beforeend', newRow);
            
            // Charger les produits pour la nouvelle ligne
            const newSelect = document.querySelector(`#row_${rowCount} .product-select`);
            loadProducts(newSelect);
            // AJOUTEZ CETTE LIGNE: Initialiser Select2 sur le nouveau select
            $(newSelect).select2({
                width: '100%',
                placeholder: 'Sélectionner un produit',
                allowClear: true
            });
        });
    }
    
    // Supprimer une ligne (avec délégation d'événement)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('removeRow') || e.target.closest('.removeRow')) {
            const row = e.target.closest('tr');
            const tbody = document.querySelector('#productTable tbody');
            
            if (tbody.rows.length > 1) {
                row.remove();
            } else {
                // Utiliser SweetAlert si disponible, sinon alert standard
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Impossible',
                        text: 'Vous devez avoir au moins une ligne de produit.',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Vous devez avoir au moins une ligne de produit.');
                }
            }
        }
    });
    
    // Calculer le total lors de la modification des quantités ou prix (avec délégation d'événement)
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity') || e.target.classList.contains('price')) {
            calculateTotal(e.target.closest('tr'));
        }
    });
    
    // Validation du formulaire
    const entreeForm = document.getElementById('entreeForm');
    if (entreeForm) {
        entreeForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            this.classList.add('was-validated');
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
