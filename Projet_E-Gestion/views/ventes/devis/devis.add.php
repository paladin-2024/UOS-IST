<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des clients actifs
$queryClients = "SELECT * FROM client WHERE actif = 1 ORDER BY nom_client";
$stmtClients = $db->prepare($queryClients);
$stmtClients->execute();
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

// Génération d'un numéro de devis automatique
function generateDevisNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_devis, 6) AS UNSIGNED)) as max_num 
              FROM devis 
              WHERE numero_devis LIKE 'DEV" . $year . "%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'DEV' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextDevisNumber = generateDevisNumber($db);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVEAU DEVIS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="ventes/devis/devis.list">Devis</a></li>
                <li class="breadcrumb-item active">Nouveau devis</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du devis</h5>

                        <form id="devisForm" action="controller/create_devis.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-4">
                                <label for="numero_devis" class="form-label">Numéro de devis</label>
                                <input type="text" class="form-control" id="numero_devis" name="numero_devis" value="<?= $nextDevisNumber ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_devis" class="form-label">Date du devis</label>
                                <input type="date" class="form-control" id="date_devis" name="date_devis" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="validite" class="form-label">Validité (jours)</label>
                                <input type="number" class="form-control" id="validite" name="validite" value="30" min="1" required>
                                <div class="invalid-feedback">Veuillez entrer une durée de validité.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_client" class="form-label">Client</label>
                                <select class="form-select select2" id="id_client" name="id_client" required>
                                    <option value="" selected disabled>Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id_client'] ?>"><?= htmlspecialchars($client['code_client'] . ' - ' . $client['nom_client']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un client.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="taux_tva" class="form-label">Taux de TVA (%)</label>
                                <input type="number" class="form-control" id="taux_tva" name="taux_tva" value="16" min="0" max="100" step="0.01" required>
                                <div class="invalid-feedback">Veuillez entrer un taux de TVA valide.</div>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="2"></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="30%">Produit</th>
                                            <th width="25%">Désignation</th>
                                            <th width="10%">Quantité</th>
                                            <th width="10%">Prix unitaire</th>
                                            <th width="5%">Remise (%)</th>
                                            <th width="10%">Montant HT</th>
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
                                                <input type="text" class="form-control designation" name="products[1][designation]" required>
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
                                                <button type="button" class="btn btn-danger btn-sm removeRow">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Total HT:</strong></td>
                                            <td colspan="2">
                                                <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>TVA:</strong></td>
                                            <td colspan="2">
                                                <input type="number" class="form-control" id="montant_tva" name="montant_tva" step="0.01" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Total TTC:</strong></td>
                                            <td colspan="2">
                                                <input type="number" class="form-control" id="montant_ttc" name="montant_ttc" step="0.01" readonly>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" id="addRowBtn" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Ajouter un produit
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='ventes/devis/devis.list'">
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
            option.dataset.price = product.prix_vente || 0;
            option.dataset.designation = product.libelle_produit || '';
            selectElement.appendChild(option);
        });
    })
    .catch(error => {
        console.error('Erreur lors du chargement des produits:', error);
        selectElement.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
    });
}

// Fonction pour calculer les montants d'une ligne
function calculateRowTotal(row) {
    const quantityInput = row.querySelector('.quantity');
    const priceInput = row.querySelector('.price');
    const discountInput = row.querySelector('.discount');
    const subtotalInput = row.querySelector('.subtotal');
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const discount = parseFloat(discountInput.value) || 0;
    
    const subtotal = quantity * price * (1 - discount / 100);
    
    subtotalInput.value = subtotal.toFixed(2);
    
    // Recalculer les totaux
    calculateTotals();
}

// Fonction pour calculer les totaux
function calculateTotals() {
    const subtotalInputs = document.querySelectorAll('.subtotal');
    const montantHtInput = document.getElementById('montant_ht');
    const montantTvaInput = document.getElementById('montant_tva');
    const montantTtcInput = document.getElementById('montant_ttc');
    const tauxTvaInput = document.getElementById('taux_tva');
    
    let totalHt = 0;
    
    subtotalInputs.forEach(input => {
        totalHt += parseFloat(input.value) || 0;
    });
    
    const tauxTva = parseFloat(tauxTvaInput.value) || 0;
    const montantTva = totalHt * (tauxTva / 100);
    const totalTtc = totalHt + montantTva;
    
    montantHtInput.value = totalHt.toFixed(2);
    montantTvaInput.value = montantTva.toFixed(2);
    montantTtcInput.value = totalTtc.toFixed(2);
}

let rowCount = 1;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser Select2 pour le client
    $('.select2').select2({
        width: '100%',
        placeholder: 'Sélectionner un client',
        allowClear: true
    });
    
    // Charger les produits pour la première ligne
    const firstProductSelect = document.querySelector('#row_1 .product-select');
    if (firstProductSelect) {
        loadProducts(firstProductSelect);
        
        // Initialiser Select2 pour le produit
        $(firstProductSelect).select2({
            width: '100%',
            placeholder: 'Sélectionner un produit',
            allowClear: true
        });
    }
    
    // Gérer le changement de produit
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
                        calculateRowTotal(row[0]);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de la récupération des détails du produit:', error);
            }
        });
    }
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
                        <input type="number" class="form-control subtotal" name="products[${rowCount}][montant_ht]" step="0.01" readonly>
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
    
    // Calculer le total lors de la modification des quantités, prix ou remises (avec délégation d'événement)
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity') || e.target.classList.contains('price') || e.target.classList.contains('discount')) {
            calculateRowTotal(e.target.closest('tr'));
        }
        
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
});
</script>

<?php include "./views/include/footer.php"; ?>
