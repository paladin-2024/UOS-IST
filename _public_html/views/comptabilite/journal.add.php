<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Ajouter un Journal Comptable</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Ajouter Journal</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Journaux</h5>

                        <!-- Form to add a new journal -->
    <form id="addJournalForm" action="controller/create_journal.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="nomJournal" class="form-label">Nom du Journal <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nomJournal" name="nomJournal" required>
            </div>
            <div class="col-md-6">
                <label for="codeJournal" class="form-label">Code du Journal <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="codeJournal" name="codeJournal" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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