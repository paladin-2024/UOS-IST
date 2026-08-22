<?php
include "./views/include/header.php";

$compteModel = new Comptabilite();
$userId = $_SESSION['id']; // Retrieve user ID from session
$journaux = $compteModel->getJournauxByUserAccess($userId);

$reportDebit = 0;
$reportCredit = 0;
$totalDebit = 0;
$totalCredit = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $journalId = $_POST['journal'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    // Calculate report period balances
    $reportBalances = $compteModel->getReportPeriodBalances($journalId, $startDate);
    $reportDebit = $reportBalances['report_debit'];
    $reportCredit = $reportBalances['report_credit'];

    // Fetch entries for the selected period
    $entries = $compteModel->getEcrituresByJournalAndPeriod($journalId, $startDate, $endDate);

    // Calculate total debit and credit for the selected period
    foreach ($entries as $entry) {
        $totalDebit += $entry['total_debit'];
        $totalCredit += $entry['total_credit'];
    }

    // Add report balances to the totals
    $totalDebit += $reportDebit;
    $totalCredit += $reportCredit;

    $exportUrl = "controller/excel_journal.php?journal=$journalId&startDate=$startDate&endDate=$endDate";
} else {
    $entries = [];
}
?>

<main id="main" class="main">
    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Consultation des Écritures Comptables</h5>
                        <form action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="journal" class="form-label">Journal <span class="text-danger">*</span></label>
                                    <select class="form-select" id="journal" name="journal" required>
                                        <option value="">Sélectionner un journal</option>
                                        <?php foreach ($journaux as $journal): ?>
                                            <option value="<?= $journal['idJournaux'] ?>"><?= $journal['code_journal']." ".$journal['nom_journal'] ?></option>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3">Solde avant <?= htmlspecialchars($startDate) ?></td>
                                    <td><?= number_format($reportDebit, 2) ?></td>
                                    <td><?= number_format($reportCredit, 2) ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="table mt-3">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Numéro de Pièce</th>
                                    <th>Libellé</th>
                                    <th>Total Débit</th>
                                    <th>Total Crédit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($entries)): ?>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?= $entry['dateEcriture'] ?></td>
                                            <td><?= $entry['numeroPiece'] ?></td>
                                            <td><?= $entry['libelle'] ?></td>
                                            <td><?= number_format($entry['total_debit'], 2) ?></td>
                                            <td><?= number_format($entry['total_credit'], 2) ?></td>
                                            <td>
                                                <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?= $entry['idEcriture'] ?>" aria-expanded="false" aria-controls="details-<?= $entry['idEcriture'] ?>">
                                                    Voir détails
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="collapse" id="details-<?= $entry['idEcriture'] ?>">
                                            <td colspan="6">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Compte</th>
                                                            <th>Intitulé</th>
                                                            <th>Débit</th>
                                                            <th>Crédit</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $details = $compteModel->getDetailsByEcritureId($entry['idEcriture']);
                                                        foreach ($details as $detail): ?>
                                                            <tr>
                                                                <td><?= $detail['compte'] ?></td>
                                                                <td><?= $detail['intitule'] ?></td>
                                                                <td><?= number_format($detail['debit'], 2) ?></td>
                                                                <td><?= number_format($detail['credit'], 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucune écriture trouvée pour cette période.</td>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3">Total incluant le solde de report</td>
                                    <td><?= number_format($totalDebit, 2) ?></td>
                                    <td><?= number_format($totalCredit, 2) ?></td>
                                    <td></td>
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