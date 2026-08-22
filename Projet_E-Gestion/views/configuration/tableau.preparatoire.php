<?php
include "./views/include/header.php";
$universite = new Universite();

// Récupérer l'année académique sélectionnée, ou la plus récente par défaut
$academicYears = $universite->getAcademicYears();
$selectedYear = isset($_GET['annee']) ? $_GET['annee'] : (count($academicYears) > 0 ? $academicYears[0]['idannee_acad'] : null);

// Récupérer les statistiques des choix préparatoires
$stats = [];
if ($selectedYear) {
    $stats = $universite->getPreparatoireStatsByYear($selectedYear);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD DES CHOIX PRÉPARATOIRES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Étudiants</li>
                <li class="breadcrumb-item active">Choix préparatoires</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="configuration/tableau.preparatoire">
                            
                            <div class="col-md-10">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select">
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $selectedYear == $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= $year['designation'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Graphique et tableau des préparatoires -->
            <div class="row">
                <!-- Graphique -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Répartition par classe préparatoire</h5>
                            <canvas id="preparatoireChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tableau détaillé -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Nombre d'étudiants par classe préparatoire</h5>
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Classe préparatoire</th>
                                        <th>Nombre d'étudiants</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($stats['classes']) && is_array($stats['classes'])): ?>
                                        <?php foreach ($stats['classes'] as $classe): ?>
                                            <tr>
                                                <td><?= $classe['designation'] ?></td>
                                                <td><?= $classe['total'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center">Aucune donnée disponible</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Script pour les graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données pour le graphique de répartition par classe préparatoire
    <?php if (isset($stats['classes']) && is_array($stats['classes'])): ?>
    const preparatoireData = {
        labels: [<?= implode(', ', array_map(function($item) { return '"' . $item['designation'] . '"'; }, $stats['classes'])) ?>],
        datasets: [{
            label: 'Nombre d\'étudiants',
            data: [<?= implode(', ', array_map(function($item) { return $item['total']; }, $stats['classes'])) ?>],
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 99, 132, 0.6)',
                'rgba(75, 192, 192, 0.6)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)'
            ],
            borderWidth: 1
        }]
    };

    // Configuration du graphique de répartition par classe préparatoire
    const preparatoireConfig = {
        type: 'pie',
        data: preparatoireData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                            return `${label}: ${value} (${percentage})`;
                        }
                    }
                }
            }
        }
    };

    // Initialisation du graphique de répartition par classe préparatoire
    const preparatoireChart = new Chart(
        document.getElementById('preparatoireChart'),
        preparatoireConfig
    );
    <?php endif; ?>
});
</script>

<?php include "./views/include/footer.php"; ?>
