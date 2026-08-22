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
            text: 'Vous n\'avez pas l\'autorisation d\'ajouter des réceptions pour aucun dépôt. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

// Récupération des fournisseurs actifs
$queryFournisseurs = "SELECT id_fournisseur, code_fournisseur, nom_fournisseur 
                     FROM fournisseur 
                     WHERE actif = 1 
                     ORDER BY nom_fournisseur";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Récupération des commandes en attente de réception
$queryCommandes = "SELECT cf.id_commande, cf.numero_commande, cf.date_commande, f.nom_fournisseur 
                  FROM commande_fournisseur cf 
                  JOIN fournisseur f ON cf.id_fournisseur = f.id_fournisseur 
                  WHERE cf.etat = 'Validé' OR cf.etat = 'Réceptionné partiellement'
                  ORDER BY cf.date_commande DESC";
$stmtCommandes = $db->prepare($queryCommandes);
$stmtCommandes->execute();
$commandes = $stmtCommandes->fetchAll(PDO::FETCH_ASSOC);

// Génération d'un numéro de réception automatique
function generateReceptionNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_reception, 5) AS UNSIGNED)) as max_num 
              FROM reception_fournisseur 
              WHERE numero_reception LIKE 'BR" . $year . "%' 
              AND YEAR(date_reception) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'BR' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}


$nextReceptionNumber = generateReceptionNumber($db);

// Récupération de l'ID de commande si passé en paramètre
$commandeId = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$commandeDetails = null;
$lignesCommande = [];

if ($commandeId > 0) {
    // Récupérer les détails de la commande
    $queryCommandeDetails = "SELECT cf.*, f.nom_fournisseur, f.code_fournisseur 
                            FROM commande_fournisseur cf 
                            JOIN fournisseur f ON cf.id_fournisseur = f.id_fournisseur 
                            WHERE cf.id_commande = :id_commande";
    $stmtCommandeDetails = $db->prepare($queryCommandeDetails);
    $stmtCommandeDetails->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
    $stmtCommandeDetails->execute();
    $commandeDetails = $stmtCommandeDetails->fetch(PDO::FETCH_ASSOC);
    
    if ($commandeDetails) {
        // Récupérer les lignes de la commande
        $queryLignesCommande = "SELECT lcf.*, p.code_produit, p.libelle_produit, p.est_peremption_suivi,
                               COALESCE(SUM(lrf.quantite), 0) as quantite_deja_recue
                               FROM ligne_commande_fournisseur lcf 
                               JOIN produit p ON lcf.id_produit = p.id_produit 
                               LEFT JOIN ligne_reception_fournisseur lrf ON lcf.id_produit = lrf.id_produit
                               LEFT JOIN reception_fournisseur rf ON lrf.id_reception = rf.id_reception 
                                   AND rf.id_commande = lcf.id_commande AND rf.etat = 'Validé'
                               WHERE lcf.id_commande = :id_commande
                               GROUP BY lcf.id_ligne_commande, lcf.id_produit
                               HAVING lcf.quantite > COALESCE(SUM(lrf.quantite), 0)
                               ORDER BY p.libelle_produit";
        $stmtLignesCommande = $db->prepare($queryLignesCommande);
        $stmtLignesCommande->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
        $stmtLignesCommande->execute();
        $lignesCommande = $stmtLignesCommande->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVELLE RÉCEPTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/receptions/receptions.list">Réceptions</a></li>
                <li class="breadcrumb-item active">Nouvelle réception</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la réception</h5>

                        <form id="receptionForm" action="controller/create_reception.php" method="POST" class="needs-validation" novalidate>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="numero_reception" class="form-label">Numéro de réception</label>
                                    <input type="text" class="form-control" id="numero_reception" name="numero_reception" value="<?= $nextReceptionNumber ?>" readonly>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="date_reception" class="form-label">Date de réception</label>
                                    <input type="date" class="form-control" id="date_reception" name="date_reception" value="<?= date('Y-m-d') ?>" required>
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
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="id_fournisseur" class="form-label">Fournisseur</label>
                                    <select class="form-select" id="id_fournisseur" name="id_fournisseur" <?= $commandeDetails ? 'readonly' : 'required' ?>>
                                        <option value="" selected disabled>Sélectionner un fournisseur</option>
                                        <?php foreach ($fournisseurs as $fournisseur): ?>
                                            <option value="<?= $fournisseur['id_fournisseur'] ?>" <?= $commandeDetails && $commandeDetails['id_fournisseur'] == $fournisseur['id_fournisseur'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($fournisseur['code_fournisseur'] . ' - ' . $fournisseur['nom_fournisseur']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un fournisseur.</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="id_commande" class="form-label">Commande (optionnel)</label>
                                    <select class="form-select" id="id_commande" name="id_commande" <?= $commandeDetails ? 'readonly' : '' ?>>
                                        <option value="">Sélectionner une commande</option>
                                        <?php foreach ($commandes as $commande): ?>
                                            <option value="<?= $commande['id_commande'] ?>" <?= $commandeId == $commande['id_commande'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($commande['numero_commande'] . ' - ' . $commande['nom_fournisseur']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="reference_bl" class="form-label">Référence BL</label>
                                    <input type="text" class="form-control" id="reference_bl" name="reference_bl">
                                    <div class="form-text">Numéro du bon de livraison du fournisseur</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="observation" class="form-label">Observation</label>
                                    <textarea class="form-control" id="observation" name="observation" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5 class="card-title">Produits à réceptionner</h5>
                            
                            <?php if ($commandeDetails && !empty($lignesCommande)): ?>
                                <!-- Produits de la commande -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="commandeProductTable">
                                        <thead>
                                            <tr>
                                                <th width="10%">Code</th>
                                                <th width="20%">Produit</th>
                                                <th width="10%">Qté commandée</th>
                                                <th width="10%">Qté déjà reçue</th>
                                                <th width="10%">Qté à recevoir</th>
                                                <th width="10%">Prix unitaire</th>
                                                <th width="10%">Montant total</th>
                                                <th width="10%">N° Lot</th>
                                                <th width="10%">Date péremption</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lignesCommande as $index => $ligne): ?>
                                                <?php $quantiteRestante = $ligne['quantite'] - $ligne['quantite_deja_recue']; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                    <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                    <td class="text-end"><?= number_format($ligne['quantite'], 2, '.', ' ') ?></td>
                                                    <td class="text-end"><?= number_format($ligne['quantite_deja_recue'], 2, '.', ' ') ?></td>
                                                    <td>
                                                        <input type="hidden" name="produits_commande[<?= $index ?>][id_produit]" value="<?= $ligne['id_produit'] ?>">
                                                        <input type="hidden" name="produits_commande[<?= $index ?>][designation]" value="<?= htmlspecialchars($ligne['designation']) ?>">
                                                        <input type="number" class="form-control quantity" name="produits_commande[<?= $index ?>][quantite]" value="<?= $quantiteRestante ?>" step="0.01" min="0.01" max="<?= $quantiteRestante ?>" required>
                                                    </td>
                                                    <td>
                                                    <input type="number" class="form-control price" name="produits_commande[<?= $index ?>][prix_unitaire]" value="<?= $ligne['prix_unitaire'] ?>" step="0.01" min="0.01" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control total" name="produits_commande[<?= $index ?>][montant_total]" value="<?= $ligne['prix_unitaire'] * $quantiteRestante ?>" step="0.01" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control" name="produits_commande[<?= $index ?>][numero_lot]" required>
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control" name="produits_commande[<?= $index ?>][date_peremption]" <?= $ligne['est_peremption_suivi'] ? 'required' : '' ?>>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <!-- Produits hors commande -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="productTable">
                                        <thead>
                                            <tr>
                                                <th width="25%">Produit</th>
                                                <th width="10%">Quantité</th>
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
                                                    <select class="form-select product-select" name="produits[1][id_produit]" required>
                                                        <option value="" selected disabled>Sélectionner un produit</option>
                                                    </select>
                                                    <input type="hidden" name="produits[1][designation]" class="designation-input" value="">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control quantity" name="produits[1][quantite]" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price" name="produits[1][prix_unitaire]" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control total" name="produits[1][montant_total]" step="0.01" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="produits[1][numero_lot]" required>
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control date-peremption" name="produits[1][date_peremption]">
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
                                
                                <div class="text-center mt-3">
                                    <button type="button" id="addRowBtn" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Ajouter un produit
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Récapitulatif des totaux -->
                            <div class="row mt-4">
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/receptions/receptions.list'">
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
            option.setAttribute('data-peremption', product.est_peremption_suivi);
            selectElement.appendChild(option);
        });
        
        // Réinitialiser Select2 après le chargement des données
        if (typeof $.fn.select2 !== 'undefined') {
            $(selectElement).select2({
                width: '100%',
                placeholder: 'Sélectionner un produit',
                allowClear: true
            });
        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement des produits:', error);
        selectElement.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
    });
}

// Fonction pour calculer les montants totaux d'une ligne
function calculateTotal(row) {
    const quantityInput = row.querySelector('.quantity');
    const priceInput = row.querySelector('.price');
    const totalInput = row.querySelector('.total');
    
    if (!quantityInput || !priceInput || !totalInput) return;
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const total = quantity * price;
    
    totalInput.value = total.toFixed(2);
    
    // Recalculer le total général
    calculateGrandTotal();
}

// Fonction pour calculer le total général
function calculateGrandTotal() {
    let totalHT = 0;
    
    // Calculer le total des produits de la commande (si présent)
    const commandeRows = document.querySelectorAll('#commandeProductTable tbody tr');
    commandeRows.forEach(row => {
        const totalInput = row.querySelector('.total');
        if (totalInput) {
            totalHT += parseFloat(totalInput.value) || 0;
        }
    });
    
    // Calculer le total des produits hors commande (si présent)
    const productRows = document.querySelectorAll('#productTable tbody tr');
    productRows.forEach(row => {
        const totalInput = row.querySelector('.total');
        if (totalInput) {
            totalHT += parseFloat(totalInput.value) || 0;
        }
    });
    
    // Mettre à jour le total HT
    document.getElementById('montant_ht').value = totalHT.toFixed(2);
}

let rowCount = 1;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser Select2 pour les sélecteurs existants
    if (typeof $.fn.select2 !== 'undefined') {
        $('#id_fournisseur, #id_commande, #id_depot').select2({
            width: '100%',
            placeholder: 'Sélectionner...',
            allowClear: true
        });
        
        $('.product-select').select2({
            width: '100%',
            placeholder: 'Sélectionner un produit',
            allowClear: true
        });
    }
    
    // Charger les produits pour la première ligne (si présente)
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
                        <select class="form-select product-select" name="produits[${rowCount}][id_produit]" required>
                            <option value="" selected disabled>Sélectionner un produit</option>
                        </select>
                        <input type="hidden" name="produits[${rowCount}][designation]" class="designation-input" value="">
                    </td>
                    <td>
                        <input type="number" class="form-control quantity" name="produits[${rowCount}][quantite]" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control price" name="produits[${rowCount}][prix_unitaire]" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control total" name="produits[${rowCount}][montant_total]" step="0.01" readonly>
                    </td>
                    <td>
                        <input type="text" class="form-control" name="produits[${rowCount}][numero_lot]" required>
                    </td>
                    <td>
                        <input type="date" class="form-control date-peremption" name="produits[${rowCount}][date_peremption]">
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
            
            // Ajouter l'événement pour gérer la date de péremption
            newSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (!selectedOption.value) return;
                
                const requiresExpiry = selectedOption.getAttribute('data-peremption') === '1';
                const datePeremption = this.closest('tr').querySelector('.date-peremption');
                
                if (requiresExpiry) {
                    datePeremption.setAttribute('required', 'required');
                } else {
                    datePeremption.removeAttribute('required');
                }
                
                // Mettre à jour le champ de désignation caché
                const designationInput = this.parentElement.querySelector('.designation-input');
                designationInput.value = selectedOption.textContent.trim();
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
                calculateGrandTotal(); // Recalculer le total après suppression
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
    
    // Gérer la sélection de produit et la date de péremption
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-select')) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            if (!selectedOption.value) return;
            
            const requiresExpiry = selectedOption.getAttribute('data-peremption') === '1';
            const datePeremption = e.target.closest('tr').querySelector('.date-peremption');
            
            if (requiresExpiry) {
                datePeremption.setAttribute('required', 'required');
            } else {
                datePeremption.removeAttribute('required');
            }
            
                        // Mettre à jour le champ de désignation caché
                        const designationInput = e.target.parentElement.querySelector('.designation-input');
            designationInput.value = selectedOption.textContent.trim();
        }
    });
    
    // Gestion de la sélection de commande
    const commandeSelect = document.getElementById('id_commande');
    if (commandeSelect) {
        commandeSelect.addEventListener('change', function() {
            const commandeId = this.value;
            if (commandeId) {
                // Rediriger vers la même page avec l'ID de commande
                window.location.href = 'achats/receptions/receptions.add&commande=' + commandeId;
            }
        });
    }
    
    // Gestion de la sélection de fournisseur
    const fournisseurSelect = document.getElementById('id_fournisseur');
    if (fournisseurSelect) {
        fournisseurSelect.addEventListener('change', function() {
            const fournisseurId = this.value;
            if (fournisseurId) {
                // Filtrer les commandes par fournisseur
                const commandeSelect = document.getElementById('id_commande');
                if (commandeSelect) {
                    for (let i = 0; i < commandeSelect.options.length; i++) {
                        const option = commandeSelect.options[i];
                        if (option.value === '') continue; // Ignorer l'option vide
                        
                        // Vérifier si l'option contient le nom du fournisseur
                        const optionText = option.textContent;
                        const fournisseurOption = fournisseurSelect.options[fournisseurSelect.selectedIndex];
                        const fournisseurText = fournisseurOption.textContent.split(' - ')[1]; // Obtenir le nom du fournisseur
                        
                        if (optionText.includes(fournisseurText)) {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                    
                    // Réinitialiser la sélection de commande
                    commandeSelect.value = '';
                    if (typeof $.fn.select2 !== 'undefined') {
                        $(commandeSelect).trigger('change');
                    }
                }
            }
        });
    }
    
    // Validation du formulaire
    const receptionForm = document.getElementById('receptionForm');
    if (receptionForm) {
        receptionForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Afficher un message d'erreur
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Veuillez remplir tous les champs obligatoires.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
            
            this.classList.add('was-validated');
        });
    }
    
    // Calculer les totaux initiaux
    document.querySelectorAll('#commandeProductTable tbody tr, #productTable tbody tr').forEach(row => {
        calculateTotal(row);
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
