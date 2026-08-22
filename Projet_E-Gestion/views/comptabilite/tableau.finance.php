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

                        <!-- Form to select parameters -->
                        <form id="dashboardForm">
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
                            </div>
                            <button type="button" class="btn btn-primary" onclick="updateDashboard()">
                                <i class="bi bi-bar-chart"></i> Mettre à jour le Tableau de Bord
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
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function updateDashboard() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const structureId = document.getElementById('structureId').value;

    if (!startDate || !endDate || !structureId) {
        alert('Veuillez remplir tous les champs.');
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
        updateSummaryTable(data.tableData);
    })
    .catch(error => console.error('Erreur:', error));
}

function updateChart(chartData) {
    const ctx = document.getElementById('financialChart').getContext('2d');
    new Chart(ctx, {
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

function updateSummaryTable(tableData) {
    const tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';
    tableData.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.type}</td>
            <td>${row.groupe}</td>
            <td>${row.ligne}</td>
            <td>${row.montant}</td>
        `;
        tbody.appendChild(tr);
    });
}
</script>

<?php include "./views/include/footer.php"; ?>