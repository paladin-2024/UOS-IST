<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Groupe de Dépense</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Groupe de Dépense</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Groupes de Dépense</h5>

                        <!-- Form to add a new expense group -->
                        <form id="addGroupeDepenseForm" action="controller/create_groupe_depense.php" method="POST">
                            <div class="mb-3">
                                <label for="designationGD" class="form-label">Désignation</label>
                                <input type="text" class="form-control" id="designationGD" name="designationGD" required>
                            </div>
                            <div class="mb-3">
                                <label for="soldeGD" class="form-label">Solde Initial</label>
                                <input type="number" step="0.01" class="form-control" id="soldeGD" name="soldeGD" required>
                            </div>
                            <div class="mb-3">
                                <label for="budgetDepenseStructureId" class="form-label">Budget de Dépense</label>
                                <select class="form-select" id="budgetDepenseStructureId" name="budgetDepenseStructureId" required>
                                    <option value="">Sélectionner un budget</option>
                                        <?php
                                        
                                            $budgets = $structureModel->getBudgetsByUser($userId);
                                            foreach ($budgets as $budget):
                                        ?>
                                            <option value="<?= $budget['idBudget_depense_structure'] ?>"><?= $budget['designation'] ?></option>
                                        <?php endforeach;?>
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