<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter une Banque</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Banque</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Banques</h5>

                        <!-- Form to add a new bank -->
    <form id="addBanqueForm" action="controller/create_banque.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="designation" name="designation" required>
            </div>
            <div class="col-md-6">
                <label for="numeroCompte" class="form-label">Numéro de Compte <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="numeroCompte" name="numeroCompte" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="solde" class="form-label">Solde Initial (USD)</label>
                <input type="number" step="0.01" class="form-control" id="solde" name="solde">
            </div>
            <div class="col-md-6">
                <label for="compteId" class="form-label">Compte Comptable <span class="text-danger">*</span></label>
                <select class="form-select" id="compteId" name="compteId" required>
                    <option value="">Sélectionner un compte</option>
                    <?php
                    $comptes = $structureModel->getAuthorizedComptes($userId);
                    foreach ($comptes as $compte): ?>
                        <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte']." : ".$compte['intituleCompte'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Ajouter Banque
        </button>
    </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>