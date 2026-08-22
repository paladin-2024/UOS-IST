<?php
include "./views/include/header.php";
$universite = new Universite();
$horaireModel = new Horaire();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les salles disponibles
$salles = $universite->getSalles();

// Gérer la navigation par semaine
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Déterminer le premier jour de la semaine courante (lundi)
$currentMonday = date('Y-m-d', strtotime("monday this week $weekOffset weeks", strtotime($today)));
$currentSunday = date('Y-m-d', strtotime("sunday this week $weekOffset weeks", strtotime($today)));

// Récupérer les horaires pour la semaine spécifique pour toutes les salles
$horaires = $horaireModel->getOccupationSalles(
    $currentYear['idannee_acad'],
    $currentMonday,   // Date de début de la semaine
    $currentSunday    // Date de fin de la semaine
);

// Récupérer les statistiques d'occupation des salles
$sallesStats = $horaireModel->getSallesOccupationRate(
    $currentYear['idannee_acad'],
    $currentMonday,
    $currentSunday
);

// Extraire les types de cours disponibles
$typesCours = ['CM', 'TD', 'TP', 'Évaluation'];

// Extraire les capacités de salles pour les filtres
$capacites = [];
foreach ($salles as $salle) {
    if (!empty($salle['capacite']) && !in_array($salle['capacite'], $capacites)) {
        $capacites[] = $salle['capacite'];
    }
}
sort($capacites);

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

// Organiser les horaires par salle, jour et période
$salleHoraires = [];

// Initialiser la structure pour toutes les salles et périodes
foreach ($salles as $salle) {
    $salleId = $salle['designationSalle'];
    $salleHoraires[$salleId] = [];
    
    foreach ($weekDates as $jour => $dateInfo) {
        $salleHoraires[$salleId][$jour] = [];
        
        foreach ($periodes as $periode) {
            $salleHoraires[$salleId][$jour][$periode] = [];
        }
    }
}

// Remplir la structure avec les horaires
foreach ($horaires as $h) {
    $salleId = $h['salle'];
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
            
            if (isset($salleHoraires[$salleId][$jour][$periode])) {
                $salleHoraires[$salleId][$jour][$periode][] = $h;
            }
        }
    }
}

// Extraire les données pour les filtres
$promotions = [];
foreach ($horaires as $h) {
    if (!empty($h['designationPromotion']) && !in_array($h['designationPromotion'], $promotions)) {
        $promotions[] = $h['designationPromotion'];
    }
}
sort($promotions);

$enseignants = [];
foreach ($horaires as $h) {
    if (!empty($h['enseignant_nom']) && !in_array($h['enseignant_nom'], $enseignants)) {
        $enseignants[] = $h['enseignant_nom'];
    }
}
sort($enseignants);

// Calculer les taux d'occupation pour le filtre
foreach ($sallesStats as &$stat) {
    $maxMinutes = 10 * 60 * 6; // 10h par jour * 6 jours
    $stat['taux_occupation'] = min(100, round(($stat['minutes_totales'] / $maxMinutes) * 100));
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD D'OCCUPATION DES SALLES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="#">Enseignement</a></li>
                <li class="breadcrumb-item active">Occupation des salles</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Occupation des salles: Semaine du <?= date('d/m/Y', strtotime($currentMonday)) ?> au <?= date('d/m/Y', strtotime($currentSunday)) ?></h5>
                        
                        <!-- Navigation entre les semaines -->
                        <div class="d-flex justify-content-between mb-4">
                            <a href="?view=enseignement/occupation_salles&week=<?= $weekOffset - 1 ?>" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Semaine précédente
                            </a>
                            <a href="?view=enseignement/occupation_salles&week=0" class="btn btn-outline-secondary">
                                Semaine actuelle
                            </a>
                            <a href="?view=enseignement/occupation_salles&week=<?= $weekOffset + 1 ?>" class="btn btn-outline-primary">
                                Semaine suivante <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        
                        <!-- Filtres -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Filtres</h5>
                                <form id="filterForm" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filterSalle" class="form-label">Salle</label>
                                        <select id="filterSalle" class="form-select">
                                            <option value="">Toutes les salles</option>
                                            <?php foreach ($salles as $salle): ?>
                                                <option value="<?= htmlspecialchars($salle['designationSalle']) ?>">
                                                    <?= htmlspecialchars($salle['designationSalle']) ?> 
                                                    <?= !empty($salle['capacite']) ? '(' . $salle['capacite'] . ' places)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterCapacite" class="form-label">Capacité minimale</label>
                                        <select id="filterCapacite" class="form-select">
                                            <option value="">Toutes capacités</option>
                                            <?php foreach ($capacites as $capacite): ?>
                                                <option value="<?= $capacite ?>"><?= $capacite ?> places ou plus</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterPromotion" class="form-label">Promotion</label>
                                        <select id="filterPromotion" class="form-select">
                                            <option value="">Toutes les promotions</option>
                                            <?php foreach ($promotions as $promotion): ?>
                                                <option value="<?= htmlspecialchars($promotion) ?>"><?= htmlspecialchars($promotion) ?></option>
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
                                    <div class="col-md-3">
                                        <label for="filterPeriode" class="form-label">Période</label>
                                        <select id="filterPeriode" class="form-select">
                                            <option value="">Toutes les périodes</option>
                                            <?php foreach ($periodes as $periode): ?>
                                                <option value="<?= $periode ?>"><?= $periode ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="button" id="applyFilters" class="btn btn-primary">Appliquer les filtres</button>
                                        <button type="button" id="resetFilters" class="btn btn-outline-secondary">Réinitialiser</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Statistiques d'occupation des salles -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Taux d'occupation des salles</h5>
                                        <div class="row" id="statsContainer">
                                        <?php foreach ($sallesStats as $stat): ?>
                                                <div class="col-md-3 col-sm-6 mb-3 stat-card" 
                                                     data-salle="<?= htmlspecialchars($stat['salle']) ?>"
                                                     data-occupation="<?= $stat['taux_occupation'] ?>">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title h6"><?= htmlspecialchars($stat['salle']) ?></h5>
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
                        
                        <!-- Tableau d'occupation des salles -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 180px;">Salle / Période</th>
                                        <?php foreach ($weekDates as $day): ?>
                                            <th><?= $day['display'] ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody id="sallesTableBody">
                                    <?php foreach ($salles as $salle): 
                                        $salleId = $salle['designationSalle'];
                                        $capacite = $salle['capacite'] ?? 0;
                                        if (isset($salleHoraires[$salleId])): ?>
                                            <!-- Entête de la salle -->
                                            <tr class="table-light salle-row" 
                                                data-salle="<?= htmlspecialchars($salleId) ?>" 
                                                data-capacite="<?= $capacite ?>">
                                                <th colspan="<?= count($weekDates) + 1 ?>" class="text-center">
                                                    <?= htmlspecialchars($salleId) ?> 
                                                    <?= !empty($salle['capacite']) ? '(' . $salle['capacite'] . ' places)' : '' ?>
                                                </th>
                                            </tr>
                                            
                                            <?php foreach ($periodes as $periode): ?>
                                                <tr class="periode-row" data-periode="<?= $periode ?>" data-salle="<?= htmlspecialchars($salleId) ?>">
                                                    <td class="fw-bold"><?= $periode ?></td>
                                                    <?php foreach ($weekDays as $jour): ?>
                                                        <td class="jour-cell" data-jour="<?= $jour ?>">
                                                            <?php if (isset($salleHoraires[$salleId][$jour][$periode]) && !empty($salleHoraires[$salleId][$jour][$periode])): ?>
                                                                <?php foreach ($salleHoraires[$salleId][$jour][$periode] as $cours): ?>
                                                                    <div class="card mb-1 bg-<?= $cours['typeClass'] ?> text-white cours-card" 
                                                                         data-type="<?= $cours['typeClass'] ?>"
                                                                         data-promotion="<?= htmlspecialchars($cours['designationPromotion']) ?>"
                                                                         data-enseignant="<?= htmlspecialchars($cours['enseignant_nom'] ?? '') ?>">
                                                                        <div class="card-body py-1 px-2">
                                                                            <p class="mb-0 small"><?= htmlspecialchars($cours['designationECUE']) ?></p>
                                                                            <p class="mb-0 small"><?= $cours['exact_hours'] ?> - <?= htmlspecialchars($cours['type_cours']) ?></p>
                                                                            <p class="mb-0 small"><?= htmlspecialchars($cours['designationPromotion']) ?></p>
                                                                            <p class="mb-0 small"><?= htmlspecialchars($cours['enseignant_nom'] ?? 'Non assigné') ?></p>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les filtres
    const filters = {
        salle: '',
        capacite: '',
        promotion: '',
        jour: '',
        typeCours: '',
        enseignant: '',
        occupation: '',
        periode: ''
    };
    
    // Fonction pour appliquer les filtres
    function applyFilters() {
        // Récupérer les valeurs des filtres
        filters.salle = document.getElementById('filterSalle').value;
        filters.capacite = document.getElementById('filterCapacite').value;
        filters.promotion = document.getElementById('filterPromotion').value;
        filters.jour = document.getElementById('filterJour').value;
        filters.typeCours = document.getElementById('filterTypeCours').value;
        filters.enseignant = document.getElementById('filterEnseignant').value;
        filters.occupation = document.getElementById('filterOccupation').value;
        filters.periode = document.getElementById('filterPeriode').value;
        
        // Filtrer les statistiques
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            let show = true;
            
            // Filtre par salle
            if (filters.salle && card.dataset.salle !== filters.salle) {
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
        
        // Filtrer les lignes de salle
        const salleRows = document.querySelectorAll('.salle-row');
        salleRows.forEach(row => {
            let showSalle = true;
            
            // Filtre par salle
            if (filters.salle && row.dataset.salle !== filters.salle) {
                showSalle = false;
            }
            
            // Filtre par capacité
            if (filters.capacite && parseInt(row.dataset.capacite) < parseInt(filters.capacite)) {
                showSalle = false;
            }
            
            row.style.display = showSalle ? '' : 'none';
            
            // Trouver les lignes associées à cette salle
            const periodeRows = document.querySelectorAll(`.periode-row[data-salle="${row.dataset.salle}"]`);
            periodeRows.forEach(periodeRow => {
                let showPeriode = showSalle;
                
                // Filtre par période
                if (filters.periode && periodeRow.dataset.periode !== filters.periode) {
                    showPeriode = false;
                }
                
                periodeRow.style.display = showPeriode ? '' : 'none';
                
                // Si on affiche la période, vérifier chaque cellule de jour
                if (showPeriode) {
                    const jourCells = periodeRow.querySelectorAll('.jour-cell');
                    jourCells.forEach(cell => {
                        // Filtre par jour
                        if (filters.jour && cell.dataset.jour !== filters.jour) {
                            cell.style.display = 'none';
                        } else {
                            cell.style.display = '';
                            
                            // Filtrer les cours à l'intérieur de la cellule
                            const coursCards = cell.querySelectorAll('.cours-card');
                            coursCards.forEach(card => {
                                let showCours = true;
                                
                                // Filtre par type de cours
                                if (filters.typeCours && card.dataset.type !== filters.typeCours) {
                                    showCours = false;
                                }
                                
                                // Filtre par promotion
                                if (filters.promotion && card.dataset.promotion !== filters.promotion) {
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
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.display = '';
        });
        
        document.querySelectorAll('.salle-row, .periode-row').forEach(row => {
            row.style.display = '';
        });
        
        document.querySelectorAll('.jour-cell').forEach(cell => {
            cell.style.display = '';
        });
        
        document.querySelectorAll('.cours-card').forEach(card => {
            card.style.display = '';
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
