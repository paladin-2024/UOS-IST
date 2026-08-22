<?php
include "./views/include/header.php";
$universite = new Universite();
$horaireModel = new Horaire();

// Récupérer l'utilisateur connecté
$userId = $_SESSION['id'];

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Vérifier si l'utilisateur est un responsable de section
$isResponsable = $universite->isUserSectionResponsable($userId, $currentYear['idannee_acad']);

// Récupérer les promotions selon le rôle de l'utilisateur
if ($isResponsable) {
    // L'utilisateur est un responsable de section, filtrer les promotions
    $promotions = $universite->getPromotionsByResponsable($currentYear['idannee_acad'], $userId);
} else {
    // L'utilisateur n'est pas un responsable, vérifier s'il a un rôle admin
    if (isset($_SESSION['idRole']) && $_SESSION['idRole'] === 1) {
        // Pour les administrateurs, afficher toutes les promotions
        $promotions = $universite->getPromotions($currentYear['idannee_acad']);
    } else {
        // Utilisateur standard sans droits spécifiques
        $promotions = [];
        $_SESSION['error'] = "Vous n'avez pas les droits pour accéder aux horaires des promotions.";
    }
}

// Si aucune promotion n'est accessible, afficher un message
if (empty($promotions)) {
    ?>
    <main id="main" class="main">
        <div class="pagetitle">
            <h1>TABLEAU DE BORD D'OCCUPATION DES PROMOTIONS</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                    <li class="breadcrumb-item active">Occupation des promotions</li>
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
                                Vous n'êtes pas responsable d'une section ou aucune promotion n'est disponible pour l'année académique en cours.
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

// Gérer la navigation par semaine
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Déterminer le premier jour de la semaine courante (lundi)
$currentMonday = date('Y-m-d', strtotime("monday this week $weekOffset weeks", strtotime($today)));
$currentSunday = date('Y-m-d', strtotime("sunday this week $weekOffset weeks", strtotime($today)));

// Créer un tableau avec les IDs des promotions accessibles
$promotionIds = array_column($promotions, 'idpromotion');

// Récupérer les horaires pour la semaine spécifique pour les promotions autorisées
$horaires = $horaireModel->getOccupationPromotions(
    $currentYear['idannee_acad'],
    $currentMonday,
    $currentSunday
);

// Filtrer les horaires pour ne garder que ceux des promotions autorisées
$horaires = array_filter($horaires, function($h) use ($promotionIds) {
    return in_array($h['idpromotion'], $promotionIds);
});

// Récupérer les statistiques d'occupation uniquement pour les promotions autorisées
$promotionsStats = $horaireModel->getPromotionsOccupationRate(
    $currentYear['idannee_acad'],
    $currentMonday,
    $currentSunday
);

// Filtrer les statistiques pour ne garder que celles des promotions autorisées
$promotionsStats = array_filter($promotionsStats, function($s) use ($promotionIds) {
    return in_array($s['idpromotion'], $promotionIds);
});

// Extraire les données pour les filtres
$cycles = array_unique(array_column($promotions, 'cycle'));
sort($cycles);

$salles = [];
$enseignants = [];
foreach ($horaires as $h) {
    if (!empty($h['salle']) && !in_array($h['salle'], $salles)) {
        $salles[] = $h['salle'];
    }
    if (!empty($h['enseignant_nom']) && !in_array($h['enseignant_nom'], $enseignants)) {
        $enseignants[] = $h['enseignant_nom'];
    }
}
sort($salles);
sort($enseignants);

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

// Périodes horaires à afficher
$periodes = [
    '08:00-10:00', 
    '10:00-12:00',
    '13:00-15:00',
    '15:00-17:00',
    '17:00-19:00'
];

// Organiser les horaires par promotion, jour et période
$promotionHoraires = [];

// Initialiser la structure pour toutes les promotions et périodes
foreach ($promotions as $promotion) {
    $promotionId = $promotion['idpromotion'];
    $promotionHoraires[$promotionId] = [
        'designation' => $promotion['designationPromotion'],
        'cycle' => $promotion['cycle'],
        'horaires' => []
    ];
    
    foreach ($weekDates as $jour => $dateInfo) {
        $promotionHoraires[$promotionId]['horaires'][$jour] = [];
        
        foreach ($periodes as $periode) {
            $promotionHoraires[$promotionId]['horaires'][$jour][$periode] = [];
        }
    }
}

// Remplir la structure avec les horaires
foreach ($horaires as $h) {
    $promotionId = $h['idpromotion'];
    $heureDebut = substr($h['heure_debut'], 0, 5);
    $heureFin = substr($h['heure_fin'], 0, 5);
    
    // Convertir la date en jour de la semaine en français
    if (!empty($h['date_cours'])) {
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
    
    // Déterminer dans quelle période placer l'horaire
    foreach ($periodes as $periode) {
        list($periodeDebut, $periodeFin) = explode('-', $periode);
        
        // Vérifier si l'horaire chevauche cette période
        if (($heureDebut < $periodeFin) && ($heureFin > $periodeDebut)) {
            // Ajouter des propriétés pour identifier le type de cours
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
            
            $h['typeClass'] = $typeClass;
            $h['exact_hours'] = "$heureDebut - $heureFin";
            
            if (isset($promotionHoraires[$promotionId]['horaires'][$jour][$periode])) {
                $promotionHoraires[$promotionId]['horaires'][$jour][$periode][] = $h;
            }
        }
    }
}

// Calculer les taux d'occupation pour le filtre
foreach ($promotionsStats as &$stat) {
    $maxMinutes = 10 * 60 * 6; // 10h par jour * 6 jours
    $stat['taux_occupation'] = min(100, round(($stat['minutes_totales'] / $maxMinutes) * 100));
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD D'OCCUPATION DES PROMOTIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="#">Enseignement</a></li>
                <li class="breadcrumb-item active">Occupation des promotions</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Occupation des promotions: Semaine du <?= date('d/m/Y', strtotime($currentMonday)) ?> au <?= date('d/m/Y', strtotime($currentSunday)) ?></h5>
                        
                        <!-- Navigation entre les semaines -->
                        <div class="d-flex justify-content-between mb-4">
                            <a href="?view=enseignement/occupation_promotions&week=<?= $weekOffset - 1 ?>" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Semaine précédente
                            </a>
                            <a href="?view=enseignement/occupation_promotions&week=0" class="btn btn-outline-secondary">
                                Semaine actuelle
                            </a>
                            <a href="?view=enseignement/occupation_promotions&week=<?= $weekOffset + 1 ?>" class="btn btn-outline-primary">
                                Semaine suivante <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        
                        <!-- Filtres -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Filtres</h5>
                                <form id="filterForm" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filterCycle" class="form-label">Cycle</label>
                                        <select id="filterCycle" class="form-select">
                                            <option value="">Tous les cycles</option>
                                            <?php foreach ($cycles as $cycle): ?>
                                                <option value="<?= htmlspecialchars($cycle) ?>"><?= htmlspecialchars($cycle) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterPromotion" class="form-label">Promotion</label>
                                        <select id="filterPromotion" class="form-select">
                                            <option value="">Toutes les promotions</option>
                                            <?php foreach ($promotions as $promotion): ?>
                                                <option value="<?= htmlspecialchars($promotion['idpromotion']) ?>"><?= htmlspecialchars($promotion['designationPromotion']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterJour" class="form-label">Jour</label>
                                        <select id="filterJour" class="form-select">
                                            <option value="">Tous les jours</option>
                                            <?php foreach ($weekDates as $jour => $info): ?>
                                                <option value="<?= $jour ?>"><?= $info['display'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterPeriode" class="form-label">Période</label>
                                        <select id="filterPeriode" class="form-select">
                                            <option value="">Toutes les périodes</option>
                                            <?php foreach ($periodes as $periode): ?>
                                                <option value="<?= $periode ?>"><?= $periode ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterTypeCours" class="form-label">Type de cours</label>
                                        <select id="filterTypeCours" class="form-select">
                                            <option value="">Tous types</option>
                                            <option value="primary">CM</option>
                                            <option value="success">TD</option>
                                            <option value="warning">TP</option>
                                            <option value="danger">Évaluation</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                    <label for="filterSalle" class="form-label">Salle</label>
                                        <select id="filterSalle" class="form-select">
                                            <option value="">Toutes les salles</option>
                                            <?php foreach ($salles as $salle): ?>
                                                <option value="<?= htmlspecialchars($salle) ?>"><?= htmlspecialchars($salle) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterEnseignant" class="form-label">Enseignant</label>
                                        <select id="filterEnseignant" class="form-select">
                                            <option value="">Tous les enseignants</option>
                                            <?php foreach ($enseignants as $enseignant): ?>
                                                <option value="<?= htmlspecialchars($enseignant) ?>"><?= htmlspecialchars($enseignant) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterOccupation" class="form-label">Taux d'occupation</label>
                                        <select id="filterOccupation" class="form-select">
                                            <option value="">Tous les taux</option>
                                            <option value="low">Faible (0-30%)</option>
                                            <option value="medium">Moyen (31-70%)</option>
                                            <option value="high">Élevé (71-100%)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="button" id="applyFilters" class="btn btn-primary">Appliquer les filtres</button>
                                        <button type="button" id="resetFilters" class="btn btn-outline-secondary">Réinitialiser</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Statistiques d'occupation des promotions -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Taux d'occupation des promotions</h5>
                                        <div class="row">
                                            <?php foreach ($promotionsStats as $stat): ?>
                                                <div class="col-md-3 col-sm-6 mb-3 stat-card" 
                                                     data-promotion="<?= $stat['idpromotion'] ?>"
                                                     data-cycle="<?= htmlspecialchars($stat['cycle']) ?>"
                                                     data-occupation="<?= $stat['taux_occupation'] ?>">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title h6">
                                                                <?= htmlspecialchars($stat['designationPromotion']) ?>
                                                                <span class="badge bg-info"><?= htmlspecialchars($stat['cycle']) ?></span>
                                                            </h5>
                                                            <div class="d-flex justify-content-between">
                                                                <span><?= $stat['nombre_cours'] ?> cours</span>
                                                                <span><?= round($stat['minutes_totales']/60, 1) ?>h</span>
                                                            </div>
                                                            <div class="progress mt-2">
                                                                <?php 
                                                                    $percentage = $stat['taux_occupation'];
                                                                    $progressClass = 'bg-primary';
                                                                    if ($percentage < 30) {
                                                                        $progressClass = 'bg-info';
                                                                    } elseif ($percentage < 70) {
                                                                        $progressClass = 'bg-success';
                                                                    } elseif ($percentage >= 70) {
                                                                        $progressClass = 'bg-danger';
                                                                    }
                                                                ?>
                                                                <div class="progress-bar <?= $progressClass ?>" role="progressbar" style="width: <?= $percentage ?>%" 
                                                                     aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                                    <?= $percentage ?>%
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Légende -->
                        <div class="mb-3">
                            <span class="badge bg-primary me-2">CM</span>
                            <span class="badge bg-success me-2">TD</span>
                            <span class="badge bg-warning me-2">TP</span>
                            <span class="badge bg-danger me-2">Évaluation</span>
                        </div>
                        
                        <!-- Accordéon pour afficher chaque promotion -->
                        <div class="accordion" id="accordionPromotions">
                            <?php 
                            $index = 0;
                            $cycleGroups = [];
                            
                            // Regrouper les promotions par cycle
                            foreach ($promotionHoraires as $promotionId => $promotion) {
                                $cycle = $promotion['cycle'];
                                if (!isset($cycleGroups[$cycle])) {
                                    $cycleGroups[$cycle] = [];
                                }
                                $cycleGroups[$cycle][$promotionId] = $promotion;
                            }
                            
                            // Afficher les promotions par groupe de cycle
                            foreach ($cycleGroups as $cycle => $promotionsGroup):
                            ?>
                                <div class="accordion-item" data-cycle="<?= htmlspecialchars($cycle) ?>">
                                    <h2 class="accordion-header" id="headingCycle<?= $cycle ?>">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapseCycle<?= $cycle ?>" aria-expanded="true" 
                                                aria-controls="collapseCycle<?= $cycle ?>">
                                            <?= htmlspecialchars($cycle) ?> (<?= count($promotionsGroup) ?> promotions)
                                        </button>
                                    </h2>
                                    <div id="collapseCycle<?= $cycle ?>" class="accordion-collapse collapse show" 
                                         aria-labelledby="headingCycle<?= $cycle ?>">
                                        <div class="accordion-body">
                                            <?php foreach ($promotionsGroup as $promotionId => $promotion): $index++; ?>
                                                <div class="card mb-4 promotion-card" 
                                                     data-promotion="<?= $promotionId ?>" 
                                                     data-cycle="<?= htmlspecialchars($promotion['cycle']) ?>">
                                                    <div class="card-header">
                                                        <h5 class="card-title"><?= htmlspecialchars($promotion['designation']) ?></h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <!-- Tableau d'occupation de la promotion -->
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 180px;">Période</th>
                                                                        <?php foreach ($weekDates as $day): ?>
                                                                            <th><?= $day['display'] ?></th>
                                                                        <?php endforeach; ?>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($periodes as $periode): ?>
                                                                        <tr class="periode-row" data-periode="<?= $periode ?>">
                                                                            <td class="fw-bold"><?= $periode ?></td>
                                                                            <?php foreach ($weekDays as $jour): ?>
                                                                                <td class="jour-cell" data-jour="<?= $jour ?>">
                                                                                    <?php if (isset($promotion['horaires'][$jour][$periode]) && !empty($promotion['horaires'][$jour][$periode])): ?>
                                                                                        <?php foreach ($promotion['horaires'][$jour][$periode] as $cours): ?>
                                                                                            <div class="card mb-1 bg-<?= $cours['typeClass'] ?> text-white cours-card"
                                                                                                 data-type="<?= $cours['typeClass'] ?>"
                                                                                                 data-salle="<?= htmlspecialchars($cours['salle']) ?>"
                                                                                                 data-enseignant="<?= htmlspecialchars($cours['enseignant_nom'] ?? '') ?>">
                                                                                                <div class="card-body py-1 px-2">
                                                                                                    <p class="mb-0 small"><?= htmlspecialchars($cours['designationECUE']) ?></p>
                                                                                                    <p class="mb-0 small"><?= $cours['exact_hours'] ?> - <?= htmlspecialchars($cours['type_cours']) ?></p>
                                                                                                    <p class="mb-0 small">Salle: <?= htmlspecialchars($cours['salle']) ?></p>
                                                                                                    <p class="mb-0 small"><?= htmlspecialchars($cours['enseignant_nom'] ?? 'Non assigné') ?></p>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les filtres
    const filters = {
        cycle: '',
        promotion: '',
        jour: '',
        periode: '',
        typeCours: '',
        salle: '',
        enseignant: '',
        occupation: ''
    };
    
    // Fonction pour appliquer les filtres
    function applyFilters() {
        // Récupérer les valeurs des filtres
        filters.cycle = document.getElementById('filterCycle').value;
        filters.promotion = document.getElementById('filterPromotion').value;
        filters.jour = document.getElementById('filterJour').value;
        filters.periode = document.getElementById('filterPeriode').value;
        filters.typeCours = document.getElementById('filterTypeCours').value;
        filters.salle = document.getElementById('filterSalle').value;
        filters.enseignant = document.getElementById('filterEnseignant').value;
        filters.occupation = document.getElementById('filterOccupation').value;
        
        // Filtrer les statistiques de promotion
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            let show = true;
            
            // Filtre par cycle
            if (filters.cycle && card.dataset.cycle !== filters.cycle) {
                show = false;
            }
            
            // Filtre par promotion
            if (filters.promotion && card.dataset.promotion !== filters.promotion) {
                show = false;
            }
            
            // Filtre par taux d'occupation
            if (filters.occupation) {
                const occupation = parseInt(card.dataset.occupation);
                if (filters.occupation === 'low' && occupation > 30) show = false;
                if (filters.occupation === 'medium' && (occupation <= 30 || occupation > 70)) show = false;
                if (filters.occupation === 'high' && occupation <= 70) show = false;
            }
            
            card.style.display = show ? '' : 'none';
        });
        
        // Filtrer les accordéons de cycle
        const accordionItems = document.querySelectorAll('.accordion-item');
        accordionItems.forEach(item => {
            let showCycle = true;
            
            // Filtre par cycle
            if (filters.cycle && item.dataset.cycle !== filters.cycle) {
                showCycle = false;
            }
            
            item.style.display = showCycle ? '' : 'none';
        });
        
        // Filtrer les cartes de promotion
        const promotionCards = document.querySelectorAll('.promotion-card');
        promotionCards.forEach(card => {
            let showPromotion = true;
            
            // Filtre par cycle
            if (filters.cycle && card.dataset.cycle !== filters.cycle) {
                showPromotion = false;
            }
            
            // Filtre par promotion
            if (filters.promotion && card.dataset.promotion !== filters.promotion) {
                showPromotion = false;
            }
            
            card.style.display = showPromotion ? '' : 'none';
            
            // Si la promotion est visible, filtrer ses cours
            if (showPromotion) {
                // Filtrer les lignes de période
                const periodeRows = card.querySelectorAll('.periode-row');
                periodeRows.forEach(row => {
                    let showPeriode = true;
                    
                    // Filtre par période
                    if (filters.periode && row.dataset.periode !== filters.periode) {
                        showPeriode = false;
                    }
                    
                    row.style.display = showPeriode ? '' : 'none';
                    
                    // Si la période est visible, filtrer les cellules de jour
                    if (showPeriode) {
                        const jourCells = row.querySelectorAll('.jour-cell');
                        jourCells.forEach(cell => {
                            let showJour = true;
                            
                            // Filtre par jour
                            if (filters.jour && cell.dataset.jour !== filters.jour) {
                                showJour = false;
                            }
                            
                            cell.style.display = showJour ? '' : 'none';
                            
                                                        // Si la cellule de jour est visible, filtrer les cours
                                                        if (showJour) {
                                const coursCards = cell.querySelectorAll('.cours-card');
                                coursCards.forEach(card => {
                                    let showCours = true;
                                    
                                    // Filtre par type de cours
                                    if (filters.typeCours && card.dataset.type !== filters.typeCours) {
                                        showCours = false;
                                    }
                                    
                                    // Filtre par salle
                                    if (filters.salle && card.dataset.salle !== filters.salle) {
                                        showCours = false;
                                    }
                                    
                                    // Filtre par enseignant
                                    if (filters.enseignant && card.dataset.enseignant !== filters.enseignant) {
                                        showCours = false;
                                    }
                                    
                                    card.style.display = showCours ? '' : 'none';
                                });
                            }
                        });
                    }
                });
            }
        });
    }
    
    // Fonction pour réinitialiser les filtres
    function resetFilters() {
        document.getElementById('filterForm').reset();
        
        // Réinitialiser les filtres
        for (let key in filters) {
            filters[key] = '';
        }
        
        // Réinitialiser l'affichage
        document.querySelectorAll('.stat-card, .accordion-item, .promotion-card, .periode-row, .jour-cell, .cours-card').forEach(el => {
            el.style.display = '';
        });
    }
    
    // Ajouter les écouteurs d'événements
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    
    // Permettre le filtrage immédiat lorsqu'on change un filtre
    const filterInputs = document.querySelectorAll('#filterForm select');
    filterInputs.forEach(input => {
        input.addEventListener('change', applyFilters);
    });
});
</script>

<?php include "./views/include/footer.php"; ?>

