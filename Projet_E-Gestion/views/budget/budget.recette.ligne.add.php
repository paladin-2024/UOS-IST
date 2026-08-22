<?php
include "./views/include/header.php";

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

$structureModel = new Structure();
$groupesRecette = $structureModel->getGroupesRecetteByUserAccess($userId); // Use the new method for groupes de recettes
$comptesComptables = $structureModel->getComptesComptablesByUserAccess($userId); // Use the new method for comptes comptables

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter une Ligne de Recette</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Ligne de Recette</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Lignes de Recette</h5>

                        <!-- Form to add a new ligne de recette -->
                        <form id="addLigneRecetteForm" action="controller/create_ligne_recette.php" method="POST">
                            <div class="mb-3">
                                <label for="codeLigne" class="form-label">Code Ligne</label>
                                <input type="text" class="form-control" id="codeLigne" name="codeLigne" required>
                            </div>
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation</label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                            </div>
                            <div class="mb-3">
                                <label for="montant" class="form-label">Montant</label>
                                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                            </div>
                            <div class="mb-3">
                                <input type="hidden" step="0.01" value="0.00" class="form-control" id="solde" name="solde" required>
                            </div>
                            <div class="mb-3">
                                <label for="Groupe_recette_structure_idGroupe_recette_structure" class="form-label">Groupe de Recette</label>
                                <select class="form-select" id="Groupe_recette_structure_idGroupe_recette_structure" name="Groupe_recette_structure_idGroupe_recette_structure" required>
                                    <option value="">Sélectionner un groupe de recette</option>
                                    <?php foreach ($groupesRecette as $groupe): ?>
                                        <option value="<?= $groupe['idGroupe_recette_structure'] ?>"><?= $groupe['designationGR'].' / '.$groupe['budgetDesignation'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="Compte_idCompte" class="form-label">Compte de produit</label>
                                <select class="form-select" id="Compte_idCompte" name="Compte_idCompte" required>
                                    <option value="">Sélectionner un compte de produit</option>
                                    <?php foreach ($comptesComptables as $compte){ 
                                        if($compte['classeCompte']==7){
                                        ?>
                                        <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte'].' '.$compte['intituleCompte'] ?></option>
                                    <?php } } ?>
                                </select>
                            </div>
                            <button type="submit" class="btnModSave ladda-button" data-style="zoom-out">Ajouter ligne de récette</button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>