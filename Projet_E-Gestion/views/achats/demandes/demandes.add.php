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

// Génération d'un numéro de demande automatique
function generateDemandeNumber($db) {
    $year = date('y'); // Année courante en 2 chiffres
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_demande, 6) AS UNSIGNED)) as max_num 
              FROM demande_prix 
              WHERE numero_demande LIKE 'DPX" . $year . "%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'DPX' . $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

$nextDemandeNumber = generateDemandeNumber($db);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>NOUVELLE DEMANDE DE PRIX</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/demandes.list">Demandes de prix</a></li>
                <li class="breadcrumb-item active">Nouvelle demande</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la demande</h5>

                        <form id="demandeForm" action="controller/create_demande_prix.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-4">
                                <label for="numero_demande" class="form-label">Numéro de demande</label>
                                <input type="text" class="form-control" id="numero_demande" name="numero_demande" value="<?= $nextDemandeNumber ?>" readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_demande" class="form-label">Date de demande</label>
                                <input type="date" class="form-control" id="date_demande" name="date_demande" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="id_fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="id_fournisseur" name="id_fournisseur" required>
                                    <option value="" selected disabled>Sélectionner un fournisseur</option>
                                    <?php foreach ($fournisseurs as $fournisseur): ?>
                                        <option value="<?= $fournisseur['id_fournisseur'] ?>">
                                            <?= $fournisseur['code_fournisseur'] ?> - <?= htmlspecialchars($fournisseur['nom_fournisseur']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un fournisseur.</div>
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
                                            <th width="50%">Produit</th>
                                            <th width="20%">Quantité</th>
                                            <th width="25%">Désignation</th>
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
                                                <input type="text" class="form-control designation" name="products[1][designation]" required>
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
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='achats/demandes.list'">
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
            option.setAttribute('data-designation', product.libelle_produit);
            selectElement.appendChild(option);
        });
    })
    .catch(error => {
        console.error('Erreur lors du chargement des produits:', error);
        selectElement.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
    });
}

let rowCount = 1;

// Attendre que le DOM soit entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
    // Charger les produits pour la première ligne
    const firstProductSelect = document.querySelector('#row_1 .product-select');
    if (firstProductSelect) {
        loadProducts(firstProductSelect);
    }
    
    // Initialiser Select2 pour le premier select de produit
    $(firstProductSelect).select2({
        width: '100%',
        placeholder: 'Sélectionner un produit',
        allowClear: true
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
                                                <select class="form-select product-select" name="products[${rowCount}][id_produit]" required>
                            <option value="" selected disabled>Sélectionner un produit</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control quantity" name="products[${rowCount}][quantite]" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="text" class="form-control designation" name="products[${rowCount}][designation]" required>
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
    
    // Remplir automatiquement la désignation lors de la sélection d'un produit
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-select')) {
            const row = e.target.closest('tr');
            const designationInput = row.querySelector('.designation');
            const selectedOption = e.target.options[e.target.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.designation) {
                designationInput.value = selectedOption.dataset.designation;
            }
        }
    });
    
    // Validation du formulaire
    const demandeForm = document.getElementById('demandeForm');
    if (demandeForm) {
        demandeForm.addEventListener('submit', function(event) {
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
