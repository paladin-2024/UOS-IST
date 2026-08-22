<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du produit
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Produit non trouvé'
        }).then(() => {
            window.location.href = '../produits/produits.list';
        });
    </script>";
    exit;
}

// Récupération des détails du produit
$query = "SELECT * FROM produit WHERE id_produit = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $productId, PDO::PARAM_INT);
$stmt->execute();
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Produit non trouvé'
        }).then(() => {
            window.location.href = '../produits/produits.list';
        });
    </script>";
    exit;
}

// Récupération des catégories
$queryCategories = "SELECT * FROM categorie_produit ORDER BY libelle_categorie";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Récupération des unités de mesure
$queryUnites = "SELECT * FROM unite_mesure WHERE actif = 1 ORDER BY libelle_unite";
$stmtUnites = $db->prepare($queryUnites);
$stmtUnites->execute();
$unites = $stmtUnites->fetchAll(PDO::FETCH_ASSOC);

// Récupération des comptes comptables
$queryComptes = "SELECT * FROM compte_comptable ORDER BY numero_compte";
$stmtComptes = $db->prepare($queryComptes);
$stmtComptes->execute();
$comptes = $stmtComptes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des fournisseurs du produit
$queryFournisseurs = "SELECT pf.*, f.nom_fournisseur 
                      FROM produit_fournisseur pf
                      JOIN fournisseur f ON pf.id_fournisseur = f.id_fournisseur
                      WHERE pf.id_produit = :id_produit
                      ORDER BY pf.est_fournisseur_principal DESC";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->bindParam(':id_produit', $productId, PDO::PARAM_INT);
$stmtFournisseurs->execute();
$fournisseursProduit = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les fournisseurs actifs
$queryAllFournisseurs = "SELECT * FROM fournisseur WHERE actif = 1 ORDER BY nom_fournisseur";
$stmtAllFournisseurs = $db->prepare($queryAllFournisseurs);
$stmtAllFournisseurs->execute();
$allFournisseurs = $stmtAllFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Chemin de l'image actuelle
$imagePath = !empty($produit['image_produit']) 
    ? './uploads/produits/' . $produit['image_produit'] 
    : './assets/img/no-image.png';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFICATION D'UN PRODUIT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="produits/produits.list">Produits</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Modifier le produit: <?= htmlspecialchars($produit['libelle_produit']) ?>
                            <div class="float-end">
                                <a href="produits/produits.view&id=<?= $produit['id_produit'] ?>" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </h5>

                        <form action="controller/update_produit.php" method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="id_produit" value="<?= $produit['id_produit'] ?>">

                            <div class="col-md-4">
                                <label for="code_produit" class="form-label">Code produit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_produit" name="code_produit" value="<?= htmlspecialchars($produit['code_produit']) ?>" required>
                            </div>

                            <div class="col-md-8">
                                <label for="libelle_produit" class="form-label">Libellé <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="libelle_produit" name="libelle_produit" value="<?= htmlspecialchars($produit['libelle_produit']) ?>" required>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label for="id_categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_categorie" name="id_categorie" required>
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= $categorie['id_categorie'] ?>" <?= $produit['id_categorie'] == $categorie['id_categorie'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categorie['libelle_categorie']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="type_produit" class="form-label">Type de produit <span class="text-danger">*</span></label>
                                
                                <select class="form-select" id="type_produit" name="type_produit" required>
                                    <option value="" selected disabled>Sélectionner un type</option>
                                    <option value="Produit fini" <?= $produit['type_produit'] == 'Produit fini' ? 'selected' : '' ?>>Produit fini</option>
                                    <option value="Matière première" <?= $produit['type_produit'] == 'Matière première' ? 'selected' : '' ?>>Matière première</option>
                                    <option value="Service" <?= $produit['type_produit'] == 'Service' ? 'selected' : '' ?>>Service</option>
                                    <option value="Consommable" <?= $produit['type_produit'] == 'Consommable' ? 'selected' : '' ?>>Consommable</option>
                                    <option value="MEG" <?= $produit['type_produit'] == 'MEG' ? 'selected' : '' ?>>MEG</option>
                                    <option value="Médicament" <?= $produit['type_produit'] == 'Medicament' ? 'selected' : '' ?>>Médicament</option>
                                    <option value="Autre" <?= $produit['type_produit'] == 'Autre' ? 'selected' : '' ?>>Autre</option>

                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="famille" class="form-label">Famille</label>
                                <input type="text" class="form-control" id="famille" name="famille" value="<?= htmlspecialchars($produit['famille'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="id_unite_stockage" class="form-label">Unité de stockage <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_unite_stockage" name="id_unite_stockage" required>
                                    <option value="">Sélectionner une unité</option>
                                    <?php foreach ($unites as $unite): ?>
                                        <option value="<?= $unite['id_unite'] ?>" <?= $produit['id_unite_stockage'] == $unite['id_unite'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unite['libelle_unite']) ?> (<?= htmlspecialchars($unite['symbole_unite']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="id_unite_vente" class="form-label">Unité de vente <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_unite_vente" name="id_unite_vente" required>
                                    <option value="">Sélectionner une unité</option>
                                    <?php foreach ($unites as $unite): ?>
                                        <option value="<?= $unite['id_unite'] ?>" <?= $produit['id_unite_vente'] == $unite['id_unite'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unite['libelle_unite']) ?> (<?= htmlspecialchars($unite['symbole_unite']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="conditionnement" class="form-label">Conditionnement</label>
                                <input type="number" class="form-control" id="conditionnement" name="conditionnement" step="0.01" min="0.01" value="<?= $produit['conditionnement'] ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label for="marge_beneficiaire" class="form-label">Marge bénéficiaire (%)</label>
                                <input type="number" class="form-control" id="marge_beneficiaire" name="marge_beneficiaire" step="0.01" min="0" value="<?= $produit['marge_beneficiaire'] ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="id_compte_comptable" class="form-label">Compte comptable <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_compte_comptable" name="id_compte_comptable" required>
                                    <option value="">Sélectionner un compte</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id_compte'] ?>" <?= $produit['id_compte_comptable'] == $compte['id_compte'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['numero_compte']) ?> - <?= htmlspecialchars($compte['intitule_compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="poids" class="form-label">Poids (kg)</label>
                                <input type="number" class="form-control" id="poids" name="poids" step="0.001" min="0" value="<?= $produit['poids'] ?? '' ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="volume" class="form-label">Volume (m³)</label>
                                <input type="number" class="form-control" id="volume" name="volume" step="0.001" min="0" value="<?= $produit['volume'] ?? '' ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="image_produit" class="form-label">Image du produit</label>
                                <input type="file" class="form-control" id="image_produit" name="image_produit" accept="image/jpeg,image/png">
                                <small class="text-muted">Formats acceptés: JPG, PNG. Max: 2 Mo.</small>
                                <?php if (!empty($produit['image_produit'])): ?>
                                <div class="mt-2">
                                    <img src="<?= $imagePath ?>" alt="Image actuelle" class="img-thumbnail" style="max-height: 100px">
                                    <br>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="supprimer_image" name="supprimer_image">
                                        <label class="form-check-label" for="supprimer_image">
                                            Supprimer l'image actuelle
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="est_stock_suivi" name="est_stock_suivi" value="1" <?= $produit['est_stock_suivi'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_stock_suivi">Suivi du stock</label>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="est_peremption_suivi" name="est_peremption_suivi" value="1" <?= $produit['est_peremption_suivi'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_peremption_suivi">Suivi de la date de péremption</label>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" <?= $produit['actif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="actif">Produit actif</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3">Fournisseurs du produit</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="fournisseurs-table">
                                        <thead>
                                            <tr>
                                                <th>Fournisseur</th>
                                                <th>Prix d'achat</th>
                                                <th>Délai de livraison (jours)</th>
                                                <th>Principal</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fournisseursProduit as $index => $fournisseur): ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="fournisseur_id[]" value="<?= $fournisseur['id_fournisseur'] ?>">
                                                    <?= htmlspecialchars($fournisseur['nom_fournisseur']) ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="prix_achat[]" value="<?= $fournisseur['prix_achat'] ?>" step="0.01" min="0" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" name="delai_livraison[]" value="<?= $fournisseur['delai_livraison'] ?>" min="0">
                                                </td>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input fournisseur-principal" type="radio" name="fournisseur_principal" value="<?= $index ?>" <?= $fournisseur['est_fournisseur_principal'] ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-fournisseur">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5">
                                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addFournisseurModal">
                                                    <i class="bi bi-plus-circle"></i> Ajouter un fournisseur
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer les modifications
                                </button>
                                <a href="produits/produits.view&id=<?= $produit['id_produit'] ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un fournisseur -->
<div class="modal fade" id="addFournisseurModal" tabindex="-1" aria-labelledby="addFournisseurModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFournisseurModalLabel">Ajouter un fournisseur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addFournisseurForm">
                    <div class="mb-3">
                        <label for="fournisseur_select" class="form-label">Fournisseur</label>
                        <select class="form-select" id="fournisseur_select" required>
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($allFournisseurs as $fournisseur): ?>
                                <option value="<?= $fournisseur['id_fournisseur'] ?>" data-name="<?= htmlspecialchars($fournisseur['nom_fournisseur']) ?>">
                                    <?= htmlspecialchars($fournisseur['nom_fournisseur']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="prix_achat_add" class="form-label">Prix d'achat</label>
                        <input type="number" class="form-control" id="prix_achat_add" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="delai_livraison_add" class="form-label">Délai de livraison (jours)</label>
                        <input type="number" class="form-control" id="delai_livraison_add" min="0">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="fournisseur_principal_add">
                        <label class="form-check-label" for="fournisseur_principal_add">
                            Définir comme fournisseur principal
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="addFournisseurBtn">Ajouter</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Gestion des fournisseurs
        $('#addFournisseurBtn').click(function() {
            const fournisseurId = $('#fournisseur_select').val();
            const fournisseurName = $('#fournisseur_select option:selected').data('name');
            const prixAchat = $('#prix_achat_add').val();
            const delaiLivraison = $('#delai_livraison_add').val();
            const isPrincipal = $('#fournisseur_principal_add').prop('checked');
            
            if (!fournisseurId || !prixAchat) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez remplir tous les champs obligatoires'
                });
                return;
            }
            
            // Vérifier si ce fournisseur existe déjà dans le tableau
            let fournisseurExists = false;
            $('#fournisseurs-table tbody tr').each(function() {
                const existingId = $(this).find('input[name="fournisseur_id[]"]').val();
                if (existingId === fournisseurId) {
                    fournisseurExists = true;
                    return false;
                }
            });
            
            if (fournisseurExists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Ce fournisseur est déjà associé à ce produit'
                });
                return;
            }
            
            // Compter les fournisseurs actuels pour l'index
            const currentIndex = $('#fournisseurs-table tbody tr').length;
            
            // Créer une nouvelle ligne de tableau
            const newRow = `
                <tr>
                    <td>
                        <input type="hidden" name="fournisseur_id[]" value="${fournisseurId}">
                        ${fournisseurName}
                    </td>
                    <td>
                        <input type="number" class="form-control" name="prix_achat[]" value="${prixAchat}" step="0.01" min="0" required>
                    </td>
                    <td>
                        <input type="number" class="form-control" name="delai_livraison[]" value="${delaiLivraison}" min="0">
                    </td>
                    <td>
                        <div class="form-check">
                            <input class="form-check-input fournisseur-principal" type="radio" name="fournisseur_principal" value="${currentIndex}" ${isPrincipal ? 'checked' : ''}>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-fournisseur">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            // Ajouter la ligne au tableau
            $('#fournisseurs-table tbody').append(newRow);
            
            // Si le nouveau fournisseur est principal, décocher les autres
            if (isPrincipal) {
                $('#fournisseurs-table tbody tr').not(':last').find('.fournisseur-principal').prop('checked', false);
            }
            
            // Fermer la modal et réinitialiser le formulaire
            $('#addFournisseurModal').modal('hide');
            $('#addFournisseurForm')[0].reset();
        });
        
        // Supprimer un fournisseur
        $(document).on('click', '.remove-fournisseur', function() {
            const row = $(this).closest('tr');
            const isPrincipal = row.find('.fournisseur-principal').prop('checked');
            
            row.remove();
            
            // Réindexer les boutons radio
            $('#fournisseurs-table tbody tr').each(function(index) {
                $(this).find('.fournisseur-principal').val(index);
            });
            
            // Si on a supprimé le fournisseur principal, sélectionner le premier comme principal
            if (isPrincipal && $('#fournisseurs-table tbody tr').length > 0) {
                $('#fournisseurs-table tbody tr:first').find('.fournisseur-principal').prop('checked', true);
            }
        });
        
        // Validation du formulaire
        $('form').submit(function(e) {
            const code = $('#code_produit').val();
            const libelle = $('#libelle_produit').val();
            const categorie = $('#id_categorie').val();
            const typeProduit = $('#type_produit').val();
            const uniteStockage = $('#id_unite_stockage').val();
            const uniteVente = $('#id_unite_vente').val();
            const compteComptable = $('#id_compte_comptable').val();
            
            if (!code || !libelle || !categorie || !typeProduit || !uniteStockage || !uniteVente || !compteComptable) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez remplir tous les champs obligatoires'
                });
                return false;
            }
            
            return true;
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
