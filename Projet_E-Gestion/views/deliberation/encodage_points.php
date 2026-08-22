<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();
$deliberation = new Deliberation();

// Récupérer le crédit horaire et les pondérations depuis la configuration
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure, ponderation_cc_defaut, ponderation_ex_defaut FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;
$ponderationCCDefaut = $config && isset($config['ponderation_cc_defaut']) ? (float)$config['ponderation_cc_defaut'] : 0.4;
$ponderationEXDefaut = $config && isset($config['ponderation_ex_defaut']) ? (float)$config['ponderation_ex_defaut'] : 0.6;

// Vérifier si l'utilisateur est administrateur ou membre d'un jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryMember = false;
$isJuryPresident = false;

// Récupérer les bureaux de jury où l'agent est membre (président, secrétaire ou autre)
$juryBureaux = [];
if ($agentId) {
    $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
    $isJuryPresident = $deliberation->isJuryPresident($agentId);
}


// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres de sélection
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($bureauId && $sessionId && $anneeId) {
    // Récupérer les cours autorisés pour cet agent
    $authorizedEcues = [];
    if ($agentId && !$isAdmin) {
        $authorizedEcues = $universite->getAuthorizedEcuesForAgent($agentId, $sessionId, $anneeId);
        // Transformer le tableau pour un accès plus facile par ID
        $authorizedEcuesIds = array_column($authorizedEcues, 'idECUE');
    }
}


// Ajouter ce code pour récupérer la configuration de délibération

$configDeliberation = null;
$calculerAvecNotesVides = false;

if ($bureauId && $sessionId && $anneeId) {
    $configDeliberation = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
    $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ?
        (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
}


// Validation des accès
if ($bureauId && !$isAdmin) {
    // Vérifier si l'agent est membre de ce bureau
    $hasAccess = false;
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId) {
            $hasAccess = true;
            break;
        }
    }

    if (!$hasAccess) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas autorisé à encoder des points pour ce jury.'
            }).then(() => {
                window.location.href = 'deliberation/encodage_points';
            });
        </script>";
        exit();
    }
}

// Récupérer les données pour les sélecteurs
if ($isAdmin) {
    $bureaux = $deliberation->getJurys('', true); // Tous les jurys actifs
} else {
    $bureaux = $juryBureaux; // Seulement les jurys où l'agent est membre
}

// Récupérer les promotions associées au bureau sélectionné
$promotions = [];
if ($bureauId) {
    $promotions = $deliberation->getPromotionsByJury($bureauId);
}

// Récupérer les semestres de la promotion sélectionnée
$semestres = [];
if ($promotionId) {
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
}

// Récupérer les sessions et années académiques
$sessions = $deliberation->getAllSessions();
$annees = $deliberation->getAcademicYears();

// Récupérer les cours (ECUE) du semestre sélectionné
$ecues = [];
$statistiquesCotes = [];
if ($semestreId && $sessionId && $anneeId) {
    $ecues = $universite->getEcuesBySemestre($semestreId);

    // Pour chaque ECUE, récupérer les statistiques des cotes
    foreach ($ecues as $index => $ecueData) {
        $idEcue = $ecueData['idECUE'];
        $statistiques = $universite->getStatistiquesEcue($idEcue, $sessionId, $anneeId, $promotionId);
        $statistiquesCotes[$idEcue] = $statistiques;

        // Déterminer si c'est la deuxième session
        $isSecondSession = $ecue->isDeuxiemeSession($sessionId);

        // En deuxième session, vérifier quels étudiants ont échoué à l'UE
        if ($isSecondSession) {
            $ecues[$index]['etudiants_eligibles'] = $ecue->getStudentsEligibleForSecondSessionCours($idEcue, $anneeId);
        }
    }
}
?>


<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Encodage des Points par le Jury</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Encodage des points</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Sélection des paramètres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélection du bureau, de la promotion et du semestre</h5>

                        <!-- Simplification du formulaire avec un rechargement de page à chaque étape -->
                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/encodage_points">

                            <div class="col-md-3">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($bureaux as $bureau): ?>
                                        <option value="<?= $bureau['idbureau'] ?>" <?= ($bureauId == $bureau['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bureau['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Si un bureau est sélectionné, afficher les promotions disponibles -->
                            <?php if ($bureauId): ?>
                                <div class="col-md-3">
                                    <label for="promotion" class="form-label">Promotion</label>
                                    <select name="promotion" id="promotion" class="form-select" required onchange="this.form.submit()">
                                        <option value="">Sélectionner une promotion</option>
                                        <?php
                                        // Charger les promotions du bureau sélectionné
                                        $promotions = $universite->getPromotionsByJury($bureauId);
                                        foreach ($promotions as $promotion):
                                        ?>
                                            <option value="<?= $promotion['idpromotion'] ?>" <?= ($promotionId == $promotion['idpromotion']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($promotion['designationPromotion']) . '-' . $promotion['anneeDesignation'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <!-- Si une promotion est sélectionnée, afficher les semestres disponibles -->
                            <?php if ($promotionId): ?>
                                <div class="col-md-2">
                                    <label for="semestre" class="form-label">Semestre</label>
                                    <select name="semestre" id="semestre" class="form-select" required>
                                        <option value="">Sélectionner un semestre</option>
                                        <?php
                                        // Charger les semestres de la promotion sélectionnée
                                        $semestres = $universite->getSemestresByPromotion($promotionId);
                                        foreach ($semestres as $semestre):
                                        ?>
                                            <option value="<?= $semestre['idsemestre'] ?>" <?= ($semestreId == $semestre['idsemestre']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label for="session" class="form-label">Session</label>
                                    <select name="session" id="session" class="form-select" required>
                                        <option value="">Sélectionner une session</option>
                                        <?php foreach ($sessions as $session): ?>
                                            <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($session['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label for="annee" class="form-label">Année</label>
                                    <select name="annee" id="annee" class="form-select" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php foreach ($annees as $annee): ?>
                                            <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($annee['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Afficher les cours
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>


                        <!-- Ajouter ce bloc juste après les sélecteurs et avant l'affichage des cours -->
                        <?php if ($isJuryPresident && $bureauId): ?>
                            <div class="col-lg-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-shield-lock me-1"></i>
                                            Gestion des autorisations d'encodage
                                        </h5>
                                        <p>En tant que président du jury, vous pouvez définir qui est autorisé à encoder les points pour chaque cours.</p>
                                        <a href="deliberation/gestion_autorisations?bureau=<?= $bureauId ?>&promotion=<?= $promotionId ?>&semestre=<?= $semestreId ?>&session=<?= $sessionId ?>&annee=<?= $anneeId ?>" class="btn btn-primary">
                                            <i class="bi bi-person-check me-1"></i>
                                            Gérer les autorisations d'encodage
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>



                    </div>
                </div>
            </div>

            <!-- Affichage des cours (ECUE) -->
            <?php if ($semestreId && $sessionId && $anneeId && !empty($ecues)): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-book me-2"></i>
                                Liste des cours du semestre
                                <?php
                                foreach ($semestres as $semestre) {
                                    if ($semestre['idsemestre'] == $semestreId) {
                                        echo htmlspecialchars($semestre['numeroSemestre']);
                                        break;
                                    }
                                }

                                // Filtrer les ECUEs selon les autorisations (sauf pour les admins)
                                $filteredEcues = $ecues;
                                if (!$isAdmin && !$isJuryPresident && !empty($authorizedEcuesIds)) {
                                    $filteredEcues = array_filter($ecues, function ($ecueItem) use ($authorizedEcuesIds) {
                                        return in_array($ecueItem['idECUE'], $authorizedEcuesIds);
                                    });
                                }
                                ?>
                            </h5>

                            <!-- Filtres et options -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="searchCourses" placeholder="Rechercher un cours...">
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary" id="viewAll">Tous</button>
                                        <button type="button" class="btn btn-outline-success" id="viewSuccess">Réussite > 50%</button>
                                        <button type="button" class="btn btn-outline-danger" id="viewFailed">Réussite < 50%</button>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="courseCards">
                                <?php foreach ($filteredEcues  as $ecueItem): ?>
                                    <?php
                                    // Récupérer les statistiques
                                    $stats = $statistiquesCotes[$ecueItem['idECUE']] ?? [
                                        'reussite' => 0,
                                        'echec' => 0,
                                        'manquant' => 0,
                                        'moyenne' => 0,
                                        'max' => 0,
                                        'taux_reussite' => 0
                                    ];

                                    // Déterminer la classe de la card en fonction du taux de réussite
                                    $cardClass = '';
                                    $tauxReussite = $stats['taux_reussite'] ?? 0;
                                    if ($tauxReussite >= 70) {
                                        $cardClass = 'border-success';
                                    } elseif ($tauxReussite >= 50) {
                                        $cardClass = 'border-warning';
                                    } else {
                                        $cardClass = 'border-danger';
                                    }
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-4 course-card"
                                        data-taux="<?= $tauxReussite ?>"
                                        data-name="<?= strtolower(htmlspecialchars($ecueItem['designationECUE'])) ?>">
                                        <div class="card h-100 shadow-sm <?= $cardClass ?>" style="border-width: 2px;">
                                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="card-title text-white mb-0"><?= htmlspecialchars($ecueItem['designationECUE']) ?></h5>
                                                    <small>UE: <?= htmlspecialchars($ecueItem['designationUE']) ?></small>
                                                </div>
                                                <span class="badge bg-light text-dark rounded-pill">
                                                    <?= ((float)$ecueItem['CMI'] + (float)$ecueItem['TD'] + (float)$ecueItem['TP']) / $heureCredit ?> crédits
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-3">

                                                    <div class="col-4 text-center">
                                                        <div class="stat-circle bg-success text-white">
                                                            <span class="stat-number"><?= $stats['reussite'] ?? 0 ?></span>
                                                        </div>
                                                        <div class="stat-label">Réussites</div>
                                                    </div>
                                                    <div class="col-4 text-center">
                                                        <div class="stat-circle bg-danger text-white">
                                                            <span class="stat-number"><?= $stats['echec'] ?? 0 ?></span>
                                                        </div>
                                                        <div class="stat-label">Échecs</div>
                                                    </div>
                                                    <div class="col-4 text-center">
                                                        <div class="stat-circle bg-warning text-white">
                                                            <span class="stat-number"><?= $stats['manquant'] ?? 0 ?></span>
                                                        </div>
                                                        <div class="stat-label">Manquants</div>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-calculator me-2 text-info fs-4"></i>
                                                            <div>
                                                                <div class="small text-muted">Moyenne</div>
                                                                <div class="fw-bold"><?= number_format($stats['moyenne'] ?? 0, 2) ?>/20</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-trophy me-2 text-primary fs-4"></i>
                                                            <div>
                                                                <div class="small text-muted">Note Max</div>
                                                                <div class="fw-bold"><?= number_format($stats['max'] ?? 0, 2) ?>/20</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="progress mb-3" style="height: 20px;">
                                                    <?php
                                                    $progressClass = 'bg-danger';
                                                    if ($tauxReussite >= 70) {
                                                        $progressClass = 'bg-success';
                                                    } elseif ($tauxReussite >= 50) {
                                                        $progressClass = 'bg-warning';
                                                    } elseif ($tauxReussite >= 30) {
                                                        $progressClass = 'bg-info';
                                                    }
                                                    ?>
                                                    <div class="progress-bar <?= $progressClass ?>" role="progressbar"
                                                        style="width: <?= $tauxReussite ?>%;"
                                                        aria-valuenow="<?= $tauxReussite ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?= number_format($tauxReussite, 1) ?>%
                                                    </div>
                                                </div>

                                                <?php if ($ecue->isDeuxiemeSession($sessionId)): ?>
                                                    <div class="alert alert-info d-flex align-items-center">
                                                        <i class="bi bi-info-circle-fill me-2"></i>
                                                        <small>
                                                            <?= count($ecueItem['etudiants_eligibles'] ?? []) ?> étudiant(s) éligible(s) pour la 2ème session
                                                        </small>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="text-center mt-3">
                                                    <button type="button" class="btn btn-primary btn-lg w-100"
                                                        onclick="ouvrirFicheCotes(<?= $ecueItem['idECUE'] ?>, <?= $sessionId ?>, <?= $anneeId ?>, <?= $promotionId ?>)">
                                                        <i class="bi bi-pencil-square me-2"></i> Encoder les points
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> Dernière modification:
                                                        <?= isset($stats['derniere_modification']) ? date('d/m/Y H:i', strtotime($stats['derniere_modification'])) : 'Jamais' ?>
                                                    </small>
                                                    <span class="badge <?= $tauxReussite >= 50 ? 'bg-success' : 'bg-danger' ?>">
                                                        <?= $tauxReussite >= 50 ? 'Satisfaisant' : 'À améliorer' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($semestreId && $sessionId && $anneeId): ?>
                <div class="col-lg-12">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                        <div>
                            Aucun cours trouvé pour ce semestre. Veuillez vérifier vos sélections ou contacter l'administrateur.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($semestreId && $sessionId && $anneeId && !empty($ecues)): ?>
                <!-- Tableau de statistiques du semestre -->
                <div class="col-lg-12 mt-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-bar-chart-line me-2"></i>
                                Statistiques globales du semestre
                            </h5>

                            <?php
                            // Calculer les statistiques globales du semestre
                            $totalCours = count($ecues);
                            $totalCredits = 0;

                            // Variables pour calculer la moyenne des taux de réussite
                            $sommeTauxReussite = 0;
                            $coursAvecDonnees = 0;

                            // Tableau pour stocker les cours avec leurs taux d'échec
                            $coursEchecs = [];

                            foreach ($ecues as $ecueItem) {
                                // Calculer les crédits pour ce cours et les ajouter au total
                                $creditsEcue = ((float)$ecueItem['CMI'] + (float)$ecueItem['TD'] + (float)$ecueItem['TP']) / $heureCredit;
                                $totalCredits += $creditsEcue;

                                $stats = $statistiquesCotes[$ecueItem['idECUE']] ?? [
                                    'reussite' => 0,
                                    'echec' => 0,
                                    'manquant' => 0,
                                    'taux_reussite' => 0
                                ];

                                $tauxReussite = $stats['taux_reussite'] ?? 0;

                                // Ne compter que les cours qui ont des données
                                if (($stats['reussite'] + $stats['echec']) > 0) {
                                    $sommeTauxReussite += $tauxReussite;
                                    $coursAvecDonnees++;
                                }

                                // Ajouter le cours au tableau des échecs
                                $coursEchecs[] = [
                                    'designation' => $ecueItem['designationECUE'],
                                    'echecs' => $stats['echec'] ?? 0,
                                    'total' => ($stats['reussite'] ?? 0) + ($stats['echec'] ?? 0),
                                    'taux_echec' => 100 - $tauxReussite
                                ];
                            }

                            // Calculer le taux de réussite moyen du semestre
                            $tauxReussiteGlobal = $coursAvecDonnees > 0 ? $sommeTauxReussite / $coursAvecDonnees : 0;

                            // Trier les cours par taux d'échec décroissant
                            usort($coursEchecs, function ($a, $b) {
                                return $b['taux_echec'] - $a['taux_echec'];
                            });

                            // Prendre les 3 premiers (top 3 des échecs)
                            $topEchecs = array_slice($coursEchecs, 0, 3);

                            // Calculer le nombre total d'étudiants (en prenant le premier cours comme référence)
                            $totalEtudiants = 0;
                            if (!empty($coursEchecs)) {
                                $totalEtudiants = $coursEchecs[0]['total'];
                            }
                            // Déterminer si c'est la deuxième session
                            $isSecondSession = $ecue->isDeuxiemeSession($sessionId);
                            ?>
                            <?php if ($isSecondSession) { ?>
                            <?php } else { ?>
                                <div class="row">
                                    <!-- Statistiques générales -->
                                    <div class="col-md-6">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead class="table-primary">
                                                    <tr>
                                                        <th colspan="2" class="text-center">Statistiques générales</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td width="60%"><strong>Nombre total de cours</strong></td>
                                                        <td class="text-center"><?= $totalCours ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Total des crédits du semestre</strong></td>
                                                        <td class="text-center fw-bold"><?= number_format($totalCredits, 1) ?> crédits</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Nombre d'étudiants par cours</strong></td>
                                                        <td class="text-center"><?= $totalEtudiants ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Nombre de cours avec données</strong></td>
                                                        <td class="text-center"><?= $coursAvecDonnees ?></td>
                                                    </tr>
                                                    <tr class="table-info">
                                                        <td><strong>Taux de réussite moyen du semestre</strong></td>
                                                        <td class="text-center fw-bold <?= $tauxReussiteGlobal >= 50 ? 'text-success' : 'text-danger' ?>">
                                                            <?= number_format($tauxReussiteGlobal, 2) ?>%
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Top 3 des cours avec le plus d'échecs -->
                                    <div class="col-md-6">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead class="table-danger">
                                                    <tr>
                                                        <th colspan="3" class="text-center">Top 3 des cours avec le plus d'échecs</th>
                                                    </tr>
                                                    <tr>
                                                        <th width="10%">#</th>
                                                        <th width="60%">Cours</th>
                                                        <th width="30%">Taux d'échec</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($topEchecs)): ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">Aucune donnée disponible</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($topEchecs as $index => $cours): ?>
                                                            <tr>
                                                                <td class="text-center"><?= $index + 1 ?></td>
                                                                <td><?= htmlspecialchars($cours['designation']) ?></td>
                                                                <td class="text-center text-danger fw-bold">
                                                                    <?= number_format($cours['taux_echec'], 2) ?>%
                                                                    <small class="text-muted d-block">(<?= $cours['echecs'] ?>/<?= $cours['total'] ?>)</small>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>


                                <!-- Graphique de progression du taux de réussite -->
                                <div class="mt-4">
                                    <div class="progress" style="height: 25px;">
                                        <?php
                                        $progressClass = 'bg-danger';
                                        if ($tauxReussiteGlobal >= 70) {
                                            $progressClass = 'bg-success';
                                        } elseif ($tauxReussiteGlobal >= 50) {
                                            $progressClass = 'bg-warning';
                                        } elseif ($tauxReussiteGlobal >= 30) {
                                            $progressClass = 'bg-info';
                                        }
                                        ?>
                                        <div class="progress-bar <?= $progressClass ?>" role="progressbar"
                                            style="width: <?= $tauxReussiteGlobal ?>%;"
                                            aria-valuenow="<?= $tauxReussiteGlobal ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="fw-bold"><?= number_format($tauxReussiteGlobal, 1) ?>% de réussite moyenne</span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>



</main>
<!-- Modal pour l'encodage des points -->
<div class="modal fade" id="ficheCotesModal" tabindex="-1" aria-labelledby="ficheCotesModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ficheCotesModalLabel">Encodage des points</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Information du cours -->
                <div id="infoEcue" class="mb-4"></div>

                <!-- Ajouter ceci juste après l'affichage des informations du cours, avant le tableau -->
                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="coteUniqueSwitch">
                        <label class="form-check-label" for="coteUniqueSwitch">
                            <span class="badge bg-info">Mode cote unique</span>
                            <small class="text-muted d-block">Lorsque activé, la note d'examen sera dupliquée vers le CC si ce dernier est vide</small>
                        </label>
                    </div>

                    <div>
                        <button type="button" class="btn btn-info me-2" onclick="exportNotesTemplate()">
                            <i class="bi bi-file-earmark-excel"></i> Exporter modèle Excel
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importCotesModal">
                            <i class="bi bi-file-earmark-arrow-up"></i> Importer depuis Excel
                        </button>
                    </div>
                </div>


                <!-- Tableau des cotes -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableCotes">
                        <thead class="table-primary">
                            <tr>
                                <th width="5%">#</th>
                                <th width="30%">Étudiant</th>
                                <th width="15%">Matricule</th>
                                <th width="12%">CC /20</th>
                                <th width="12%">EX /20</th>
                                <th width="12%">MF /20</th>
                                <?php if ($isAdmin || $isJuryPresident): ?>
                                    <th width="14%">Historique</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyCotes">
                            <tr>
                                <td colspan="<?php echo ($isAdmin || $isJuryPresident) ? '7' : '6'; ?>" class="text-center">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="btnSauvegarder">
                    <i class="bi bi-save"></i> Sauvegarder
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour importer des notes depuis Excel -->
<div class="modal fade" id="importCotesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des notes depuis Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importCotesForm" action="controller/import_cotes.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="ecueId" id="import_ecueId">
                    <input type="hidden" name="sessionId" id="import_sessionId">
                    <input type="hidden" name="anneeId" id="import_anneeId">
                    <input type="hidden" name="promotionId" id="import_promotionId">
                    <!-- Ajouter ces deux champs cachés -->
                    <input type="hidden" name="bureauId" id="import_bureauId">
                    <input type="hidden" name="semestreId" id="import_semestreId">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Veuillez d'abord exporter le modèle Excel, remplir les notes, puis le réimporter ici.
                        <br>
                        <strong>Important :</strong> Ne modifiez pas la structure du fichier Excel.
                    </div>

                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel (*.xlsx)</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx" required>
                        <div class="form-text">Seuls les fichiers Excel (XLSX) sont acceptés.</div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmImport" name="confirmImport" required>
                        <label class="form-check-label" for="confirmImport">
                            Je confirme que les données importées sont correctes et correspondent au cours sélectionné.
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Importer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire caché pour l'export du modèle -->
<form id="exportTemplateForm" action="controller/export_cotes_template.php" method="POST" style="display: none;">
    <input type="hidden" name="ecueId" id="export_template_ecueId">
    <input type="hidden" name="sessionId" id="export_template_sessionId">
    <input type="hidden" name="anneeId" id="export_template_anneeId">
    <input type="hidden" name="promotionId" id="export_template_promotionId">
</form>


<!-- Modal pour afficher l'historique des modifications -->
<div class="modal fade" id="historiqueModal" tabindex="-1" aria-labelledby="historiqueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="historiqueModalLabel">Historique des modifications</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="infoEtudiant" class="mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableHistorique">
                        <thead class="table-info">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Motif</th>
                                <th>CC Avant</th>
                                <th>EX Avant</th>
                                <th>MF Avant</th>
                                <th>CC Après</th>
                                <th>EX Après</th>
                                <th>MF Après</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorique">
                            <tr>
                                <td colspan="10" class="text-center">Chargement de l'historique...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Passer la configuration au JavaScript
    const calculerAvecNotesVides = <?php echo $calculerAvecNotesVides ? 'true' : 'false'; ?>;
</script>

<script>
    // Variable pour le modal d'historique
    let historiqueModal;

    // Variables globales
    let ficheCotesModal;
    let currentEcueId, currentSessionId, currentAnneeId, currentPromotionId;
    let currentPonderations = {
        cc: <?= $ponderationCCDefaut ?>,
        ex: <?= $ponderationEXDefaut ?>
    }; // Valeurs par défaut configurables
    const bureauSelect = document.getElementById('bureau');
    const promotionSelect = document.getElementById('promotion');
    console.log('Element select des promotions:', promotionSelect);

    const semestreSelect = document.getElementById('semestre');
    const sessionSelect = document.getElementById('session');
    const anneeSelect = document.getElementById('annee');

    let isAdminOrPresident = <?php echo ($isAdmin || $isJuryPresident) ? 'true' : 'false'; ?>;

    // Fonction pour exporter le modèle Excel
    function exportNotesTemplate() {
        document.getElementById('export_template_ecueId').value = currentEcueId;
        document.getElementById('export_template_sessionId').value = currentSessionId;
        document.getElementById('export_template_anneeId').value = currentAnneeId;
        document.getElementById('export_template_promotionId').value = currentPromotionId;
        document.getElementById('exportTemplateForm').submit();
    }

    // Fonction pour préparer le formulaire d'importation
    function prepareImportForm() {
        document.getElementById('import_ecueId').value = currentEcueId;
        document.getElementById('import_sessionId').value = currentSessionId;
        document.getElementById('import_anneeId').value = currentAnneeId;
        document.getElementById('import_promotionId').value = currentPromotionId;

        // Ajouter ces deux lignes pour récupérer les valeurs des sélecteurs
        document.getElementById('import_bureauId').value = document.getElementById('bureau').value;
        document.getElementById('import_semestreId').value = document.getElementById('semestre').value;
    }

    let isCoteUnique = false;

    document.addEventListener('DOMContentLoaded', function() {
        animateCounters();
        // Initialize the modal only when it's used
        const ficheCotesModalElement = document.getElementById('ficheCotesModal');
        if (ficheCotesModalElement) {
            // Use the global variable instead of creating a new local one
            ficheCotesModal = new bootstrap.Modal(ficheCotesModalElement);

            // Add the event to the Save button if it exists
            const btnSauvegarder = document.getElementById('btnSauvegarder');
            if (btnSauvegarder) {
                btnSauvegarder.addEventListener('click', function() {
                    sauvegarderCotes(currentEcueId, currentSessionId, currentAnneeId);
                });
            }
        }


        // Préparer le formulaire d'importation lorsque le modal est ouvert
        const importCotesModal = document.getElementById('importCotesModal');
        if (importCotesModal) {
            importCotesModal.addEventListener('show.bs.modal', function(event) {
                prepareImportForm();
            });
        }

        // Initialiser le modal d'historique
        const historiqueModalElement = document.getElementById('historiqueModal');
        if (historiqueModalElement) {
            historiqueModal = new bootstrap.Modal(historiqueModalElement);
        }

        // Définir si l'utilisateur est admin ou président pour l'affichage conditionnel
        isAdminOrPresident = <?php echo ($isAdmin || $isJuryPresident) ? 'true' : 'false'; ?>;

        // Ajouter cette partie pour le switch de cote unique
        const coteUniqueSwitch = document.getElementById('coteUniqueSwitch');
        if (coteUniqueSwitch) {
            coteUniqueSwitch.addEventListener('change', function() {
                isCoteUnique = this.checked;

                // Recalculer toutes les moyennes selon le nouveau mode
                document.querySelectorAll('#tbodyCotes tr').forEach(row => {
                    const ccInput = row.querySelector('input[data-type="CC"]');
                    const exInput = row.querySelector('input[data-type="EX"]');

                    // Si le mode cote unique est activé et que CC est vide mais EX a une valeur
                    if (isCoteUnique && ccInput.value === '' && exInput.value !== '') {
                        ccInput.value = exInput.value;
                    }

                    // Déclencher le calcul de la moyenne
                    exInput.dispatchEvent(new Event('change'));
                });
            });
        }
    });


    // Fonctionnalités de recherche et filtrage pour les cards de cours
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchCourses');
        const viewAllBtn = document.getElementById('viewAll');
        const viewSuccessBtn = document.getElementById('viewSuccess');
        const viewFailedBtn = document.getElementById('viewFailed');
        const courseCards = document.querySelectorAll('.course-card');

        if (searchInput) {
            searchInput.addEventListener('input', filterCourses);
        }

        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', function() {
                resetButtons();
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                showAllCourses();
            });
        }

        if (viewSuccessBtn) {
            viewSuccessBtn.addEventListener('click', function() {
                resetButtons();
                this.classList.remove('btn-outline-success');
                this.classList.add('btn-success');
                filterCoursesBySuccess(true);
            });
        }

        if (viewFailedBtn) {
            viewFailedBtn.addEventListener('click', function() {
                resetButtons();
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                filterCoursesBySuccess(false);
            });
        }

        function resetButtons() {
            viewAllBtn.className = 'btn btn-outline-primary';
            viewSuccessBtn.className = 'btn btn-outline-success';
            viewFailedBtn.className = 'btn btn-outline-danger';
        }

        function showAllCourses() {
            courseCards.forEach(card => {
                card.style.display = 'block';
            });

            // Appliquer le filtre de recherche actuel
            if (searchInput.value.trim() !== '') {
                filterCourses();
            }
        }

        function filterCoursesBySuccess(isSuccess) {
            courseCards.forEach(card => {
                const tauxReussite = parseFloat(card.getAttribute('data-taux'));
                if ((isSuccess && tauxReussite >= 50) || (!isSuccess && tauxReussite < 50)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Appliquer le filtre de recherche actuel
            if (searchInput.value.trim() !== '') {
                filterCourses();
            }
        }

        function filterCourses() {
            const searchTerm = searchInput.value.toLowerCase().trim();

            courseCards.forEach(card => {
                // Ne pas modifier la visibilité si déjà masqué par un autre filtre
                if (card.style.display !== 'none' || searchTerm === '') {
                    const courseName = card.getAttribute('data-name');
                    if (courseName.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
        // Animation des barres de progression au chargement
        setTimeout(() => {
            document.querySelectorAll('.progress-bar').forEach(bar => {
                const width = bar.getAttribute('aria-valuenow') + '%';
                bar.style.width = width;
            });
        }, 300);

        // Ajouter des tooltips pour les statistiques
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Fonction pour animer les compteurs de statistiques
    function animateCounters() {
        document.querySelectorAll('.stat-number').forEach(counter => {
            const target = parseInt(counter.textContent);
            let count = 0;
            const duration = 1000; // ms
            const frameRate = 30; // fps
            const increment = target / (duration / (1000 / frameRate));

            const timer = setInterval(() => {
                count += increment;
                if (count >= target) {
                    clearInterval(timer);
                    counter.textContent = target;
                } else {
                    counter.textContent = Math.floor(count);
                }
            }, 1000 / frameRate);
        });
    }





    // Fonction pour afficher l'historique des modifications
    function afficherHistorique(matricule, nomEtudiant) {
        // Afficher un loader dans le modal
        document.getElementById('infoEtudiant').innerHTML = `Chargement de l'historique pour ${nomEtudiant} (${matricule})...`;
        document.getElementById('tbodyHistorique').innerHTML = '<tr><td colspan="10" class="text-center">Chargement des données...</td></tr>';

        // Ouvrir le modal
        historiqueModal.show();

        // Charger les données d'historique
        fetch(`controller/get_historique_cotes.php?ecue=${currentEcueId}&session=${currentSessionId}&annee=${currentAnneeId}&matricule=${matricule}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.error
                    });
                    return;
                }

                // Afficher les informations de l'étudiant
                document.getElementById('historiqueModalLabel').textContent = `Historique des modifications - ${nomEtudiant}`;
                document.getElementById('infoEtudiant').innerHTML = `
                <div class="alert alert-info">
                    <strong>Étudiant:</strong> ${nomEtudiant}<br>
                    <strong>Matricule:</strong> ${matricule}<br>
                    <strong>ECUE:</strong> ${data.ecue?.designationECUE || 'N/A'}<br>
                    <strong>Session:</strong> ${data.session?.designSession || 'N/A'}
                </div>
            `;

                // Afficher l'historique
                if (data.historique && data.historique.length > 0) {
                    let tableHtml = '';
                    data.historique.forEach((item, index) => {
                        tableHtml += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${formatDate(item.date_modification)}</td>
                            <td>${item.nom_utilisateur}</td>
                            <td>${item.motif}</td>
                            <td>${item.cc_avant !== null ? item.cc_avant : 'N/A'}</td>
                            <td>${item.ex_avant !== null ? item.ex_avant : 'N/A'}</td>
                            <td>${item.mf_avant !== null ? item.mf_avant : 'N/A'}</td>
                            <td>${item.cc_apres !== null ? item.cc_apres : 'N/A'}</td>
                            <td>${item.ex_apres !== null ? item.ex_apres : 'N/A'}</td>
                            <td>${item.mf_apres !== null ? item.mf_apres : 'N/A'}</td>
                        </tr>
                    `;
                    });
                    document.getElementById('tbodyHistorique').innerHTML = tableHtml;
                } else {
                    document.getElementById('tbodyHistorique').innerHTML = '<tr><td colspan="10" class="text-center">Aucun historique trouvé</td></tr>';
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement de l\'historique:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger l\'historique. Veuillez réessayer.'
                });
            });
    }

    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }



    // Fonction pour ouvrir la fiche des cotes
    function ouvrirFicheCotes(ecueId, sessionId, anneeId, promotionId) {
        console.log('Ouverture de la fiche cotes:', ecueId, sessionId, anneeId, promotionId);

        // Stocker les valeurs courantes
        currentEcueId = ecueId;
        currentSessionId = sessionId;
        currentAnneeId = anneeId;
        currentPromotionId = promotionId;

        currentBureauId = <?php echo $bureauId; ?>;

        // Afficher un loader dans le modal
        document.getElementById('infoEcue').innerHTML = 'Chargement des informations...';
        document.getElementById('tbodyCotes').innerHTML = '<tr><td colspan="' + (isAdminOrPresident ? '7' : '6') + '" class="text-center">Chargement des données...</td></tr>';

        // Ouvrir le modal
        ficheCotesModal.show();

        // Charger les données
        fetch(`controller/get_fiche_cotes.php?ecue=${ecueId}&session=${sessionId}&annee=${anneeId}&promotion=${promotionId}`)
            .then(response => {
                console.log('Réponse reçue:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);

                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.error
                    });
                    return;
                }

                // Stocker les pondérations reçues du serveur
                if (data.ponderations) {
                    currentPonderations = data.ponderations;
                    console.log('Pondérations récupérées:', currentPonderations);
                }

                // Stocker le flag de 2ème session dans la variable globale
                isSecondSession = data.isSecondSession || false;
                const cotesPremiereSession = data.cotesPremiereSession || [];
                console.log('Is Second Session:', isSecondSession);
                console.log('Cotes Première Session:', cotesPremiereSession);

                // Afficher les informations de l'ECUE
                afficherInfoEcue(data.ecue);

                // Récupérer le nombre de modifications pour chaque étudiant
                if (isAdminOrPresident) {
                    fetch(`controller/get_historique_count.php?ecue=${ecueId}&session=${sessionId}&annee=${anneeId}`)
                        .then(response => response.json())
                        .then(historiqueData => {
                            // Ajouter le nombre d'historiques à chaque cote
                            if (data.cotes && historiqueData) {
                                data.cotes.forEach(cote => {
                                    cote.historique_count = historiqueData[cote.matricule] || 0;
                                });
                            }

                            // Afficher les cotes des étudiants avec le compteur d'historique
                            afficherCotes(data.etudiants, data.cotes, isSecondSession, cotesPremiereSession);
                        })
                        .catch(error => {
                            console.error('Erreur lors du chargement des compteurs d\'historique:', error);
                            // Afficher quand même les cotes sans le compteur d'historique
                            afficherCotes(data.etudiants, data.cotes, isSecondSession, cotesPremiereSession);
                        });
                } else {
                    // Afficher les cotes des étudiants sans le compteur d'historique
                    afficherCotes(data.etudiants, data.cotes, isSecondSession, cotesPremiereSession);
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement de la fiche cotes:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger les données. Veuillez réessayer.'
                });
            });
    }

    const heureCredit = <?php echo $heureCredit; ?>;
    // Variable globale pour stocker l'état de la deuxième session
    let isSecondSession = false;
    
    // Fonction pour afficher les informations de l'ECUE
    function afficherInfoEcue(ecueData) {
        document.getElementById('ficheCotesModalLabel').textContent = `Encodage des points - ${ecueData.designationECUE}`;

        const infoHtml = `
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>UE:</strong> ${ecueData.designationUE}</p>
                        <p class="mb-1"><strong>Code UE:</strong> ${ecueData.codeUE || 'N/A'}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Promotion:</strong> ${ecueData.designationPromotion}</p>
                        <p class="mb-1"><strong>Semestre:</strong> ${ecueData.numeroSemestre}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Crédits:</strong> ${((ecueData.CMI || 0) + (ecueData.TD || 0) + (ecueData.TP || 0))/heureCredit} crédits</p>
                       <p class="mb-1"><strong>Session:</strong> ${ecueData.designSession}</p>
                       <p class="mb-1"><strong>Pondérations:</strong> CC: ${(currentPonderations.cc * 100).toFixed(0)}%, EX: ${(currentPonderations.ex * 100).toFixed(0)}%</p>
                    </div>
                </div>
            </div>
        </div>
    `;

        document.getElementById('infoEcue').innerHTML = infoHtml;
    }


    // Fonction pour afficher les cotes des étudiants
    function afficherCotes(etudiants, cotes, isSecondSessionParam = false, cotesPremiereSession = []) {
        // Assigner les paramètres aux variables globales
        isSecondSession = isSecondSessionParam;
        
        if (!etudiants || etudiants.length === 0) {
            document.getElementById('tbodyCotes').innerHTML = '<tr><td colspan="' + (isAdminOrPresident ? '7' : '6') + '" class="text-center">Aucun étudiant trouvé</td></tr>';
            return;
        }

        // Préparer un objet pour accéder plus facilement aux cotes par matricule
        const cotesByMatricule = {};
        cotes.forEach(cote => {
            cotesByMatricule[cote.matricule] = cote;
        });

        // Préparer un objet pour accéder aux cotes de première session
        const cotesPremiereSessionByMatricule = {};
        cotesPremiereSession.forEach(cote => {
            cotesPremiereSessionByMatricule[cote.matricule] = cote;
        });

        // Générer les lignes du tableau
        let tableHtml = '';
        etudiants.forEach((etudiant, index) => {
            const cote = cotesByMatricule[etudiant.matricule] || {};
            const cotePremiereSession = cotesPremiereSessionByMatricule[etudiant.matricule] || {};

            // En deuxième session, préremplir CC avec la cote de 1ère session
            let ccValue = cote.CC !== undefined ? cote.CC : '';
            let exValue = cote.EX !== undefined ? cote.EX : '';

            if (isSecondSession && ccValue === '' && cotePremiereSession.CC !== undefined) {
                ccValue = cotePremiereSession.CC; // Copier CC de 1ère session
            }

            // Déterminer la valeur de MF en fonction de la configuration
            let mfValue = '';
            if (cote.MF !== undefined && cote.MF !== null) {
                // Si MF existe déjà dans les données, l'utiliser
                mfValue = cote.MF;
            } else {
                // En 2ème session : toujours appliquer les pondérations si CC et EX sont présents
                if (isSecondSession) {
                    if (ccValue !== '' && exValue !== '') {
                        // Si les deux notes sont présentes en 2ème session, appliquer les pondérations
                        mfValue = ((parseFloat(ccValue) * currentPonderations.cc) + (parseFloat(exValue) * currentPonderations.ex)).toFixed(2);
                    }
                } else if (calculerAvecNotesVides) {
                    // Si on est configuré pour calculer avec des notes vides (1ère session)
                    if (ccValue !== '' && exValue !== '') {
                        // Si les deux notes sont présentes
                        mfValue = ((parseFloat(ccValue) * currentPonderations.cc) + (parseFloat(exValue) * currentPonderations.ex)).toFixed(2);
                    } else if (ccValue !== '') {
                        // Si seulement CC est présent
                        mfValue = parseFloat(ccValue).toFixed(2);
                    } else if (exValue !== '') {
                        // Si seulement EX est présent
                        mfValue = parseFloat(exValue).toFixed(2);
                    }
                } else {
                    // Si on n'est pas configuré pour calculer avec des notes vides (1ère session)
                    // Ne calculer que si les deux notes sont présentes
                    if (ccValue !== '' && exValue !== '') {
                        mfValue = ((parseFloat(ccValue) * currentPonderations.cc) + (parseFloat(exValue) * currentPonderations.ex)).toFixed(2);
                    }
                }
            }

            const historiqueCount = cote.historique_count || 0;

            const rowClass = mfValue !== '' ? (parseFloat(mfValue) >= 10 ? 'table-success' : 'table-danger') : '';

            // Le reste du code pour générer la ligne du tableau reste inchangé
            tableHtml += `
            <tr class="${rowClass}" data-matricule="${etudiant.matricule}">
                <td>${index + 1}</td>
                <td>${etudiant.noms}</td>
                <td>${etudiant.matricule}</td>
                <td>
                    <input type="number" class="form-control form-control-sm cote-input" 
                           data-type="CC" min="0" max="20" step="0.01" value="${ccValue}">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm cote-input" 
                           data-type="EX" min="0" max="20" step="0.01" value="${exValue}">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           data-type="MF" min="0" max="20" step="0.01" value="${mfValue}" readonly>
                </td>`;

            // Ajouter la colonne d'historique si l'utilisateur est admin ou président
            if (isAdminOrPresident) {
                tableHtml += `
                <td>
                    <button type="button" class="btn btn-sm ${historiqueCount > 0 ? 'btn-info' : 'btn-outline-secondary'}" 
                            onclick="afficherHistorique('${etudiant.matricule}', '${etudiant.noms}')" 
                            ${historiqueCount === 0 ? 'disabled' : ''}>
                        <i class="bi bi-clock-history"></i> 
                        ${historiqueCount > 0 ? historiqueCount + ' modification(s)' : 'Aucune modification'}
                    </button>
                </td>`;
            }

            tableHtml += `</tr>`;
        });

        document.getElementById('tbodyCotes').innerHTML = tableHtml;
        // Ajouter des écouteurs d'événements pour le calcul automatique de la MF
        // Remplacer la fonction existante qui ajoute des écouteurs d'événements pour le calcul automatique de la MF
        // Rechercher cette partie dans le code existant (vers la ligne 1000-1100)
        document.querySelectorAll('.cote-input').forEach(input => {
            input.addEventListener('change', function() {
                // Validation de la cote maximale à 20
                if (this.value !== '' && parseFloat(this.value) > 20) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cote invalide',
                        text: 'La cote ne peut pas être supérieure à 20.',
                        confirmButtonText: 'OK'
                    });
                    this.value = '20'; // Limiter à 20
                }

                // Validation de la cote minimale à 0
                if (this.value !== '' && parseFloat(this.value) < 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cote invalide',
                        text: 'La cote ne peut pas être négative.',
                        confirmButtonText: 'OK'
                    });
                    this.value = '0'; // Limiter à 0
                }

                const row = this.closest('tr');
                const ccInput = row.querySelector('input[data-type="CC"]');
                const exInput = row.querySelector('input[data-type="EX"]');
                const mfInput = row.querySelector('input[data-type="MF"]');

                // Si c'est le champ EX qui a été modifié et mode cote unique activé
                if (this.dataset.type === 'EX' && isCoteUnique && this.value !== '' && ccInput.value === '') {
                    ccInput.value = this.value;
                }

                const cc = ccInput.value !== '' ? parseFloat(ccInput.value) : null;
                const ex = exInput.value !== '' ? parseFloat(exInput.value) : null;

                // Calculer la moyenne finale en tenant compte de la configuration
                // En 2ème session : appliquer les pondérations si CC et EX sont présents
                if (isSecondSession) {
                    // 2ème session : toujours appliquer les pondérations
                    if (cc !== null && ex !== null) {
                        const mf = (cc * currentPonderations.cc) + (ex * currentPonderations.ex);
                        mfInput.value = mf.toFixed(2);

                        if (mf >= 10) {
                            row.className = 'table-success';
                        } else {
                            row.className = 'table-danger';
                        }
                    } else {
                        // Pas assez de notes pour calculer
                        mfInput.value = '';
                        row.className = '';
                    }
                } else if (cc !== null && ex !== null) {
                    // 1ère session : si les deux notes sont présentes, toujours calculer
                    const mf = (cc * currentPonderations.cc) + (ex * currentPonderations.ex);
                    mfInput.value = mf.toFixed(2);

                    // Mettre à jour la classe de la ligne
                    if (mf >= 10) {
                        row.className = 'table-success';
                    } else {
                        row.className = 'table-danger';
                    }
                } else if (calculerAvecNotesVides) {
                    // 1ère session : mode flexible avec notes vides
                    if (cc !== null) {
                        // Si seulement CC est présent
                        mfInput.value = cc.toFixed(2);
                        if (cc >= 10) {
                            row.className = 'table-success';
                        } else {
                            row.className = 'table-danger';
                        }
                    } else if (ex !== null) {
                        // Si seulement EX est présent
                        mfInput.value = ex.toFixed(2);
                        if (ex >= 10) {
                            row.className = 'table-success';
                        } else {
                            row.className = 'table-danger';
                        }
                    } else {
                        // Si aucune note n'est présente
                        mfInput.value = '';
                        row.className = '';
                    }
                } else {
                    // 1ère session : mode strict - pas de notes vides
                    mfInput.value = '';
                    row.className = '';
                }
            });
        });

    }

    // Fonction pour sauvegarder les cotes
    function sauvegarderCotes(ecueId, sessionId, anneeId) {
        console.log('Sauvegarde des cotes pour ECUE:', ecueId, 'Session:', sessionId, 'Année:', anneeId);

        // Collecter les données
        const cotes = [];
        document.querySelectorAll('#tbodyCotes tr').forEach(row => {
            const matricule = row.getAttribute('data-matricule');
            if (!matricule) return;

            const ccInput = row.querySelector('input[data-type="CC"]');
            const exInput = row.querySelector('input[data-type="EX"]');
            const mfInput = row.querySelector('input[data-type="MF"]');

            const cc = ccInput.value !== '' ? parseFloat(ccInput.value) : null;
            const ex = exInput.value !== '' ? parseFloat(exInput.value) : null;

            // Recalculer MF pour s'assurer de la cohérence
            let mf = null;

            // Même logique que le serveur : en 2ème session, appliquer les pondérations
            if (isSecondSession) {
                // 2ème session : appliquer les pondérations si CC et EX sont présents
                if (cc !== null && ex !== null) {
                    mf = (cc * currentPonderations.cc) + (ex * currentPonderations.ex);
                }
            } else if (cc !== null && ex !== null) {
                // 1ère session : si les deux notes sont présentes, toujours calculer
                mf = (cc * currentPonderations.cc) + (ex * currentPonderations.ex);
            } else if (calculerAvecNotesVides) {
                // 1ère session : mode flexible avec notes vides
                if (cc !== null) {
                    mf = cc;
                } else if (ex !== null) {
                    mf = ex;
                }
            } else {
                // 1ère session : mode strict - pas de notes vides
                mf = null;
            }

            cotes.push({
                matricule: matricule,
                cc: cc,
                ex: ex,
                mf: mf
            });
        });

        // Envoyer les données au serveur
        fetch('controller/save_cotes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ecueId: ecueId,
                    sessionId: sessionId,
                    anneeId: anneeId,
                    bureauId: currentBureauId, // Ajouter le bureauId
                    cotes: cotes
                })
            })
            .then(response => {
                console.log('Réponse reçue:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Les points ont été enregistrés avec succès.'
                    }).then(() => {
                        // Fermer le modal
                        ficheCotesModal.hide();

                        // Recharger la page pour actualiser les statistiques
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.error || 'Une erreur est survenue lors de l\'enregistrement des points.'
                    });
                    // Ajouter ceci pour voir les détails dans la console
                    console.error('Erreurs détaillées:', data);
                }
            })
            .catch(error => {
                console.error('Erreur lors de l\'enregistrement des cotes:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible d\'enregistrer les points. Veuillez réessayer.'
                });
            });
    }
</script>

<?php include "./views/include/footer.php"; ?>