<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Budget de Dépense</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Budget de Dépense</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Budgets de Dépense</h5>

                        <!-- Form to add a new budget depense structure -->
    <form id="addBudgetDepenseForm" action="controller/create_budget_depense.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="designation" name="designation" required>
            </div>
            <div class="col-md-6">
                <label for="annee" class="form-label">Année <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="annee" name="annee" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="solde_b_depense" class="form-label">Solde Initial <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="solde_b_depense" name="solde_b_depense" required>
            </div>
            <div class="col-md-6">
                <label for="Structure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
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
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Ajouter
        </button>
    </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>