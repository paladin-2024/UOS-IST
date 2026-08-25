<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

// Récupération des dépôts auxquels l'utilisateur a accès avec droits de modification
if ($isAdmin) {
    // Les administrateurs ont accès à tous les dépôts
    $queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->execute();
} else {
    // Utilisateurs normaux - seulement les dépôts avec droit de modification
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
            text: 'Vous n\'avez pas l\'autorisation d\'ajouter des entrées de stock pour aucun dépôt. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'stock/stock.entree.list';
        });
    </script>";
    exit;
}

// Génération d'un numéro d'entrée automatique
function generateEntryNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_entree, 6) AS INTEGER)) as max_num 
              FROM entree_stock 
              WHERE numero_entree LIKE 'ENT" . $year . "%' 
              AND EXTRACT(YEAR FROM date_entree) = EXTRACT(YEAR FROM CURRENT_DATE)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'ENT' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}


$nextEntryNumber = generateEntryNumber($db);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVELLE ENTRÉE DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="stock/stock.entree.list">Entrées</a></li>
                <li class="breadcrumb-item active">Nouvelle entrée</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de l'entrée</h5>

                        <form id="entreeForm" action="controller/create_entree_stock.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-4">
                                <label for="numero_entree" class="form-label">Numéro d'entrée</label>
                                <input type="text" class="form-control" id="numero_entree" name="numero_entree" value="<?= $nextEntryNumber ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_entree" class="form-label">Date d'entrée</label>
                                <input type="date" class="form-control" id="date_entree" name="date_entree" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_depot" class="form-label">Dépôt</label>
                                <select class="form-select" id="id_depot" name="id_depot" required>
                                    <option value="" selected disabled>Sélectionner un dépôt</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un dépôt.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="type_entree" class="form-label">Type d'entrée</label>
                                <select class="form-select" id="type_entree" name="type_entree" required>
                                    <option value="" selected disabled>Sélectionner un type</option>
                                    <option value="Achat">Achat</option>
                                    <option value="Transfert">Transfert</option>
                                    <option value="Inventaire">Inventaire</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un type d'entrée.</div>
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
                                        <tr id="row_1">
                                            <td>
                                                <select class="form-select product-select" name="products[1][id_produit]" required>
                                                    <option value="" selected disabled>Sélectionner un produit</option>
                                                </select>
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
                                                <input type="text" class="form-control" name="products[1][numero_lot]" required>
                                            </td>
                                            <td>
                                                <input type="date" class="form-control" name="products[1][date_peremption]">
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
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='stock/stock.entree.list'">
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
// Fonction pour charger les produits depuis l'API avec Fetch
function loadProducts(selectElement) {
    // Afficher l'état de chargement
    selectElement.innerHTML = '<option value="" selected disabled>Chargement...</option>';
    
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
        // Vider puis remplir le select
        selectElement.innerHTML = '<option value="" selected disabled>Sélectionner un produit</option>';
        
        // Ajouter chaque produit comme option
        data.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id_produit;
            option.textContent = product.code_produit + ' - ' + product.libelle_produit;
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

let rowCount = 1;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Charger les produits pour la première ligne
    const firstProductSelect = document.querySelector('#row_1 .product-select');
    if (firstProductSelect) {
        loadProducts(firstProductSelect);
    }
    
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
