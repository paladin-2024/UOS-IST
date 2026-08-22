<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la réception
$receptionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receptionId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Réception non trouvée'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la réception
$queryReception = "SELECT rf.*, f.nom_fournisseur, f.code_fournisseur 
                  FROM reception_fournisseur rf 
                  JOIN fournisseur f ON rf.id_fournisseur = f.id_fournisseur 
                  WHERE rf.id_reception = :id";
$stmtReception = $db->prepare($queryReception);
$stmtReception->bindParam(':id', $receptionId, PDO::PARAM_INT);
$stmtReception->execute();
$reception = $stmtReception->fetch(PDO::FETCH_ASSOC);

if (!$reception) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Réception non trouvée'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

// Vérifier si la réception est en état "En cours"
if ($reception['etat'] !== 'En cours') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Modification impossible',
            text: 'Cette réception ne peut pas être modifiée car elle n\'est pas en état \"En cours\".'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.view&id=$receptionId';
        });
    </script>";
    exit;
}

// Récupération des lignes de la réception
$queryLignes = "SELECT lr.*, p.code_produit, p.libelle_produit, p.est_peremption_suivi 
                FROM ligne_reception_fournisseur lr 
                JOIN produit p ON lr.id_produit = p.id_produit 
                WHERE lr.id_reception = :id_reception
                ORDER BY p.libelle_produit";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_reception', $receptionId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dépôts actifs
$queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmtDepots = $db->prepare($queryDepots);
$stmtDepots->execute();
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Récupération des produits pour l'ajout de produits supplémentaires
$queryProduits = "SELECT p.id_produit, p.code_produit, p.libelle_produit, p.est_peremption_suivi 
                 FROM produit p 
                 WHERE p.actif = 1 
                 ORDER BY p.libelle_produit";
$stmtProduits = $db->prepare($queryProduits);
$stmtProduits->execute();
$produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER LA RÉCEPTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/receptions/receptions.list">Réceptions</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Modification de la réception N° <?= htmlspecialchars($reception['numero_reception']) ?>
                        </h5>

                        <form id="receptionForm" action="controller/update_reception.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="id_reception" value="<?= $receptionId ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="numero_reception" class="form-label">Numéro de réception</label>
                                        <input type="text" class="form-control" id="numero_reception" name="numero_reception" value="<?= htmlspecialchars($reception['numero_reception']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="date_reception" class="form-label">Date de réception</label>
                                        <input type="date" class="form-control" id="date_reception" name="date_reception" value="<?= $reception['date_reception'] ?>" required>
                                        <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="id_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="id_depot" name="id_depot" required>
                                            <option value="">Sélectionner un dépôt</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>" <?= $depot['id_depot'] == $reception['id_depot'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($depot['libelle_depot']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Veuillez sélectionner un dépôt.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="fournisseur" class="form-label">Fournisseur</label>
                                        <input type="text" class="form-control" id="fournisseur" value="<?= htmlspecialchars($reception['code_fournisseur'] . ' - ' . $reception['nom_fournisseur']) ?>" readonly>
                                        <input type="hidden" name="id_fournisseur" value="<?= $reception['id_fournisseur'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="reference_bl" class="form-label">Référence BL</label>
                                        <input type="text" class="form-control" id="reference_bl" name="reference_bl" value="<?= htmlspecialchars($reception['reference_bl'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="observation" class="form-label">Observation</label>
                                        <textarea class="form-control" id="observation" name="observation" rows="1"><?= htmlspecialchars($reception['observation'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Produits de la réception -->
                            <h5 class="card-title">Produits de la réception</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="productTable">
                                    <thead>
                                        <tr>
                                            <th width="10%">Code</th>
                                            <th width="20%">Produit</th>
                                            <th width="15%">Quantité</th>
                                            <th width="15%">Prix unitaire</th>
                                            <th width="15%">N° Lot</th>
                                            <th width="15%">Date péremption</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lignes as $index => $ligne): ?>
                                            <tr id="row_<?= $index ?>">
                                                <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                <td>
                                                    <input type="hidden" name="lignes[<?= $index ?>][id_ligne_reception]" value="<?= $ligne['id_ligne_reception'] ?>">
                                                    <input type="hidden" name="lignes[<?= $index ?>][id_produit]" value="<?= $ligne['id_produit'] ?>">
                                                    <input type="number" class="form-control quantity" name="lignes[<?= $index ?>][quantite]" value="<?= $ligne['quantite'] ?>" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price" name="lignes[<?= $index ?>][prix_unitaire]" value="<?= $ligne['prix_unitaire'] ?>" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="lignes[<?= $index ?>][numero_lot]" value="<?= htmlspecialchars($ligne['numero_lot']) ?>" required>
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control" name="lignes[<?= $index ?>][date_peremption]" value="<?= $ligne['date_peremption'] ?>" <?= $ligne['est_peremption_suivi'] ? 'required' : '' ?>>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section pour ajouter des produits supplémentaires -->
                            <div class="mt-4">
                                <h5 class="card-title">Ajouter des produits</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="additionalProductTable">
                                        <thead>
                                            <tr>
                                                <th width="30%">Produit</th>
                                                <th width="15%">Quantité</th>
                                                <th width="15%">Prix unitaire</th>
                                                <th width="15%">N° Lot</th>
                                                <th width="15%">Date péremption</th>
                                                <th width="10%">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="additional_row_0">
                                                <td>
                                                    <select class="form-select product-select" name="produits_additionnels[0][id_produit]">
                                                        <option value="">Sélectionner un produit</option>
                                                        <?php foreach ($produits as $produit): ?>
                                                            <option value="<?= $produit['id_produit'] ?>" 
                                                                    data-peremption="<?= $produit['est_peremption_suivi'] ?>">
                                                                <?= htmlspecialchars($produit['code_produit'] . ' - ' . $produit['libelle_produit']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="produits_additionnels[0][designation]" class="designation-input" value="">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control quantity" name="produits_additionnels[0][quantite]" step="0.01" min="0.01">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price" name="produits_additionnels[0][prix_unitaire]" step="0.01" min="0.01">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="produits_additionnels[0][numero_lot]">
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control date-peremption" name="produits_additionnels[0][date_peremption]">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" id="addProductBtn" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Ajouter un produit
                                    </button>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/receptions/receptions.view&id=<?= $receptionId ?>'">
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des variables
    let additionalRowCount = 1;
    
    // Fonction pour ajouter une nouvelle ligne de produit supplémentaire
    const addAdditionalProduct = () => {
        const tbody = document.querySelector('#additionalProductTable tbody');
        const newRow = document.createElement('tr');
        newRow.id = `additional_row_${additionalRowCount}`;
        
        newRow.innerHTML = `
            <td>
                <select class="form-select product-select" name="produits_additionnels[${additionalRowCount}][id_produit]">
                    <option value="">Sélectionner un produit</option>
                    <?php foreach ($produits as $produit): ?>
                        <option value="<?= $produit['id_produit'] ?>" 
                                data-peremption="<?= $produit['est_peremption_suivi'] ?>">
                            <?= htmlspecialchars($produit['code_produit'] . ' - ' . $produit['libelle_produit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="produits_additionnels[${additionalRowCount}][designation]" class="designation-input" value="">
            </td>
            <td>
                <input type="number" class="form-control quantity" name="produits_additionnels[${additionalRowCount}][quantite]" step="0.01" min="0.01">
            </td>
            <td>
                <input type="number" class="form-control price" name="produits_additionnels[${additionalRowCount}][prix_unitaire]" step="0.01" min="0.01">
            </td>
            <td>
                <input type="text" class="form-control" name="produits_additionnels[${additionalRowCount}][numero_lot]">
            </td>
            <td>
                <input type="date" class="form-control date-peremption" name="produits_additionnels[${additionalRowCount}][date_peremption]">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(newRow);
        
        // Ajouter l'événement pour supprimer cette ligne
        newRow.querySelector('.remove-row').addEventListener('click', function() {
            newRow.remove();
        });
        
        // Gérer la date de péremption en fonction du produit sélectionné
        const productSelect = newRow.querySelector('.product-select');
        const datePeremption = newRow.querySelector('.date-peremption');
        
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) return;
            
            const requiresExpiry = selectedOption.getAttribute('data-peremption') === '1';
            
            if (requiresExpiry) {
                datePeremption.setAttribute('required', 'required');
            } else {
                datePeremption.removeAttribute('required');
            }
            
            // Mettre à jour le champ de désignation caché
            const designationInput = this.parentElement.querySelector('.designation-input');
            designationInput.value = selectedOption.textContent.trim();
        });
        
        // Initialiser Select2 pour le nouveau sélecteur de produit
        if (typeof $.fn.select2 !== 'undefined') {
            $(productSelect).select2({
                width: '100%',
                placeholder: 'Sélectionner un produit',
                allowClear: true
            });
        }
        
        additionalRowCount++;
    };
    
    // Ajouter un événement pour le bouton d'ajout de produit
    document.getElementById('addProductBtn').addEventListener('click', function() {
        addAdditionalProduct();
    });
    
    // Supprimer une ligne de produit (pour les lignes existantes)
    document.querySelectorAll('.remove-row').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const table = row.closest('table');
            
            // Si c'est une ligne de produit existante, vérifier qu'il reste au moins une ligne
            if (table.id === 'productTable' && table.querySelectorAll('tbody tr').length <= 1) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Vous devez conserver au moins un produit dans la réception.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            row.remove();
        });
    });
    
    // Gérer la date de péremption en fonction du produit sélectionné (pour les lignes existantes)
    document.querySelectorAll('.product-select').forEach(select => {
        select.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) return;
            
            const datePeremption = this.closest('tr').querySelector('.date-peremption');
            const requiresExpiry = selectedOption.getAttribute('data-peremption') === '1';
            
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
    
    // Validation du formulaire
    const form = document.getElementById('receptionForm');
    form.addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            
            // Afficher un message d'erreur
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
        
        this.classList.add('was-validated');
    });
    
    // Initialiser Select2 pour les sélecteurs de produits
    if (typeof $.fn.select2 !== 'undefined') {
        $('.product-select').select2({
            width: '100%',
            placeholder: 'Sélectionner un produit',
            allowClear: true
        });
        
        $('#id_depot').select2({
            width: '100%',
            placeholder: 'Sélectionner un dépôt',
            allowClear: true
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>

