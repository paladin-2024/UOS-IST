<?php
include "./views/include/header.php";
$universite = new Universite();
$horaireModel = new Horaire();

$userId = $_SESSION['id'];

// Récupérer les salles disponibles
$salles = $universite->getSalles();

// Récupérer toutes les années académiques
$allYears = $universite->getAllAcademicYears();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer l'année sélectionnée depuis l'URL ou utiliser l'année actuelle
$selectedYearId = isset($_GET['annee']) ? intval($_GET['annee']) : $currentYear['idannee_acad'];

// Trouver les données de l'année sélectionnée
$selectedYear = null;
foreach ($allYears as $year) {
    if ($year['idannee_acad'] == $selectedYearId) {
        $selectedYear = $year;
        break;
    }
}

// Si l'année sélectionnée n'existe pas, utiliser l'année actuelle
if (!$selectedYear) {
    $selectedYear = $currentYear;
    $selectedYearId = $currentYear['idannee_acad'];
}

// Vérifier si l'utilisateur est un responsable de section
$isResponsable = $universite->isUserSectionResponsable($userId, $selectedYearId);

// Récupérer les promotions selon le rôle de l'utilisateur
if ($isResponsable) {
    // L'utilisateur est un responsable de section, filtrer les promotions
    $promotions = $universite->getPromotionsByResponsable($selectedYearId, $userId);
} else {
    // L'utilisateur n'est pas un responsable, vérifier s'il a un rôle admin
    if (isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1) {
        // Pour les administrateurs, afficher toutes les promotions
        $promotions = $universite->getPromotionsByAnneeAcad($selectedYearId);
    } else {
        // Utilisateur standard sans droits spécifiques
        $promotions = [];
        $_SESSION['error'] = "Vous n'avez pas les droits pour accéder aux horaires des promotions.";
    }
}

// Vérifier si l'utilisateur est administrateur
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;

// Si aucune promotion n'est accessible, afficher un message
if (empty($promotions)) {
    ?>
    <main id="main" class="main">
        <div class="pagetitle">
            <h1>GESTION DES HORAIRES</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                    <li class="breadcrumb-item active">Horaires</li>
                </ol>
            </nav>
        </div>

        <section class="section dashboard">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Vous n'êtes pas responsable d'une section ou aucune promotion n'est disponible pour l'année académique sélectionnée.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php
    include "./views/include/footer.php";
    exit;
}

// Récupérer les horaires pour la promotion sélectionnée
$idPromotion = isset($_GET['promotion']) ? intval($_GET['promotion']) : (isset($promotions[0]) ? $promotions[0]['idpromotion'] : 0);

// Vérifier si la promotion sélectionnée fait partie des promotions autorisées
$promotionAuthorized = false;
foreach ($promotions as $p) {
    if ($p['idpromotion'] === $idPromotion) {
        $promotionAuthorized = true;
        break;
    }
}

if (!$promotionAuthorized && !empty($promotions)) {
    // Si la promotion n'est pas autorisée, utiliser la première promotion disponible
    $idPromotion = $promotions[0]['idpromotion'];
}

// Gérer la navigation par semaine
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Déterminer le premier jour de la semaine courante (lundi)
$currentMonday = date('Y-m-d', strtotime("monday this week $weekOffset weeks", strtotime($today)));
$currentSunday = date('Y-m-d', strtotime("sunday this week $weekOffset weeks", strtotime($today)));


// Récupérer les horaires pour la semaine spécifique
$horaires = $horaireModel->getHorairesByPromotionAndDates(
    $idPromotion,
    $selectedYearId,
    $currentMonday,   // Date de début de la semaine
    $currentSunday    // Date de fin de la semaine
);

// Récupérer les ECUE disponibles pour cette promotion
$ecues = $universite->getEcuesByPromotion($idPromotion);


// Pour l'affichage
$weekDates = [];
$weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
foreach ($weekDays as $index => $day) {
    $date = date('Y-m-d', strtotime("+$index days", strtotime($currentMonday)));
    $weekDates[$day] = [
        'date' => $date,
        'display' => $day . ' ' . date('d/m', strtotime($date))
    ];
}

// Après l'initialisation de $weekDates, ajoutez:
$jours = array_keys($weekDates); // Extrait les jours (Lundi, Mardi, etc.) de $weekDates


// Nouvelles plages horaires simplifiées
$periodes = [
    '08:00-12:00', // Avant-midi
    '13:00-18:00'  // Après-midi
];

// Organiser les horaires par jour et période
$horaireGrid = [];
foreach ($jours as $jour) {
    $horaireGrid[$jour] = [];
    foreach ($periodes as $periode) {
        $horaireGrid[$jour][$periode] = [];
    }
}

// Remplir la grille avec les horaires
$processedIds = []; // Pour suivre les IDs déjà traités
foreach ($horaires as $h) {
    // Vérifier si cet horaire a déjà été traité
    if (in_array($h['idhoraire'], $processedIds)) {
        continue; // Ignorer ce doublon
    }
    
    $processedIds[] = $h['idhoraire']; // Marquer cet ID comme traité
    
    $heureDebut = substr($h['heure_debut'], 0, 5);
    $heureFin = substr($h['heure_fin'], 0, 5);
    
    // Si on a une date_cours spécifique, utiliser le jour correspondant à cette date
    if (!empty($h['date_cours'])) {
        // Convertir la date en jour de la semaine en français
        $jourMapping = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];
        $jourSemaine = date('l', strtotime($h['date_cours']));
        $jour = $jourMapping[$jourSemaine];
    } else {
        $jour = $h['jour'];
    }
    
    // Déterminer si c'est un cours du matin ou de l'après-midi
    if ($heureDebut >= '08:00' && $heureDebut < '13:00') {
        $periode = '08:00-12:00';
    } else if ($heureDebut >= '13:00' && $heureDebut < '18:00') {
        $periode = '13:00-18:00';
    } else {
        continue; // Ignorer les horaires hors des plages définies
    }
    
    // Ajouter des propriétés pour identifier les cours de longue durée
    $dureeEnHeures = (strtotime("2000-01-01 $heureFin") - strtotime("2000-01-01 $heureDebut")) / 3600;
    $h['multi_period'] = $dureeEnHeures > 2;
    $h['full_day'] = $dureeEnHeures >= 4;
    $h['exact_hours'] = "$heureDebut - $heureFin"; // Ajout pour afficher les heures exactes
    
    // Ajouter l'horaire à la période correspondante
    if (isset($horaireGrid[$jour][$periode])) {
        $horaireGrid[$jour][$periode][] = $h;
    }
}



?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES HORAIRES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Horaires</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsable && !$isAdmin): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Accès restreint :</strong> Vous visualisez et gérez uniquement les horaires des promotions de vos sections.
            <?php 
            // Récupérer les noms des sections du responsable
            $pdo = Connexion::getInstance()->getPDO();
            $querySections = "SELECT DISTINCT s.\"designationSection\"
                              FROM section s
                              INNER JOIN responsable_section rs ON rs.section_idsection = s.idsection
                              WHERE rs.\"idUser\" = :userId
                              AND rs.annee_acad_idannee_acad = :anneeId";
            $stmtSections = $pdo->prepare($querySections);
            $stmtSections->bindParam(':userId', $userId);
            $stmtSections->bindParam(':anneeId', $selectedYearId);
            $stmtSections->execute();
            $sectionNames = $stmtSections->fetchAll(PDO::FETCH_COLUMN);
            ?>
            <?php if (!empty($sectionNames)): ?>
                <br><small><strong>Sections :</strong> <?= implode(', ', $sectionNames) ?></small>
            <?php endif; ?>
        </div>
        <?php elseif ($isAdmin): ?>
        <div class="alert alert-success">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Accès administrateur :</strong> Vous avez accès à toutes les sections et promotions.
        </div>
        <?php endif; ?>

        <!-- Sélection de l'année académique -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélection de l'année académique</h5>
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="enseignement/horaires">
                            <div class="col-md-4">
                                <select name="annee" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionnez une année</option>
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $selectedYearId == $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélection de la promotion</h5>
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="enseignement/horaires">
                            <input type="hidden" name="annee" value="<?= $selectedYearId ?>">
                            <div class="col-md-8">
                                <select name="promotion" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionnez une promotion</option>
                                    <?php foreach ($promotions as $p): ?>
                                        <option value="<?= $p['idpromotion'] ?>" <?= $idPromotion == $p['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['designationPromotion']) ?> (<?= $p['cycle'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                            <div class="d-grid gap-2">
                            <a data-bs-toggle="modal" data-bs-target="#addHoraireModal" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Ajouter un horaire
                                    </a>
                                     <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addHoraireTroncModal">
                                         <i class="bi bi-plus-circle"></i> Horaire en tronc commun
                                     </button>
                                 </div>
                             </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Légende</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-primary text-white p-2 me-2" style="width: 20px; height: 20px;"></div>
                                    <span>CMI</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success text-white p-2 me-2" style="width: 20px; height: 20px;"></div>
                                    <span>TD</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-warning text-white p-2 me-2" style="width: 20px; height: 20px;"></div>
                                    <span>TP</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-danger text-white p-2 me-2" style="width: 20px; height: 20px;"></div>
                                    <span>Évaluation</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ajouter cette section après le formulaire de sélection de promotion -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres avancés</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter-salle">Salle</label>
                                    <input type="text" class="form-control" id="filter-salle" placeholder="Filtrer par salle">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter-ecue">Cours/ECUE</label>
                                    <select class="form-select" id="filter-ecue">
                                        <option value="">Tous les cours</option>
                                        <?php foreach ($ecues as $e): ?>
                                            <option value="<?= $e['idECUE'] ?>"><?= htmlspecialchars($e['designationECUE']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter-type">Type de cours</label>
                                    <select class="form-select" id="filter-type">
                                        <option value="">Tous les types</option>
                                        <option value="CM">Cours Magistral</option>
                                        <option value="TD">Travaux Dirigés</option>
                                        <option value="TP">Travaux Pratiques</option>
                                        <option value="Evaluation">Évaluation</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter-enseignant">Enseignant</label>
                                    <input type="text" class="form-control" id="filter-enseignant" placeholder="Filtrer par enseignant">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button id="btn-apply-filters" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Appliquer les filtres
                                </button>
                                <button id="btn-reset-filters" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Navigation par semaine -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <a href="?view=enseignement/horaires&promotion=<?= $idPromotion ?>&week=<?= $weekOffset - 1 ?>&annee=<?= $selectedYearId ?>" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Semaine précédente
                        </a>

                        <div>
                            <h5 class="mb-0">
                                Semaine du <?= date('d/m/Y', strtotime($currentMonday)) ?>
                                au <?= date('d/m/Y', strtotime($currentSunday)) ?>
                            </h5>
                            <center>
                            <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="duplicateWeek()">
                                <i class="bi bi-files"></i> Dupliquer cette semaine
                            </button>
                            </center>
                        </div>

                        <a href="?view=enseignement/horaires&promotion=<?= $idPromotion ?>&week=<?= $weekOffset + 1 ?>&annee=<?= $selectedYearId ?>" class="btn btn-outline-primary">
                            Semaine suivante <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>


        <!-- Ajouter ce bouton juste en dessous du titre de la carte de l'emploi du temps -->


        
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                    <div class="card-title d-flex justify-content-between align-items-center">
                        <h5>
                            Emploi du temps -
                            <?php 
                            $promotionName = '';
                            foreach ($promotions as $p) {
                                if ($p['idpromotion'] == $idPromotion) {
                                    $promotionName = $p['designationPromotion'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($promotionName);
                            ?>
                            (<?= htmlspecialchars($selectedYear['designation']) ?>)
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportAllPromotionSchedules()">
                                <i class="bi bi-file-pdf"></i> Exporter toutes les promotions
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToPDF()">
                                <i class="bi bi-file-pdf"></i> PDF
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="exportToExcel()">
                                <i class="bi bi-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                            <table class="table table-bordered horaire-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">Horaire</th>
                                        <?php foreach ($weekDates as $jour => $dateInfo): ?>
                                            <th style="width: 15%;"><?= $dateInfo['display'] ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($periodes as $periode): 
                                        list($debut, $fin) = explode('-', $periode);
                                    ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?= $debut ?><br>-<br><?= $fin ?></td>
                                            <?php foreach ($weekDates as $jour => $dateInfo): ?>
                                                <td class="horaire-cell <?= date('Y-m-d') == $dateInfo['date'] ? 'current-day' : '' ?>" data-date="<?= $dateInfo['date'] ?>">
                                                    <?php 
                                                    if (!empty($horaireGrid[$jour][$periode])):
                                                        // Trier les horaires par heure de début
                                                        usort($horaireGrid[$jour][$periode], function($a, $b) {
                                                            return strcmp($a['heure_debut'], $b['heure_debut']);
                                                        });
                                                        
                                                        foreach ($horaireGrid[$jour][$periode] as $h): 
                                                            // Déterminer la couleur en fonction du type de cours (code existant)
                                                            $typeClass = 'primary'; 
                                                            if (isset($h['type_cours'])) {
                                                                if (strpos(strtolower($h['type_cours']), 'td') !== false) {
                                                                    $typeClass = 'success';
                                                                } elseif (strpos(strtolower($h['type_cours']), 'tp') !== false) {
                                                                    $typeClass = 'warning';
                                                                } elseif (strpos(strtolower($h['type_cours']), 'eval') !== false) {
                                                                    $typeClass = 'danger';
                                                                }
                                                            }
                                                            
                                                            // Classes additionnelles (existantes)
                                                            $additionalClass = '';
                                                            if (isset($h['full_day']) && $h['full_day']) {
                                                                $additionalClass = 'full-day';
                                                            } elseif (isset($h['multi_period']) && $h['multi_period']) {
                                                                $additionalClass = 'multi-period';
                                                            }
                                                            
                                                            // Vérifier les conflits (nouveau code)
                                                            $conflictClass = '';
                                                            $conflictTooltip = '';
                                                            
                                                            // Ces informations devraient être ajoutées lors de la préparation des données
                                                            if (isset($h['has_conflict']) && $h['has_conflict']) {
                                                                $conflictClass = 'has-conflict';
                                                                $conflictTooltip = htmlspecialchars($h['conflict_message']);
                                                            } elseif (isset($h['has_warning']) && $h['has_warning']) {
                                                                $conflictClass = 'has-warning';
                                                                $conflictTooltip = htmlspecialchars($h['warning_message']);
                                                            }
                                                    ?>
                                                        <div class="horaire-item bg-<?= $typeClass ?> text-white p-2 mb-2 rounded <?= $additionalClass ?> <?= $conflictClass ?>"
                                                        <?php if (!empty($conflictTooltip)): ?>
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $conflictTooltip ?>"
                                                        <?php endif; ?>>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-bold"><?= htmlspecialchars($h['designationECUE']) ?></span>
                                                            <span class="badge bg-light text-dark"><?= $h['exact_hours'] ?></span>
                                                        </div>
                                                        <div>Salle: <?= htmlspecialchars($h['salle']) ?></div>
                                                        <div class="small"><?= htmlspecialchars($h['enseignant_nom']) ?></div>
                                                        <!-- Ajouter un bouton dans la carte d'horaire pour dupliquer -->
                                                        <div class="d-flex justify-content-end mt-1">
                                                            <button class="btn btn-sm btn-light" onclick="editHoraire(<?= $h['idhoraire'] ?>)">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-info ms-1" onclick="duplicateHoraire(<?= $h['idhoraire'] ?>)">
                                                                <i class="bi bi-files"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger ms-1" onclick="deleteHoraire(<?= $h['idhoraire'] ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>

                                                    </div>
                                                    <?php 
                                                        endforeach;
                                                    endif;
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un horaire -->
<div class="modal fade" id="addHoraireModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un horaire de cours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="horaireForm" method="POST" action="controller/horaire_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_horaire">
                    <input type="hidden" name="idAnneeAcad" value="<?= $selectedYearId ?>">
                    <input type="hidden" name="promotion" value="<?= $idPromotion ?>">
                    <input type="hidden" name="week" value="<?= $weekOffset ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="idECUE" class="form-label">Cours (ECUE)</label>
                            <select name="idECUE" id="idECUE" class="form-select" required>
                                <option value="">Sélectionnez un cours</option>
                                <?php foreach ($ecues as $e): ?>
                                    <option value="<?= $e['idECUE'] ?>"><?= htmlspecialchars($e['designationECUE']) ?> (<?= htmlspecialchars($e['designationUE']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cours.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                    <div class="col-md-12">
                    <label for="date_cours" class="form-label">Date du cours</label>
                    <input type="date" name="date_cours" id="date_cours" class="form-control" required>
                    <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                    </div>
                    </div>
                    <input type="hidden" name="jour" id="jour">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="type_cours" class="form-label">Type de cours</label>
                            <select name="type_cours" id="type_cours" class="form-select" required>
                                <option value="">Sélectionnez un type</option>
                                <option value="CM">Cours Magistral</option>
                                <option value="TD">Travaux Dirigés</option>
                                <option value="TP">Travaux Pratiques</option>
                                <option value="Evaluation">Évaluation</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type de cours.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="salle" class="form-label">Salle</label>
                            <select name="salle" id="salle" class="form-select" required>
                                <option value="">Sélectionnez une salle</option>
                                <?php foreach ($salles as $s): ?>
                                    <option value="<?= htmlspecialchars($s['designationSalle']) ?>"><?= htmlspecialchars($s['designationSalle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une salle.</div>
                        </div>

                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="heure_debut" class="form-label">Heure de début</label>
                            <input type="time" name="heure_debut" id="heure_debut" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une heure de début.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="heure_fin" class="form-label">Heure de fin</label>
                            <input type="time" name="heure_fin" id="heure_fin" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une heure de fin.</div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="tronc_commun" name="tronc_commun">
                    <label class="form-check-label" for="tronc_commun">
                    Cours en tronc commun (permet plusieurs promotions dans la même salle avec le même enseignant)
                    </label>
                    </div>

                    

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un horaire en tronc commun -->
<div class="modal fade" id="addHoraireTroncModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Ajouter un horaire en tronc commun</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<form id="horaireTroncForm" method="POST" action="controller/horaire_controller.php" class="needs-validation" novalidate>
<input type="hidden" name="action" value="add_horaire_tronc">
<input type="hidden" name="idAnneeAcad" value="<?= $selectedYearId ?>">
<input type="hidden" name="week" value="<?= $weekOffset ?>">

<div class="row mb-3">
<div class="col-md-12">
<label for="tronc_promotions" class="form-label">Promotions concernées <span class="text-danger">*</span></label>
<select name="promotions[]" id="tronc_promotions" class="form-select" multiple required>
<?php
$currentYearPromotions = $universite->getPromotionsByAnneeAcad($selectedYearId);
foreach ($currentYearPromotions as $p): ?>
<option value="<?= $p['idpromotion'] ?>">
<?= htmlspecialchars($p['designationPromotion']) ?> (<?= $p['cycle'] ?>)
</option>
<?php endforeach; ?>
</select>
<div class="invalid-feedback">Veuillez sélectionner au moins une promotion.</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-12">
<label for="tronc_idECUE" class="form-label">Cours (ECUE) <span class="text-danger">*</span></label>
<select name="idECUE" id="tronc_idECUE" class="form-select" required>
<option value="">Sélectionnez un cours</option>
<?php
// Get ECUEs from promotions of the selected year
$currentYearEcues = [];
$currentYearPromotions = $universite->getPromotionsByAnneeAcad($selectedYearId);
foreach ($currentYearPromotions as $p) {
$ecues = $universite->getEcuesByPromotion($p['idpromotion']);
foreach ($ecues as $e) {
$key = $e['idECUE'];
if (!isset($currentYearEcues[$key])) {
$currentYearEcues[$key] = $e;
}
}
}
foreach ($currentYearEcues as $e): ?>
<option value="<?= $e['idECUE'] ?>"><?= htmlspecialchars($e['designationECUE']) ?> (<?= htmlspecialchars($e['designationUE']) ?>)</option>
<?php endforeach; ?>
</select>
<div class="invalid-feedback">Veuillez sélectionner un cours.</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-12">
<label for="tronc_date_cours" class="form-label">Date du cours <span class="text-danger">*</span></label>
<input type="date" name="date_cours" id="tronc_date_cours" class="form-control" required>
<div class="invalid-feedback">Veuillez sélectionner une date.</div>
</div>
</div>
<input type="hidden" name="jour" id="tronc_jour">

<div class="row mb-3">
<div class="col-md-6">
<label for="tronc_type_cours" class="form-label">Type de cours <span class="text-danger">*</span></label>
<select name="type_cours" id="tronc_type_cours" class="form-select" required>
<option value="">Sélectionnez un type</option>
<option value="CM">Cours Magistral</option>
<option value="TD">Travaux Dirigés</option>
<option value="TP">Travaux Pratiques</option>
<option value="Evaluation">Évaluation</option>
</select>
<div class="invalid-feedback">Veuillez sélectionner un type de cours.</div>
</div>
<div class="col-md-6">
<label for="tronc_salle" class="form-label">Salle <span class="text-danger">*</span></label>
<select name="salle" id="tronc_salle" class="form-select" required>
<option value="">Sélectionnez une salle</option>
<?php foreach ($salles as $s): ?>
<option value="<?= htmlspecialchars($s['designationSalle']) ?>"><?= htmlspecialchars($s['designationSalle']) ?></option>
<?php endforeach; ?>
</select>
<div class="invalid-feedback">Veuillez sélectionner une salle.</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-6">
<label for="tronc_heure_debut" class="form-label">Heure de début <span class="text-danger">*</span></label>
<input type="time" name="heure_debut" id="tronc_heure_debut" class="form-control" required>
<div class="invalid-feedback">Veuillez entrer une heure de début.</div>
</div>
<div class="col-md-6">
<label for="tronc_heure_fin" class="form-label">Heure de fin <span class="text-danger">*</span></label>
<input type="time" name="heure_fin" id="tronc_heure_fin" class="form-control" required>
<div class="invalid-feedback">Veuillez entrer une heure de fin.</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
<button type="submit" class="btn btn-primary">
<i class="bi bi-save"></i> Créer les horaires
</button>
</div>
</form>
</div>
</div>
</div>
</div>

<!-- Modal pour modifier un horaire -->
<div class="modal fade" id="editHoraireModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un horaire de cours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editHoraireForm" method="POST" action="controller/horaire_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit_horaire">
                    <input type="hidden" name="idHoraire" id="edit_idHoraire">
                    <input type="hidden" name="idAnneeAcad" value="<?= $selectedYearId ?>">
                    <input type="hidden" name="promotion" value="<?= $idPromotion ?>">
                    <input type="hidden" name="week" value="<?= $weekOffset ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_idECUE" class="form-label">Cours (ECUE)</label>
                            <select name="idECUE" id="edit_idECUE" class="form-select" required>
                                <option value="">Sélectionnez un cours</option>
                                <?php foreach ($ecues as $e): ?>
                                    <option value="<?= $e['idECUE'] ?>"><?= htmlspecialchars($e['designationECUE']) ?> (<?= htmlspecialchars($e['designationUE']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cours.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_jour" class="form-label">Jour</label>
                            <select name="jour" id="edit_jour" class="form-select" required>
                                <option value="">Sélectionnez un jour</option>
                                <?php foreach ($jours as $jour): ?>
                                    <option value="<?= $jour ?>"><?= $jour ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un jour.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date_cours" class="form-label">Date du cours</label>
                            <input type="date" name="date_cours" id="edit_date_cours" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_type_cours" class="form-label">Type de cours</label>
                            <select name="type_cours" id="edit_type_cours" class="form-select" required>
                                <option value="">Sélectionnez un type</option>
                                <option value="CM">Cours Magistral</option>
                                <option value="TD">Travaux Dirigés</option>
                                <option value="TP">Travaux Pratiques</option>
                                <option value="Evaluation">Évaluation</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type de cours.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_salle" class="form-label">Salle</label>
                            <select name="salle" id="edit_salle" class="form-select" required>
                                <option value="">Sélectionnez une salle</option>
                                <?php foreach ($salles as $s): ?>
                                    <option value="<?= htmlspecialchars($s['designationSalle']) ?>"><?= htmlspecialchars($s['designationSalle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une salle.</div>
                        </div>

                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_heure_debut" class="form-label">Heure de début</label>
                            <input type="time" name="heure_debut" id="edit_heure_debut" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une heure de début.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_heure_fin" class="form-label">Heure de fin</label>
                            <input type="time" name="heure_fin" id="edit_heure_fin" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une heure de fin.</div>
                        </div>
                        </div>

                        <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_tronc_commun" name="tronc_commun">
                        <label class="form-check-label" for="edit_tronc_commun">
                        Cours en tronc commun (permet plusieurs promotions dans la même salle avec le même enseignant)
                        </label>
                        </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>

function duplicateWeek() {
    Swal.fire({
        title: 'Dupliquer cette semaine',
        html: `
            <div class="mb-3">
                <label for="dupliquer-semaine-date" class="form-label">Date de début de la nouvelle semaine</label>
                <input type="date" id="dupliquer-semaine-date" class="form-control" required>
                <small class="text-muted">Choisissez le lundi de la semaine cible</small>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Dupliquer',
        cancelButtonText: 'Annuler',
        preConfirm: () => {
            const newDate = document.getElementById('dupliquer-semaine-date').value;
            if (!newDate) {
                Swal.showValidationMessage('Veuillez sélectionner une date');
                return false;
            }
            
            // Vérifier que la date sélectionnée est un lundi
            const selectedDate = new Date(newDate);
            if (selectedDate.getDay() !== 1) { // 1 = Lundi
                Swal.showValidationMessage('Veuillez sélectionner un lundi comme date de début');
                return false;
            }
            
            return { date: newDate };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher un message de chargement
            Swal.fire({
                title: 'Duplication en cours...',
                text: 'Veuillez patienter pendant la duplication des horaires',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Envoyer la requête de duplication au NOUVEAU contrôleur
            fetch('controller/horaire_duplicate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=duplicate_week&promotion_id=<?= $idPromotion ?>&current_monday=<?= $currentMonday ?>&current_sunday=<?= $currentSunday ?>&new_monday=${result.value.date}&annee_acad=<?= $currentYear['idannee_acad'] ?>`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Duplication réussie',
                        text: data.message || 'La semaine a été dupliquée avec succès.',
                        confirmButtonText: 'Voir la semaine dupliquée',
                    }).then(() => {
                        // Rediriger vers la semaine dupliquée
                        window.location.href = `?view=enseignement/horaires&promotion=<?= $idPromotion ?>&week=${data.weekOffset}`;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la duplication.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la duplication de la semaine.'
                });
            });
        }
    });
}









// Validation des heures
document.addEventListener('DOMContentLoaded', function() {
    const heureDebut = document.getElementById('heure_debut');
    const heureFin = document.getElementById('heure_fin');
    const editHeureDebut = document.getElementById('edit_heure_debut');
    const editHeureFin = document.getElementById('edit_heure_fin');
    
    function validateHoraire() {
        if (heureDebut.value && heureFin.value) {
            if (heureDebut.value >= heureFin.value) {
                heureFin.setCustomValidity('L\'heure de fin doit être postérieure à l\'heure de début');
            } else {
                heureFin.setCustomValidity('');
            }
        }
    }
    
    function validateEditHoraire() {
        if (editHeureDebut.value && editHeureFin.value) {
            if (editHeureDebut.value >= editHeureFin.value) {
                editHeureFin.setCustomValidity('L\'heure de fin doit être postérieure à l\'heure de début');
            } else {
                editHeureFin.setCustomValidity('');
            }
        }
    }
    
    heureDebut.addEventListener('change', validateHoraire);
    heureFin.addEventListener('change', validateHoraire);
    editHeureDebut.addEventListener('change', validateEditHoraire);
    editHeureFin.addEventListener('change', validateEditHoraire);
});

// Fonction pour éditer un horaire
function editHoraire(idHoraire) {
    // Charger les détails de l'horaire via AJAX
    fetch(`controller/get_horaire.php?id=${idHoraire}`)
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
            
            // Remplir le formulaire avec les données
            document.getElementById('edit_idHoraire').value = data.idhoraire;
            document.getElementById('edit_idECUE').value = data.idECUE;
            document.getElementById('edit_jour').value = data.jour;
            document.getElementById('edit_type_cours').value = data.type_cours;
            document.getElementById('edit_heure_debut').value = data.heure_debut;
            document.getElementById('edit_heure_fin').value = data.heure_fin;
            document.getElementById('edit_salle').value = data.salle;
            
            // Remplir le champ date si disponible
            if (data.date_cours) {
                document.getElementById('edit_date_cours').value = data.date_cours;
            } else {
                // Si date_cours n'est pas disponible, utiliser la date d'aujourd'hui
                document.getElementById('edit_date_cours').value = new Date().toISOString().split('T')[0];
            }
            
            // Afficher le modal
            new bootstrap.Modal(document.getElementById('editHoraireModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la récupération des détails de l\'horaire.'
            });
        });
}


// Fonction pour supprimer un horaire
function deleteHoraire(idHoraire) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action ne peut pas être annulée!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/horaire_controller.php?action=delete_horaire&id=${idHoraire}&idAnneeAcad=<?= $currentYear['idannee_acad'] ?>&promotion=<?= $idPromotion ?>`;
        }
    });
}




// Ajouter ce script à la section JavaScript existante
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des clics sur le calendrier
    setupCalendarCellClicks();

    // Récupération des éléments du formulaire d'ajout
    const jourSelect = document.getElementById('jour');
    const dateInput = document.getElementById('date_cours');
    
    // Récupération des éléments du formulaire de modification
    const editJourSelect = document.getElementById('edit_jour');
    const editDateInput = document.getElementById('edit_date_cours');
    
    // Mapping des jours français vers index (0 = Lundi, 6 = Dimanche)
    const jourMapping = {
        'Lundi': 0,
        'Mardi': 1,
        'Mercredi': 2,
        'Jeudi': 3,
        'Vendredi': 4,
        'Samedi': 5,
        'Dimanche': 6
    };
    
    // Mapping inverse (pour convertir index en jour)
    const indexMapping = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    
    // Fonction pour mettre à jour la date en fonction du jour sélectionné
    function updateDateFromDay(jourSelectElement, dateInputElement) {
        if (!jourSelectElement.value) return;
        
        // Trouver le lundi de la semaine courante
        const currentDate = new Date();
        const currentDay = currentDate.getDay(); // 0 = Dimanche, 1 = Lundi, etc.
        const daysToMonday = currentDay === 0 ? 6 : currentDay - 1;
        const mondayDate = new Date(currentDate);
        mondayDate.setDate(currentDate.getDate() - daysToMonday);
        
        // Calculer la date correspondant au jour sélectionné
        const selectedJourIndex = jourMapping[jourSelectElement.value];
        const targetDate = new Date(mondayDate);
        targetDate.setDate(mondayDate.getDate() + selectedJourIndex);
        
        // Formater la date au format YYYY-MM-DD pour l'input date
        const formattedDate = targetDate.toISOString().split('T')[0];
        dateInputElement.value = formattedDate;
    }
    
    // Fonction pour mettre à jour le jour en fonction de la date sélectionnée
    function updateDayFromDate(dateInputElement, jourSelectElement) {
        if (!dateInputElement.value) return;
        
        const selectedDate = new Date(dateInputElement.value);
        const dayOfWeek = selectedDate.getDay(); // 0 = Dimanche, 1 = Lundi, etc.
        const adjustedIndex = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
        jourSelectElement.value = indexMapping[adjustedIndex];
    }
    
    // Appliquer les événements pour le formulaire d'ajout
    if (jourSelect && dateInput) {
        jourSelect.addEventListener('change', function() {
            updateDateFromDay(jourSelect, dateInput);
        });
        
        dateInput.addEventListener('change', function() {
            updateDayFromDate(dateInput, jourSelect);
        });
        
        // Initialiser la date avec le jour déjà sélectionné (si c'est le cas)
        if (jourSelect.value) {
            updateDateFromDay(jourSelect, dateInput);
        }
    }
    
    // Appliquer les événements pour le formulaire de modification
    if (editJourSelect && editDateInput) {
        editJourSelect.addEventListener('change', function() {
            updateDateFromDay(editJourSelect, editDateInput);
        });
        
        editDateInput.addEventListener('change', function() {
            updateDayFromDate(editDateInput, editJourSelect);
        });
    }
    
    // Initialiser la date du modal d'ajout avec la date sélectionnée dans le calendrier
    const currentDate = new Date();
    dateInput.value = currentDate.toISOString().split('T')[0];
    updateDayFromDate(dateInput, jourSelect);
});


// Fonction pour permettre d'ajouter un horaire en cliquant sur une cellule
function setupCalendarCellClicks() {
    const cells = document.querySelectorAll('.horaire-cell');
    cells.forEach(cell => {
        cell.addEventListener('click', function(e) {
            // Ne déclencher que pour les clics directs sur la cellule (pas sur les horaires existants)
            if (e.target === cell || e.target.classList.contains('add-placeholder')) {
                const date = cell.getAttribute('data-date');
                if (date) {
                    // Ouvrir le modal d'ajout
                    const modal = new bootstrap.Modal(document.getElementById('addHoraireModal'));
                    
                    // Mettre à jour la date
                    document.getElementById('date_cours').value = date;
                    
                    // Mettre à jour le jour correspondant
                    updateDayFromDate(document.getElementById('date_cours'), document.getElementById('jour'));
                    
                    modal.show();
                }
            }
        });
    });
}




<!-- Ajouter dans le script JavaScript après la définition de la fonction setupCalendarCellClicks() -->

// Fonction pour vérifier les conflits avant soumission
function checkConflicts() {
    // Récupérer les valeurs du formulaire
    const date_cours = document.getElementById('date_cours').value;
    const jour = document.getElementById('jour').value;
    const heureDebut = document.getElementById('heure_debut').value;
    const heureFin = document.getElementById('heure_fin').value;
    const salle = document.getElementById('salle').value;
    const idECUE = document.getElementById('idECUE').value;
    const anneeAcad = <?= $currentYear['idannee_acad'] ?>;
    
    // Référence au preloader
    const preloader = document.getElementById("preloader");
    
    // Vérifier si tous les champs nécessaires sont remplis
    if (!date_cours || !jour || !heureDebut || !heureFin || !salle || !idECUE) {
        return true; // Permettre la soumission si tous les champs ne sont pas remplis
    }
    
    // Prévenez la soumission par défaut
    event.preventDefault();
    
    // Afficher le preloader pendant la vérification AJAX
    if (preloader) {
        preloader.style.display = "flex";
        preloader.style.opacity = "1";
    }
    
    // Envoyer une requête AJAX pour vérifier les conflits
    fetch('controller/check_conflicts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `jour=${jour}&heure_debut=${heureDebut}&heure_fin=${heureFin}&salle=${salle}&idECUE=${idECUE}&idAnneeAcad=${anneeAcad}&date_cours=${date_cours}`
    })
    .then(response => response.json())
    .then(data => {
        // Masquer le preloader une fois la réponse reçue
        if (preloader) {
            preloader.style.opacity = "0";
            setTimeout(() => {
                preloader.style.display = "none";
            }, 500);
        }
        
        if (data.hasConflicts) {
            // Afficher les conflits
            Swal.fire({
                icon: 'warning',
                title: 'Conflits détectés',
                html: data.conflictMessages.join('<br>'),
                showCancelButton: true,
                confirmButtonText: 'Continuer quand même',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Réafficher le preloader avant la soumission
                    if (preloader) {
                        preloader.style.display = "flex";
                        preloader.style.opacity = "1";
                    }
                    // Si l'utilisateur choisit de continuer, soumettre le formulaire
                    document.getElementById('horaireForm').submit();
                }
            });
        } else {
            // Réafficher le preloader avant la soumission
            if (preloader) {
                preloader.style.display = "flex";
                preloader.style.opacity = "1";
            }
            // Pas de conflit, soumettre le formulaire
            document.getElementById('horaireForm').submit();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        // Masquer le preloader en cas d'erreur
        if (preloader) {
            preloader.style.opacity = "0";
            setTimeout(() => {
                preloader.style.display = "none";
            }, 500);
        }
        // En cas d'erreur, permettre la soumission du formulaire
        document.getElementById('horaireForm').submit();
    });
    
    return false; // Empêcher la soumission par défaut
}


// Modifier le formulaire pour utiliser cette fonction
document.addEventListener('DOMContentLoaded', function() {
    // Appliquer la vérification de conflits au formulaire d'ajout
    document.getElementById('horaireForm').addEventListener('submit', function(event) {
        const isTroncCommun = document.getElementById('tronc_commun').checked;
        if (isTroncCommun) {
            // Skip conflict check for common core courses
            return true; // Allow form submission
        }
        return checkConflicts();
    });
    
    // Faire de même pour le formulaire de modification
    document.getElementById('editHoraireForm').addEventListener('submit', function(event) {
        const isTroncCommun = document.getElementById('edit_tronc_commun').checked;
        if (isTroncCommun) {
            // Skip conflict check for common core courses
            return true; // Allow form submission
        }

        // Récupérer les valeurs du formulaire de modification
        const jour = document.getElementById('edit_jour').value;
        const heureDebut = document.getElementById('edit_heure_debut').value;
        const heureFin = document.getElementById('edit_heure_fin').value;
        const salle = document.getElementById('edit_salle').value;
        const idECUE = document.getElementById('edit_idECUE').value;
        const idHoraire = document.getElementById('edit_idHoraire').value;
        const anneeAcad = <?= $selectedYearId ?>;
        
        // Vérifier si tous les champs nécessaires sont remplis
        if (!jour || !heureDebut || !heureFin || !salle || !idECUE) {
            return true; // Permettre la soumission si tous les champs ne sont pas remplis
        }
        
        // Prévenez la soumission par défaut
        event.preventDefault();
        
        // Envoyer une requête AJAX pour vérifier les conflits
        fetch('controller/check_conflicts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `jour=${jour}&heure_debut=${heureDebut}&heure_fin=${heureFin}&salle=${salle}&idECUE=${idECUE}&idAnneeAcad=${anneeAcad}&idHoraire=${idHoraire}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.hasConflicts) {
                // Afficher les conflits
                Swal.fire({
                    icon: 'warning',
                    title: 'Conflits détectés',
                    html: data.conflictMessages.join('<br>'),
                    showCancelButton: true,
                    confirmButtonText: 'Continuer quand même',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Si l'utilisateur choisit de continuer, soumettre le formulaire
                        document.getElementById('editHoraireForm').submit();
                    }
                });
            } else {
                // Pas de conflit, soumettre le formulaire
                document.getElementById('editHoraireForm').submit();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            // En cas d'erreur, permettre la soumission du formulaire
            document.getElementById('editHoraireForm').submit();
        });
        
        return false; // Empêcher la soumission par défaut
    });
});

// Auto-select day when date is changed - normal modal
document.getElementById('date_cours').addEventListener('change', function() {
    if (this.value) {
        const date = new Date(this.value);
        const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        const frenchDay = days[date.getDay()];
        document.getElementById('jour').value = frenchDay;
    }
});

// Auto-select day when date is changed - tronc modal
document.getElementById('tronc_date_cours').addEventListener('change', function() {
    if (this.value) {
        const date = new Date(this.value);
        const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        const frenchDay = days[date.getDay()];
        document.getElementById('tronc_jour').value = frenchDay;
    }
});


</script>

<!-- Ajouter ce script à la fin du fichier, dans la section script -->
<script>
// Fonction pour filtrer les horaires
function filterHoraires() {
    const salle = document.getElementById('filter-salle').value.toLowerCase();
    const ecue = document.getElementById('filter-ecue').value;
    const type = document.getElementById('filter-type').value;
    const enseignant = document.getElementById('filter-enseignant').value.toLowerCase();
    
    // Récupérer tous les éléments d'horaire
    const horaireItems = document.querySelectorAll('.horaire-item');
    
    horaireItems.forEach(item => {
        let shouldShow = true;
        
        // Vérifier le filtre de salle
        if (salle && !item.textContent.toLowerCase().includes('salle: ' + salle) && 
            !item.textContent.toLowerCase().includes('salle:' + salle)) {
            shouldShow = false;
        }
        
        // Vérifier le filtre d'ECUE
        if (ecue && !item.hasAttribute('data-ecue-id') && item.getAttribute('data-ecue-id') !== ecue) {
            shouldShow = false;
        }
        
        // Vérifier le filtre de type
        if (type) {
            const typeMatch = Array.from(item.classList).some(cls => {
                if (type === 'CM' && cls === 'bg-primary') return true;
                if (type === 'TD' && cls === 'bg-success') return true;
                if (type === 'TP' && cls === 'bg-warning') return true;
                if (type === 'Evaluation' && cls === 'bg-danger') return true;
                return false;
            });
            
            if (!typeMatch) shouldShow = false;
        }
        
        // Vérifier le filtre d'enseignant
        if (enseignant && !item.textContent.toLowerCase().includes(enseignant)) {
            shouldShow = false;
        }
        
        // Afficher ou masquer l'élément
        item.style.display = shouldShow ? 'block' : 'none';
    });
}

// Attacher les événements aux boutons de filtre
document.addEventListener('DOMContentLoaded', function() {
    // Appliquer les filtres
    document.getElementById('btn-apply-filters').addEventListener('click', filterHoraires);
    
    // Réinitialiser les filtres
    document.getElementById('btn-reset-filters').addEventListener('click', function() {
        document.getElementById('filter-salle').value = '';
        document.getElementById('filter-ecue').value = '';
        document.getElementById('filter-type').value = '';
        document.getElementById('filter-enseignant').value = '';
        
        // Réafficher tous les éléments
        document.querySelectorAll('.horaire-item').forEach(item => {
            item.style.display = 'block';
        });
    });
    
        // Activer les tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
        });
});


// Fonction pour exporter l'emploi du temps en PDF
function exportToPDF() {
    // Afficher un message de chargement
    Swal.fire({
        title: 'Génération du PDF...',
        text: 'Veuillez patienter',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Créer un formulaire caché pour envoyer les données au serveur
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'controller/export_horaire.php';
    form.target = '_blank';
    
    // Ajouter les paramètres nécessaires
    const params = {
        promotion: <?= $idPromotion ?>,
        annee_acad: <?= $selectedYearId ?>,
        date_debut: '<?= $currentMonday ?>',
        date_fin: '<?= $currentSunday ?>',
        format: 'pdf',
        titre: 'Emploi du temps - <?= addslashes($promotionName) ?> (<?= addslashes($selectedYear['designation']) ?>)'
    };
    
    // Ajouter chaque paramètre comme un champ caché
    for (const key in params) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    // Ajouter le formulaire au document, le soumettre, puis le supprimer
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Fermer le message de chargement après un court délai
    setTimeout(() => {
        Swal.close();
    }, 2000);
}

// Fonction pour exporter l'emploi du temps en Excel
function exportToExcel() {
    // Afficher un message de chargement
    Swal.fire({
        title: 'Génération du fichier Excel...',
        text: 'Veuillez patienter',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Créer un formulaire caché pour envoyer les données au serveur
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'controller/export_horaire.php';
    form.target = '_blank';
    
    // Ajouter les paramètres nécessaires
    const params = {
        promotion: <?= $idPromotion ?>,
        annee_acad: <?= $selectedYearId ?>,
        date_debut: '<?= $currentMonday ?>',
        date_fin: '<?= $currentSunday ?>',
        format: 'excel',
        titre: 'Emploi du temps - <?= addslashes($promotionName) ?> (<?= addslashes($selectedYear['designation']) ?>)'
    };
    
    // Ajouter chaque paramètre comme un champ caché
    for (const key in params) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    // Ajouter le formulaire au document, le soumettre, puis le supprimer
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Fermer le message de chargement après un court délai
    setTimeout(() => {
        Swal.close();
    }, 2000);
}


// Fonction pour dupliquer un horaire
function duplicateHoraire(idHoraire) {
    Swal.fire({
        title: 'Dupliquer cet horaire',
        html: `
            <div class="mb-3">
                <label for="dupliquer-date" class="form-label">Nouvelle date</label>
                <input type="date" id="dupliquer-date" class="form-control" required>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Dupliquer',
        cancelButtonText: 'Annuler',
        preConfirm: () => {
            const newDate = document.getElementById('dupliquer-date').value;
            if (!newDate) {
                Swal.showValidationMessage('Veuillez sélectionner une date');
                return false;
            }
            return { date: newDate };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Envoyer la requête de duplication
            window.location.href = `controller/horaire_controller.php?action=duplicate_horaire&id=${idHoraire}&new_date=${result.value.date}&promotion=<?= $idPromotion ?>&week=<?= $weekOffset ?>&annee=<?= $selectedYearId ?>`;
        }
    });
}


// Fonction pour exporter les horaires de toutes les promotions en PDF
function exportAllPromotionSchedules() {
    // Afficher un message de chargement
    Swal.fire({
        title: 'Génération du PDF pour toutes les promotions...',
        text: 'Veuillez patienter, cette opération peut prendre un peu de temps',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Créer un formulaire caché pour envoyer les données au serveur
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'controller/export_all_horaires.php';
    form.target = '_blank';
    
    // Ajouter les paramètres nécessaires
    const params = {
        section_id: '<?= isset($_SESSION['idSection']) ? $_SESSION['idSection'] : 0 ?>',
        annee_acad: <?= $selectedYearId ?>,
        date_debut: '<?= $currentMonday ?>',
        date_fin: '<?= $currentSunday ?>',
        format: 'pdf',
        titre: 'Emploi du temps hebdomadaire - <?= addslashes($selectedYear['designation']) ?>'
    };
    
    // Ajouter chaque paramètre comme un champ caché
    for (const key in params) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    // Ajouter le formulaire au document, le soumettre, puis le supprimer
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Fermer le message de chargement après un délai
    setTimeout(() => {
        Swal.close();
    }, 3000);
}



</script>



<style>
.horaire-table th, .horaire-table td {
    vertical-align: middle;
    padding: 0.5rem;
}

.horaire-table th {
    text-align: center;
    background-color: #f8f9fa;
}

.horaire-item {
    font-size: 0.85rem;
}

/* Styles pour les grandes périodes */
.horaire-cell {
    position: relative;
    min-height: 200px;
    padding: 10px;
    vertical-align: top;
}

/* Badge pour les heures exactes */
.horaire-item .badge {
    font-size: 0.75rem;
}

/* Espacement entre les cartes d'horaires */
.horaire-item {
    margin-bottom: 10px;
}

/* Indicateurs visuels pour les cours multi-périodes */
.horaire-item.multi-period {
    border-left: 4px solid #ffcc00;
}

.horaire-item.full-day {
    border-left: 4px solid #ff9900;
    font-weight: bold;
}

/* Pour mieux différencier les cases matin/après-midi */
.horaire-table tr:nth-child(odd) td:not(:first-child) {
    background-color: rgba(0, 0, 0, 0.02);
}

/* Afficher les heures de début et de fin */
.horaire-item .badge {
    background-color: rgba(255, 255, 255, 0.8) !important;
    font-weight: normal;
}

/* Styles pour indiquer le jour actuel */
.current-day {
    background-color: rgba(0, 123, 255, 0.1);
}

/* Styles pour les conflits potentiels */
.horaire-item.has-conflict {
    border: 2px dashed red !important;
    position: relative;
}

.horaire-item.has-warning {
    border: 2px dashed orange !important;
}

.horaire-item.has-conflict::after,
.horaire-item.has-warning::after {
    content: "⚠️";
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 16px;
}

/* Style pour le survol des horaires avec conflit */
.horaire-item.has-conflict:hover,
.horaire-item.has-warning:hover {
    background-color: rgba(255, 0, 0, 0.1) !important;
}

/* Tooltip pour les informations de conflit */
.conflict-tooltip {
    position: relative;
    display: inline-block;
}

.conflict-tooltip .tooltiptext {
    visibility: hidden;
    width: 200px;
    background-color: rgba(0, 0, 0, 0.8);
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
}

.conflict-tooltip:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}

</style>

<?php include "./views/include/footer.php"; ?>

