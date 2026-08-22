<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Budget de Recette</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Budget de Recette</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Budgets de Recette</h5>

                        <!-- Form to add a new budget recette structure -->
                        <form id="addBudgetRecetteForm" action="controller/create_budget_recette.php" method="POST">
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation</label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                            </div>
                            <div class="mb-3">
                                <label for="annee" class="form-label">Année</label>
                                <input type="text" class="form-control" id="annee" name="annee" required>
                            </div>
                            <div class="mb-3">
                                <label for="solde_b_recette" class="form-label">Solde Initial</label>
                                <input type="number" step="0.01" class="form-control" id="solde_b_recette" name="solde_b_recette" required>
                            </div>
                            <div class="mb-3">
                                <label for="Structure_idStructure" class="form-label">Structure</label>
                                <select class="form-select" id="Structure_idStructure" name="Structure_idStructure" required>
                                    <option value="">Sélectionner une structure</option>
                                    <?php foreach ($structures as $structure): ?>
                                        <?php
                                        // Check permission for each structure
                                        $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                        if ($ver1->fetch()):
                                        ?>
                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btnModSave ladda-button" data-style="zoom-out">Ajouter</button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>