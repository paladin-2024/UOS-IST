<?php
include "./views/include/header.php";

// Récupération des données nécessaires
$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Statistiques générales
$statsQuery = "
    SELECT 
        COUNT(*) as total_visites,
        COUNT(CASE WHEN statut_visite = 'programmee' THEN 1 END) as visites_programmees,
        COUNT(CASE WHEN statut_visite = 'en_cours' THEN 1 END) as visites_en_cours,
        COUNT(CASE WHEN statut_visite = 'terminee' THEN 1 END) as visites_terminees,
        COUNT(CASE WHEN statut_visite = 'annulee' THEN 1 END) as visites_annulees,
        COUNT(CASE WHEN DATE(date_visite) = CURDATE() THEN 1 END) as visites_aujourdhui,
        COUNT(CASE WHEN DATE(date_visite) = CURDATE() + INTERVAL 1 DAY THEN 1 END) as visites_demain
    FROM visites 
    WHERE cree_par = ?
";
$stmt = $db->prepare($statsQuery);
$stmt->execute([$userId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Visites par type
$typeQuery = "
    SELECT 
        type_visite,
        COUNT(*) as nombre
    FROM visites 
    WHERE cree_par = ?
    GROUP BY type_visite
";
$stmt = $db->prepare($typeQuery);
$stmt->execute([$userId]);
$visitesByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Visites par mois (derniers 6 mois)
$monthlyQuery = "
    SELECT 
        DATE_FORMAT(date_visite, '%Y-%m') as mois,
        COUNT(*) as nombre
    FROM visites 
    WHERE cree_par = ? 
    AND date_visite >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_visite, '%Y-%m')
    ORDER BY mois
";
$stmt = $db->prepare($monthlyQuery);
$stmt->execute([$userId]);
$monthlyVisites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Visites du jour
$todayQuery = "
    SELECT v.*, a.noms as nom_agent, s.designation as nom_service
    FROM visites v
    LEFT JOIN agent a ON v.Agent_idAgent = a.idAgent
    LEFT JOIN service s ON v.Service_idService = s.idService
    WHERE v.cree_par = ? AND DATE(v.date_visite) = CURDATE()
    ORDER BY v.heure_debut
";
$stmt = $db->prepare($todayQuery);
$stmt->execute([$userId]);
$visitesToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Visites récentes
$recentQuery = "
    SELECT v.*, a.noms as nom_agent, s.designation as nom_service
    FROM visites v
    LEFT JOIN agent a ON v.Agent_idAgent = a.idAgent
    LEFT JOIN service s ON v.Service_idService = s.idService
    WHERE v.cree_par = ?
    ORDER BY v.date_creation DESC
    LIMIT 10
";
$stmt = $db->prepare($recentQuery);
$stmt->execute([$userId]);
$recentVisites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Visites par service
$serviceQuery = "
    SELECT 
        s.designation as service_nom,
        COUNT(v.idVisite) as nombre_visites
    FROM service s
    LEFT JOIN visites v ON s.idService = v.Service_idService AND v.cree_par = ?
    WHERE s.Structure_idStructure IN (
        SELECT DISTINCT idStructure FROM agent WHERE idAgent = 
        (SELECT idAgent FROM t_users WHERE idUser = ?)
    )
    GROUP BY s.idService, s.designation
    ORDER BY nombre_visites DESC
";
$stmt = $db->prepare($serviceQuery);
$stmt->execute([$userId, $userId]);
$serviceVisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Dashboard des Visites</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Dashboard Visites</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Statistiques générales -->
        <div class="row">
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Visites</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['total_visites'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Aujourd'hui</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['visites_aujourdhui'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">En Cours</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['visites_en_cours'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Programmées</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['visites_programmees'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row">
            <!-- Graphique par statut -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par Statut</h5>
                        <canvas id="statusChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Graphique par type -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par Type</h5>
                        <canvas id="typeChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Évolution mensuelle -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Évolution des Visites (6 derniers mois)</h5>
                        <canvas id="monthlyChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visites du jour et récentes -->
        <div class="row">
            <!-- Visites du jour -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Visites d'Aujourd'hui</h5>
                        <div class="activity">
                            <?php if (empty($visitesToday)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucune visite programmée aujourd'hui.
                                </div>
                            <?php else: ?>
                                <?php foreach ($visitesToday as $visite): ?>
                                    <div class="activity-item d-flex">
                                        <div class="activite-label">
                                            <?= date('H:i', strtotime($visite['heure_debut'])) ?>
                                        </div>
                                        <i class="bi bi-circle-fill activity-badge 
                                            <?= $visite['statut_visite'] == 'terminee' ? 'text-success' : 
                                                ($visite['statut_visite'] == 'en_cours' ? 'text-warning' : 'text-primary') ?> 
                                            align-self-start"></i>
                                        <div class="activity-content">
                                            <strong><?= htmlspecialchars($visite['nom_visiteur'] . ' ' . $visite['prenom_visiteur']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($visite['entreprise_visiteur']) ?> - 
                                                <?= htmlspecialchars($visite['nom_agent']) ?>
                                            </small>
                                            <br>
                                            <span class="badge 
                                                <?= $visite['statut_visite'] == 'terminee' ? 'bg-success' : 
                                                    ($visite['statut_visite'] == 'en_cours' ? 'bg-warning' : 'bg-primary') ?>">
                                                <?= ucfirst($visite['statut_visite']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visites récentes -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Visites Récentes</h5>
                        <div class="news">
                            <?php foreach ($recentVisites as $visite): ?>
                                                                <div class="post-item clearfix">
                                    <h4><a href="#" onclick="voirDetails(<?= $visite['idVisite'] ?>)">
                                        <?= htmlspecialchars($visite['nom_visiteur'] . ' ' . $visite['prenom_visiteur']) ?>
                                    </a></h4>
                                    <p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar3"></i> 
                                            <?= date('d/m/Y à H:i', strtotime($visite['date_visite'])) ?>
                                        </small>
                                        <br>
                                        <small>
                                            <i class="bi bi-building"></i> 
                                            <?= htmlspecialchars($visite['entreprise_visiteur']) ?>
                                        </small>
                                        <br>
                                        <small>
                                            <i class="bi bi-person"></i> 
                                            Agent: <?= htmlspecialchars($visite['nom_agent']) ?>
                                        </small>
                                        <br>
                                        <span class="badge 
                                            <?php 
                                            switch($visite['statut_visite']) {
                                                case 'programmee': echo 'bg-info'; break;
                                                case 'en_cours': echo 'bg-warning'; break;
                                                case 'terminee': echo 'bg-success'; break;
                                                case 'annulee': echo 'bg-danger'; break;
                                                case 'reportee': echo 'bg-secondary'; break;
                                                default: echo 'bg-light text-dark';
                                            }
                                            ?>">
                                            <?= ucfirst($visite['statut_visite']) ?>
                                        </span>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visites par service -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Visites par Service</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Nombre de Visites</th>
                                        <th>Progression</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxVisites = !empty($serviceVisites) ? max(array_column($serviceVisites, 'nombre_visites')) : 1;
                                    foreach ($serviceVisites as $service): 
                                        $percentage = $maxVisites > 0 ? ($service['nombre_visites'] / $maxVisites) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($service['service_nom']) ?></td>
                                            <td><span class="badge bg-primary"><?= $service['nombre_visites'] ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" 
                                                         style="width: <?= $percentage ?>%" 
                                                         aria-valuenow="<?= $percentage ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Actions Rapides</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary w-100 mb-2" 
                                        onclick="window.location.href='reception/visites.add'">
                                    <i class="bi bi-plus-circle"></i> Nouvelle Visite
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-success w-100 mb-2" 
                                        onclick="window.location.href='reception/visites.add'">
                                    <i class="bi bi-list-ul"></i> Voir Toutes les Visites
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-info w-100 mb-2" 
                                        onclick="exportVisites()">
                                    <i class="bi bi-download"></i> Exporter Rapport
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-warning w-100 mb-2" 
                                        onclick="window.print()">
                                    <i class="bi bi-printer"></i> Imprimer Dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal de détails de visite -->
    <div class="modal fade" id="detailsVisiteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de la Visite</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsVisiteContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Scripts pour les graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données PHP vers JavaScript
    const statsData = <?= json_encode($stats) ?>;
    const typeData = <?= json_encode($visitesByType) ?>;
    const monthlyData = <?= json_encode($monthlyVisites) ?>;

    // Graphique par statut (Doughnut)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Programmées', 'En Cours', 'Terminées', 'Annulées'],
            datasets: [{
                data: [
                    statsData.visites_programmees,
                    statsData.visites_en_cours,
                    statsData.visites_terminees,
                    statsData.visites_annulees
                ],
                backgroundColor: [
                    '#0d6efd',
                    '#ffc107',
                    '#198754',
                    '#dc3545'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Graphique par type (Pie)
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    const typeLabels = typeData.map(item => {
        switch(item.type_visite) {
            case 'professionnelle': return 'Professionnelle';
            case 'personnelle': return 'Personnelle';
            case 'officielle': return 'Officielle';
            case 'urgente': return 'Urgente';
            default: return item.type_visite;
        }
    });
    const typeValues = typeData.map(item => item.nombre);

    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: [
                    '#0d6efd',
                    '#6610f2',
                    '#6f42c1',
                    '#d63384'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Graphique mensuel (Line)
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthLabels = monthlyData.map(item => {
        const date = new Date(item.mois + '-01');
        return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' });
    });
    const monthValues = monthlyData.map(item => item.nombre);

    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Nombre de visites',
                data: monthValues,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Actualisation automatique toutes les 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});

// Fonction pour voir les détails d'une visite
function voirDetails(visiteId) {
    fetch('controller/getVisiteDetails.php?id=' + visiteId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailsVisiteContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('detailsVisiteModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire('Erreur', 'Impossible de charger les détails', 'error');
        });
}

// Fonction d'export
function exportVisites() {
    const dateDebut = prompt('Date de début (YYYY-MM-DD):');
    const dateFin = prompt('Date de fin (YYYY-MM-DD):');
    
    if (dateDebut && dateFin) {
        window.open(`controller/exportVisites.php?debut=${dateDebut}&fin=${dateFin}`, '_blank');
    }
}

// Fonction de mise à jour en temps réel
function updateDashboard() {
    fetch('controller/getDashboardData.php')
        .then(response => response.json())
        .then(data => {
            // Mettre à jour les statistiques
            document.querySelectorAll('.card-body h6').forEach((element, index) => {
                switch(index) {
                    case 0: element.textContent = data.total_visites; break;
                    case 1: element.textContent = data.visites_aujourdhui; break;
                    case 2: element.textContent = data.visites_en_cours; break;
                    case 3: element.textContent = data.visites_programmees; break;
                }
            });
        })
        .catch(error => {
            console.error('Erreur lors de la mise à jour:', error);
        });
}

// Notification pour les nouvelles visites
function checkNewVisites() {
    fetch('controller/checkNewVisites.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNew) {
                // Afficher une notification toast
                const toast = document.createElement('div');
                toast.className = 'toast show position-fixed top-0 end-0 m-3';
                toast.innerHTML = `
                    <div class="toast-header">
                        <i class="bi bi-bell-fill text-primary me-2"></i>
                        <strong class="me-auto">Nouvelle visite</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${data.message}
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Supprimer le toast après 5 secondes
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }
        });
}

// Vérifier les nouvelles visites toutes les 30 secondes
setInterval(checkNewVisites, 30000);
</script>

<style>
.info-card {
    border-left: 4px solid #0d6efd;
}

.revenue-card {
    border-left: 4px solid #198754;
}

.customers-card {
    border-left: 4px solid #ffc107;
}

.card-icon {
    color: #0d6efd;
    background: rgba(13, 110, 253, 0.1);
}

.revenue-card .card-icon {
    color: #198754;
    background: rgba(25, 135, 84, 0.1);
}

.customers-card .card-icon {
    color: #ffc107;
    background: rgba(255, 193, 7, 0.1);
}

.activity-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activite-label {
    color: #899bbd;
    position: relative;
    flex-shrink: 0;
    flex-grow: 0;
    min-width: 64px;
}

.activity-badge {
    margin-top: 3px;
    z-index: 1;
    font-size: 11px;
    line-height: 0;
    border-radius: 50%;
    flex-shrink: 0;
        border: 3px solid #fff;
    margin-left: 6px;
    margin-right: 10px;
    margin-top: 0;
}

.activity-content {
    padding-left: 10px;
    flex-grow: 1;
}

.post-item {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.post-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.progress {
    height: 6px !important;
}

.card {
    box-shadow: 0px 0 30px rgba(1, 41, 112, 0.1);
    border: 0;
    border-radius: 10px;
}

.card-title {
    padding: 20px 0 15px 0;
    font-size: 18px;
    font-weight: 500;
    color: #012970;
    font-family: "Poppins", sans-serif;
}

@media print {
    .btn, .modal, .breadcrumb {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}

.toast {
    z-index: 1055;
}

.chart-container {
    position: relative;
    height: 400px;
    margin: 20px 0;
}

/* Animation pour les cartes de statistiques */
.info-card:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
    box-shadow: 0px 4px 40px rgba(1, 41, 112, 0.15);
}

/* Style pour les badges de statut */
.badge {
    font-size: 0.75em;
}

/* Responsive design */
@media (max-width: 768px) {
    .card-title {
        font-size: 16px;
    }
    
    .info-card .card-icon {
        width: 60px;
        height: 60px;
    }
    
    .info-card h6 {
        font-size: 24px;
    }
}
</style>

<?php include "./views/include/footer.php"; ?>

