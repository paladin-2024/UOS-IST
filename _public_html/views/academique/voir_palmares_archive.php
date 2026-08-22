<?php
include "./views/include/header.php";

// VÃ©rifier si l'ID du palmarÃ¨s est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<script>window.location.href = "?view=academique/palmares_archives";</script>';
    exit;
}

$idPalmares = intval($_GET['id']);
$pdo = Connexion::getInstance()->getPDO();

// RÃ©cupÃ©rer les informations du palmarÃ¨s
$query = "SELECT p.*, u.\"nomUser\" 
          FROM palmares_archives p 
          LEFT JOIN t_users u ON p.\"idUser\" = u.\"idUser\" 
          WHERE p.idpalmares = :id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
$stmt->execute();
$palmares = $stmt->fetch(PDO::FETCH_ASSOC);

// DÃ©terminer le type de palmarÃ¨s
$typePalmares = isset($palmares['type_palmares']) ? strtolower($palmares['type_palmares']) : 'classique';
$isLmd = ($typePalmares === 'lmd');

if (!$palmares) {
    echo '<script>
        Swal.fire({
            icon: "error",
            title: "Erreur",
            text: "PalmarÃ¨s introuvable",
            confirmButtonText: "Retour"
        }).then(() => {
            window.location.href = "?view=academique/palmares_archives";
        });
    </script>';
    exit;
}

// RÃ©cupÃ©rer les Ã©tudiants associÃ©s Ã  ce palmarÃ¨s
$queryEtudiants = "SELECT * FROM etudiants_palmares_archives 
                  WHERE idpalmares = :id 
                  ORDER BY pourcentage DESC, nom_complet ASC";
$stmtEtudiants = $pdo->prepare($queryEtudiants);
$stmtEtudiants->bindParam(':id', $idPalmares, PDO::PARAM_INT);
$stmtEtudiants->execute();
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$statsDecisions = [];
$pourcentageTotal = 0;
$pourcentageMax = 0;
$pourcentageMin = $isLmd ? 20 : 100;
$etudiantMax = null;
$etudiantMin = null;

foreach ($etudiants as $etudiant) {
    // Statistiques par dÃ©cision
    if (!isset($statsDecisions[$etudiant['decision']])) {
        $statsDecisions[$etudiant['decision']] = 0;
    }
    $statsDecisions[$etudiant['decision']]++;
    
    // Pourcentage total pour calculer la moyenne
    $pourcentageTotal += $etudiant['pourcentage'];
    
    // Recherche du pourcentage max et min
    if ($etudiant['pourcentage'] > $pourcentageMax) {
        $pourcentageMax = $etudiant['pourcentage'];
        $etudiantMax = $etudiant;
    }
    if ($etudiant['pourcentage'] < $pourcentageMin) {
        $pourcentageMin = $etudiant['pourcentage'];
        $etudiantMin = $etudiant;
    }
}

// Calculer la moyenne
$pourcentageMoyen = count($etudiants) > 0 ? $pourcentageTotal / count($etudiants) : 0;

// Obtenir les 3 meilleurs Ã©tudiants
$meilleursEtudiants = array_slice($etudiants, 0, 3);
?>

<main id="main" class="main">
    <section class="section profile">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <!-- En-tÃªte avec les informations principales et les actions -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                            <div>
                                <h5 class="card-title mb-0">
                                    <?= htmlspecialchars($palmares['designation']) ?>
                                </h5>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($palmares['annee_academique']) ?> | 
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($palmares['section']) ?> | 
                                    <i class="bi bi-mortarboard"></i> <?= htmlspecialchars($palmares['promotion']) ?> | 
                                    <i class="bi bi-clock"></i> <?= htmlspecialchars($palmares['session']) ?> |
                                    <i class="bi bi-diagram-3"></i> Type: <?= $isLmd ? 'LMD' : 'PADEM' ?>
                                </p>
                            </div>
                            <div class="btn-toolbar">
                                <div class="btn-group">
                                    <?php if (!empty($palmares['fichier_scanne'])): ?>
                                    <a href="<?= htmlspecialchars($palmares['fichier_scanne']) ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="bi bi-file-pdf"></i> Voir le PDF
                                    </a>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPdfModal">
                                        <i class="bi bi-cloud-upload"></i> Ajouter un PDF
                                    </button>
                                    <?php endif; ?>
                                    <a href="controller/exporter_palmares_archive.php?id=<?= $idPalmares ?>" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-file-excel"></i> Exporter Excel
                                    </a>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Imprimer
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Onglets de navigation -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="apercu-tab" data-bs-toggle="tab" data-bs-target="#apercu" type="button" role="tab" aria-controls="apercu" aria-selected="true">
                                    <i class="bi bi-info-circle me-1"></i> Aperçu
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="liste-tab" data-bs-toggle="tab" data-bs-target="#liste" type="button" role="tab" aria-controls="liste" aria-selected="false">
                                    <i class="bi bi-list-ul me-1"></i> Liste complète <span class="badge bg-primary"><?= count($etudiants) ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="false">
                                    <i class="bi bi-bar-chart me-1"></i> Statistiques
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="top-tab" data-bs-toggle="tab" data-bs-target="#top" type="button" role="tab" aria-controls="top" aria-selected="false">
                                    <i class="bi bi-trophy me-1"></i> Podium
                                </button>
                            </li>
                        </ul>

                        <!-- Contenu des onglets -->
                        <div class="tab-content pt-3">
                            <!-- Onglet Aperçu -->
                            <div class="tab-pane fade show active" id="apercu" role="tabpanel" aria-labelledby="apercu-tab">
                                <div class="row">
                                    <!-- Informations générales -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Informations générales</h5>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Année académique</div>
                                                    <div class="col-lg-8 col-md-8"><?= htmlspecialchars($palmares['annee_academique']) ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Section</div>
                                                    <div class="col-lg-8 col-md-8"><?= htmlspecialchars($palmares['section']) ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Promotion</div>
                                                    <div class="col-lg-8 col-md-8"><?= htmlspecialchars($palmares['promotion']) ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Session</div>
                                                    <div class="col-lg-8 col-md-8"><?= htmlspecialchars($palmares['session']) ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Date de création</div>
                                                    <div class="col-lg-8 col-md-8"><?= date('d/m/Y H:i', strtotime($palmares['date_creation'])) ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-4 label">Créé par</div>
                                                    <div class="col-lg-8 col-md-8"><?= htmlspecialchars($palmares['nomUser']) ?></div>
                                                </div>
                                                <?php if (!empty($palmares['description'])): ?>
                                                <div class="row mt-3">
                                                    <div class="col-12 label">Description</div>
                                                    <div class="col-12 mt-2">
                                                        <div class="alert alert-light">
                                                            <?= nl2br(htmlspecialchars($palmares['description'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Résumé des statistiques -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Résumé des statistiques</h5>
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <div class="card mini-stat bg-light">
                                                            <div class="card-body text-center">
                                                                <h2 class="mb-0"><?= count($etudiants) ?></h2>
                                                                <p class="text-muted mb-0">Étudiants</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card mini-stat bg-light">
                                                            <div class="card-body text-center">
                                                                <h2 class="mb-0"><?= number_format($isLmd ? ($pourcentageMoyen * 5) : $pourcentageMoyen, 2) ?>%</h2>
                                                                <p class="text-muted mb-0">Moyenne</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card mini-stat bg-light">
                                                            <div class="card-body text-center">
                                                                <h2 class="mb-0"><?= number_format($isLmd ? ($pourcentageMax * 5) : $pourcentageMax, 2) ?>%</h2>
                                                                <p class="text-muted mb-0">Maximum</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Statistiques par décision -->
                                                <h6 class="card-subtitle mt-3 mb-2">Répartition par décision</h6>
                                                <?php foreach ($statsDecisions as $decision => $count): ?>
                                                    <?php
                                                    $badgeClass = 'bg-secondary';
                                                    $percentage = count($etudiants) > 0 ? ($count / count($etudiants) * 100) : 0;
                                                                                                        if (stripos($decision, 'admi') !== false) {
                                                        $badgeClass = 'bg-success';
                                                    } elseif (stripos($decision, 'ajourn') !== false) {
                                                        $badgeClass = 'bg-danger';
                                                    } elseif (stripos($decision, 'condition') !== false) {
                                                        $badgeClass = 'bg-warning';
                                                    }
                                                    ?>
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($decision) ?></span></span>
                                                            <span class="badge bg-light text-dark"><?= $count ?> (<?= number_format($percentage, 1) ?>%)</span>
                                                        </div>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar <?= $badgeClass ?>" role="progressbar" style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <!-- Performance globale -->
                                                <h6 class="card-subtitle mt-4 mb-2">Performance globale</h6>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <div class="small text-muted"><?= $isLmd ? 'Moyenne minimale' : 'Pourcentage minimum' ?></div>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $isLmd ? ($pourcentageMin * 5) : $pourcentageMin ?>%" aria-valuenow="<?= $isLmd ? ($pourcentageMin * 5) : $pourcentageMin ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <span><?= number_format($pourcentageMin, 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="small text-muted"><?= $isLmd ? 'Moyenne maximale' : 'Pourcentage maximum' ?></div>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $isLmd ? ($pourcentageMax * 5) : $pourcentageMax ?>%" aria-valuenow="<?= $isLmd ? ($pourcentageMax * 5) : $pourcentageMax ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <span><?= number_format($pourcentageMax, 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Aperçu des 5 premiers étudiants -->
                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <h5 class="card-title">Top 5 des étudiants</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Matricule</th>
                                                                <th>Nom complet</th>
                                                                <th><?= $isLmd ? 'Moyenne' : 'Pourcentage' ?></th>
                                                                <th>Décision</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach (array_slice($etudiants, 0, 5) as $index => $etudiant): ?>
                                                                <tr>
                                                                    <td><?= $index + 1 ?></td>
                                                                    <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                                    <td><?= htmlspecialchars($etudiant['nom_complet']) ?></td>
                                                                    <td>
                                                                        <?php
                                                                        $scorePercent = $isLmd ? (floatval($etudiant['pourcentage']) * 5) : floatval($etudiant['pourcentage']);
                                                                        $badgeClass = 'bg-danger';
                                                                        if ($scorePercent >= 80) $badgeClass = 'bg-success';
                                                                        elseif ($scorePercent >= 70) $badgeClass = 'bg-info';
                                                                        elseif ($scorePercent >= 60) $badgeClass = 'bg-primary';
                                                                        elseif ($scorePercent >= 50) $badgeClass = 'bg-warning';
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>"><?= number_format($etudiant['pourcentage'], 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                                    </td>
                                                                    <td><?= htmlspecialchars($etudiant['decision']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Liste complète -->
                            <div class="tab-pane fade" id="liste" role="tabpanel" aria-labelledby="liste-tab">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Liste complète des étudiants</h5>
                                            
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped datatable" id="students-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 5%">#</th>
                                                        <th style="width: 15%">Matricule</th>
                                                        <th style="width: 35%">Nom complet</th>
                                                        <th style="width: 15%"><?php echo $isLmd ? 'Moyenne' : 'Pourcentage'; ?></th>
                                                        <th style="width: 15%">Décision</th>
                                                        <?php if ($isLmd): ?>
                                                        <th style="width: 10%">Crédits validés</th>
                                                        <?php endif; ?>
                                                        <th style="width: 15%">Session</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($etudiants as $index => $etudiant): ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                        <td><?= htmlspecialchars($etudiant['nom_complet']) ?></td>
                                                        <td>
                                                            <?php
                                                            $scorePercent = $isLmd ? (floatval($etudiant['pourcentage']) * 5) : floatval($etudiant['pourcentage']);
                                                            $badgeClass = 'bg-danger';
                                                            if ($scorePercent >= 80) $badgeClass = 'bg-success';
                                                            elseif ($scorePercent >= 70) $badgeClass = 'bg-info';
                                                            elseif ($scorePercent >= 60) $badgeClass = 'bg-primary';
                                                            elseif ($scorePercent >= 50) $badgeClass = 'bg-warning';
                                                            ?>
                                                            <div class="d-flex align-items-center">
                                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                                    <div class="progress-bar <?= $badgeClass ?>" role="progressbar" style="width: <?= $scorePercent ?>%" aria-valuenow="<?= $scorePercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                                </div>
                                                                <span class="badge <?= $badgeClass ?>"><?= number_format($etudiant['pourcentage'], 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $decisionClass = 'bg-secondary';
                                                            if (stripos($etudiant['decision'], 'admi') !== false) {
                                                                $decisionClass = 'bg-success';
                                                            } elseif (stripos($etudiant['decision'], 'ajourn') !== false) {
                                                                $decisionClass = 'bg-danger';
                                                            } elseif (stripos($etudiant['decision'], 'condition') !== false) {
                                                                $decisionClass = 'bg-warning';
                                                            }
                                                            ?>
                                                            <span class="badge <?= $decisionClass ?>"><?= htmlspecialchars($etudiant['decision']) ?></span>
                                                        </td>
                                                        <?php if ($isLmd): ?>
                                                        <td><?= ($etudiant['credits_valides'] === null || $etudiant['credits_valides'] === '') ? '-' : htmlspecialchars($etudiant['credits_valides']) ?></td>
                                                        <?php endif; ?>
                                                        <td><?= htmlspecialchars($etudiant['session']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Statistiques -->
                            <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                                <div class="row">
                                    <!-- Graphique de répartition des décisions -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Répartition par décision</h5>
                                                <div>
                                                    <canvas id="decisionsChart" style="height: 300px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Graphique de répartition des pourcentages -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?=$isLmd ? 'Distribution des moyennes' : 'Distribution des pourcentages'?></h5>
                                                <div>
                                                    <canvas id="percentageChart" style="height: 300px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tableau de statistiques détaillées -->
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Statistiques détaillées</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-bordered">
                                                    <tbody>
                                                        <tr>
                                                            <th>Nombre total d'étudiants</th>
                                                            <td><?= count($etudiants) ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th><?= $isLmd ? 'Moyenne générale' : 'Pourcentage moyen' ?></th>
                                                            <td><?= number_format($pourcentageMoyen, 2) ?><?= $isLmd ? '/20' : '%' ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th><?= $isLmd ? 'Moyenne minimale' : 'Pourcentage minimum' ?></th>
                                                            <td><?= number_format($pourcentageMin, 2) ?><?= $isLmd ? '/20' : '%' ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th><?= $isLmd ? 'Moyenne maximale' : 'Pourcentage maximum' ?></th>
                                                            <td><?= number_format($pourcentageMax, 2) ?><?= $isLmd ? '/20' : '%' ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Décision</th>
                                                            <th>Nombre</th>
                                                            <th><?= $isLmd ? 'Moyenne' : 'Pourcentage' ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($statsDecisions as $decision => $count): ?>
                                                            <?php $percentage = count($etudiants) > 0 ? ($count / count($etudiants) * 100) : 0; ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($decision) ?></td>
                                                                <td><?= $count ?></td>
                                                                <td><?= number_format($percentage, 1) ?>%</td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Podium (3 meilleurs étudiants) -->
                            <div class="tab-pane fade" id="top" role="tabpanel" aria-labelledby="top-tab">
                                <div class="row justify-content-center mb-4">
                                    <div class="col-lg-10">
                                                                                <div class="card border-0 bg-light shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title text-center mb-4">Podium - Les meilleurs étudiants</h5>
                                                
                                                <div class="row podium-container">
                                                    <?php if (count($etudiants) >= 2): ?>
                                                    <!-- 2ème place -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative">
                                                            <div class="podium-block silver-podium mb-3">
                                                                <div class="rank-number">2</div>
                                                            </div>
                                                            <div class="avatar-circle silver mx-auto">
                                                                <i class="bi bi-person-circle display-4"></i>
                                                            </div>
                                                        </div>
                                                        <div class="card mt-2">
                                                            <div class="card-body py-2">
                                                                <h5 class="card-title mb-0"><?= htmlspecialchars($etudiants[1]['nom_complet']) ?></h5>
                                                                <div class="text-muted small mb-2"><?= htmlspecialchars($etudiants[1]['matricule']) ?></div>
                                                                <span class="badge bg-info"><?= number_format($etudiants[1]['pourcentage'], 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                                <div class="mt-2">
                                                                    <span class="badge bg-secondary"><?= htmlspecialchars($etudiants[1]['decision']) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if (count($etudiants) >= 1): ?>
                                                    <!-- 1ère place -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative">
                                                            <div class="podium-block gold-podium mb-3">
                                                                <div class="rank-number">1</div>
                                                            </div>
                                                            <div class="avatar-circle gold mx-auto">
                                                                <i class="bi bi-trophy-fill display-4"></i>
                                                            </div>
                                                        </div>
                                                        <div class="card mt-2 border-primary">
                                                            <div class="card-body py-2">
                                                                <h5 class="card-title mb-0"><?= htmlspecialchars($etudiants[0]['nom_complet']) ?></h5>
                                                                <div class="text-muted small mb-2"><?= htmlspecialchars($etudiants[0]['matricule']) ?></div>
                                                                <span class="badge bg-success"><?= number_format($etudiants[0]['pourcentage'], 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                                <div class="mt-2">
                                                                    <span class="badge bg-primary"><?= htmlspecialchars($etudiants[0]['decision']) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if (count($etudiants) >= 3): ?>
                                                    <!-- 3ème place -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative">
                                                            <div class="podium-block bronze-podium mb-3">
                                                                <div class="rank-number">3</div>
                                                            </div>
                                                            <div class="avatar-circle bronze mx-auto">
                                                                <i class="bi bi-person-circle display-4"></i>
                                                            </div>
                                                        </div>
                                                        <div class="card mt-2">
                                                            <div class="card-body py-2">
                                                                <h5 class="card-title mb-0"><?= htmlspecialchars($etudiants[2]['nom_complet']) ?></h5>
                                                                <div class="text-muted small mb-2"><?= htmlspecialchars($etudiants[2]['matricule']) ?></div>
                                                                <span class="badge bg-warning"><?= number_format($etudiants[2]['pourcentage'], 2) ?><?= $isLmd ? '/20' : '%' ?></span>
                                                                <div class="mt-2">
                                                                    <span class="badge bg-secondary"><?= htmlspecialchars($etudiants[2]['decision']) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Informations complémentaires sur les meilleurs étudiants -->
                                                <div class="mt-4">
                                                    <h6 class="text-center mb-3">Analyses comparatives</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Position</th>
                                                                    <th>Nom</th>
                                                                    <th><?= $isLmd ? 'Moyenne' : 'Pourcentage' ?></th>
                                                                    <th>Écart avec le suivant</th>
                                                                    <th>Écart avec moyenne</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php for ($i = 0; $i < min(3, count($etudiants)); $i++): ?>
                                                                    <tr>
                                                                        <td class="text-center"><?= $i + 1 ?></td>
                                                                        <td><?= htmlspecialchars($etudiants[$i]['nom_complet']) ?></td>
                                                                        <td class="text-center"><?= number_format($etudiants[$i]['pourcentage'], 2) ?>%</td>
                                                                        <td class="text-center">
                                                                            <?php if ($i < min(2, count($etudiants) - 1)): ?>
                                                                                <?= number_format($etudiants[$i]['pourcentage'] - $etudiants[$i + 1]['pourcentage'], 2) ?>%
                                                                            <?php else: ?>
                                                                                -
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <?= number_format($etudiants[$i]['pourcentage'] - $pourcentageMoyen, 2) ?>%
                                                                        </td>
                                                                    </tr>
                                                                <?php endfor; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour le téléchargement du PDF -->
<?php if (empty($palmares['fichier_scanne'])): ?>
<div class="modal fade" id="uploadPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un fichier PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/upload_pdf_palmares.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id_palmares" value="<?= $idPalmares ?>">
                    <div class="mb-3">
                        <label for="pdf_file" class="form-label">Sélectionner un fichier PDF</label>
                        <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
                        <div class="form-text">Le fichier doit être au format PDF et ne pas dépasser 10 Mo.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Télécharger</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CSS pour le podium -->
<style>
.podium-block {
    height: 80px;
    margin: 0 auto;
    width: 70px;
    position: relative;
    border-radius: 5px 5px 0 0;
}

.gold-podium {
    background-color: #FFD700;
    height: 100px;
}

.silver-podium {
    background-color: #C0C0C0;
    height: 80px;
}

.bronze-podium {
    background-color: #CD7F32;
    height: 60px;
}

.rank-number {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    font-weight: bold;
    color: white;
}

.avatar-circle {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.gold {
    background-color: #FFD700;
    border: 3px solid #e6c200;
}

.silver {
    background-color: #C0C0C0;
    border: 3px solid #a8a8a8;
}

.bronze {
    background-color: #CD7F32;
    border: 3px solid #b87229;
}

.mini-stat {
    border-radius: 10px;
    transition: transform 0.3s;
}

.mini-stat:hover {
    transform: translateY(-5px);
}

/* Styles d'impression */
@media print {
    .nav-tabs, .btn-toolbar, .card-header button {
        display: none !important;
    }
    
    .tab-pane {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .card {
        break-inside: avoid;
    }
}
</style>

<!-- JavaScript pour les graphiques et les fonctionnalités interactives -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche dans le tableau des Ã©tudiants
    // Recherche dans le tableau des Ã©tudiants
    const searchInput = document.getElementById('search-students');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#students-table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    
    // Données pour le graphique des décisions
    const decisionsChart = document.getElementById('decisionsChart');
    const decisionsData = {
        labels: <?= json_encode(array_keys($statsDecisions)) ?>,
        datasets: [{
            label: 'Nombre d\'étudiants',
            data: <?= json_encode(array_values($statsDecisions)) ?>,
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 205, 86, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgb(75, 192, 192)',
                'rgb(255, 99, 132)',
                'rgb(255, 205, 86)',
                'rgb(54, 162, 235)',
                'rgb(153, 102, 255)'
            ],
            borderWidth: 1
        }]
    };
    // Déclaration unique de lmdRanges
    const lmdRanges = {
        '0 - 10': 0,
        '10 - 12': 0,
        '12 - 14': 0,
        '14 - 16': 0,
        '16 - 18': 0,
        '18 - 20': 0
    };
    <?php if ($isLmd): ?>
    // Adapter les tranches pour LMD (sur 20)
    Object.keys(percentageRanges).forEach(k => delete percentageRanges[k]);
    Object.assign(percentageRanges, lmdRanges);
    <?php endif; ?>
    
    if (decisionsChart) {
        new Chart(decisionsChart, {
            type: 'pie',
            data: decisionsData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Répartition par décision'
                    }
                }
            }
        });
    }
    
    // Préparation des données pour le graphique de distribution des pourcentages
    const percentageChart = document.getElementById('percentageChart');
    
    // Créer des tranches de pourcentages (0-50, 50-60, 60-70, 70-80, 80-90, 90-100)
    const percentageRanges = {
        'Moins de 50%': 0,
        '50% - 60%': 0,
        '60% - 70%': 0,
        '70% - 80%': 0,
        '80% - 90%': 0,
        '90% - 100%': 0
    };
    <?php if ($isLmd): ?>
    
    // Remplacer les tranches par défaut
    Object.keys(percentageRanges).forEach(k => delete percentageRanges[k]);
    Object.assign(percentageRanges, lmdRanges);
    <?php endif; ?>
    
    // Compter le nombre d'étudiants dans chaque tranche
    <?php foreach ($etudiants as $etudiant): ?>
    if (<?= $etudiant['pourcentage'] ?> < 50) percentageRanges['Moins de 50%']++;
    else if (<?= $etudiant['pourcentage'] ?> < 60) percentageRanges['50% - 60%']++;
    else if (<?= $etudiant['pourcentage'] ?> < 70) percentageRanges['60% - 70%']++;
    else if (<?= $etudiant['pourcentage'] ?> < 80) percentageRanges['70% - 80%']++;
    else if (<?= $etudiant['pourcentage'] ?> < 90) percentageRanges['80% - 90%']++;
    else percentageRanges['90% - 100%']++;
<?php endforeach; ?>

    <?php if ($isLmd): ?>
    // Recalculer les tranches pour LMD (sur 20)
    (function(){
        const lmdScores = <?= json_encode(array_map(function($e){ return isset($e['pourcentage']) ? floatval($e['pourcentage']) : 0; }, $etudiants)) ?>;
        // Réinitialiser les tranches
        Object.keys(percentageRanges).forEach(k => percentageRanges[k] = 0);
        lmdScores.forEach(v => {
            if (v < 10) percentageRanges['0 - 10']++;
            else if (v < 12) percentageRanges['10 - 12']++;
            else if (v < 14) percentageRanges['12 - 14']++;
            else if (v < 16) percentageRanges['14 - 16']++;
            else if (v < 18) percentageRanges['16 - 18']++;
            else percentageRanges['18 - 20']++;
        });
    })();
    <?php endif; ?>

    
    const percentageData = {
        labels: Object.keys(percentageRanges),
        datasets: [{
            label: 'Nombre d\'étudiants',
            data: Object.values(percentageRanges),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(255, 205, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgb(255, 99, 132)',
                'rgb(255, 159, 64)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)',
                'rgb(54, 162, 235)',
                'rgb(153, 102, 255)'
            ],
            borderWidth: 1
        }]
    };
    
    if (percentageChart) {
        new Chart(percentageChart, {
            type: 'bar',
            data: percentageData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre d\'étudiants'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: <?= json_encode($isLmd ? 'Tranches de moyenne' : 'Tranches de pourcentage') ?>
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribution des pourcentages'
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Gestion de l'impression
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tabEl => {
        tabEl.addEventListener('click', function(e) {
            // Retirer le paramètre d'URL tab s'il existe
            const url = new URL(window.location.href);
            url.searchParams.delete('tab');
            
            // Ajouter le nouveau paramètre tab
            const tabId = this.getAttribute('data-bs-target').replace('#', '');
            url.searchParams.set('tab', tabId);
            
            // Remplacer l'URL sans recharger la page
            window.history.replaceState({}, '', url);
        });
    });
    
    // Vérifier s'il y a un paramètre tab dans l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam) {
        // Activer l'onglet correspondant
        const tabEl = document.querySelector(`[data-bs-target="#${tabParam}"]`);
        if (tabEl) {
            new bootstrap.Tab(tabEl).show();
        }
    }
});
</script>

<?php include "./views/include/footer.php"; ?>







