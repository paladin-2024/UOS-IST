<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'dashboard';

// Récupérer les statistiques de base pour le dashboard
$db = Connexion::getInstance()->getPDO();

// Nombre d'articles
$stmt = $db->query("SELECT COUNT(*) as total FROM news");
$newsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre de pages
$stmt = $db->query("SELECT COUNT(*) as total FROM pages");
$pagesCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre de formations
$stmt = $db->query("SELECT COUNT(*) as total FROM formations");
$formationsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre de membres du personnel
$stmt = $db->query("SELECT COUNT(*) as total FROM staff");
$staffCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre de messages de contact non lus
$stmt = $db->query("SELECT COUNT(*) as total FROM contact_submissions WHERE is_read = 0");
$unreadMessages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre d'utilisateurs
$stmt = $db->query("SELECT COUNT(*) as total FROM users");
$usersCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Derniers messages de contact
$stmt = $db->query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5");
$recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Derniers articles
$stmt = $db->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 5");
$recentNews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour le graphique des visites: nombre d'articles par mois sur l'année en cours
$currentYear = date('Y');
$visitData = [];
$visitLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

// Requête pour compter le nombre d'articles publiés par mois pour l'année en cours
$stmt = $db->prepare("SELECT EXTRACT(MONTH FROM created_at) as month, COUNT(*) as count
                      FROM news
                      WHERE EXTRACT(YEAR FROM created_at) = ?
                      GROUP BY EXTRACT(MONTH FROM created_at)
                      ORDER BY month");
$stmt->execute([$currentYear]);
$monthlyNews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialiser le tableau avec des zéros
for ($i = 1; $i <= 12; $i++) {
    $visitData[$i] = 0;
}

// Remplir avec les données réelles
foreach ($monthlyNews as $item) {
    $visitData[$item['month']] = $item['count'];
}

// Convertir en tableau simple pour JavaScript
$visitDataArray = array_values($visitData);

// Données pour le graphique du personnel par département
$stmt = $db->query("SELECT department, COUNT(*) as count 
                    FROM staff 
                    GROUP BY department
                    ORDER BY count DESC
                    LIMIT 4");
$departmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departmentLabels = [];
$departmentCounts = [];

foreach ($departmentData as $dept) {
    $departmentLabels[] = $dept['department'] ?: 'Non spécifié';
    $departmentCounts[] = $dept['count'];
}

// Si on a moins de 4 départements, on ajoute des valeurs par défaut
if (count($departmentLabels) < 4) {
    $defaultDepts = ['Administration', 'Enseignement', 'Technique', 'Recherche'];
    $defaultCounts = [0, 0, 0, 0];
    
    for ($i = count($departmentLabels); $i < 4; $i++) {
        $departmentLabels[] = $defaultDepts[$i];
        $departmentCounts[] = $defaultCounts[$i];
    }
}



// Inclure le header
include_once './views/admin/include/header.php';
?>


<!-- Contenu du dashboard -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Tableau de bord</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-export me-1"></i>Exporter
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center">
            <i class="fas fa-calendar-alt me-1"></i>
            Cette semaine
        </button>
    </div>
</div>

<!-- Vue d'ensemble -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-white p-3 mb-4">
            <h5 class="card-title text-primary mb-3">Vue d'ensemble</h5>
            <p class="text-muted">Bienvenue dans votre tableau de bord d'administration ISTM Beni. Surveillez les statistiques clés et gérez facilement votre site.</p>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(($newsCount / 10) * 100, 100); ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques en cartes -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div>
                    <h5 class="card-title">Actualités</h5>
                    <h2 class="mb-0"><?php echo $newsCount; ?></h2>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="news" class="text-white text-decoration-none">Voir détails</a>
                <i class="fas fa-arrow-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card stat-card bg-success text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <h5 class="card-title">Pages</h5>
                    <h2 class="mb-0"><?php echo $pagesCount; ?></h2>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="pages" class="text-white text-decoration-none">Voir détails</a>
                <i class="fas fa-arrow-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card stat-card bg-warning text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h5 class="card-title">Formations</h5>
                    <h2 class="mb-0"><?php echo $formationsCount; ?></h2>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="formations" class="text-white text-decoration-none">Voir détails</a>
                <i class="fas fa-arrow-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card stat-card bg-danger text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <h5 class="card-title">Messages</h5>
                    <h2 class="mb-0"><?php echo $unreadMessages; ?> <small>non lus</small></h2>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="contact" class="text-white text-decoration-none">Voir détails</a>
                <i class="fas fa-arrow-right text-white"></i>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <!-- Derniers messages de contact -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-envelope me-2 text-primary"></i>Derniers messages</h5>
                <a href="contact" class="btn btn-sm btn-outline-primary">Voir tous</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMessages)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>Aucun message récent</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentMessages as $message): ?>
                            <a href="contact&id=<?php echo $message['id']; ?>" class="list-group-item list-group-item-action <?php echo $message['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 <?php echo $message['is_read'] ? '' : 'fw-bold'; ?>">
                                        <?php echo htmlspecialchars($message['name']); ?>
                                        <?php if (!$message['is_read']): ?><span class="badge bg-primary ms-2">Nouveau</span><?php endif; ?>
                                    </h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($message['message'], 0, 80)) . '...'; ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Derniers articles -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-newspaper me-2 text-primary"></i>Dernières actualités</h5>
                <a href="news" class="btn btn-sm btn-outline-primary">Voir toutes</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentNews)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-newspaper fa-3x mb-3"></i>
                        <p>Aucune actualité récente</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Titre</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentNews as $news): ?>
                                    <tr>
                                        <td class="text-nowrap text-truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($news['title']); ?>
                                        </td>
                                        <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($news['created_at'])); ?></td>
                                        <td>
                                            <?php if ($news['is_published']): ?>
                                                <span class="badge bg-success">Publié</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Brouillon</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="news/edit&id=<?php echo $news['id']; ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="news/preview&id=<?php echo $news['id']; ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Aperçu">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Liens rapides -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link me-2 text-primary"></i>Liens rapides</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <a href="news" class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center">
                            <i class="fas fa-plus-circle fa-2x mb-2"></i>
                            Ajouter un article
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="pages" class="btn btn-outline-success w-100 p-3 d-flex flex-column align-items-center">
                            <i class="fas fa-file-medical fa-2x mb-2"></i>
                            Créer une page
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="gallery" class="btn btn-outline-info w-100 p-3 d-flex flex-column align-items-center">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            Téléverser des médias
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des visites (basé sur les publications par mois)
    const visitsCtx = document.getElementById('visitsChart').getContext('2d');
    const visitsChart = new Chart(visitsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($visitLabels); ?>,
            datasets: [{
                label: 'Publications',
                data: <?php echo json_encode($visitDataArray); ?>,
                backgroundColor: 'rgba(0, 51, 102, 0.1)',
                borderColor: 'rgba(0, 51, 102, 0.8)',
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(0, 51, 102, 0.8)',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    cornerRadius: 4,
                    caretSize: 6
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Graphique pour le personnel par département
    const staffCtx = document.getElementById('staffChart').getContext('2d');
    const staffChart = new Chart(staffCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($departmentLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($departmentCounts); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>


<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>

