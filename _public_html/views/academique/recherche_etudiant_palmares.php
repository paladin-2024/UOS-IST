<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer les paramètres de recherche
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchMatricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;

// Récupérer les années académiques présentes dans les palmarès pour le filtre
$queryAnnees = "SELECT DISTINCT annee_acad_idannee_acad as idannee_acad, annee_academique as designation 
                FROM palmares_archive 
                WHERE annee_acad_idannee_acad IS NOT NULL 
                ORDER BY annee_academique DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$annees = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Pour récupérer aussi les années qui n'ont que le champ textuel rempli
$queryAnneesText = "SELECT DISTINCT 0 as idannee_acad, annee_academique as designation 
                    FROM palmares_archive 
                    WHERE annee_acad_idannee_acad IS NULL 
                    ORDER BY annee_academique DESC";
$stmtAnneesText = $pdo->prepare($queryAnneesText);
$stmtAnneesText->execute();
$anneesText = $stmtAnneesText->fetchAll(PDO::FETCH_ASSOC);

// Fusionner les deux ensembles de résultats
$annees = array_merge($annees, $anneesText);

// Trier les années par désignation en ordre décroissant
usort($annees, function($a, $b) {
    return strcmp($b['designation'], $a['designation']);
});

// Récupérer les sessions présentes dans les palmarès pour le filtre
$querySessions = "SELECT DISTINCT session_idsession as idsession, session as \"designSession\" 
                  FROM palmares_archive 
                  WHERE session_idsession IS NOT NULL 
                  ORDER BY session";
$stmtSessions = $pdo->prepare($querySessions);
$stmtSessions->execute();
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

// Pour récupérer aussi les sessions qui n'ont que le champ textuel rempli
$querySessionsText = "SELECT DISTINCT 0 as idsession, session as \"designSession\" 
                      FROM palmares_archive 
                      WHERE session_idsession IS NULL 
                      ORDER BY session";
$stmtSessionsText = $pdo->prepare($querySessionsText);
$stmtSessionsText->execute();
$sessionsText = $stmtSessionsText->fetchAll(PDO::FETCH_ASSOC);

// Fusionner les deux ensembles de résultats
$sessions = array_merge($sessions, $sessionsText);

// Éliminer les doublons potentiels
$sessionUnique = [];
foreach ($sessions as $session) {
    $sessionUnique[$session['designSession']] = $session;
}
$sessions = array_values($sessionUnique);

// Trier les sessions par désignation
usort($sessions, function($a, $b) {
    return strcmp($a['designSession'], $b['designSession']);
});

// Résultats de recherche
$resultats = [];
$etudiants = [];

// Effectuer la recherche si des termes sont fournis
if (!empty($searchTerm) || !empty($searchMatricule)) {
    $query = "SELECT pe.*, pa.designation as palmares_designation, 
                    pa.promotion, pa.session, pa.annee_academique,
                    pa.id_palmares, pa.date_creation,
                    pa.annee_acad_idannee_acad, pa.session_idsession
              FROM palmares_etudiant pe
              JOIN palmares_archive pa ON pe.id_palmares = pa.id_palmares
              WHERE 1=1";
    
    $params = [];
    
    // Filtrer par nom d'étudiant
    if (!empty($searchTerm)) {
        $query .= " AND pe.nom_complet LIKE ?";
        $params[] = "%{$searchTerm}%";
    }
    
    // Filtrer par matricule
    if (!empty($searchMatricule)) {
        $query .= " AND pe.matricule LIKE ?";
        $params[] = "%{$searchMatricule}%";
    }
    
    // Filtrer par année académique
    if ($anneeId > 0) {
        $query .= " AND pa.annee_acad_idannee_acad = ?";
        $params[] = $anneeId;
    }
    
    // Filtrer par session
    if ($sessionId > 0) {
        $query .= " AND pa.session_idsession = ?";
        $params[] = $sessionId;
    }
    
    $query .= " ORDER BY pa.date_creation DESC, pe.rang ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Regrouper les étudiants uniques pour les statistiques
    $etudiants = [];
    foreach ($resultats as $resultat) {
        $key = !empty($resultat['matricule']) ? $resultat['matricule'] : $resultat['nom_complet'];
        if (!isset($etudiants[$key])) {
            $etudiants[$key] = [
                'nom_complet' => $resultat['nom_complet'],
                'matricule' => $resultat['matricule'],
                'resultats' => [],
                'statistiques' => [
                    'count' => 0,
                    'avg_percentage' => 0,
                    'total_percentage' => 0,
                    'best_percentage' => 0,
                    'best_mention' => ''
                ]
            ];
        }
        
        $etudiants[$key]['resultats'][] = $resultat;
        $etudiants[$key]['statistiques']['count']++;
        $etudiants[$key]['statistiques']['total_percentage'] += $resultat['pourcentage'];
        
        if ($resultat['pourcentage'] > $etudiants[$key]['statistiques']['best_percentage']) {
            $etudiants[$key]['statistiques']['best_percentage'] = $resultat['pourcentage'];
            $etudiants[$key]['statistiques']['best_mention'] = $resultat['mention'];
        }
    }
    
    // Calculer les moyennes
    foreach ($etudiants as $key => $etudiant) {
        if ($etudiant['statistiques']['count'] > 0) {
            $etudiants[$key]['statistiques']['avg_percentage'] = 
                $etudiant['statistiques']['total_percentage'] / $etudiant['statistiques']['count'];
        }
    }
}

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


<main id="main" class="main">
    <div class="pagetitle">
        <h1>RECHERCHE D'ÉTUDIANTS DANS LES PALMARÈS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=academique/palmares">Palmarès</a></li>
                <li class="breadcrumb-item active">Recherche d'étudiants</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Formulaire de recherche -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rechercher un étudiant</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="academique/recherche_etudiant_palmares">
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Nom de l'étudiant</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Nom complet ou partiel">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="matricule" class="form-label">Matricule</label>
                                <input type="text" class="form-control" id="matricule" name="matricule" value="<?= htmlspecialchars($searchMatricule) ?>" placeholder="Numéro matricule">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="annee" class="form-label">Année académique</label>
                                <select class="form-select" id="annee" name="annee">
                                    <option value="0">Toutes les années</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $anneeId == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="session" class="form-label">Session</label>
                                <select class="form-select" id="session" name="session">
                                    <option value="0">Toutes les sessions</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= $sessionId == $session['idsession'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['designSession']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Résultats de recherche -->
        <?php if (!empty($searchTerm) || !empty($searchMatricule)): ?>
            <?php if (empty($resultats)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucun résultat trouvé pour cette recherche.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($etudiants as $etudiant): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?= htmlspecialchars($etudiant['nom_complet']) ?>
                                        <?php if (!empty($etudiant['matricule'])): ?>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($etudiant['matricule']) ?></span>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <!-- Statistiques de l'étudiant -->
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="card border">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title">Nombre de palmarès</h6>
                                                    <h2 class="text-center"><?= $etudiant['statistiques']['count'] ?></h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title">Moyenne générale</h6>
                                                    <h2 class="text-center"><?= number_format($etudiant['statistiques']['avg_percentage'], 2) ?>%</h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title">Meilleur pourcentage</h6>
                                                    <h2 class="text-center"><?= number_format($etudiant['statistiques']['best_percentage'], 2) ?>%</h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title">Meilleure mention</h6>
                                                    <h2 class="text-center">
                                                        <?php if (!empty($etudiant['statistiques']['best_mention'])): ?>
                                                            <span class="badge bg-<?= getMentionBadgeColor($etudiant['statistiques']['best_mention']) ?>">
                                                                <?= htmlspecialchars($etudiant['statistiques']['best_mention']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Non spécifiée</span>
                                                        <?php endif; ?>
                                                    </h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tableau des résultats -->
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Palmarès</th>
                                                    <th>Promotion</th>
                                                    <th>Session</th>
                                                    <th>Année académique</th>
                                                    <th>Rang</th>
                                                    <th>Pourcentage</th>
                                                    <th>Mention</th>
                                                    <th>Crédits obtenus</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($etudiant['resultats'] as $resultat): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($resultat['palmares_designation']) ?></td>
                                                        <td><?= htmlspecialchars($resultat['promotion']) ?></td>
                                                        <td><?= htmlspecialchars($resultat['session']) ?></td>
                                                        <td><?= htmlspecialchars($resultat['annee_academique']) ?></td>
                                                        <td><?= $resultat['rang'] ?></td>
                                                        <td class="text-end"><?= number_format($resultat['pourcentage'], 2) ?>%</td>
                                                        <td>
                                                            <?php if (!empty($resultat['mention'])): ?>
                                                                <span class="badge bg-<?= getMentionBadgeColor($resultat['mention']) ?>">
                                                                    <?= htmlspecialchars($resultat['mention']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Non spécifiée</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if (!empty($resultat['credit_obtenu']) && !empty($resultat['credit_total'])): ?>
                                                                <?= $resultat['credit_obtenu'] ?>/<?= $resultat['credit_total'] ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="?view=academique/details_palmares&id=<?= $resultat['id_palmares'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-eye"></i> Voir
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Graphique d'évolution -->
                                    <?php if (count($etudiant['resultats']) > 1): ?>
                                    <div class="mt-4">
                                        <h6 class="fw-bold">Évolution des résultats</h6>
                                        <canvas id="evolution-<?= !empty($etudiant['matricule']) ? 'mat-'.htmlspecialchars($etudiant['matricule']) : 'nom-'.md5($etudiant['nom_complet']) ?>" style="height: 300px;"></canvas>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les graphiques d'évolution
    <?php foreach ($etudiants as $key => $etudiant): ?>
        <?php if (count($etudiant['resultats']) > 1): ?>
            // Trier les résultats par date
            <?php
            usort($etudiant['resultats'], function($a, $b) {
                return strtotime($a['date_creation']) - strtotime($b['date_creation']);
            });
            ?>
            
            // Préparer les données
            const ctx_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?> = document.getElementById('evolution-<?= !empty($etudiant['matricule']) ? 'mat-'.htmlspecialchars($etudiant['matricule']) : 'nom-'.md5($etudiant['nom_complet']) ?>').getContext('2d');
            
            const labels_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?> = [
                <?php foreach ($etudiant['resultats'] as $resultat): ?>
                    '<?= addslashes($resultat['palmares_designation']) ?> (<?= addslashes($resultat['annee_academique']) ?>)',
                <?php endforeach; ?>
            ];
            
            const data_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?> = [
                <?php foreach ($etudiant['resultats'] as $resultat): ?>
                    <?= $resultat['pourcentage'] ?>,
                <?php endforeach; ?>
            ];
            
            new Chart(ctx_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?>, {
                type: 'line',
                data: {
                    labels: labels_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?>,
                    datasets: [{
                        label: 'Pourcentage',
                        data: data_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?>,
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: Math.max(0, Math.min(...data_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?>) - 10),
                            max: Math.min(100, Math.max(...data_<?= !empty($etudiant['matricule']) ? 'mat_'.preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['matricule']) : 'nom_'.md5($etudiant['nom_complet']) ?>) + 10)
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.parsed.y.toFixed(2)}%`;
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    <?php endforeach; ?>
    
    // Appliquer DataTables aux tableaux de résultats
    $(document).ready(function() {
        $('.table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            "pageLength": 5,
            "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "Tous"]],
            "order": [[3, "desc"], [2, "asc"]]  // Trier par année académique (desc) puis session
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
