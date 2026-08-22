<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();
$accounts = $structureModel->getComptes();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

$comptesComptables = $structureModel->getComptesComptablesByUserAccess($userId); // Use the new method for comptes comptables

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Client</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Client</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Clients</h5>

                        <!-- Form to add a new client -->
                        <form id="addClientForm" action="controller/create_client.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="noms" class="form-label">Noms <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="noms" name="noms" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="adresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="adresse" name="adresse" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="telephone" name="telephone" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="solde" class="form-label">Solde <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="solde" name="solde" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="structureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="structureId" name="structureId" required onchange="fetchAccounts(this.value)">
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
                            <div class="mb-3">
                                <label for="compteId" class="form-label">Compte de la comptabilité <span class="text-danger">*</span></label>
                                <select class="form-select" id="compteId" name="compteId">
                                    <option value="">Sélectionner un compte</option>
                                    <?php foreach ($comptesComptables as $compte) {
                                        if ($compte['classeCompte'] == 4) {
                                            ?>
                                            <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte'] . ' ' . $compte['intituleCompte'] ?></option>
                                        <?php }
                                    } ?>
                                </select>
                                <div id="spinner" class="spinner" style="display: none;"></div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Ajouter Client
                            </button>
                        </form>

                        

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>