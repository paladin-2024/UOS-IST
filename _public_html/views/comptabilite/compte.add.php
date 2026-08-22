<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Compte</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Compte</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Comptes</h5>

                        <!-- Form to add a new account -->
    <form id="addAccountForm" action="controller/create_compte.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="numeroCompte" class="form-label">Numéro de Compte <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="numeroCompte" name="numeroCompte" required>
            </div>
            <div class="col-md-6">
                <label for="intituleCompte" class="form-label">Intitulé du compte <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="intituleCompte" name="intituleCompte" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="typeCompte" class="form-label">Type de Compte <span class="text-danger">*</span></label>
                <select class="form-select" id="typeCompte" name="typeCompte" required>
                    <option value="">Sélectionner un type de compte</option>
                    <option value="Actif">Actif</option>
                    <option value="Passif">Passif</option>
                    <option value="Charges">Charges</option>
                    <option value="Produits">Produits</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="classeCompte" class="form-label">Classe du Compte <span class="text-danger">*</span></label>
                <select class="form-select" id="classeCompte" name="classeCompte" required>
                    <option value="">Sélectionner une classe</option>
                    <option value="1">1ère classe</option>
                    <option value="2">2ème classe</option>
                    <option value="3">3ème classe</option>
                    <option value="4">4ème classe</option>
                    <option value="5">5ème classe</option>
                    <option value="6">6ème classe</option>
                    <option value="7">7ème classe</option>
                    <option value="8">8ème classe</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="structureId" class="form-label">Structure <span class="text-danger">*</span></label>
            <select class="form-select" id="structureId" name="structureId" required>
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