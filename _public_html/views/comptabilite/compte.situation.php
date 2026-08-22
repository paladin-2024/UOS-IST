<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Gérer l'Historique du Compte</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Historique du Compte</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionner un Compte pour Voir l'Historique</h5>

                        <!-- Form to view account history -->
                        <form id="viewAccountHistoryForm" action="controller/view_account_history.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="structureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="structureId" name="structureId" required onchange="updateAccounts()">
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
                                <div class="col-md-6">
                                    <label for="accountId" class="form-label">Compte <span class="text-danger">*</span></label>
                                    <select class="form-select" id="accountId" name="accountId" required>
                                        <option value="">Sélectionner un compte</option>
                                        <!-- Options will be populated based on selected structure -->
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Voir l'Historique
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script>
function updateAccounts() {
    const structureId = document.getElementById('structureId').value;
    const accountIdSelect = document.getElementById('accountId');

    // Clear existing options
    accountIdSelect.innerHTML = '<option value="">Sélectionner un compte</option>';

    if (structureId) {
        // Fetch accounts based on selected structure
        fetch(`controller/get_accounts.php?structureId=${structureId}`)
            .then(response => response.json())
            .then(data => {
                data.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.idCompte;
                    option.textContent = account.numeroCompte +' '+account.intituleCompte;
                    accountIdSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching accounts:', error));
    }
}
</script>

<?php include "./views/include/footer_file.php"; ?>