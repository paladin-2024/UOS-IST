<?php
include "./views/include/header.php";

$compteModel = new Comptabilite();
$userId = $_SESSION['id']; // Retrieve user ID from session
$comptes = $compteModel->getComptesByUserAccess($userId);

$reportDebit = 0;
$reportCredit = 0;
$totalDebit = 0;
$totalCredit = 0;
$transactions = [];
$runningBalance = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $compteId = $_POST['compte'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    // Calculate report period balances
    $reportBalances = $compteModel->getReportPeriodBalancesByCompte($compteId, $startDate);
    $reportDebit = $reportBalances['report_debit'];
    $reportCredit = $reportBalances['report_credit'];

    // Determine account class
    $compteDetails = $compteModel->getCompteDetails($compteId); // Assuming this method exists
    $classeCompte = $compteDetails['classeCompte'];
    $is_debit_account = in_array($classeCompte, ['2', '3', '4', '5', '6']);
    $is_credit_account = in_array($classeCompte, ['1', '7']);
    

    // Initialize running balance
    if ($is_debit_account) {
        $runningBalance = $reportDebit - $reportCredit;
    } elseif ($is_credit_account) {
        $runningBalance = $reportCredit - $reportDebit;
    }

    // Fetch transactions for the selected period
    $transactions = $compteModel->getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate);

    // Calculate total debit and credit for the selected period
    foreach ($transactions as $transaction) {
        $totalDebit += $transaction['debit'];
        $totalCredit += $transaction['credit'];
    }

    // Add report balances to the totals
    $totalDebit += $reportDebit;
    $totalCredit += $reportCredit;

    // Calculate the final balance based on account class
    $finalBalance = 0;
    if ($is_debit_account) {
        $finalBalance = $totalDebit - $totalCredit;
    } elseif ($is_credit_account) {
        $finalBalance = $totalCredit - $totalDebit;
    }

    $exportUrl = "controller/export_excel.php?compte=$compteId&startDate=$startDate&endDate=$endDate";
}
?>

<main id="main" class="main">
    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Historique du Compte Comptable</h5>
                        <form action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="compte" class="form-label">Compte <span class="text-danger">*</span></label>
                                    <select class="form-select" id="compte" name="compte" required>
                                        <option value="">Sélectionner un compte</option>
                                        <?php foreach ($comptes as $compte): ?>
                                            <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte']." ".$compte['intituleCompte'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="startDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="endDate" class="form-label">Date de fin <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">Consulter</button>
                                </div>
                            </div>
                        </form>

                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                            <a href="<?= $exportUrl ?>" class="btn btn-success mb-3">Exporter en Excel</a>
                            <!-- Existing table code... -->
                        <?php endif; ?>

                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th colspan="3">Solde de Report</th>
                                        <th>Total Débit</th>
                                        <th>Total Crédit</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3">Solde avant <?= htmlspecialchars($startDate) ?></td>
                                        <td><?= number_format($reportDebit, 2) ?></td>
                                        <td><?= number_format($reportCredit, 2) ?></td>
                                        <td><?= number_format($runningBalance, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Libellé</th>
                                        <th>Débit</th>
                                        <th>Crédit</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $transaction): 
                                            if ($is_debit_account) {
                                                $runningBalance += $transaction['debit'] - $transaction['credit'];
                                            } elseif ($is_credit_account) {
                                                $runningBalance += $transaction['credit'] - $transaction['debit'];
                                            }
                                            ?>
                                            <tr>
                                                <td><?= $transaction['date'] ?></td>
                                                <td><?= $transaction['libelle'] ?></td>
                                                <td><?= number_format($transaction['debit'], 2) ?></td>
                                                <td><?= number_format($transaction['credit'], 2) ?></td>
                                                <td><?= number_format($runningBalance, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Aucune transaction trouvée pour cette période.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th colspan="3">Total Général</th>
                                        <th>Total Débit</th>
                                        <th>Total Crédit</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3">Total incluant le solde de report</td>
                                        <td><?= number_format($totalDebit, 2) ?></td>
                                        <td><?= number_format($totalCredit, 2) ?></td>
                                        <td><?= number_format($finalBalance, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>