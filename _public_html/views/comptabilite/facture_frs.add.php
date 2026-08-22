<?php
include "./views/include/header.php";

$structureModel = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session
$suppliers = $structureModel->getFournisseursByUserAccess($userId);

$comptesComptables = $structureModel->getComptesComptablesByUserAccess($userId); // Use the new method for comptes comptables


?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Ajouter une Facture Fournisseur</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Factures Fournisseur</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Nouvelle Facture Fournisseur</h5>

                        <form id="addSupplierInvoiceForm" action="controller/create_supplier_invoice.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fournisseurId" class="form-label">Fournisseur <span class="text-danger">*</span></label>
                                <select class="form-select" id="fournisseurId" name="fournisseurId" required>
                                    <option value="">Sélectionner un fournisseur</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= htmlspecialchars($supplier['idFournisseur']) ?>">
                                            <?= htmlspecialchars($supplier['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="numeroFacture" class="form-label">Numéro de Facture <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="numeroFacture" name="numeroFacture" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dateFacture" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateFacture" name="dateFacture" required>
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="motif" class="form-label">Motif</label>
                                <input type="text" class="form-control" id="motif" name="motif">
                            </div>
                            <div class="col-md-6">
                                <label for="compteId" class="form-label">Compte de charge <span class="text-danger">*</span></label>
                                <select class="form-select" id="compteId" name="compteId">
                                    <option value="">Sélectionner un compte</option>
                                    <?php foreach ($comptesComptables as $compte) {
                                        if ($compte['classeCompte'] == 6) {
                                            ?>
                                            <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte'] . ' ' . $compte['intituleCompte'] ?></option>
                                        <?php }
                                    } ?>
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