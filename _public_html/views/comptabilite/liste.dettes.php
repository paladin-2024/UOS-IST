<?php
include "./views/include/header.php";

$structureModel = new Structure();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session
$userName = $_SESSION['nom']; // Example: Retrieve user name from session
$structures = $structureModel->getStructuresByUserAccess($userId);

// Fetch supplier debts based on filters
$debts = [];
$totalDebt = 0;
$totalOutstandingDebt = 0;
$structureInfo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $structureId = $_POST['structureId'];
    $debts = $structureModel->getSupplierDebts($structureId, $startDate, $endDate);

    // Fetch structure information
    $structureInfo = $structureModel->getStructureById($structureId);

    // Calculate totals
    foreach ($debts as $debt) {
        $totalDebt += $debt['total_amount'];
        $totalOutstandingDebt += $debt['outstanding_amount'];
    }
}

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Visualiser les Dettes envers les Fournisseurs</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Dettes des Fournisseurs</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Filtrer les Dettes des Fournisseurs</h5>

                        <!-- Form to filter supplier debts -->
                        <form id="filterDebtsForm" action="" method="POST">
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
                                <div class="col-md-12">
                                    <label for="structureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="structureId" name="structureId" required>
                                        <option value="">Sélectionner une structure</option>
                                        <?php foreach ($structures as $structure): ?>
                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Filtrer
                            </button>
                        </form>

                        <!-- Table to display supplier debts -->
                        <h5 class="card-title mt-4">Liste des Dettes des Fournisseurs</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Fournisseur</th>
                                    <th scope="col">Numéro de Facture</th>
                                    <th scope="col">Montant Total</th>
                                    <th scope="col">Montant Restant</th>
                                    <th scope="col">Date Facture</th>
                                    <th scope="col">Date de Paiement</th>
                                    <th scope="col">Jours de Retard</th>
                                    <th scope="col">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $supplierDebts = [];
                                foreach ($debts as $debt) {
                                    $supplierDebts[$debt['supplier_name']][] = $debt;
                                }
                                foreach ($supplierDebts as $supplier => $supplierDebtList): 
                                    $supplierTotal = 0;
                                    $supplierOutstanding = 0;
                                    foreach ($supplierDebtList as $debt):
                                        $supplierTotal += $debt['total_amount'];
                                        $supplierOutstanding += $debt['outstanding_amount'];
                                        $recoveryDate = date('Y-m-d', strtotime($debt['due_date'] . ' +30 days'));
                                        $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($recoveryDate)) / (60 * 60 * 24));
                                        $rowClass = $daysOverdue > 0 ? 'table-danger' : '';
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><?= htmlspecialchars($debt['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($debt['invoice_number']) ?></td>
                                        <td><?= number_format($debt['total_amount'], 2) ?> $</td>
                                        <td><?= number_format($debt['outstanding_amount'], 2) ?> $</td>
                                        <td><?= date('d/m/Y', strtotime($debt['due_date'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($recoveryDate)) ?></td>
                                        <td><?= $daysOverdue ?></td>
                                        <td><?= htmlspecialchars($debt['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="2"><strong>Sous-total pour <?= htmlspecialchars($supplier) ?></strong></td>
                                    <td><strong><?= number_format($supplierTotal, 2) ?> $</strong></td>
                                    <td><strong><?= number_format($supplierOutstanding, 2) ?> $</strong></td>
                                    <td colspan="4"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Totaux</th>
                                    <th><?= number_format($totalDebt, 2) ?> $</th>
                                    <th><?= number_format($totalOutstandingDebt, 2) ?> $</th>
                                    <th colspan="4"></th>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="d-flex justify-content-between">
                            <!-- Button to export to Excel -->
                            <form action="controller/export_debts_excel.php" method="POST">
                                <input type="hidden" name="startDate" value="<?= htmlspecialchars($startDate ?? '') ?>">
                                <input type="hidden" name="endDate" value="<?= htmlspecialchars($endDate ?? '') ?>">
                                <input type="hidden" name="structureId" value="<?= htmlspecialchars($structureId ?? '') ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-file-earmark-excel"></i> Exporter en Excel
                                </button>
                            </form>

                            <!-- Button to print -->
                            <button onclick="printDebts()" class="btn btn-secondary">
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
function printDebts() {
    let newWindow = window.open('', '', 'width=800,height=600');
    newWindow.document.write('<html><head><title>Rapport des Dettes</title><style>');
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

    newWindow.document.write('<h3>Rapport des Dettes des Fournisseurs du <?= date("d/m/Y", strtotime($startDate)) ?> au <?= date("d/m/Y", strtotime($endDate)) ?></h3>');
    newWindow.document.write('<p><strong>Imprimé par :</strong> <?= htmlspecialchars($userName) ?> le <?= date("d/m/Y H:i:s") ?></p>'); // Add user and date

    <?php foreach ($supplierDebts as $supplier => $supplierDebtList): 
        $supplierTotal = 0;
        $supplierOutstanding = 0;?>
        newWindow.document.write('<h4>Fournisseur: <?= htmlspecialchars($supplier) ?></h4>');
        newWindow.document.write('<table><thead><tr><th>Numéro de Facture</th><th>Montant Total</th><th>Montant Restant</th><th>Date Facture</th><th>Date de Paiement</th><th>Jours de Retard</th><th>Statut</th></tr></thead><tbody>');
    <?php
        foreach ($supplierDebtList as $debt):
            $supplierTotal += $debt['total_amount'];
            $supplierOutstanding += $debt['outstanding_amount'];
            $recoveryDate = date('Y-m-d', strtotime($debt['due_date'] . ' +30 days'));
            $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($recoveryDate)) / (60 * 60 * 24));
    ?>
        newWindow.document.write('<tr><td><?= htmlspecialchars($debt['invoice_number']) ?></td><td><?= number_format($debt['total_amount'], 2) ?> $</td><td><?= number_format($debt['outstanding_amount'], 2) ?> $</td><td><?= date('d/m/Y', strtotime($debt['due_date'])) ?></td><td><?= date('d/m/Y', strtotime($recoveryDate)) ?></td><td><?= $daysOverdue ?></td><td><?= htmlspecialchars($debt['status']) ?></td></tr>');
    <?php endforeach; ?>
    newWindow.document.write('</tbody></table>');
    newWindow.document.write('<p><strong>Sous-total pour <?= htmlspecialchars($supplier) ?> :</strong> <?= number_format($supplierTotal, 2) ?> $</p>');
    newWindow.document.write('<p><strong>Montant Restant pour <?= htmlspecialchars($supplier) ?> :</strong> <?= number_format($supplierOutstanding, 2) ?> $</p>');
    <?php endforeach; ?>

    newWindow.document.write('<p><strong>Total des Dettes :</strong> <?= number_format($totalDebt, 2) ?> $</p>');
    newWindow.document.write('<p><strong>Total Restant :</strong> <?= number_format($totalOutstandingDebt, 2) ?> $</p>');
    newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
    newWindow.document.write('</body></html>');
    newWindow.document.close();
}
</script>

<?php include "./views/include/footer_file.php"; ?>