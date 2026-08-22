<?php
include "./views/include/header.php";

$structureModel = new Structure();
$banqueModel = new Banque();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userId = $_SESSION['id'];
$depenses = $structureModel->getDepensesByUser($userId, $search);

// Fetch accessible budget lines for the user
$budgetLines = $structureModel->getLignesDepenseByUser($userId);
$banks = $banqueModel->getBanksByUserAccess($userId);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Dépenses</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Dépenses</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Dépenses</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <!-- Add Depense Button -->
                            <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addDepenseModal">
                                + Ajouter une nouvelle dépense
                            </button>
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/depense.add">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une dépense...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="depenseTable">
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
                                foreach ($depenses as $depense) {
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$depense['dateoperation']}</td>
                                            <td>{$depense['montantD']}</td>
                                            <td>{$depense['motifD']}</td>
                                            <td>{$depense['designation']}</td>
                                            <td>
                                                <button type='button' class='btn btn-info btn-sm print-btn' data-depense-id='{$depense['idDepense_structure']}'><i class='bi bi-printer'></i> Imprimer</button>
                                                <button type='button' class='btn btn-secondary btn-sm pos-btn' data-depense-id='{$depense['idDepense_structure']}'><i class='bi bi-printer'></i> POS</button>
                                                <form action='controller/delete_depense.php' method='POST' class='delete-depense-form' style='display:inline;'>
                                                    <input type='hidden' name='idDepense' value='{$depense['idDepense_structure']}'>
                                                    <button type='button' class='btn btn-danger btn-sm delete-depense-btn'>Supprimer</button>
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

                        <!-- Modal for adding a new depense -->
<div class="modal fade" id="addDepenseModal" tabindex="-1" aria-labelledby="addDepenseModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepenseModalLabel">Ajouter une nouvelle dépense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDepenseForm" action="controller/add_depense.php" method="POST">
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
                            <label for="addMontantD" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="addMontantD" name="montantD" required>
                        </div>
                        <div class="col-md-6">
                            <label for="addMotifD" class="form-label">Motif <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addMotifD" name="motifD" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="beneficiary" class="form-label">Bénéficiaire <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="beneficiary" name="beneficiaire" required>
                        </div>
                        <div class="col-md-6">
                            <label for="addLigneDepenseId" class="form-label">Ligne Budgétaire <span class="text-danger">*</span></label>
                            <select class="form-select" id="addLigneDepenseId" name="ligneDepenseId" required>
                                <option value="">Sélectionner une ligne budgétaire</option>
                                <?php foreach ($budgetLines as $line): ?>
                                    <option value="<?= $line['idligne_depense_structure'] ?>">
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
                                document.querySelectorAll('.delete-depense-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-depense-form');
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
                                        const depenseId = this.getAttribute('data-depense-id');
                                        window.open(`comptabilite/generate_depense_receipt?depenseId=${depenseId}`, '_blank');
                                    });
                                });

                                document.querySelectorAll('.print-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const depenseId = this.getAttribute('data-depense-id');
                                        window.open(`comptabilite/generate_depense_print?depenseId=${depenseId}`, '_blank');
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