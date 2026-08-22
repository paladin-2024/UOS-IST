<?php
include "./views/include/header.php";

$structureModel = new Structure();
$structures = $structureModel->getStructures();

// Assuming $userId is obtained from the session or authentication context
$userId = $_SESSION['id']; // Example: Retrieve user ID from session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Tableau de Bord Financier</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Tableau de Bord</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionnez les paramètres</h5>

                        <!-- Form to select parameters on a single line -->
                        <form id="dashboardForm" class="d-flex align-items-center">
                            <div class="me-3">
                                <label for="startDate" class="form-label">Date de Début <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="startDate" name="startDate" required>
                            </div>
                            <div class="me-3">
                                <label for="endDate" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="endDate" name="endDate" required>
                            </div>
                            <div class="me-3">
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
                            <button type="button" class="btn btn-primary" onclick="updateDashboard()">
                                <i class="bi bi-bar-chart"></i> Mettre à jour le Tableau
                            </button>
                        </form>

                        <!-- Chart and Summary Table -->
                        <div class="mt-5">
                            <canvas id="financialChart"></canvas>
                            <table class="table table-striped mt-3">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Groupe</th>
                                        <th>Ligne</th>
                                        <th>Montant</th>
                                    </tr>
                                </thead>
                                <tbody id="summaryTableBody">
                                    <!-- Dynamic content will be inserted here -->
                                </tbody>
                            </table>
                            <div class="mt-3">
                                <p><strong>Solde de Report:</strong> <span id="soldeReport"></span> $</p>
                                <p><strong>Total Entrées:</strong> <span id="totalEntrees"></span> $</p>
                                <p><strong>Total Sorties:</strong> <span id="totalSorties"></span> $</p>
                                <p><strong>Solde Final:</strong> <span id="soldeFinal"></span> $</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let financialChart; // Declare the chart variable outside the function

function updateDashboard() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const structureId = document.getElementById('structureId').value;

    if (!startDate || !endDate || !structureId) {
        Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs.'
            });
        return;
    }

    fetch('controller/getFinancialData.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ startDate, endDate, structureId })
    })
    .then(response => response.json())
    .then(data => {
        updateChart(data.chartData);
        updateSummaryTable(data);
        document.getElementById('soldeReport').textContent = data.soldeReport.toFixed(2);
        document.getElementById('totalEntrees').textContent = data.totalEntrees.toFixed(2);
        document.getElementById('totalSorties').textContent = data.totalSorties.toFixed(2);
        document.getElementById('soldeFinal').textContent = data.soldeFinal.toFixed(2);
    })
    .catch(error => console.error('Erreur:', error));
}

function updateChart(chartData) {
    const ctx = document.getElementById('financialChart').getContext('2d');

    // Destroy the previous chart instance if it exists
    if (financialChart) {
        financialChart.destroy();
    }

    financialChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Montant',
                data: chartData.values,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateSummaryTable(data) {
    const tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = ''; // Clear existing table data
    data.tableData.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.type}</td>
            <td>${row.groupe}</td>
            <td>${row.ligne}</td>
            <td>${row.montant}</td>
        `;
        tbody.appendChild(tr);
    });

    // Add client payments and supplier payments to the table
    const clientPaymentsRow = document.createElement('tr');
    clientPaymentsRow.innerHTML = `
        <td>Entrée</td>
        <td>Clients</td>
        <td>Paiements Clients</td>
        <td>${data.clientPayments.toFixed(2)}</td>
    `;
    tbody.appendChild(clientPaymentsRow);

    const supplierPaymentsRow = document.createElement('tr');
    supplierPaymentsRow.innerHTML = `
        <td>Sortie</td>
        <td>Fournisseurs</td>
        <td>Paiements Fournisseurs</td>
        <td>${data.supplierPayments.toFixed(2)}</td>
    `;
    tbody.appendChild(supplierPaymentsRow);
}
</script>

<?php include "./views/include/footer.php"; ?>