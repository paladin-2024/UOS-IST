<?php
include "./views/include/header.php";

$structureModel = new Structure();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session
$userName = $_SESSION['nom']; // Example: Retrieve user name from session
$structures = $structureModel->getStructuresByUserAccess($userId);
$budgets = $structureModel->getRecetteBudgetsByUser($userId); // Fetch all budgets

// Fetch periodic revenues based on filters
$revenues = [];
$totalRevenue = 0;
$groupedRevenues = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $structureId = $_POST['structureId'];
    $budgetId = $_POST['budgetId']; // New budget filter
    
    $revenues = $structureModel->getPeriodicRevenues($structureId, $startDate, $endDate,$budgetId);

    // Fetch structure information
    $structureInfo = $structureModel->getStructureById($structureId);
    

    // Group revenues by 'designationGR' and calculate subtotals
    foreach ($revenues as $revenue) {
        $designationGR = $revenue['designationGR'];
        if (!isset($groupedRevenues[$designationGR])) {
            $groupedRevenues[$designationGR] = [
                'subTotal' => 0,
                'items' => []
            ];
        }
        $groupedRevenues[$designationGR]['items'][] = $revenue;
        $groupedRevenues[$designationGR]['subTotal'] += $revenue['montantR'];
        $totalRevenue += $revenue['montantR'];
    }
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Visualiser les Recettes Périodiques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Recettes Périodiques</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Filtrer les Recettes Périodiques</h5>

                        <!-- Form to filter periodic revenues -->
                        <form id="filterRevenuesForm" action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="startDate" class="form-label">Date de Début <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="endDate" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="structureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="structureId" name="structureId" required>
                                        <option value="">Sélectionner une structure</option>
                                        <?php foreach ($structures as $structure): ?>
                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="budgetId" class="form-label">Budget </label>
                                    <select class="form-select" id="budgetId" name="budgetId">
                                        <option value="">Tous les budgets</option>
                                        <?php foreach ($budgets as $budget): ?>
                                            <option value="<?= $budget['idBudget_recette_structure'] ?>"><?= $budget['designation'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Filtrer
                            </button>
                        </form>

                        <!-- Table to display grouped periodic revenues -->
                        <h5 class="card-title mt-4">Liste des Recettes Périodiques par Groupe</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Groupe dépense</th>
                                    <th scope="col">Motif</th>
                                    <th scope="col">Montant</th>
                                    <th scope="col">Dépositaire</th>
                                    <th scope="col">Date d'Opération</th>
                                    <th scope="col">Date d'Enregistrement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedRevenues as $designationGR => $group): ?>
                                    <tr>
                                        <td colspan="6" class="table-active"><strong><?= htmlspecialchars($designationGR) ?></strong></td>
                                    </tr>
                                    <?php foreach ($group['items'] as $revenue): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($revenue['designationGR']) ?></td>
                                            <td><?= htmlspecialchars($revenue['motif']) ?></td>
                                            <td><?= number_format($revenue['montantR'], 2) ?> $</td>
                                            <td><?= htmlspecialchars($revenue['depositaire']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($revenue['dateOperation'])) ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($revenue['dateEnregistrement'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="2"><strong>Sous-total</strong></td>
                                        <td><strong><?= number_format($group['subTotal'], 2) ?> $</strong></td>
                                        <td colspan="3"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Total</th>
                                    <th><?= number_format($totalRevenue, 2) ?> $</th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="d-flex justify-content-between">
                        <!-- Button to export to Excel -->
                        <form action="controller/export_revenues_excel.php" method="POST">
                            <input type="hidden" name="startDate" value="<?= htmlspecialchars($startDate ?? '') ?>">
                            <input type="hidden" name="endDate" value="<?= htmlspecialchars($endDate ?? '') ?>">
                            <input type="hidden" name="structureId" value="<?= htmlspecialchars($structureId ?? '') ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Exporter en Excel
                            </button>
                        </form>

                        <!-- Button to print -->
                        <button onclick="printRevenues()" class="btn btn-secondary mt-4">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script>
function printRevenues() {
    let newWindow = window.open('', '', 'width=800,height=600');
    newWindow.document.write('<html><head><title>Rapport des Recettes</title><style>');
    newWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }');
    newWindow.document.write('h2, h3 { color: #333; font-size: 16px; }');
    newWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px;}');
    newWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    newWindow.document.write('th { background-color: #f4f4f4; }');
    newWindow.document.write('.logo { float: right; width: 100px; height: auto; margin-bottom: 20px; }');
    newWindow.document.write('@media print { button { display: none; } }'); // Hide button on print
    newWindow.document.write('</style></head><body>');

    <?php if ($structureInfo): ?>
        newWindow.document.write('<img src="../uploads/<?= $structureInfo['logo'] ?>" alt="Logo" class="logo">');
        newWindow.document.write('<h2><?= htmlspecialchars($structureInfo['designation']) ?></h2>');
        newWindow.document.write('<p><strong>Adresse :</strong> <?= htmlspecialchars($structureInfo['adresse']) ?></p>');
        newWindow.document.write('<p><strong>Téléphone :</strong> <?= htmlspecialchars($structureInfo['phone1']) ?></p>');
    <?php endif; ?>

    newWindow.document.write('<h3>Rapport des Recettes Périodiques du <?= date("d/m/Y", strtotime($startDate)) ?> au <?= date("d/m/Y", strtotime($endDate)) ?></h3>');
    newWindow.document.write('<p><strong>Imprimé par :</strong> <?= htmlspecialchars($userName) ?> le <?= date("d/m/Y H:i:s") ?></p>'); // Add user and date
    newWindow.document.write('<table><thead><tr><th>Groupe dépense</th><th>Motif</th><th>Montant</th><th>Dépositaire</th><th>Date d\'Opération</th><th>Date d\'Enregistrement</th></tr></thead><tbody>');

    <?php foreach ($groupedRevenues as $designationGR => $group): ?>
        newWindow.document.write('<tr><td colspan="6" class="table-active"><strong><?= htmlspecialchars($designationGR) ?></strong></td></tr>');
        <?php foreach ($group['items'] as $revenue): ?>
            newWindow.document.write('<tr><td><?= htmlspecialchars($revenue['designationGR']) ?></td><td><?= htmlspecialchars($revenue['motif']) ?></td><td><?= number_format($revenue['montantR'], 2) ?> $</td><td><?= htmlspecialchars($revenue['depositaire']) ?></td><td><?= date('d/m/Y', strtotime($revenue['dateOperation'])) ?></td><td><?= date('d/m/Y H:i:s', strtotime($revenue['dateEnregistrement'])) ?></td></tr>');
        <?php endforeach; ?>
        newWindow.document.write('<tr><td colspan="2"><strong>Sous-total</strong></td><td><strong><?= number_format($group['subTotal'], 2) ?> $</strong></td><td colspan="3"></td></tr>');
    <?php endforeach; ?>

    newWindow.document.write('</tbody></table>');
    newWindow.document.write('<p><strong>Total des Recettes :</strong> <?= number_format($totalRevenue, 2) ?> $</p>');
    newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
    newWindow.document.write('</body></html>');
    newWindow.document.close();
}

</script>

<?php include "./views/include/footer_file.php"; ?>