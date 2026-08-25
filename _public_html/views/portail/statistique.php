<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les statistiques globales
// Nombre total de travaux
$queryTotalTravaux = "SELECT COUNT(*) as total FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé'";
$stmtTotalTravaux = $db->prepare($queryTotalTravaux);
$stmtTotalTravaux->execute();
$totalTravaux = $stmtTotalTravaux->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre total d'auteurs
$queryTotalAuteurs = "SELECT COUNT(DISTINCT nom_auteur) as total FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé'";
$stmtTotalAuteurs = $db->prepare($queryTotalAuteurs);
$stmtTotalAuteurs->execute();
$totalAuteurs = $stmtTotalAuteurs->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre total d'orientations
$queryTotalOrientations = "SELECT COUNT(DISTINCT orientation_id) as total FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé' AND orientation_id IS NOT NULL";
$stmtTotalOrientations = $db->prepare($queryTotalOrientations);
$stmtTotalOrientations->execute();
$totalOrientations = $stmtTotalOrientations->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre total de consultations
$queryTotalConsultations = "SELECT COUNT(*) as total FROM consultations";
$stmtTotalConsultations = $db->prepare($queryTotalConsultations);
$stmtTotalConsultations->execute();
$totalConsultations = $stmtTotalConsultations->fetch(PDO::FETCH_ASSOC)['total'];

// Récupérer les statistiques par type de document
$queryStatsParType = "SELECT type_document, COUNT(*) as count FROM travaux_scientifiques 
                      WHERE est_public = 1 AND statut = 'Validé' 
                      GROUP BY type_document";
$stmtStatsParType = $db->prepare($queryStatsParType);
$stmtStatsParType->execute();
$statsParTypeData = $stmtStatsParType->fetchAll(PDO::FETCH_ASSOC);

// Formater les données pour le graphique
$statsParType = [
    'Thèse' => 0,
    'Mémoire' => 0,
    'Article scientifique' => 0,
    'Projet tutoré' => 0,
    'Rapport de stage' => 0,
    'Livre' => 0,
    'Cours' => 0
];

foreach ($statsParTypeData as $stat) {
    $statsParType[$stat['type_document']] = (int)$stat['count'];
}

// Récupérer les statistiques par orientation
$queryStatsParOrientation = "SELECT o.\"designationOrientation\", COUNT(t.id) as count 
                            FROM travaux_scientifiques t
                            JOIN orientation o ON t.orientation_id = o.idorientation
                            WHERE t.est_public = 1 AND t.statut = 'Validé' 
                            GROUP BY t.orientation_id, o.\"designationOrientation\"
                            ORDER BY count DESC";
$stmtStatsParOrientation = $db->prepare($queryStatsParOrientation);
$stmtStatsParOrientation->execute();
$statsParOrientation = $stmtStatsParOrientation->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques par année
$queryStatsParAnnee = "SELECT EXTRACT(YEAR FROM date_depot)::int as annee, COUNT(*) as count
                       FROM travaux_scientifiques
                       WHERE est_public = 1 AND statut = 'Validé'
                       GROUP BY EXTRACT(YEAR FROM date_depot)
                       ORDER BY EXTRACT(YEAR FROM date_depot) DESC";
$stmtStatsParAnnee = $db->prepare($queryStatsParAnnee);
$stmtStatsParAnnee->execute();
$statsParAnneeData = $stmtStatsParAnnee->fetchAll(PDO::FETCH_ASSOC);

// Formater les données pour le graphique
$statsParAnnee = [];
foreach ($statsParAnneeData as $stat) {
    $statsParAnnee[$stat['annee']] = (int)$stat['count'];
}

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Statistiques</li>
            </ol>
        </nav>
    </div>

    <!-- En-tête statistiques globales -->
    <div class="bg-primary text-white py-4 mb-4">
        <div class="container">
            <h1 class="h2 mb-4">Statistiques de la bibliothèque numérique</h1>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="display-4 mb-2"><?= number_format($totalTravaux) ?></div>
                        <div>Travaux publiés</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="display-4 mb-2"><?= number_format($totalAuteurs) ?></div>
                        <div>Auteurs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="display-4 mb-2"><?= number_format($totalOrientations) ?></div>
                        <div>Orientations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="display-4 mb-2"><?= number_format($totalConsultations) ?></div>
                        <div>Consultations</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row g-4">
            <!-- Répartition par type -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            Répartition par type de document
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Évolution par année -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Évolution des publications
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="evolutionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top orientations -->
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-university me-2"></i>
                            Publications par orientation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($statsParOrientation as $orientation): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-truncate me-3">
                                        <?= htmlspecialchars($orientation['designationOrientation']) ?>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="width: 100px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($orientation['count'] / $totalTravaux * 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="badge bg-primary"><?= $orientation['count'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Configuration des graphiques
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des types de documents
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($statsParType)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statsParType)) ?>,
                backgroundColor: [
                    '#0d6efd', // Thèses
                    '#198754', // Mémoires
                    '#dc3545', // Articles
                    '#ffc107', // Projets
                    '#6c757d', // Rapports
                    '#20c997', // Livres
                    '#6610f2'  // Cours
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        // Ajuster la taille de la légende si nécessaire
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Graphique d'évolution
    const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($statsParAnnee)) ?>,
            datasets: [{
                label: 'Publications',
                data: <?= json_encode(array_values($statsParAnnee)) ?>,
                borderColor: '#0d6efd',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<style>
.chart-container {
    position: relative;
    margin: auto;
}

.progress {
    height: 8px;
    background-color: #e9ecef;
}

.progress-bar {
    background-color: #0d6efd;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
}

.bg-primary {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #0056b3 100%);
}
</style>

<?php include 'footer.php'; ?>
