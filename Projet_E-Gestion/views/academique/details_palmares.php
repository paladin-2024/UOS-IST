<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'ID du palmarès
$idPalmares = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPalmares <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Palmarès non spécifié.'
        }).then(() => {
            window.location.href = '?view=academique/palmares';
        });
    </script>";
    exit;
}

// Récupérer les données du palmarès
$query = "SELECT pa.*, u.nomUser as nom_utilisateur 
          FROM palmares_archive pa
          LEFT JOIN t_users u ON pa.idUser = u.idUser
          WHERE pa.id_palmares = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$idPalmares]);
$palmares = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$palmares) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Palmarès non trouvé.'
        }).then(() => {
            window.location.href = '?view=academique/palmares';
        });
    </script>";
    exit;
}

// Récupérer les étudiants associés à ce palmarès
$query = "SELECT * FROM palmares_etudiant WHERE id_palmares = ? ORDER BY rang";
$stmt = $pdo->prepare($query);
$stmt->execute([$idPalmares]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$stats = [
    'count' => count($etudiants),
    'avg_percentage' => 0,
    'min_percentage' => 100,
    'max_percentage' => 0,
    'mentions' => [],
    'avg_credits' => 0,
    'total_credits' => 0,
    'pass_rate' => 0
];

if (!empty($etudiants)) {
    $totalPercentage = 0;
    $totalCredits = 0;
    $validCredits = 0;
    $passing = 0;
    
    foreach ($etudiants as $etudiant) {
        // Pourcentages
        $percentage = floatval($etudiant['pourcentage']);
        $totalPercentage += $percentage;
        $stats['min_percentage'] = min($stats['min_percentage'], $percentage);
        $stats['max_percentage'] = max($stats['max_percentage'], $percentage);
        
        // Mentions
        $mention = $etudiant['mention'] ?: 'Non spécifiée';
        if (!isset($stats['mentions'][$mention])) {
            $stats['mentions'][$mention] = 0;
        }
        $stats['mentions'][$mention]++;
        
        // Crédits
        if (!empty($etudiant['credit_obtenu']) && !empty($etudiant['credit_total'])) {
            $totalCredits += $etudiant['credit_total'];
            $validCredits += $etudiant['credit_obtenu'];
        }
        
        // Taux de réussite (>= 50%)
        if ($percentage >= 50) {
            $passing++;
        }
    }
    
    $stats['avg_percentage'] = $totalPercentage / $stats['count'];
    $stats['avg_credits'] = $totalCredits > 0 ? ($validCredits / $totalCredits) * 100 : 0;
    $stats['total_credits'] = $validCredits;
    $stats['pass_rate'] = ($passing / $stats['count']) * 100;
}

// Couleurs pour le graphique des mentions
$mentionColors = [
    'Passable' => '#ffc107',
    'Assez Bien' => '#17a2b8',
    'Bien' => '#28a745',
    'Très Bien' => '#007bff',
    'Excellent' => '#6610f2',
    'Distinction' => '#fd7e14',
    'Grande Distinction' => '#e83e8c',
    'La Plus Grande Distinction' => '#dc3545',
    'Non spécifiée' => '#6c757d'
];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU PALMARÈS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=academique/palmares">Palmarès</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Informations générales -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Informations générales</h5>
                            <div>
                                <a href="?view=academique/modifier_palmares&id=<?= $idPalmares ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil-square"></i> Modifier
                                </a>
                                <a href="?view=academique/imprimer_palmares&id=<?= $idPalmares ?>" class="btn btn-info btn-sm">
                                    <i class="bi bi-printer"></i> Imprimer
                                </a>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Titre:</span>
                                        <span><?= htmlspecialchars($palmares['designation']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Promotion:</span>
                                        <span><?= htmlspecialchars($palmares['promotion']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Session:</span>
                                        <span><?= htmlspecialchars($palmares['session']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Année académique:</span>
                                        <span><?= htmlspecialchars($palmares['annee_academique']) ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Date de création:</span>
                                        <span><?= date('d/m/Y H:i', strtotime($palmares['date_creation'])) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Créé par:</span>
                                        <span><?= htmlspecialchars($palmares['nom_utilisateur']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Nombre d'étudiants:</span>
                                        <span><?= $stats['count'] ?></span>
                                    </li>
                                    <?php if (!empty($palmares['fichier_scanne'])): ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Fichier scanné:</span>
                                        <a href="<?= $palmares['fichier_scanne'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-pdf"></i> Voir le document
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <?php if (!empty($palmares['description'])): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold">Description:</h6>
                            <p><?= nl2br(htmlspecialchars($palmares['description'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques</h5>
                        
                        <div class="row">
                            <!-- KPIs -->
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-6 col-sm-6">
                                        <div class="card info-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Moyenne <span>| Pourcentage</span></h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-bar-chart"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= number_format($stats['avg_percentage'], 2) ?>%</h6>
                                                        <span class="text-muted small pt-1">
                                                            Min: <?= number_format($stats['min_percentage'], 2) ?>% / 
                                                            Max: <?= number_format($stats['max_percentage'], 2) ?>%
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 col-sm-6">
                                        <div class="card info-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Crédits <span>| Réussite</span></h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-award"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= number_format($stats['avg_credits'], 2) ?>%</h6>
                                                        <span class="text-muted small pt-1">
                                                            Total: <?= $stats['total_credits'] ?> crédits
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 col-sm-6">
                                        <div class="card info-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Taux <span>| de réussite</span></h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-check-circle"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= number_format($stats['pass_rate'], 2) ?>%</h6>
                                                        <span class="text-muted small pt-1">
                                                            des étudiants ont réussi
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 col-sm-6">
                                        <div class="card info-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Meilleure <span>| mention</span></h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-trophy"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <?php
                                                        $bestMention = 'Non spécifiée';
                                                        $mentionOrder = [
                                                            'Non spécifiée' => 0,
                                                            'Passable' => 1,
                                                            'Assez Bien' => 2,
                                                            'Bien' => 3,
                                                            'Très Bien' => 4,
                                                            'Excellent' => 5,
                                                            'Distinction' => 6,
                                                            'Grande Distinction' => 7,
                                                            'La Plus Grande Distinction' => 8
                                                        ];
                                                        
                                                        $highestOrder = -1;
                                                        $count = 0;
                                                        
                                                        foreach ($stats['mentions'] as $mention => $nbr) {
                                                            $order = $mentionOrder[$mention] ?? 0;
                                                            if ($order > $highestOrder) {
                                                                $highestOrder = $order;
                                                                $bestMention = $mention;
                                                                $count = $nbr;
                                                            } else if ($order == $highestOrder) {
                                                                $count += $nbr;
                                                            }
                                                        }
                                                        ?>
                                                        <h6><?= htmlspecialchars($bestMention) ?></h6>
                                                        <span class="text-muted small pt-1">
                                                            <?= $count ?> étudiant(s)
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Graphique des mentions -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Répartition des mentions</h5>
                                        <div>
                                            <canvas id="mentionsChart" style="min-height: 250px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Distribution des pourcentages -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Distribution des pourcentages</h5>
                                        <canvas id="percentageDistribution" style="min-height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des étudiants -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des étudiants</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="etudiantsTable">
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Nom complet</th>
                                        <th>Matricule</th>
                                        <th>Pourcentage</th>
                                        <th>Mention</th>
                                        <th>Crédits validés</th>
                                        <th>Crédits totaux</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $etudiant): ?>
                                    <tr>
                                        <td><?= $etudiant['rang'] ?></td>
                                        <td><?= htmlspecialchars($etudiant['nom_complet']) ?></td>
                                        <td><?= htmlspecialchars($etudiant['matricule'] ?? 'Non spécifié') ?></td>
                                        <td class="text-end"><?= number_format($etudiant['pourcentage'], 2) ?>%</td>
                                        <td>
                                            <?php if (!empty($etudiant['mention'])): ?>
                                                <span class="badge bg-<?= getMentionBadgeColor($etudiant['mention']) ?>">
                                                    <?= htmlspecialchars($etudiant['mention']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non spécifiée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= !empty($etudiant['credit_obtenu']) ? $etudiant['credit_obtenu'] : '-' ?></td>
                                        <td class="text-center"><?= !empty($etudiant['credit_total']) ? $etudiant['credit_total'] : '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($etudiants)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Aucun étudiant enregistré dans ce palmarès</td>
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

<?php
// Fonction pour obtenir la couleur du badge pour une mention
function getMentionBadgeColor($mention) {
    switch ($mention) {
        case 'Passable': return 'warning';
        case 'Assez Bien': return 'info';
        case 'Bien': return 'success';
        case 'Très Bien': return 'primary';
        case 'Excellent': return 'primary';
        case 'Distinction': return 'warning';
        case 'Grande Distinction': return 'danger';
        case 'La Plus Grande Distinction': return 'danger';
        default: return 'secondary';
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Transformer les données pour le graphique des mentions
    const mentionsLabels = [];
    const mentionsData = [];
    const mentionsColors = [];
    
    <?php foreach ($stats['mentions'] as $mention => $count): ?>
    mentionsLabels.push('<?= addslashes($mention) ?>');
    mentionsData.push(<?= $count ?>);
    mentionsColors.push('<?= $mentionColors[$mention] ?? '#6c757d' ?>');
    <?php endforeach; ?>
    
    // Graphique des mentions
    const mentionsCtx = document.getElementById('mentionsChart').getContext('2d');
    new Chart(mentionsCtx, {
        type: 'pie',
        data: {
            labels: mentionsLabels,
            datasets: [{
                data: mentionsData,
                backgroundColor: mentionsColors,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Préparer les données pour la distribution des pourcentages
    const percentages = [];
    <?php foreach ($etudiants as $etudiant): ?>
    percentages.push(<?= floatval($etudiant['pourcentage']) ?>);
    <?php endforeach; ?>
    
    // Créer des tranches pour l'histogramme
    const ranges = [
        '0-10%', '10-20%', '20-30%', '30-40%', '40-50%', 
        '50-60%', '60-70%', '70-80%', '80-90%', '90-100%'
    ];
    
    const distribution = Array(10).fill(0);
    percentages.forEach(percentage => {
        const index = Math.min(Math.floor(percentage / 10), 9);
        distribution[index]++;
    });
    
    // Graphique de distribution des pourcentages
    const distributionCtx = document.getElementById('percentageDistribution').getContext('2d');
    new Chart(distributionCtx, {
        type: 'bar',
        data: {
            labels: ranges,
            datasets: [{
                label: 'Nombre d\'étudiants',
                data: distribution,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Distribution des pourcentages par tranche'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.parsed.y} étudiant(s)`;
                        }
                    }
                }
            }
        }
    });
    
    // Initialiser le tableau des étudiants avec DataTables
    $(document).ready(function() {
        $('#etudiantsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]], // Trier par rang
            "responsive": true
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
