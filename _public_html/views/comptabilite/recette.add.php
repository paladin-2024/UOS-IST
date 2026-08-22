<?php
include "./views/include/header.php";

$structureModel = new Structure();
$banqueModel = new Banque();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userId = $_SESSION['id'];
$recettes = $structureModel->getRecettesByUser($userId,$search);

// Fetch accessible budget lines for the user
$budgetLines = $structureModel->getLignesRecetteByUser($userId);
$banks = $banqueModel->getBanksByUserAccess($userId);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Recettes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Recettes</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Recettes</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <!-- Add Recette Button -->
                            <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addRecetteModal">
                                + Ajouter une nouvelle recette
                            </button>
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/recette.add">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une recette...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        

                        <table class="table table-striped table-bordered" id="recetteTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Motif</th>
                                    <th>Ligne Budgétaire</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($recettes as $recette) {
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$recette['dateOperation']}</td>
                                            <td>{$recette['montantR']}</td>
                                            <td>{$recette['motif']}</td>
                                            <td>{$recette['designation']}</td>
                                            <td>
                                                <button type='button' class='btn btn-info btn-sm print-btn' data-recette-id='{$recette['idRecette_structure']}'><i class='bi bi-printer'></i> Imprimer</button>
                                                <button type='button' class='btn btn-secondary btn-sm pos-btn' data-recette-id='{$recette['idRecette_structure']}'><i class='bi bi-printer'></i> POS</button>
                                                <form action='controller/delete_recette.php' method='POST' class='delete-recette-form' style='display:inline;'>
                                                    <input type='hidden' name='idRecette' value='{$recette['idRecette_structure']}'>
                                                    <button type='button' class='btn btn-danger btn-sm delete-recette-btn'>Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='5' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal for adding a new recette -->
    <div class="modal fade" id="addRecetteModal" tabindex="-1" aria-labelledby="addRecetteModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRecetteModalLabel">Ajouter une nouvelle recette</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addRecetteForm" action="controller/add_recette.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="paymentBank" class="form-label">Sélectionner la Banque <span class="text-danger">*</span></label>
                                <select class="form-select" id="paymentBank" name="bankId" required>
                                    <?php foreach ($banks as $bank): ?>
                                        <option value="<?= htmlspecialchars($bank['idBanque']) ?>"><?= htmlspecialchars($bank['designation']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="addDateOperation" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="addDateOperation" name="dateOperation" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addMontantR" class="form-label">Montant <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="addMontantR" name="montantR" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addMotif" class="form-label">Motif <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addMotif" name="motif" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="deposit" class="form-label">Dépositaire <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="deposit" name="depositaire" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addLigneRecetteId" class="form-label">Ligne Budgétaire <span class="text-danger">*</span></label>
                                <select class="form-select" id="addLigneRecetteId" name="ligneRecetteId" required>
                                    <option value="">Sélectionner une ligne budgétaire</option>
                                    <?php foreach ($budgetLines as $line): ?>
                                        <option value="<?= $line['idligne_recette_structure'] ?>">
                                            <?= htmlspecialchars($line['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-recette-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-recette-form');
                                        Swal.fire({
                                            title: 'Êtes-vous sûr?',
                                            text: "Cette action est irréversible!",
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#3085d6',
                                            cancelButtonColor: '#d33',
                                            confirmButtonText: 'Oui, supprimer!',
                                            cancelButtonText: 'Annuler'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                form.submit();
                                            }
                                        });
                                    });
                                });

                                // Add event listener for POS button
                                document.querySelectorAll('.pos-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const recetteId = this.getAttribute('data-recette-id');
                                        window.open(`comptabilite/generate_recette_receipt?recetteId=${recetteId}`, '_blank');
                                    });
                                });

                                document.querySelectorAll('.print-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const recetteId = this.getAttribute('data-recette-id');
                                        window.open(`comptabilite/generate_recette_print?recetteId=${recetteId}`, '_blank');
                                    });
                                });
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>