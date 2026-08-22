<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupérer l'ID de la réception si fourni
$receptionId = isset($_GET['reception']) ? intval($_GET['reception']) : 0;

// Si une réception est spécifiée, récupérer ses informations
$reception = null;
$fournisseur = null;
$lignesReception = [];
$totalHT = 0;
$tauxTVA = 16; // Taux par défaut, à ajuster selon vos besoins

if ($receptionId > 0) {
    // Récupérer les détails de la réception
    $query = "SELECT r.*, f.id_fournisseur, f.code_fournisseur, f.nom_fournisseur, f.delai_paiement 
              FROM reception_fournisseur r 
              JOIN fournisseur f ON r.id_fournisseur = f.id_fournisseur 
              WHERE r.id_reception = :id AND r.etat = 'Validé'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $receptionId, PDO::PARAM_INT);
    $stmt->execute();
    $reception = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reception) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Réception non trouvée ou non validée'
            }).then(() => {
                window.location.href = 'achats/factures/factures.list';
            });
        </script>";
        exit;
    }
    
    // Récupérer les lignes de la réception
    $queryLignes = "SELECT l.*, p.code_produit, p.libelle_produit 
                   FROM ligne_reception_fournisseur l 
                   JOIN produit p ON l.id_produit = p.id_produit 
                   WHERE l.id_reception = :id_reception";
    $stmtLignes = $db->prepare($queryLignes);
    $stmtLignes->bindParam(':id_reception', $receptionId, PDO::PARAM_INT);
    $stmtLignes->execute();
    $lignesReception = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total HT
    foreach ($lignesReception as $ligne) {
        $totalHT += $ligne['montant_total'];
    }
    
    $fournisseur = [
        'id_fournisseur' => $reception['id_fournisseur'],
        'code_fournisseur' => $reception['code_fournisseur'],
        'nom_fournisseur' => $reception['nom_fournisseur'],
        'delai_paiement' => $reception['delai_paiement']
    ];
} else {
    // Liste des fournisseurs pour sélection manuelle
    $queryFournisseurs = "SELECT id_fournisseur, code_fournisseur, nom_fournisseur, delai_paiement 
                         FROM fournisseur 
                         WHERE actif = 1 
                         ORDER BY nom_fournisseur";
    $stmtFournisseurs = $db->prepare($queryFournisseurs);
    $stmtFournisseurs->execute();
    $fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
}

// Génération d'un numéro de facture automatique
function generateInvoiceNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_facture, 6) AS UNSIGNED)) as max_num 
              FROM facture_fournisseur 
              WHERE numero_facture LIKE 'FACT" . $year . "%' 
              AND YEAR(date_facture) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'FACT' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextInvoiceNumber = generateInvoiceNumber($db);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVELLE FACTURE FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/factures/factures.list">Factures</a></li>
                <li class="breadcrumb-item active">Nouvelle facture</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?= $reception ? 'Création de facture à partir de la réception N° ' . $reception['numero_reception'] : 'Nouvelle facture fournisseur' ?>
                        </h5>

                        <form id="factureForm" action="controller/create_facture_fournisseur.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <?php if ($reception): ?>
                                <input type="hidden" name="id_reception" value="<?= $receptionId ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label for="numero_facture" class="form-label">Numéro de facture</label>
                                <input type="text" class="form-control" id="numero_facture" name="numero_facture" value="<?= $nextInvoiceNumber ?>" required>
                                <div class="invalid-feedback">Veuillez saisir un numéro de facture.</div>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="reference_fournisseur" class="form-label">Référence fournisseur</label>
                                <input type="text" class="form-control" id="reference_fournisseur" name="reference_fournisseur">
                                <div class="form-text">Numéro de facture du fournisseur (facultatif)</div>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_facture" class="form-label">Date de facture</label>
                                <input type="date" class="form-control" id="date_facture" name="date_facture" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_echeance" class="form-label">Date d'échéance</label>
                                <input type="date" class="form-control" id="date_echeance" name="date_echeance" 
                                       value="<?= date('Y-m-d', strtotime('+' . ($fournisseur ? $fournisseur['delai_paiement'] : 30) . ' days')) ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date d'échéance.</div>
                            </div>
                            
                            <?php if ($reception): ?>
                                <div class="col-md-6">
                                    <label for="fournisseur" class="form-label">Fournisseur</label>
                                    <input type="text" class="form-control" id="fournisseur" value="<?= $fournisseur['code_fournisseur'] . ' - ' . $fournisseur['nom_fournisseur'] ?>" readonly>
                                    <input type="hidden" name="id_fournisseur" value="<?= $fournisseur['id_fournisseur'] ?>">
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label for="id_fournisseur" class="form-label">Fournisseur</label>
                                    <select class="form-select" id="id_fournisseur" name="id_fournisseur" required>
                                        <option value="" selected disabled>Sélectionner un fournisseur</option>
                                        <?php foreach ($fournisseurs as $f): ?>
                                            <option value="<?= $f['id_fournisseur'] ?>" data-delai="<?= $f['delai_paiement'] ?>">
                                                <?= $f['code_fournisseur'] . ' - ' . $f['nom_fournisseur'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un fournisseur.</div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="1"><?= $reception ? $reception['observation'] : '' ?></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="taux_tva" class="form-label">Taux de TVA (%)</label>
                                <input type="number" class="form-control" id="taux_tva" name="taux_tva" step="0.01" min="0" value="<?= $tauxTVA ?>" required>
                                <div class="invalid-feedback">Veuillez saisir un taux de TVA valide.</div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="card-title">Détails des produits</h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="10%">Code</th>
                                            <th width="25%">Produit</th>
                                            <th width="25%">Désignation</th>
                                            <th width="10%">Quantité</th>
                                            <th width="10%">Prix unitaire</th>
                                            <th width="15%">Montant total</th>
                                            <?php if (!$reception): ?>
                                                <th width="5%">Action</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($reception): ?>
                                            <?php foreach ($lignesReception as $index => $ligne): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                    <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                    <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                                    <td class="text-end"><?= number_format($ligne['quantite'], 2, '.', '') ?></td>
                                                    <td class="text-end"><?= number_format($ligne['prix_unitaire'], 2, '.', '') ?></td>
                                                    <td class="text-end"><?= number_format($ligne['montant_total'], 2, '.', '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="row_1">
                                                <td>
                                                    <input type="text" class="form-control product-code" readonly>
                                                </td>
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
                                                    <input type="number" class="form-control total" name="products[1][montant_total]" step="0.01" readonly>
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
                            
                            <?php if (!$reception): ?>
                                <div class="text-center">
                                    <button type="button" id="addRowBtn" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Ajouter un produit
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mt-4">
                                <div class="col-md-6"></div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Total HT</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_ht" name="montant_ht" value="<?= number_format($totalHT, 2, '.', '') ?>" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Montant TVA</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_tva" name="montant_tva" value="<?= number_format($totalHT * ($tauxTVA / 100), 2, '.', '') ?>" readonly>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Total TTC</th>
                                            <td>
                                                <input type="number" class="form-control" id="montant_ttc" name="montant_ttc" value="<?= number_format($totalHT * (1 + $tauxTVA / 100), 2, '.', '') ?>" readonly>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/factures/factures.list'">
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
            option.dataset.code = product.code_produit;
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
    
    // Recalculer les totaux généraux
    calculateGrandTotal();
}

// Fonction pour calculer les totaux généraux
function calculateGrandTotal() {
    let totalHT = 0;
    const totals = document.querySelectorAll('.total');
    
    totals.forEach(input => {
        totalHT += parseFloat(input.value) || 0;
    });
    
    const tauxTVA = parseFloat(document.getElementById('taux_tva').value) || 0;
    const montantTVA = totalHT * (tauxTVA / 100);
    const totalTTC = totalHT + montantTVA;
    
    document.getElementById('montant_ht').value = totalHT.toFixed(2);
    document.getElementById('montant_tva').value = montantTVA.toFixed(2);
    document.getElementById('montant_ttc').value = totalTTC.toFixed(2);
}

let rowCount = 1;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$reception): ?>
    // Charger les produits pour la première ligne
    const firstProductSelect = document.querySelector('#row_1 .product-select');
    if (firstProductSelect) {
        loadProducts(firstProductSelect);
        
        // Écouter les changements sur le select de produit
        firstProductSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const row = this.closest('tr');
            const codeInput = row.querySelector('.product-code');
            
            if (selectedOption && selectedOption.dataset.code) {
                codeInput.value = selectedOption.dataset.code;
            } else {
                codeInput.value = '';
            }
        });
    }
    
    // Ajouter une nouvelle ligne
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            rowCount++;
            const newRow = `
                <tr id="row_${rowCount}">
                    <td>
                        <input type="text" class="form-control product-code" readonly>
                    </td>
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
                        <input type="number" class="form-control total" name="products[${rowCount}][montant_total]" step="0.01" readonly>
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
            
            // Écouter les changements sur le select de produit
            newSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const row = this.closest('tr');
                const codeInput = row.querySelector('.product-code');
                
                if (selectedOption && selectedOption.dataset.code) {
                    codeInput.value = selectedOption.dataset.code;
                } else {
                    codeInput.value = '';
                }
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
                calculateGrandTotal();
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
        
        // Recalculer les totaux si le taux de TVA change
        if (e.target.id === 'taux_tva') {
            calculateGrandTotal();
        }
    });
    <?php endif; ?>
    
    // Mise à jour de la date d'échéance en fonction du fournisseur sélectionné
    const fournisseurSelect = document.getElementById('id_fournisseur');
    if (fournisseurSelect) {
        fournisseurSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.delai) {
                const delai = parseInt(selectedOption.dataset.delai) || 0;
                const dateFacture = new Date(document.getElementById('date_facture').value);
                if (!isNaN(dateFacture.getTime())) {
                    dateFacture.setDate(dateFacture.getDate() + delai);
                    const dateEcheance = dateFacture.toISOString().split('T')[0];
                    document.getElementById('date_echeance').value = dateEcheance;
                }
            }
        });
    }
    
    // Mise à jour de la date d'échéance si la date de facture change
    const dateFactureInput = document.getElementById('date_facture');
    if (dateFactureInput) {
        dateFactureInput.addEventListener('change', function() {
            const delai = <?= $fournisseur ? $fournisseur['delai_paiement'] : 30 ?>;
            const dateFacture = new Date(this.value);
            if (!isNaN(dateFacture.getTime())) {
                dateFacture.setDate(dateFacture.getDate() + delai);
                const dateEcheance = dateFacture.toISOString().split('T')[0];
                document.getElementById('date_echeance').value = dateEcheance;
            }
        });
    }
    
    // Validation du formulaire
    const factureForm = document.getElementById('factureForm');
    if (factureForm) {
        factureForm.addEventListener('submit', function(event) {
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
