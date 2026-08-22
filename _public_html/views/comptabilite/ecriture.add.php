<?php
include "./views/include/header.php";

$compteModel = new Structure();
$userId = $_SESSION['id']; // Récupération de l'ID utilisateur depuis la session
$comptes = $compteModel->getComptesComptablesByUserAccess($userId);
$journaux = $compteModel->getJournauxByUserAccess($userId);
?>

<main id="main" class="main">
    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Écritures</h5>
                        <form id="addEcritureForm" action="controller/create_ecriture.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="journal" class="form-label">Journal <span class="text-danger">*</span></label>
                                    <select class="form-select" id="journal" name="journal" required>
                                        <option value="">Sélectionner un journal</option>
                                        <?php foreach ($journaux as $journal): ?>
                                            <option value="<?= $journal['idJournaux'] ?>"><?= $journal['code_journal']." ".$journal['nom_journal'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="numeroPiece" class="form-label">Numéro de Pièce <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="numeroPiece" name="numeroPiece" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="dateEcriture" class="form-label">Date de l'Écriture <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="dateEcriture" name="dateEcriture" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="libelle" class="form-label">Libellé de l'opération <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="libelle" name="libelle" required>
                                </div>
                            </div>

                            <!-- Form for adding lines -->
                            <div class="row mb-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Compte <span class="text-danger">*</span></label>
                                    <select class="form-select" id="compteSelect" required>
                                        <option value="">Sélectionner un compte</option>
                                        <?php foreach ($comptes as $compte): ?>
                                            <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte']." : ".$compte['intituleCompte'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Montant <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="montantInput" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="typeSelect" required>
                                        <option value="debit">Débit</option>
                                        <option value="credit">Crédit</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary" id="addLineButton">+ Ajouter</button>
                                </div>
                            </div>

                            <input type="hidden" id="linesData" name="lines">

                            <!-- Table for displaying lines -->
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th>Compte</th>
                                        <th>Montant</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="linesTableBody">
                                    <!-- Lines will be added here -->
                                </tbody>
                            </table>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Total Débit</label>
                                    <input type="text" class="form-control" id="totalDebit" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Crédit</label>
                                    <input type="text" class="form-control" id="totalCredit" readonly>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary" id="submitButton" disabled>
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.getElementById('addLineButton').addEventListener('click', function() {
    const compte = document.getElementById('compteSelect').value;
    const compteText = document.getElementById('compteSelect').selectedOptions[0].text;
    const montant = parseFloat(document.getElementById('montantInput').value) || 0;
    const type = document.getElementById('typeSelect').value;

    if (compte && montant > 0) {
        const tableBody = document.getElementById('linesTableBody');
        let existingRow = null;

        // Check if the account already exists in the table
        document.querySelectorAll('#linesTableBody tr').forEach(row => {
            const rowCompteText = row.cells[0].textContent;
            const rowType = row.cells[2].textContent;

            if (rowCompteText === compteText && rowType === type) {
                existingRow = row;
            }
        });

        if (existingRow) {
            // Update the existing row's amount
            const existingAmount = parseFloat(existingRow.cells[1].textContent) || 0;
            const newAmount = existingAmount + montant;
            existingRow.cells[1].textContent = newAmount.toFixed(2);
        } else {
            // Add a new row if the account doesn't exist
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${compteText}</td>
                <td>${montant.toFixed(2)}</td>
                <td>${type}</td>
                <td><button type="button" class="btn btn-danger btn-sm removeLineButton">Supprimer</button></td>
            `;
            tableBody.appendChild(row);

            row.querySelector('.removeLineButton').addEventListener('click', function() {
                row.remove();
                calculateTotals();
            });
        }

        calculateTotals();
    }
});

function calculateTotals() {
    let totalDebit = 0;
    let totalCredit = 0;
    const lines = [];

    document.querySelectorAll('#linesTableBody tr').forEach(row => {
        const compteText = row.cells[0].textContent;
        const montant = parseFloat(row.cells[1].textContent) || 0;
        const type = row.cells[2].textContent;

        if (type === 'debit') {
            totalDebit += montant;
        } else if (type === 'credit') {
            totalCredit += montant;
        }

        lines.push({
            compteId: compteText.split(' : ')[0], // Assuming the account ID is part of the text
            montant: montant,
            type: type
        });
    });

    document.getElementById('totalDebit').value = totalDebit.toFixed(2);
    document.getElementById('totalCredit').value = totalCredit.toFixed(2);
    document.getElementById('submitButton').disabled = totalDebit !== totalCredit;

    // Store lines data in hidden input
    document.getElementById('linesData').value = JSON.stringify(lines);
}

// Add event listener to form submission to ensure lines data is updated
document.getElementById('addEcritureForm').addEventListener('submit', function() {
    calculateTotals(); // Ensure the latest lines data is stored
});
</script>

<?php include "./views/include/footer.php"; ?>
