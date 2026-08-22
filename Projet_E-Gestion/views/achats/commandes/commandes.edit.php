<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la commande
$commandeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($commandeId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Commande non trouvée'
        }).then(() => {
            window.location.href = 'achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la commande
$query = "SELECT * FROM commande_fournisseur WHERE id_commande = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $commandeId, PDO::PARAM_INT);
$stmt->execute();
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Commande non trouvée'
        }).then(() => {
            window.location.href = 'achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

// Vérifier si la commande est modifiable (état "En cours")
if ($commande['etat'] !== 'En cours') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Modification impossible',
            text: 'Seules les commandes en état \"En cours\" peuvent être modifiées.'
        }).then(() => {
            window.location.href = 'achats/commandes/commandes.view&id=" . $commandeId . "';
        });
    </script>";
    exit;
}

// Récupération des lignes de la commande
$queryLignes = "SELECT lcf.*, p.code_produit, p.libelle_produit 
                FROM ligne_commande_fournisseur lcf 
                JOIN produit p ON lcf.id_produit = p.id_produit 
                WHERE lcf.id_commande = :id_commande";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations du fournisseur
$queryFournisseur = "SELECT * FROM fournisseur WHERE id_fournisseur = :id";
$stmtFournisseur = $db->prepare($queryFournisseur);
$stmtFournisseur->bindParam(':id', $commande['id_fournisseur'], PDO::PARAM_INT);
$stmtFournisseur->execute();
$fournisseur = $stmtFournisseur->fetch(PDO::FETCH_ASSOC);

// Récupération de tous les fournisseurs pour le select
$queryFournisseurs = "SELECT id_fournisseur, code_fournisseur, nom_fournisseur FROM fournisseur WHERE actif = 1 ORDER BY nom_fournisseur";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER LA COMMANDE FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/commandes/commandes.list">Commandes</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la commande</h5>

                        <form id="commandeForm" action="controller/update_commande_fournisseur.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" name="id_commande" value="<?= $commandeId ?>">
                            
                            <div class="col-md-4">
                                <label for="numero_commande" class="form-label">Numéro de commande</label>
                                <input type="text" class="form-control" id="numero_commande" name="numero_commande" value="<?= htmlspecialchars($commande['numero_commande']) ?>" required>
                                <div class="invalid-feedback">Veuillez entrer un numéro de commande.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_commande" class="form-label">Date de commande</label>
                                <input type="date" class="form-control" id="date_commande" name="date_commande" value="<?= $commande['date_commande'] ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="id_fournisseur" name="id_fournisseur" required>
                                    <option value="">Sélectionner un fournisseur</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['id_fournisseur'] ?>" <?= $f['id_fournisseur'] == $commande['id_fournisseur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['code_fournisseur'] . ' - ' . $f['nom_fournisseur']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un fournisseur.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_livraison_prevue" class="form-label">Date de livraison prévue</label>
                                <input type="date" class="form-control" id="date_livraison_prevue" name="date_livraison_prevue" value="<?= $commande['date_livraison_prevue'] ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="taux_tva" class="form-label">Taux de TVA (%)</label>
                                <input type="number" class="form-control" id="taux_tva" name="taux_tva" step="0.01" min="0" value="<?= $commande['taux_tva'] ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"><?= htmlspecialchars($commande['observation'] ?? '') ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="25%">Produit</th>
                                            <th width="20%">Désignation</th>
                                            <th width="10%">Quantité</th>
                                            <th width="10%">Prix unitaire</th>
                                            <th width="5%">Remise (%)</th>
                                            <th width="10%">Montant HT</th>
                                            <th width="10%">Montant TTC</th>
                                            <th width="5%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lignes as $index => $ligne): ?>
                                        <tr id="row_<?= $index + 1 ?>">
                                            <td>
                                                <input type="hidden" name="products[<?= $index + 1 ?>][id_ligne_commande]" value="<?= $ligne['id_ligne_commande'] ?>">
                                                <select class="form-select product-select" name="products[<?= $index + 1 ?>][id_produit]" required>
                                                    <option value="<?= $ligne['id_produit'] ?>" selected>
                                                        <?= htmlspecialchars($ligne['code_produit'] . ' - ' . $ligne['libelle_produit']) ?>
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" name="products[<?= $index + 1 ?>][designation]" value="<?= htmlspecialchars($ligne['designation']) ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control quantity" name="products[<?= $index + 1 ?>][quantite]" step="0.01" min="0.01" value="<?= $ligne['quantite'] ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control price" name="products[<?= $index + 1 ?>][prix_unitaire]" step="0.01" min="0.01" value="<?= $ligne['prix_unitaire'] ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control discount" name="products[<?= $index + 1 ?>][remise]" step="0.01" min="0" max="100" value="<?= $ligne['remise'] ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control subtotal" name="products[<?= $index + 1 ?>][montant_ht]" step="0.01" value="<?= $ligne['montant_ht'] ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control total" name="products[<?= $index + 1 ?>][montant_ttc]" step="0.01" value="<?= $ligne['montant_ttc'] ?>" readonly>
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
                                <div class="col-md-8"></div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="mb-3 row">
                                                <label class="col-sm-6 col-form-label">Total HT:</label>
                                                <div class="col-sm-6">
                                                    <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" value="<?= $commande['montant_ht'] ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-6 col-form-label">Montant TVA:</label>
                                                <div class="col-sm-6">
                                                    <input type="number" class="form-control" id="montant_tva" name="montant_tva" step="0.01" value="<?= $commande['montant_tva'] ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-6 col-form-label">Total TTC:</label>
                                                <div class="col-sm-6">
                                                    <input type="number" class="form-control" id="montant_ttc" name="montant_ttc" step="0.01" value="<?= $commande['montant_ttc'] ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/commandes/commandes.view&id=<?= $commandeId ?>'">
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
</main>

<script>


// Fonction pour charger les produits depuis l'API avec Fetch
function loadProducts(selectElement) {
    // Conserver la valeur sélectionnée actuelle et son texte
    const currentValue = selectElement.value;
    const currentText = selectElement.options[selectElement.selectedIndex]?.text || '';
    
    // Afficher l'état de chargement
    selectElement.innerHTML = '<option value="" selected disabled>Chargement...</option>';
    
    // Si une valeur était déjà sélectionnée, la remettre immédiatement
    if (currentValue) {
        const option = document.createElement('option');
        option.value = currentValue;
        option.textContent = currentText;
        option.selected = true;
        selectElement.appendChild(option);
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
        // Vider le select tout en préservant l'option sélectionnée si elle existe
        if (currentValue) {
            // Si une valeur est sélectionnée, supprimer toutes les options sauf celle sélectionnée
            Array.from(selectElement.options).forEach(option => {
                if (option.value !== currentValue) {
                    selectElement.removeChild(option);
                }
            });
        } else {
            // Si aucune valeur n'est sélectionnée, vider complètement et ajouter l'option par défaut
            selectElement.innerHTML = '<option value="" selected disabled>Sélectionner un produit</option>';
        }
        
        // Ajouter chaque produit comme option
        data.forEach(product => {
            // Ne pas ajouter si c'est déjà le produit sélectionné
            if (product.id_produit != currentValue) {
                const option = document.createElement('option');
                option.value = product.id_produit;
                option.textContent = product.code_produit + ' - ' + product.libelle_produit;
                selectElement.appendChild(option);
            }
        });
    })
    .catch(error => {
        console.error('Erreur lors du chargement des produits:', error);
        
        // En cas d'erreur, restaurer au moins l'option sélectionnée si elle existe
        if (currentValue) {
            selectElement.innerHTML = '';
            const option = document.createElement('option');
            option.value = currentValue;
            option.textContent = currentText;
            option.selected = true;
            selectElement.appendChild(option);
        } else {
            selectElement.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
        }
    });
}


// Fonction pour calculer les montants d'une ligne
function calculateRowAmounts(row) {
    const quantityInput = row.querySelector('.quantity');
    const priceInput = row.querySelector('.price');
    const discountInput = row.querySelector('.discount');
    const subtotalInput = row.querySelector('.subtotal');
    const totalInput = row.querySelector('.total');
    const tauxTvaInput = document.getElementById('taux_tva');
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const discount = parseFloat(discountInput.value) || 0;
    const tauxTva = parseFloat(tauxTvaInput.value) || 0;
    
    // Calcul du montant HT avec remise
    const discountAmount = (quantity * price) * (discount / 100);
    const subtotal = (quantity * price) - discountAmount;
    
    // Calcul du montant TTC
    const tva = subtotal * (tauxTva / 100);
    const total = subtotal + tva;
    
    // Mise à jour des champs
    subtotalInput.value = subtotal.toFixed(2);
    totalInput.value = total.toFixed(2);
    
    // Mettre à jour les totaux généraux
    calculateTotals();
}

// Fonction pour calculer les totaux généraux
function calculateTotals() {
    const rows = document.querySelectorAll('#productTable tbody tr');
    const tauxTva = parseFloat(document.getElementById('taux_tva').value) || 0;
    
    let totalHT = 0;
    let totalTVA = 0;
    let totalTTC = 0;
    
    rows.forEach(row => {
        const subtotal = parseFloat(row.querySelector('.subtotal').value) || 0;
        totalHT += subtotal;
    });
    
    totalTVA = totalHT * (tauxTva / 100);
    totalTTC = totalHT + totalTVA;
    
    document.getElementById('montant_ht').value = totalHT.toFixed(2);
    document.getElementById('montant_tva').value = totalTVA.toFixed(2);
    document.getElementById('montant_ttc').value = totalTTC.toFixed(2);
}

let rowCount = <?= count($lignes) ?>;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser Select2 pour les selects existants
    $('.product-select').each(function() {
        $(this).select2({
            width: '100%',
            placeholder: 'Sélectionner un produit',
            allowClear: true
        });
        
        // Charger les produits pour chaque select
        loadProducts(this);
    });
    
    // Initialiser Select2 pour le select de fournisseur
    $('#id_fournisseur').select2({
        width: '100%',
        placeholder: 'Sélectionner un fournisseur',
        allowClear: true
    });
    
    // Ajouter une nouvelle ligne
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            rowCount++;
            const newRow = `
                <tr id="row_${rowCount}">
                    <td>
                        <input type="hidden" name="products[${rowCount}][id_ligne_commande]" value="0">
                        <select class="form-select product-select" name="products[${rowCount}][id_produit]" required>
                            <option value="" selected disabled>Sélectionner un produit</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control" name="products[${rowCount}][designation]" required>
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
                        <input type="number" class="form-control subtotal" name="products[${rowCount}][montant_ht]" step="0.01" readonly>
                    </td>
                    <td>
                        <input type="number" class="form-control total" name="products[${rowCount}][montant_ttc]" step="0.01" readonly>
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
    
    // Calculer les montants lors de la modification des quantités, prix ou remises (avec délégation d'événement)
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity') || 
            e.target.classList.contains('price') || 
            e.target.classList.contains('discount')) {
            calculateRowAmounts(e.target.closest('tr'));
        }
        
        // Recalculer tous les totaux si le taux de TVA change
        if (e.target.id === 'taux_tva') {
            document.querySelectorAll('#productTable tbody tr').forEach(row => {
                calculateRowAmounts(row);
            });
        }
    });
    
    // Écouter les changements de produit pour mettre à jour la désignation et le prix
    // Remplacer le gestionnaire d'événements actuel:
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product-select')) {
        // Code existant...
    }
});

// Par cette approche jQuery:
$(document).on('change', '.product-select', function() {
    const row = $(this).closest('tr');
    const productId = $(this).val();
    
    if (productId) {
        // Récupérer les informations du produit
        $.ajax({
            url: 'controller/get_product_details.php',
            type: 'GET',
            data: { id: productId },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    // Mettre à jour la désignation
                    row.find('input[name*="[designation]"]').val(data.product.libelle_produit);
                    
                    // Mettre à jour le prix si le champ est vide
                    const priceInput = row.find('.price');
                    if (!priceInput.val()) {
                        priceInput.val(data.product.prix_vente || 0);
                        
                        // Recalculer les montants
                        const quantityInput = row.find('.quantity');
                        const discountInput = row.find('.discount');
                        const subtotalInput = row.find('.subtotal');
                        const totalInput = row.find('.total');
                        const tauxTvaInput = $('#taux_tva');
                        
                        const quantity = parseFloat(quantityInput.val()) || 0;
                        const price = parseFloat(priceInput.val()) || 0;
                        const discount = parseFloat(discountInput.val()) || 0;
                        const tauxTva = parseFloat(tauxTvaInput.val()) || 0;
                        
                        // Calcul du montant HT avec remise
                        const discountAmount = (quantity * price) * (discount / 100);
                        const subtotal = (quantity * price) - discountAmount;
                        
                        // Calcul du montant TTC
                        const tva = subtotal * (tauxTva / 100);
                        const total = subtotal + tva;
                        
                        // Mise à jour des champs
                        subtotalInput.val(subtotal.toFixed(2));
                        totalInput.val(total.toFixed(2));
                        
                        // Mettre à jour les totaux généraux
                        calculateTotals();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de la récupération des détails du produit:', error);
            }
        });
    }
});

    
    // Calculer les totaux initiaux
    calculateTotals();
    
    // Validation du formulaire
    const commandeForm = document.getElementById('commandeForm');
    if (commandeForm) {
        commandeForm.addEventListener('submit', function(event) {
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
