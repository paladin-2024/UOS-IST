<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des catégories
$queryCategories = "SELECT * FROM categorie_produit ORDER BY libelle_categorie";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Récupération des unités de stockage
$queryUnites = "SELECT * FROM unite_mesure ORDER BY libelle_unite";
$stmtUnites = $db->prepare($queryUnites);
$stmtUnites->execute();
$unites = $stmtUnites->fetchAll(PDO::FETCH_ASSOC);

// Récupération des comptes comptables (CORRECTION ICI)
// Pour les produits en stock, nous utilisons les comptes de la classe 3
// Pour les services, nous utilisons les comptes de la classe 7
$queryComptes = "SELECT * FROM compte_comptable 
                WHERE (classe_compte = 3 OR classe_compte = 7) 
                ORDER BY numero_compte";
$stmtComptes = $db->prepare($queryComptes);
$stmtComptes->execute();
$comptes = $stmtComptes->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour générer un code produit automatiquement
function generateProductCode($db) {
    $query = "SELECT MAX(CAST(SUBSTRING(code_produit, 4) AS UNSIGNED)) as max_num FROM produit WHERE code_produit LIKE 'PRD%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'PRD' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

$defaultProductCode = generateProductCode($db);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>AJOUTER UN PRODUIT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="produits/produits.list">Produits</a></li>
                <li class="breadcrumb-item active">Ajouter</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du produit</h5>

                        <form action="controller/create_produit.php" method="POST" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-6">
                                <label for="code_produit" class="form-label">Code produit</label>
                                <input type="text" class="form-control" id="code_produit" name="code_produit" value="<?= $defaultProductCode ?>" required>
                                <div class="invalid-feedback">Veuillez saisir un code produit.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="libelle_produit" class="form-label">Libellé du produit</label>
                                <input type="text" class="form-control" id="libelle_produit" name="libelle_produit" required>
                                <div class="invalid-feedback">Veuillez saisir un libellé pour le produit.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_categorie" class="form-label">Catégorie</label>
                                <select class="form-select" id="id_categorie" name="id_categorie" required>
                                    <option value="" selected disabled>Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une catégorie.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type_produit" class="form-label">Type de produit</label>
                                <select class="form-select" id="type_produit" name="type_produit" required>
                                    <option value="" selected disabled>Sélectionner un type</option>
                                    <option value="Produit fini">Produit fini</option>
                                    <option value="Matière première">Matière première</option>
                                    <option value="Service">Service</option>
                                    <option value="Consommable">Consommable</option>
                                    <option value="Medicament">Médicament</option>
                                    <option value="MEG">MEG</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un type de produit.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="famille" class="form-label">Famille</label>
                                <input type="text" class="form-control" id="famille" name="famille">
                                <div class="form-text">Optionnel - Pour regrouper les produits par famille</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_unite_stockage" class="form-label">Unité de stockage</label>
                                <select class="form-select" id="id_unite_stockage" name="id_unite_stockage" required>
                                    <option value="" selected disabled>Sélectionner une unité</option>
                                    <?php foreach ($unites as $unite): ?>
                                        <option value="<?= $unite['id_unite'] ?>"><?= htmlspecialchars($unite['libelle_unite']) ?> (<?= htmlspecialchars($unite['code_unite']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une unité de stockage.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_unite_vente" class="form-label">Unité de vente</label>
                                <select class="form-select" id="id_unite_vente" name="id_unite_vente" required>
                                    <option value="" selected disabled>Sélectionner une unité</option>
                                    <?php foreach ($unites as $unite): ?>
                                        <option value="<?= $unite['id_unite'] ?>"><?= htmlspecialchars($unite['libelle_unite']) ?> (<?= htmlspecialchars($unite['code_unite']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une unité de vente.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="conditionnement" class="form-label">Conditionnement</label>
                                <input type="number" step="0.01" min="1" class="form-control" id="conditionnement" name="conditionnement" value="1.00" required>
                                <div class="form-text">Nombre d'unités de stockage par unité de vente</div>
                                <div class="invalid-feedback">Veuillez saisir le conditionnement.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="marge_beneficiaire" class="form-label">Marge bénéficiaire (%)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="marge_beneficiaire" name="marge_beneficiaire" value="0.00">
                                <div class="form-text">Pourcentage de marge sur le prix d'achat</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="poids" class="form-label">Poids (kg)</label>
                                <input type="number" step="0.001" min="0" class="form-control" id="poids" name="poids">
                                <div class="form-text">Optionnel - Pour le calcul des expéditions</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="volume" class="form-label">Volume (m³)</label>
                                <input type="number" step="0.001" min="0" class="form-control" id="volume" name="volume">
                                <div class="form-text">Optionnel - Pour le calcul du stockage</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_compte_comptable" class="form-label">Compte comptable</label>
                                <select class="form-select" id="id_compte_comptable" name="id_compte_comptable">
                                    <option value="" selected disabled>Sélectionner un compte</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id_compte'] ?>" data-classe="<?= $compte['classe_compte'] ?>">
                                            <?= htmlspecialchars($compte['numero_compte']) ?> - <?= htmlspecialchars($compte['intitule_compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un compte comptable.</div>
                                <div class="form-text">Pour les produits stockables, sélectionnez un compte de classe 3. Pour les services, sélectionnez un compte de classe 7.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="image_produit" class="form-label">Image du produit</label>
                                <input type="file" class="form-control" id="image_produit" name="image_produit" accept="image/*">
                                <div class="form-text">Optionnel - Format JPG, PNG (max 2MB)</div>
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_stock_suivi" name="est_stock_suivi" value="1" checked>
                                    <label class="form-check-label" for="est_stock_suivi">Suivi du stock</label>
                                </div>
                                <div class="form-text">Activez cette option pour gérer le stock de ce produit</div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_peremption_suivi" name="est_peremption_suivi" value="1">
                                    <label class="form-check-label" for="est_peremption_suivi">Suivi des dates de péremption</label>
                                </div>
                                <div class="form-text">Activez cette option pour les produits périssables</div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" checked>
                                    <label class="form-check-label" for="actif">Produit actif</label>
                                </div>
                                <div class="form-text">Désactivez pour masquer le produit sans le supprimer</div>
                            </div>
                            
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Aperçu de l'image</h5>
                                        <div class="text-center">
                                            <img id="preview" src="./assets/img/no-image.png" alt="Aperçu de l'image" class="img-fluid" style="max-height: 200px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='produits/produits.list'">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script>
    // Prévisualisation de l'image
    document.getElementById('image_produit').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(event) {
                document.getElementById('preview').src = event.target.result;
            }
            
            reader.readAsDataURL(file);
        }
    });
    
    // Filtrer les comptes comptables en fonction du type de produit
    document.getElementById('type_produit').addEventListener('change', function() {
        const typeProduit = this.value;
        const compteSelect = document.getElementById('id_compte_comptable');
        const options = compteSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (option.value === "") return; // Ignorer l'option par déf
            const classe = option.getAttribute('data-classe');
            
            // Pour les services, montrer les comptes de classe 7
            // Pour les autres types de produits, montrer les comptes de classe 3
            if (typeProduit === 'Service') {
                option.style.display = (classe === '7') ? '' : 'none';
            } else {
                option.style.display = (classe === '3') ? '' : 'none';
            }
        });
        
        // Réinitialiser la sélection
        compteSelect.value = "";
    });
    
    // Validation du formulaire
    (function() {
        'use strict';
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

<?php include "./views/include/footer.php"; ?>
