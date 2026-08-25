<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
$checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND table_schema = 'public' AND column_name = 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les sections dont l'utilisateur est responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE \"idUser\" = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isResponsableSection = !empty($userSections);

// Vérifier si l'utilisateur a le droit d'accéder à cette page
$hasFullAccess = $_SESSION['idRole'] == 1;

if (!$isResponsableSection && !$hasFullAccess) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php"; 
    exit;
}

// Récupérer les paramètres de filtrage
$promotionFilter = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreFilter = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;

// Fonction pour récupérer les promotions accessibles
function getPromotionsAccessibles($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT DISTINCT p.* 
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les semestres d'une promotion
function getSemestresByPromotion($pdo, $promotionId) {
    $query = "SELECT * FROM semestre WHERE promotion_idpromotion = :promotionId ORDER BY \"numeroSemestre\"";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour calculer les statistiques d'avancement
function getStatistiquesAvancement($pdo, $promotionId, $semestreId = null, $anneeAcadId, $userSections = []) {
    // Vérifier d'abord que l'utilisateur a accès à cette promotion
    if (!empty($userSections)) {
        $checkParams = [':promotionId' => $promotionId];
        $checkQuery = "SELECT COUNT(*) 
                      FROM promotion p
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      WHERE p.idpromotion = :promotionId";
        
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $checkParams[$paramName] = $section;
        }
        $checkQuery .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
        
        $stmt = $pdo->prepare($checkQuery);
        foreach ($checkParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // L'utilisateur n'a pas accès à cette promotion
            return [
                'details' => [],
                'totaux' => [
                    'CM' => ['prevu' => 0, 'realise' => 0, 'pourcentage' => 0],
                    'TD' => ['prevu' => 0, 'realise' => 0, 'pourcentage' => 0],
                    'TP' => ['prevu' => 0, 'realise' => 0, 'pourcentage' => 0],
                    'total' => ['prevu' => 0, 'realise' => 0, 'pourcentage' => 0]
                ]
            ];
        }
    }
    
    // Paramètres pour la requête principale
    $params = [
        ':promotionId' => $promotionId
    ];
    
    // Récupérer tous les ECUE de la promotion/semestre avec leurs volumes horaires prévus
    $query = "SELECT DISTINCT 
              e.\"idECUE\",
              e.\"designationECUE\",
              e.\"CMI\" as volumeHoraireCM,
              e.\"TD\" as volumeHoraireTD,
              e.\"TP\" as volumeHoraireTP,
              u.\"designationUE\",
              s.\"numeroSemestre\",
              s.idsemestre
              FROM ecue e
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              WHERE s.promotion_idpromotion = :promotionId
              AND e.\"estVisible\" = 1";
    
    if ($semestreId) {
        $query .= " AND s.idsemestre = :semestreId";
        $params[':semestreId'] = $semestreId;
    }
    
    $query .= " ORDER BY s.\"numeroSemestre\", u.\"designationUE\", e.\"designationECUE\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statistiques = [];
    $totauxGlobaux = [
        'CM' => ['prevu' => 0, 'realise' => 0],
        'TD' => ['prevu' => 0, 'realise' => 0],
        'TP' => ['prevu' => 0, 'realise' => 0],
        'total' => ['prevu' => 0, 'realise' => 0]
    ];
    
    foreach ($ecues as $ecue) {
        // Calculer les heures réalisées pour cet ECUE
        $queryRealise = "SELECT 
                        type_cours,
                        SUM(EXTRACT(EPOCH FROM (heure_fin - heure_debut))/3600.0) as heures_realisees
                        FROM suivi_enseignements
                        WHERE \"idECUE\" = :ecueId
                        AND annee_acad_idannee_acad = :anneeAcadId
                        GROUP BY type_cours";
        
        $stmtRealise = $pdo->prepare($queryRealise);
        $stmtRealise->bindParam(':ecueId', $ecue['idECUE']);
        $stmtRealise->bindParam(':anneeAcadId', $anneeAcadId);
        $stmtRealise->execute();
        $heuresRealisees = $stmtRealise->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Préparer les données pour cet ECUE
        $ecueStats = [
            'idECUE' => $ecue['idECUE'],
            'designationECUE' => $ecue['designationECUE'],
            'designationUE' => $ecue['designationUE'],
            'semestre' => $ecue['numeroSemestre'],
            'CM' => [
                'prevu' => $ecue['volumeHoraireCM'] ?: 0,
                'realise' => $heuresRealisees['CM'] ?? 0,
                'pourcentage' => 0
            ],
            'TD' => [
                'prevu' => $ecue['volumeHoraireTD'] ?: 0,
                'realise' => $heuresRealisees['TD'] ?? 0,
                'pourcentage' => 0
            ],
            'TP' => [
                'prevu' => $ecue['volumeHoraireTP'] ?: 0,
                'realise' => $heuresRealisees['TP'] ?? 0,
                'pourcentage' => 0
            ],
            'total' => [
                'prevu' => 0,
                'realise' => 0,
                'pourcentage' => 0
            ]
        ];
        
        // Calculer les totaux et pourcentages pour cet ECUE
        $ecueStats['total']['prevu'] = $ecueStats['CM']['prevu'] + $ecueStats['TD']['prevu'] + $ecueStats['TP']['prevu'];
        $ecueStats['total']['realise'] = $ecueStats['CM']['realise'] + $ecueStats['TD']['realise'] + $ecueStats['TP']['realise'];
        
        // Calculer les pourcentages
        foreach (['CM', 'TD', 'TP', 'total'] as $type) {
            if ($ecueStats[$type]['prevu'] > 0) {
                $ecueStats[$type]['pourcentage'] = round(($ecueStats[$type]['realise'] / $ecueStats[$type]['prevu']) * 100, 1);
            }
        }
        
        // Ajouter aux totaux globaux
        foreach (['CM', 'TD', 'TP'] as $type) {
            $totauxGlobaux[$type]['prevu'] += $ecueStats[$type]['prevu'];
            $totauxGlobaux[$type]['realise'] += $ecueStats[$type]['realise'];
        }
        
        $statistiques[] = $ecueStats;
    }
    
    // Calculer les totaux et pourcentages globaux
    $totauxGlobaux['total']['prevu'] = $totauxGlobaux['CM']['prevu'] + $totauxGlobaux['TD']['prevu'] + $totauxGlobaux['TP']['prevu'];
    $totauxGlobaux['total']['realise'] = $totauxGlobaux['CM']['realise'] + $totauxGlobaux['TD']['realise'] + $totauxGlobaux['TP']['realise'];
    
    foreach ($totauxGlobaux as $type => &$data) {
        if ($data['prevu'] > 0) {
            $data['pourcentage'] = round(($data['realise'] / $data['prevu']) * 100, 1);
        } else {
            $data['pourcentage'] = 0;
        }
    }
    
    return [
        'details' => $statistiques,
        'totaux' => $totauxGlobaux
    ];
}

// Récupérer les promotions accessibles
$promotions = [];
if ($isResponsableSection) {
    $promotions = getPromotionsAccessibles($pdo, $userSections, $currentYear['idannee_acad']);
} else {
    $promotions = getPromotionsAccessibles($pdo, [], $currentYear['idannee_acad']);
}

// Récupérer les semestres si une promotion est sélectionnée
$semestres = [];
if ($promotionFilter > 0) {
    $semestres = getSemestresByPromotion($pdo, $promotionFilter);
}

// Récupérer les statistiques si une promotion est sélectionnée
$statistiques = null;
if ($promotionFilter > 0) {
    if ($isResponsableSection) {
        $statistiques = getStatistiquesAvancement($pdo, $promotionFilter, $semestreFilter, $currentYear['idannee_acad'], $userSections);
    } else {
        $statistiques = getStatistiquesAvancement($pdo, $promotionFilter, $semestreFilter, $currentYear['idannee_acad']);
    }
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUIVI GLOBAL DES ENSEIGNEMENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Suivi global</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filtres -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Sélectionner une promotion</h5>
                
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="view" value="enseignement/suivi_global_enseignements">
                    
                    <div class="col-md-4">
                        <label class="form-label">Promotion</label>
                        <select name="promotion" class="form-select" id="promotionFilter" required>
                            <option value="">-- Sélectionner une promotion --</option>
                            <?php foreach ($promotions as $promo): ?>
                                <option value="<?= $promo['idpromotion'] ?>" <?= $promotionFilter == $promo['idpromotion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($promo['designationPromotion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Semestre (optionnel)</label>
                        <select name="semestre" class="form-select" id="semestreFilter">
                            <option value="">Tous les semestres</option>
                            <?php foreach ($semestres as $sem): ?>
                                <option value="<?= $sem['idsemestre'] ?>" <?= $semestreFilter == $sem['idsemestre'] ? 'selected' : '' ?>>
                                    Semestre <?= htmlspecialchars($sem['numeroSemestre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Afficher les statistiques
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($statistiques): ?>
        <!-- Statistiques globales -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Avancement Global</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['totaux']['total']['pourcentage'] ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $statistiques['totaux']['total']['realise'] ?>h / <?= $statistiques['totaux']['total']['prevu'] ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= $statistiques['totaux']['total']['pourcentage'] ?>%"
                                 aria-valuenow="<?= $statistiques['totaux']['total']['pourcentage'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Cours Magistraux (CM)</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['totaux']['CM']['pourcentage'] ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $statistiques['totaux']['CM']['realise'] ?>h / <?= $statistiques['totaux']['CM']['prevu'] ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?= $statistiques['totaux']['CM']['pourcentage'] ?>%"
                                 aria-valuenow="<?= $statistiques['totaux']['CM']['pourcentage'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux Dirigés (TD)</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['totaux']['TD']['pourcentage'] ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $statistiques['totaux']['TD']['realise'] ?>h / <?= $statistiques['totaux']['TD']['prevu'] ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?= $statistiques['totaux']['TD']['pourcentage'] ?>%"
                                 aria-valuenow="<?= $statistiques['totaux']['TD']['pourcentage'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux Pratiques (TP)</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['totaux']['TP']['pourcentage'] ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $statistiques['totaux']['TP']['realise'] ?>h / <?= $statistiques['totaux']['TP']['prevu'] ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?= $statistiques['totaux']['TP']['pourcentage'] ?>%"
                                 aria-valuenow="<?= $statistiques['totaux']['TP']['pourcentage'] ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau détaillé par ECUE -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Détail par cours (ECUE)
                    <div class="float-end">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Exporter Excel
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="printReport()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                    </div>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="statistiquesTable">
                        <thead>
                            <tr>
                                <th rowspan="2">#</th>
                                <th rowspan="2">Semestre</th>
                                <th rowspan="2">UE</th>
                                <th rowspan="2">ECUE</th>
                                <th colspan="3" class="text-center">CM</th>
                                <th colspan="3" class="text-center">TD</th>
                                <th colspan="3" class="text-center">TP</th>
                                <th colspan="3" class="text-center">TOTAL</th>
                            </tr>
                            <tr>
                                <th>Prévu</th>
                                <th>Réalisé</th>
                                <th>%</th>
                                <th>Prévu</th>
                                <th>Réalisé</th>
                                <th>%</th>
                                <th>Prévu</th>
                                <th>Réalisé</th>
                                <th>%</th>
                                <th>Prévu</th>
                                <th>Réalisé</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($statistiques['details'] as $ecue): 
                            ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td><?= htmlspecialchars($ecue['semestre']) ?></td>
                                <td><?= htmlspecialchars($ecue['designationUE']) ?></td>
                                <td><?= htmlspecialchars($ecue['designationECUE']) ?></td>
                                
                                <!-- CM -->
                                <td class="text-center"><?= $ecue['CM']['prevu'] ?></td>
                                <td class="text-center"><?= $ecue['CM']['realise'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getProgressColor($ecue['CM']['pourcentage']) ?>">
                                        <?= $ecue['CM']['pourcentage'] ?>%
                                    </span>
                                </td>
                                
                                <!-- TD -->
                                <td class="text-center"><?= $ecue['TD']['prevu'] ?></td>
                                <td class="text-center"><?= $ecue['TD']['realise'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getProgressColor($ecue['TD']['pourcentage']) ?>">
                                        <?= $ecue['TD']['pourcentage'] ?>%
                                    </span>
                                </td>
                                
                                <!-- TP -->
                                <td class="text-center"><?= $ecue['TP']['prevu'] ?></td>
                                <td class="text-center"><?= $ecue['TP']['realise'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getProgressColor($ecue['TP']['pourcentage']) ?>">
                                        <?= $ecue['TP']['pourcentage'] ?>%
                                    </span>
                                </td>
                                
                                <!-- TOTAL -->
                                <td class="text-center"><strong><?= $ecue['total']['prevu'] ?></strong></td>
                                <td class="text-center"><strong><?= $ecue['total']['realise'] ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getProgressColor($ecue['total']['pourcentage']) ?>">
                                        <?= $ecue['total']['pourcentage'] ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="4">TOTAUX</th>
                                <th class="text-center"><?= $statistiques['totaux']['CM']['prevu'] ?></th>
                                <th class="text-center"><?= $statistiques['totaux']['CM']['realise'] ?></th>
                                <th class="text-center">
                                    <span class="badge bg-<?= getProgressColor($statistiques['totaux']['CM']['pourcentage']) ?>">
                                        <?= $statistiques['totaux']['CM']['pourcentage'] ?>%
                                    </span>
                                </th>
                                <th class="text-center"><?= $statistiques['totaux']['TD']['prevu'] ?></th>
                                <th class="text-center"><?= $statistiques['totaux']['TD']['realise'] ?></th>
                                <th class="text-center">
                                    <span class="badge bg-<?= getProgressColor($statistiques['totaux']['TD']['pourcentage']) ?>">
                                        <?= $statistiques['totaux']['TD']['pourcentage'] ?>%
                                    </span>
                                </th>
                                <th class="text-center"><?= $statistiques['totaux']['TP']['prevu'] ?></th>
                                <th class="text-center"><?= $statistiques['totaux']['TP']['realise'] ?></th>
                                <th class="text-center">
                                    <span class="badge bg-<?= getProgressColor($statistiques['totaux']['TP']['pourcentage']) ?>">
                                        <?= $statistiques['totaux']['TP']['pourcentage'] ?>%
                                    </span>
                                </th>
                                <th class="text-center"><strong><?= $statistiques['totaux']['total']['prevu'] ?></strong></th>
                                <th class="text-center"><strong><?= $statistiques['totaux']['total']['realise'] ?></strong></th>
                                <th class="text-center">
                                    <span class="badge bg-<?= getProgressColor($statistiques['totaux']['total']['pourcentage']) ?>">
                                        <?= $statistiques['totaux']['total']['pourcentage'] ?>%
                                    </span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par type de cours</h5>
                        <canvas id="chartTypeCours" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Progression globale</h5>
                        <canvas id="chartProgression" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php
// Fonction pour déterminer la couleur en fonction du pourcentage
function getProgressColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'primary';
    if ($percentage >= 40) return 'warning';
    return 'danger';
}
?>

<script>
// Charger les semestres quand une promotion est sélectionnée
document.addEventListener('DOMContentLoaded', function() {
    const promotionFilter = document.getElementById('promotionFilter');
    const semestreFilter = document.getElementById('semestreFilter');
    
    if (promotionFilter && semestreFilter) {
        promotionFilter.addEventListener('change', function() {
            const promotionId = this.value;
            
            // Réinitialiser les semestres
            semestreFilter.innerHTML = '<option value="">Tous les semestres</option>';
            
            if (promotionId) {
                // Afficher un indicateur de chargement
                semestreFilter.disabled = true;
                
                // Charger les semestres via AJAX
                fetch(`controller/ajax_get_semestres.php?promotion=${promotionId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(semestre => {
                                const option = document.createElement('option');
                                option.value = semestre.idsemestre;
                                option.textContent = 'Semestre ' + semestre.numeroSemestre;
                                semestreFilter.appendChild(option);
                            });
                        }
                        semestreFilter.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des semestres:', error);
                        semestreFilter.disabled = false;
                        // Optionnel : afficher un message d'erreur à l'utilisateur
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Impossible de charger les semestres',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    });
            }
        });
        
        // Si une promotion est déjà sélectionnée au chargement de la page
        if (promotionFilter.value) {
            // Conserver la valeur du semestre sélectionné si elle existe
            const selectedSemestre = <?= json_encode($semestreFilter) ?>;
            if (selectedSemestre) {
                // Attendre un peu pour que les options soient chargées
                setTimeout(() => {
                    if (semestreFilter.querySelector(`option[value="${selectedSemestre}"]`)) {
                        semestreFilter.value = selectedSemestre;
                    }
                }, 500);
            }
        }
    }
});

// Exporter vers Excel
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'controller/export_suivi_global.php?' + params.toString();
}

// Imprimer le rapport
function printReport() {
    window.print();
}

<?php if ($statistiques): ?>
// Graphiques avec Chart.js
document.addEventListener('DOMContentLoaded', function() {
    // Graphique par type de cours
    const ctx1 = document.getElementById('chartTypeCours').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['CM', 'TD', 'TP'],
            datasets: [{
                label: 'Heures réalisées',
                data: [
                    <?= $statistiques['totaux']['CM']['realise'] ?>,
                    <?= $statistiques['totaux']['TD']['realise'] ?>,
                    <?= $statistiques['totaux']['TP']['realise'] ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value}h (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Graphique de progression
    const ctx2 = document.getElementById('chartProgression').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: ['CM', 'TD', 'TP', 'Total'],
            datasets: [{
                label: 'Heures prévues',
                data: [
                    <?= $statistiques['totaux']['CM']['prevu'] ?>,
                    <?= $statistiques['totaux']['TD']['prevu'] ?>,
                    <?= $statistiques['totaux']['TP']['prevu'] ?>,
                    <?= $statistiques['totaux']['total']['prevu'] ?>
                ],
                backgroundColor: 'rgba(201, 203, 207, 0.5)',
                borderColor: 'rgba(201, 203, 207, 1)',
                borderWidth: 1
            }, {
                label: 'Heures réalisées',
                data: [
                    <?= $statistiques['totaux']['CM']['realise'] ?>,
                    <?= $statistiques['totaux']['TD']['realise'] ?>,
                    <?= $statistiques['totaux']['TP']['realise'] ?>,
                    <?= $statistiques['totaux']['total']['realise'] ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Heures'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<style>
@media print {
    .btn, .form-select, .breadcrumb, .pagetitle nav {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 12px;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}
</style>

<?php include "./views/include/footer.php"; ?>