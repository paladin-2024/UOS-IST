<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des fournisseurs actifs
$queryFournisseurs = "SELECT id_fournisseur, code_fournisseur, nom_fournisseur 
                     FROM fournisseur 
                     WHERE actif = 1 
                     ORDER BY nom_fournisseur";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Récupération de l'ID de la demande de prix si fourni
$demandeId = isset($_GET['demande']) ? intval($_GET['demande']) : 0;
$demande = null;
$lignesDemande = [];

if ($demandeId > 0) {
    // Récupérer les informations de la demande de prix
    $queryDemande = "SELECT dp.*, f.id_fournisseur, f.code_fournisseur, f.nom_fournisseur 
                     FROM demande_prix dp 
                     JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur 
                     WHERE dp.id_demande_prix = :id AND dp.etat = 'Validé'";
    $stmtDemande = $db->prepare($queryDemande);
    $stmtDemande->bindParam(':id', $demandeId, PDO::PARAM_INT);
    $stmtDemande->execute();
    $demande = $stmtDemande->fetch(PDO::FETCH_ASSOC);
    
    if ($demande) {
        // Récupérer les lignes de la demande
        $queryLignes = "SELECT ldp.*, p.code_produit, p.libelle_produit 
                       FROM ligne_demande_prix ldp 
                       JOIN produit p ON ldp.id_produit = p.id_produit 
                       WHERE ldp.id_demande_prix = :id_demande";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_demande', $demandeId, PDO::PARAM_INT);
        $stmtLignes->execute();
        $lignesDemande = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Génération d'un numéro de commande automatique
function generateCommandeNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    $month = date('m'); // Mois courant
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_commande, 8) AS UNSIGNED)) as max_num 
              FROM commande_fournisseur 
              WHERE numero_commande LIKE 'CMD" . $year . $month . "%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'CMD' . $year . $month . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextCommandeNumber = generateCommandeNumber($db);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVELLE COMMANDE FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/commandes/commandes.list">Commandes</a></li>
                <li class="breadcrumb-item active">Nouvelle commande</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la commande</h5>

                        <form id="commandeForm" action="controller/create_commande_fournisseur.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <?php if ($demandeId > 0 && $demande): ?>
                                <input type="hidden" name="id_demande_prix" value="<?= $demandeId ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <label for="numero_commande" class="form-label">Numéro de commande</label>
                                <input type="text" class="form-control" id="numero_commande" name="numero_commande" value="<?= $nextCommandeNumber ?>" required>
                                <div class="invalid-feedback">Veuillez entrer un numéro de commande.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_commande" class="form-label">Date de commande</label>
                                <input type="date" class="form-control" id="date_commande" name="date_commande" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="id_fournisseur" name="id_fournisseur" required <?= ($demande) ? 'disabled' : '' ?>>
                                    <option value="">Sélectionner un fournisseur</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['id_fournisseur'] ?>" <?= ($demande && $f['id_fournisseur'] == $demande['id_fournisseur']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['code_fournisseur'] . ' - ' . $f['nom_fournisseur']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($demande): ?>
                                    <input type="hidden" name="id_fournisseur" value="<?= $demande['id_fournisseur'] ?>">
                                <?php endif; ?>
                                <div class="invalid-feedback">Veuillez sélectionner un fournisseur.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_livraison_prevue" class="form-label">Date de livraison prévue</label>
                                <input type="date" class="form-control" id="date_livraison_prevue" name="date_livraison_prevue">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="taux_tva" class="form-label">Taux de TVA (%)</label>
                                <input type="number" class="form-control" id="taux_tva" name="taux_tva" step="0.01" min="0" value="16.00">
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
                                        <?php if ($demande && !empty($lignesDemande)): ?>
                                            <?php foreach ($lignesDemande as $index => $ligne): ?>
                                                <tr id="row_<?= $index + 1 ?>">
                                                    <td>
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
                                                        <input type="number" class="form-control price" name="products[<?= $index + 1 ?>][prix_unitaire]" step="0.01" min="0.01" value="<?= $ligne['prix_unitaire'] ?? '' ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control discount" name="products[<?= $index + 1 ?>][remise]" step="0.01" min="0" max="100" value="0">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control subtotal" name="products[<?= $index + 1 ?>][montant_ht]" step="0.01" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control total" name="products[<?= $index + 1 ?>][montant_ttc]" step="0.01" readonly>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm removeRow">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="row_1">
                                                <td>
                                                    <select class="form-select product-select" name="products[1][id_produit]" required>
                                                        <option value="" selected disabled>Sélectionner un produit</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="products[1][designation]" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control quantity" name="products[1][quantite]" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price" name="products[1][prix_unitaire]" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control discount" name="products[1][remise]" step="0.01" min="0" max="100" value="0">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control subtotal" name="products[1][montant_ht]" step="0.01" readonly>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control total" name="products[1][montant_ttc]" step="0.01" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
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
                                                    <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" value="0.00" readonly>
                                                </div>
                                            </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-6 col-form-label">Montant TVA:</label>
                                                <div class="col-sm-6">
                                                    <input type="number" class="form-control" id="montant_tva" name="montant_tva" step="0.01" value="0.00" readonly>
                                                </div>
                                                </div>
                                            <div class="mb-3 row">
                                                <label class="col-sm-6 col-form-label">Total TTC:</label>
                                                <div class="col-sm-6">
                                                    <input type="number" class="form-control" id="montant_ttc" name="montant_ttc" step="0.01" value="0.00" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/commandes/commandes.list'">
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

let rowCount = <?= ($demande && !empty($lignesDemande)) ? count($lignesDemande) : 1 ?>;

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
    
    // Calculer les montants initiaux pour les lignes existantes
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        calculateRowAmounts(row);
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
    
    // Écouter les changements de produit pour mettre à jour la désignation et le prix
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
