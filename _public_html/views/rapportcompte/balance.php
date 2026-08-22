<?php
include "./views/include/header.php";

$compteModel = new Comptabilite();
$userId = $_SESSION['id']; // Retrieve user ID from session
$structures = $compteModel->getStructuresByUserAccess($userId); // Assuming this method exists to get structures
$totalDebit = 0;
$totalCredit = 0;
$balances = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $structureId = $_POST['structure'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    $comptes = $compteModel->getComptesByStructure($structureId); // Assuming this method exists

    foreach ($comptes as $compte) {
        $compteId = $compte['idCompte'];

        $classeCompte = $compte['classeCompte'];
        $is_debit_account = in_array($classeCompte, ['1', '2', '3', '4', '6']);
        $is_credit_account = in_array($classeCompte, ['1', '4', '5', '7']);

        // Calculate report period balances
        $reportBalances = $compteModel->getReportPeriodBalancesByCompte($compteId, $startDate);
        $reportDebit = $reportBalances['report_debit'];
        $reportCredit = $reportBalances['report_credit'];

        // Fetch transactions for the selected period
        $transactions = $compteModel->getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate);

        $debit = $reportDebit;
        $credit = $reportCredit;

        foreach ($transactions as $transaction) {
            $debit += $transaction['debit'];
            $credit += $transaction['credit'];
        }

        if ($is_debit_account) {
            $balance = $debit - $credit;
        } elseif ($is_credit_account) {
            $balance = $credit - $debit;
        }

        if ($balance != 0) {
            $balances[] = [
                'compte' => $compte['numeroCompte'] . " " . $compte['intituleCompte'],
                'debit' => $debit,
                'credit' => $credit,
                'solde_debiteur' => $balance > 0 ? $balance : 0,
                'solde_crediteur' => $balance < 0 ? abs($balance) : 0
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }
    }
}
?>

<main id="main" class="main">
    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Consultation de la Balance Générale</h5>
                        <form action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="structure" class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="structure" name="structure" required>
                                        <option value="">Sélectionner une structure</option>
                                        <?php foreach ($structures as $structure): ?>
                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
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
                            <form action="controller/export_balance.php" method="POST">
                                <input type="hidden" name="structure" value="<?= htmlspecialchars($structureId) ?>">
                                <input type="hidden" name="startDate" value="<?= htmlspecialchars($startDate) ?>">
                                <input type="hidden" name="endDate" value="<?= htmlspecialchars($endDate) ?>">
                                <button type="submit" class="btn btn-success mb-3">Exporter en Excel</button>
                            </form>

                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th>Compte</th>
                                        <th>Débit</th>
                                        <th>Crédit</th>
                                        <th>Solde Débiteur</th>
                                        <th>Solde Créditeur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($balances as $balance): ?>
                                        <tr>
                                            <td><?= $balance['compte'] ?></td>
                                            <td><?= number_format($balance['debit'], 2) ?></td>
                                            <td><?= number_format($balance['credit'], 2) ?></td>
                                            <td><?= number_format($balance['solde_debiteur'], 2) ?></td>
                                            <td><?= number_format($balance['solde_crediteur'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total Général</th>
                                        <th><?= number_format($totalDebit, 2) ?></th>
                                        <th><?= number_format($totalCredit, 2) ?></th>
                                        <th colspan="2"><?= number_format($totalDebit - $totalCredit, 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer_file.php"; ?>