<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

$comptesComptables = $structureModel->getComptesComptablesByUserAccess($userId); // Use the new method for comptes comptables


?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter une Facture Client</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Facture</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Factures</h5>

                        <!-- Form to add a new client invoice -->
                        <!-- Form to add a new client invoice -->
<form id="addInvoiceForm" action="controller/create_invoice.php" method="POST">
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="clientId" class="form-label">Client <span class="text-danger">*</span></label>
            <select class="form-select" id="clientId" name="clientId" required>
                <option value="">Sélectionner un client</option>
                <?php 
                // Fetch clients based on user permissions
                $clients = $structureModel->getClientsByUserAccess($userId);
                foreach ($clients as $client): ?>
                    <option value="<?= $client['idClient'] ?>"><?= $client['noms'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="dateFacture" class="form-label">Date de Facture <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="dateFacture" name="dateFacture" required>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
            <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
        </div>
        <div class="col-md-6">
            <label for="motif" class="form-label">Motif</label>
            <input type="text" class="form-control" id="motif" name="motif">
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="numeroFacture" class="form-label">Numéro de Facture <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="numeroFacture" name="numeroFacture" required>
        </div>
        <div class="col-md-6">
            <label for="compteId" class="form-label">Compte de produit <span class="text-danger">*</span></label>
            <select class="form-select" id="compteId" name="compteId">
                <option value="">Sélectionner un compte</option>
                <?php foreach ($comptesComptables as $compte) {
                    if ($compte['classeCompte'] == 7) {
                        ?>
                        <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte'] . ' ' . $compte['intituleCompte'] ?></option>
                    <?php }
                } ?>
            </select>
        </div>
    </div>
    <!-- Hidden field for default status -->
    <input type="hidden" id="statut" name="statut" value="Non Paye">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Ajouter Facture
    </button>
</form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>