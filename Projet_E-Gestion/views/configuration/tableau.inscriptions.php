<?php
include "./views/include/header.php";
$universite = new Universite();

// Récupérer l'année académique sélectionnée, ou la plus récente par défaut
$academicYears = $universite->getAcademicYears();
$selectedYear = isset($_GET['annee']) ? $_GET['annee'] : (count($academicYears) > 0 ? $academicYears[0]['idannee_acad'] : null);

// Récupérer la section sélectionnée, ou toutes par défaut
$sections = $universite->getSections('', $selectedYear); // Filtrer par année académique
$selectedSection = isset($_GET['section']) ? $_GET['section'] : 'all';

// Récupérer les statistiques d'inscription
$stats = []; // Vous devrez créer une méthode pour récupérer ces données
if ($selectedYear) {
    if ($selectedSection === 'all') {
        $stats = $universite->getInscriptionsStatsByYear($selectedYear);
    } else {
        $stats = $universite->getInscriptionsStatsBySectionAndYear($selectedSection, $selectedYear);
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>STATISTIQUES DES INSCRIPTIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Étudiants</li>
                <li class="breadcrumb-item active">Statistiques des inscriptions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Lien vers le tableau des choix préparatoires -->
            <div class="col-lg-12 mb-3">
                <a href="?view=configuration/tableau.preparatoire" class="btn btn-primary">
                    <i class="bi bi-bar-chart-line"></i> Voir les statistiques des choix préparatoires
                </a>
            </div>
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="configuration/tableau.inscriptions">
                            
                            <div class="col-md-5">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select">
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $selectedYear == $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= $year['designation'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="section" class="form-label">Section</label>
                                <select name="section" id="section" class="form-select">
                                    <option value="all" <?= $selectedSection == 'all' ? 'selected' : '' ?>>Toutes les sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                            <?= $section['designationSection'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                                <a href="controller/export_stats_inscriptions.php?annee=<?= $selectedYear ?>&section=<?= $selectedSection ?>" class="btn btn-success" title="Exporter en Excel">
                                    <i class="bi bi-file-earmark-excel"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Résumé statistique -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Total étudiants -->
                    <div class="col-xxl-4 col-md-6">
                        <div class="card info-card sales-card">
                            <div class="card-body">
                                <h5 class="card-title">Total des étudiants inscrits</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= isset($stats['total']) ? $stats['total'] : 0 ?></h6>
                                        <span class="text-muted small pt-2">étudiants</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Étudiants masculins -->
                    <div class="col-xxl-4 col-md-3">
                        <div class="card info-card customers-card">
                            <div class="card-body">
                                <h5 class="card-title">Masculin</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-gender-male"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= isset($stats['masculin']) ? $stats['masculin'] : 0 ?></h6>
                                        <span class="text-muted small pt-2">étudiants</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Étudiants féminins -->
                    <div class="col-xxl-4 col-md-3">
                        <div class="card info-card revenue-card">
                            <div class="card-body">
                                <h5 class="card-title">Féminin</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-gender-female"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= isset($stats['feminin']) ? $stats['feminin'] : 0 ?></h6>
                                        <span class="text-muted small pt-2">étudiants</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par sexe</h5>
                        <canvas id="genderChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par promotion</h5>
                        <canvas id="promotionChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tableau détaillé -->
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques détaillées par promotion</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Promotion</th>
                                    <th>Total</th>
                                    <th>Masculin</th>
                                    <th>Féminin</th>
                                    <th>% Masculin</th>
                                    <th>% Féminin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($stats['promotions']) && is_array($stats['promotions'])): ?>
                                    <?php foreach ($stats['promotions'] as $promotion): ?>
                                        <tr>
                                            <td><?= $promotion['designationPromotion'] ?></td>
                                            <td><?= $promotion['total'] ?></td>
                                            <td><?= $promotion['masculin'] ?></td>
                                            <td><?= $promotion['feminin'] ?></td>
                                            <td><?= number_format(($promotion['masculin'] / $promotion['total']) * 100, 1) ?>%</td>
                                            <td><?= number_format(($promotion['feminin'] / $promotion['total']) * 100, 1) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucune donnée disponible</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    // Données pour le graphique de répartition par sexe
    const genderData = {
        labels: ['Masculin', 'Féminin'],
        datasets: [{
            label: 'Nombre d\'étudiants',
            data: [
                <?= isset($stats['masculin']) ? $stats['masculin'] : 0 ?>,
                <?= isset($stats['feminin']) ? $stats['feminin'] : 0 ?>
            ],
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 99, 132, 0.6)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)'
            ],
            borderWidth: 1
        }]
    };

    // Configuration du graphique de répartition par sexe
    const genderConfig = {
        type: 'pie',
        data: genderData,
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

    // Initialisation du graphique de répartition par sexe
    const genderChart = new Chart(
        document.getElementById('genderChart'),
        genderConfig
    );

    // Données pour le graphique de répartition par promotion
    <?php if (isset($stats['promotions']) && is_array($stats['promotions'])): ?>
    const promotionData = {
        labels: [<?= implode(', ', array_map(function($item) { return '"' . $item['designationPromotion'] . '"'; }, $stats['promotions'])) ?>],
        datasets: [{
            label: 'Nombre d\'étudiants',
            data: [<?= implode(', ', array_map(function($item) { return $item['total']; }, $stats['promotions'])) ?>],
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    };

    // Configuration du graphique de répartition par promotion
    const promotionConfig = {
        type: 'bar',
        data: promotionData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    };

    // Initialisation du graphique de répartition par promotion
    const promotionChart = new Chart(
        document.getElementById('promotionChart'),
        promotionConfig
    );
    <?php endif; ?>
});
</script>

<?php include "./views/include/footer.php"; ?>
