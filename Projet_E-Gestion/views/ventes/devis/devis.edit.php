<?php
include "./views/include/header.php";
error_reporting(E_ALL); ini_set("display_errors", 1);
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du devis
$devisId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($devisId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Devis non trouvé'
        }).then(() => {
            window.location.href = 'ventes/devis/devis.list';
        });
    </script>";
    exit;
}

// Récupération des détails du devis
$query = "SELECT d.*, c.nom_client, c.code_client 
          FROM devis d 
          JOIN client c ON d.id_client = c.id_client 
          WHERE d.id_devis = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $devisId, PDO::PARAM_INT);
$stmt->execute();
$devis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devis) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Devis non trouvé'
        }).then(() => {
            window.location.href = 'ventes/devis/devis.list';
        });
    </script>";
    exit;
}

// Vérifier si le devis est modifiable (seulement en état "En cours")
if ($devis['etat'] !== 'En cours') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Modification impossible',
            text: 'Seuls les devis en état \"En cours\" peuvent être modifiés.'
        }).then(() => {
            window.location.href = 'ventes/devis/devis.view&id=" . $devisId . "';
        });
    </script>";
    exit;
}

// Récupération des lignes du devis
$queryLignes = "SELECT ld.*, p.code_produit, p.libelle_produit 
                FROM ligne_devis ld 
                JOIN produit p ON ld.id_produit = p.id_produit 
                WHERE ld.id_devis = :id_devis";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_devis', $devisId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des clients pour le formulaire
$queryClients = "SELECT id_client, code_client, nom_client FROM client WHERE actif = 1 ORDER BY nom_client";
$stmtClients = $db->prepare($queryClients);
$stmtClients->execute();
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER LE DEVIS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="ventes/devis/devis.list">Devis</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du devis</h5>

                        <form id="devisForm" action="controller/update_devis.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" name="id_devis" value="<?= $devisId ?>">
                            
                            <div class="col-md-4">
                                <label for="numero_devis" class="form-label">Numéro de devis</label>
                                <input type="text" class="form-control" id="numero_devis" name="numero_devis" value="<?= htmlspecialchars($devis['numero_devis']) ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_devis" class="form-label">Date du devis</label>
                                <input type="date" class="form-control" id="date_devis" name="date_devis" value="<?= $devis['date_devis'] ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_client" class="form-label">Client</label>
                                <select class="form-select" id="id_client" name="id_client" required>
                                    <option value="">Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id_client'] ?>" <?= ($client['id_client'] == $devis['id_client']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($client['code_client'] . ' - ' . $client['nom_client']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un client.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="validite" class="form-label">Validité (jours)</label>
                                <input type="number" class="form-control" id="validite" name="validite" value="<?= $devis['validite'] ?>" min="1" required>
                                <div class="invalid-feedback">Veuillez entrer une durée de validité.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="taux_tva" class="form-label">Taux de TVA (%)</label>
                                <input type="number" class="form-control" id="taux_tva" name="taux_tva" value="<?= $devis['taux_tva'] ?>" step="0.01" min="0" required>
                                <div class="invalid-feedback">Veuillez entrer un taux de TVA valide.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"><?= htmlspecialchars($devis['observation'] ?? '') ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="25%">Produit</th>
                                            <th width="25%">Désignation</th>
                                            <th width="10%">Quantité</th>
                                            <th width="10%">Prix unitaire</th>
                                            <th width="10%">Remise (%)</th>
                                            <th width="15%">Montant HT</th>
                                            <th width="5%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lignes as $index => $ligne): ?>
                                            <tr id="row_<?= $index + 1 ?>">
                                                <input type="hidden" name="products[<?= $index + 1 ?>][id_ligne_devis]" value="<?= $ligne['id_ligne_devis'] ?>">
                                                <td>
                                                    <select class="form-select product-select" name="products[<?= $index + 1 ?>][id_produit]" required>
                                                        <option value="">Sélectionner un produit</option>
                                                        <!-- Les options seront chargées dynamiquement -->
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control designation" name="products[<?= $index + 1 ?>][designation]" value="<?= htmlspecialchars($ligne['designation']) ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control quantity" name="products[<?= $index + 1 ?>][quantite]" value="<?= $ligne['quantite'] ?>" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price" name="products[<?= $index + 1 ?>][prix_unitaire]" value="<?= $ligne['prix_unitaire'] ?>" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control discount" name="products[<?= $index + 1 ?>][remise]" value="<?= $ligne['remise'] ?>" step="0.01" min="0" max="100">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control total" name="products[<?= $index + 1 ?>][montant_ht]" value="<?= $ligne['montant_ht'] ?>" step="0.01" readonly>
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
                            
                            <div class="row mt-3">
                                <div class="col-md-6 offset-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Total HT</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_ht" name="montant_ht" value="<?= $devis['montant_ht'] ?>" step="0.01" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Montant TVA</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_tva" name="montant_tva" value="<?= $devis['montant_tva'] ?>" step="0.01" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Total TTC</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_ttc" name="montant_ttc" value="<?= $devis['montant_ttc'] ?>" step="0.01" readonly>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='ventes/devis/devis.view&id=<?= $devisId ?>'">
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
// Variables globales
let rowCount = <?= count($lignes) ?>;
let productData = {};

// Fonction pour charger les produits depuis l'API
function loadProducts(selectElement, selectedProductId = null) {
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
        // Stocker les données des produits pour un accès facile
        productData = {};
        data.forEach(product => {
            productData[product.id_produit] = product;
        });
        
        // Vider puis remplir le select
        selectElement.innerHTML = '<option value="">Sélectionner un produit</option>';
        
        // Ajouter chaque produit comme option
        data.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id_produit;
            option.textContent = product.code_produit + ' - ' + product.libelle_produit;
            
            // Sélectionner le produit si c'est celui qui était déjà choisi
            if (selectedProductId && product.id_produit == selectedProductId) {
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

// Fonction pour calculer le total d'une ligne
function calculateRowTotal(row) {
    const quantityInput = row.querySelector('.quantity');
    const priceInput = row.querySelector('.price');
    const discountInput = row.querySelector('.discount');
    const totalInput = row.querySelector('.total');
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const discount = parseFloat(discountInput.value) || 0;
    
    // Calcul du montant HT avec remise
    const discountAmount = (price * quantity * discount) / 100;
    const total = (price * quantity) - discountAmount;
    
    totalInput.value = total.toFixed(2);
    
    // Recalculer les totaux généraux
    calculateTotals();
}

// Fonction pour calculer les totaux généraux
function calculateTotals() {
    const rows = document.querySelectorAll('#productTable tbody tr');
    let totalHT = 0;
    
    rows.forEach(row => {
        const totalInput = row.querySelector('.total');
        totalHT += parseFloat(totalInput.value) || 0;
    });
    
    const tauxTVA = parseFloat(document.getElementById('taux_tva').value) || 0;
    const montantTVA = (totalHT * tauxTVA) / 100;
    const totalTTC = totalHT + montantTVA;
    
    document.getElementById('montant_ht').value = totalHT.toFixed(2);
    document.getElementById('montant_tva').value = montantTVA.toFixed(2);
    document.getElementById('montant_ttc').value = totalTTC.toFixed(2);
}

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser Select2 pour le client
    $('#id_client').select2({
        width: '100%',
        placeholder: 'Sélectionner un client',
        allowClear: true
    });
    
    // Charger les produits pour chaque ligne existante
    document.querySelectorAll('.product-select').forEach((select, index) => {
        const row = select.closest('tr');
        const productId = <?= json_encode(array_column($lignes, 'id_produit')) ?>[index];
        loadProducts(select, productId);
    });
    
    // Écouter les changements de produit
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const productId = $(this).val();
        
        if (productId && productData[productId]) {
            // Mettre à jour la désignation avec le libellé du produit
            row.find('.designation').val(productData[productId].libelle_produit);
            
            // Mettre à jour le prix si le champ est vide ou si c'est une nouvelle ligne
            const priceInput = row.find('.price');
            if (!priceInput.val() || parseFloat(priceInput.val()) === 0) {
                priceInput.val(productData[productId].prix_vente || 0);
            }
            
            // Recalculer les montants
            calculateRowTotal(row[0]);
        }
    });
    
    // Ajouter une nouvelle ligne
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            rowCount++;
            const newRow = `
                <tr id="row_${rowCount}">
                    <input type="hidden" name="products[${rowCount}][id_ligne_devis]" value="0">
                    <td>
                        <select class="form-select product-select" name="products[${rowCount}][id_produit]" required>
                            <option value="">Sélectionner un produit</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control designation" name="products[${rowCount}][designation]" required>
                    </td>
                    <td>
                        <input type="number" class="form-control quantity" name="products[${rowCount}][quantite]" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control price" name="products[${rowCount}][prix_unitaire]" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control discount" name="products[${rowCount}][remise]" step="0.01" min="0" max="100" value="0">
                    </td>
                    <td>
                        <input type="number" class="form-control total" name="products[${rowCount}][montant_ht]" step="0.01" readonly>
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
            
            // Initialiser Select2 sur le nouveau select
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
                calculateTotals();
            } else {
                Swal.fire({
                    title: 'Impossible',
                    text: 'Vous devez avoir au moins une ligne de produit.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            }
        }
    });
    
    // Calculer le total lors de la modification des quantités, prix ou remises
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity') || 
            e.target.classList.contains('price') || 
            e.target.classList.contains('discount')) {
            calculateRowTotal(e.target.closest('tr'));
        }
        
        // Recalculer les totaux si le taux de TVA change
        if (e.target.id === 'taux_tva') {
            calculateTotals();
        }
    });
    
    // Validation du formulaire
    const devisForm = document.getElementById('devisForm');
    if (devisForm) {
        devisForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            this.classList.add('was-validated');
        });
    }
    
    // Calculer les totaux au chargement de la page
    calculateTotals();
});
</script>

<?php include "./views/include/footer.php"; ?>
