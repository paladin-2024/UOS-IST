<?php
include "./views/include/header.php";

// Vérification des droits d'accès
$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Récupérer les responsabilités de l'utilisateur (seulement si pas admin)
$userResponsibilities = [];
if (!$hasFullAccess) {
    try {
        $connexion = Connexion::getInstance()->getPDO();
        $query = "SELECT DISTINCT section_idsection FROM responsable_section 
                  WHERE idUser = :userId";
        $stmt = $connexion->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $userResponsibilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des responsabilités: " . $e->getMessage());
        $userResponsibilities = [];
    }
}

// Si l'utilisateur n'est pas admin et n'a aucune responsabilité, refuser l'accès
if (!$hasFullAccess && empty($userResponsibilities)) {
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

$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'année académique active
$query = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmt = $connexion->prepare($query);
$stmt->execute();
$activeYear = $stmt->fetch(PDO::FETCH_ASSOC);
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Année sélectionnée
$selectedYearId = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : $activeYearId;

// Récupérer toutes les années académiques
$query = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmt = $connexion->prepare($query);
$stmt->execute();
$allYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les spécialisations organisées par section, orientation et spécialisation
$specialisationsHierarchy = [];
if ($hasFullAccess) {
    // Admin - toutes les spécialisations
    $query = "SELECT s.idSpecialisation, s.designation as spec_designation, 
                     o.idorientation, o.designationOrientation as orientation_designation,
                     sec.idsection, sec.designationSection as section_designation,
                     ur.designation_UR as unite_recherche
              FROM specialisation s
              LEFT JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
              LEFT JOIN orientation o ON s.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              ORDER BY sec.designationSection, o.designationOrientation, s.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les spécialisations de ses sections
    if (!empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query = "SELECT s.idSpecialisation, s.designation as spec_designation, 
                         o.idorientation, o.designationOrientation as orientation_designation,
                         sec.idsection, sec.designationSection as section_designation,
                         ur.designation_UR as unite_recherche
                  FROM specialisation s
                  LEFT JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
                  LEFT JOIN orientation o ON s.idorientation = o.idorientation
                  LEFT JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE sec.idsection IN ($placeholders)
                  ORDER BY sec.designationSection, o.designationOrientation, s.designation";
        $stmt = $connexion->prepare($query);
        $stmt->execute($userResponsibilities);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $results = [];
    }
}

// Organiser les résultats en hiérarchie section -> orientation -> spécialisation
foreach ($results as $row) {
    // Ignorer les entrées sans section ou sans orientation
    if (empty($row['idsection']) || empty($row['idorientation'])) {
        continue;
    }

    $sectionId = $row['idsection'];
    $sectionName = $row['section_designation'];
    $orientationId = $row['idorientation'];
    $orientationName = $row['orientation_designation'];
    $specId = $row['idSpecialisation'];
    $specName = $row['spec_designation'];

    if (!isset($specialisationsHierarchy[$sectionId])) {
        $specialisationsHierarchy[$sectionId] = [
            'nom' => $sectionName,
            'orientations' => []
        ];
    }

    if (!isset($specialisationsHierarchy[$sectionId]['orientations'][$orientationId])) {
        $specialisationsHierarchy[$sectionId]['orientations'][$orientationId] = [
            'nom' => $orientationName,
            'specialisations' => []
        ];
    }

    $specialisationsHierarchy[$sectionId]['orientations'][$orientationId]['specialisations'][$specId] = $specName;
}

// Variable pour la spécialisation sélectionnée
$selectedSpecialisation = isset($_GET['specialisation']) && !empty($_GET['specialisation']) ? $_GET['specialisation'] : null;

// Variable pour le cycle sélectionné
$selectedCycle = isset($_GET['cycle']) && !empty($_GET['cycle']) ? $_GET['cycle'] : null;

// Récupérer tous les travaux (avec ou sans soutenance)
$allTravaux = [];
$debugInfo = [];
try {
    $query = "SELECT DISTINCT sj.idsujets, sj.intitule as sujet_titre, sj.cycle, sj.idSpecialisation,
                     e.noms as etudiant_nom, e.matricule, e.idetudiant,
                     d.noms as directeur_nom,
                     sp.designation as specialisation,
                     o.idorientation, o.designationOrientation as orientation_designation,
                     sec.idsection as section_idsection, sec.designationSection as section_designation,
                     s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                     dm.idDepot, dm.fichier as memoire_fichier, dm.dateDepot,
                     j.idjury, j.designation as jury_designation,
                     (SELECT COUNT(*) FROM lecteurs_soutenance WHERE idsoutenance = s.idsoutenance) as nb_lecteurs
              FROM sujets sj
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON sj.idDirecteur = d.idAgent
              LEFT JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
              LEFT JOIN orientation o ON sp.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
              LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
              LEFT JOIN jury j ON s.jury_id = j.idjury
              WHERE sj.annee_acad_idannee_acad = :anneeId";

    $executeParams = ['anneeId' => $selectedYearId];

    // Filtrer par spécialisation si sélectionnée
    if ($selectedSpecialisation) {
        $query .= " AND sp.idSpecialisation = :specialisationId";
        $executeParams['specialisationId'] = $selectedSpecialisation;
    }

    // Filtrer par cycle si sélectionné
    if ($selectedCycle) {
        $query .= " AND sj.cycle = :cycle";
        $executeParams['cycle'] = $selectedCycle;
    }

    // Filtrer par sections de l'utilisateur si pas admin
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $sectionPlaceholders = [];
        foreach ($userResponsibilities as $index => $sectionId) {
            $paramName = ":section_" . $index;
            $sectionPlaceholders[] = $paramName;
            $executeParams[$paramName] = $sectionId;
        }
        $query .= " AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
    }

    $query .= " ORDER BY CASE 
                    WHEN s.idsoutenance IS NULL THEN 0 
                    WHEN s.date_soutenance IS NULL THEN 1 
                    ELSE 2 
                END, 
                s.date_soutenance DESC, 
                e.noms ASC
                LIMIT 50";

    $debugInfo['query'] = $query;
    $debugInfo['params'] = $executeParams;

    $stmt = $connexion->prepare($query);
    $stmt->execute($executeParams);
    $allTravaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $initialCount = count($allTravaux);

    $debugInfo['count'] = count($allTravaux);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des travaux: " . $e->getMessage());
    $debugInfo['error'] = $e->getMessage();
    $allTravaux = [];
}

// Récupérer la liste des enseignants
$enseignants = [];
try {
    if ($hasFullAccess) {
        // Admin - tous les enseignants
        if ($selectedSpecialisation) {
            // Filtrer par spécialisation
            $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                       FROM agent a
                       LEFT JOIN grade g ON a.grade_id = g.idgrade
                       INNER JOIN sujets sj ON sj.idDirecteur = a.idAgent OR sj.idEncadrant = a.idAgent
                       WHERE a.type_agent = 'Enseignant' AND sj.idSpecialisation = :specialisationId
                       ORDER BY a.noms";
            $stmt = $connexion->prepare($query);
            $stmt->execute(['specialisationId' => $selectedSpecialisation]);
            $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query = "SELECT a.*, g.designation as gradeDesignation
                       FROM agent a
                       LEFT JOIN grade g ON a.grade_id = g.idgrade
                       WHERE a.type_agent = 'Enseignant'
                       ORDER BY a.noms";
            $stmt = $connexion->prepare($query);
            $stmt->execute();
            $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Responsable de section - seulement les enseignants de ses sections
        if (!empty($userResponsibilities)) {
            if ($selectedSpecialisation) {
                $sectionPlaceholders = [];
                foreach ($userResponsibilities as $index => $sectionId) {
                    $paramName = ":section_" . $index;
                    $sectionPlaceholders[] = $paramName;
                }
                $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                           FROM agent a
                           LEFT JOIN grade g ON a.grade_id = g.idgrade
                           LEFT JOIN agent_section ag_s ON ag_s.idAgent = a.idAgent
                           INNER JOIN sujets sj ON sj.idDirecteur = a.idAgent OR sj.idEncadrant = a.idAgent
                           WHERE a.type_agent = 'Enseignant' 
                           AND ag_s.idsection IN (" . implode(',', $sectionPlaceholders) . ") 
                           AND sj.idSpecialisation = :specialisationId
                           ORDER BY a.noms";
                $stmt = $connexion->prepare($query);
                $executeParams = ['specialisationId' => $selectedSpecialisation];
                foreach ($userResponsibilities as $index => $sectionId) {
                    $executeParams[":section_" . $index] = $sectionId;
                }
                $stmt->execute($executeParams);
                $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sectionPlaceholders = [];
                foreach ($userResponsibilities as $index => $sectionId) {
                    $paramName = ":section_" . $index;
                    $sectionPlaceholders[] = $paramName;
                }
                $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                           FROM agent a
                           LEFT JOIN grade g ON a.grade_id = g.idgrade
                           LEFT JOIN agent_section ag_s ON ag_s.idAgent = a.idAgent
                           WHERE a.type_agent = 'Enseignant' 
                           AND ag_s.idsection IN (" . implode(',', $sectionPlaceholders) . ")
                           ORDER BY a.noms";
                $stmt = $connexion->prepare($query);
                $executeParams = [];
                foreach ($userResponsibilities as $index => $sectionId) {
                    $executeParams[":section_" . $index] = $sectionId;
                }
                $stmt->execute($executeParams);
                $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    $enseignants = [];
}

// Récupérer les jurys disponibles pour l'année académique
$jurysDisponibles = [];
try {
    $query = "SELECT j.idjury, j.designation, j.date_creation, a1.noms as president_nom, a2.noms as secretaire_nom
              FROM jury j
              LEFT JOIN agent a1 ON j.id_president = a1.idAgent
              LEFT JOIN agent a2 ON j.id_secretaire = a2.idAgent
              WHERE j.annee_acad_id = :yearId AND j.est_actif = 1
              ORDER BY j.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute(['yearId' => $selectedYearId]);
    $jurysDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des jurys: " . $e->getMessage());
    $jurysDisponibles = [];
}

// Calculer les statistiques (requête COUNT séparée sans LIMIT)
try {
    $statsQuery = "SELECT 
                    COUNT(DISTINCT sj.idsujets) as total,
                    COUNT(DISTINCT CASE WHEN s.idsoutenance IS NULL THEN sj.idsujets END) as sans_soutenance,
                    COUNT(DISTINCT CASE WHEN s.idsoutenance IS NOT NULL THEN sj.idsujets END) as avec_soutenance,
                    COUNT(DISTINCT CASE WHEN s.idsoutenance IS NOT NULL AND (SELECT COUNT(*) FROM lecteurs_soutenance ls WHERE ls.idsoutenance = s.idsoutenance) < 2 THEN sj.idsujets END) as sans_lecteurs,
                    COUNT(DISTINCT CASE WHEN s.idsoutenance IS NOT NULL AND (SELECT COUNT(*) FROM lecteurs_soutenance ls WHERE ls.idsoutenance = s.idsoutenance) >= 2 THEN sj.idsujets END) as complets
                   FROM sujets sj
                   JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                   LEFT JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
                   LEFT JOIN orientation o ON sp.idorientation = o.idorientation
                   LEFT JOIN section sec ON o.section_idsection = sec.idsection
                   LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
                   WHERE sj.annee_acad_idannee_acad = :anneeId";

    $statsParams = ['anneeId' => $selectedYearId];

    if ($selectedSpecialisation) {
        $statsQuery .= " AND sp.idSpecialisation = :specialisationId";
        $statsParams['specialisationId'] = $selectedSpecialisation;
    }
    if ($selectedCycle) {
        $statsQuery .= " AND sj.cycle = :cycle";
        $statsParams['cycle'] = $selectedCycle;
    }
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $sectionPlaceholders = [];
        foreach ($userResponsibilities as $index => $sectionId) {
            $paramName = "section_stats_" . $index;
            $sectionPlaceholders[] = ":$paramName";
            $statsParams[$paramName] = $sectionId;
        }
        $statsQuery .= " AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
    }

    $stmtStats = $connexion->prepare($statsQuery);
    $stmtStats->execute($statsParams);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $totalTravaux = (int)($stats['total'] ?? 0);
    $travauxSansSoutenance = (int)($stats['sans_soutenance'] ?? 0);
    $travauxAvecSoutenance = (int)($stats['avec_soutenance'] ?? 0);
    $travauxSansLecteurs = (int)($stats['sans_lecteurs'] ?? 0);
    $travauxComplets = (int)($stats['complets'] ?? 0);
} catch (Exception $e) {
    error_log("Erreur statistiques assign_lecteurs: " . $e->getMessage());
    $totalTravaux = count($allTravaux);
    $travauxSansSoutenance = 0;
    $travauxAvecSoutenance = 0;
    $travauxSansLecteurs = 0;
    $travauxComplets = 0;
}
?>

<style>
    .compact-stats { display: flex; gap: 1rem; flex-wrap: wrap; }
    .compact-stats .stat-item { padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.85rem; }
    .compact-table th, .compact-table td { padding: 0.35rem 0.5rem !important; font-size: 0.85rem; vertical-align: middle; }
    .compact-table .btn-sm { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
    .compact-table .badge { font-size: 0.7rem; }
    .compact-filters .form-select, .compact-filters .form-control { padding: 0.25rem 0.5rem; font-size: 0.85rem; }
    .compact-filters label { font-size: 0.8rem; margin-bottom: 0.15rem; }
</style>

<main id="main" class="main pt-2">
    <div class="pagetitle mb-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <h1 class="mb-0" style="font-size: 1.3rem;">PROGRAMMATION DES SOUTENANCES</h1>
            <div class="compact-stats">
                <span class="stat-item bg-primary text-white"><i class="bi bi-journal-text me-1"></i>Total: <strong><?php echo $totalTravaux; ?></strong></span>
                <span class="stat-item bg-danger text-white"><i class="bi bi-exclamation-circle me-1"></i>Sans sout.: <strong><?php echo $travauxSansSoutenance; ?></strong></span>
                <span class="stat-item bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Sans lecteurs: <strong><?php echo $travauxSansLecteurs; ?></strong></span>
                <span class="stat-item bg-success text-white"><i class="bi bi-check-circle me-1"></i>Complets: <strong><?php echo $travauxComplets; ?></strong></span>
            </div>
        </div>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Filtres compacts -->
            <div class="col-lg-12 mb-2">
                <div class="card compact-filters">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2 col-6">
                                <label for="yearFilter">Année:</label>
                                <select id="yearFilter" class="form-select form-select-sm" onchange="updateYearFilter(this.value)">
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?php echo $year['idannee_acad']; ?>" <?php echo ($selectedYearId == $year['idannee_acad']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($year['designation']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="specialisationFilter">Spécialisation:</label>
                                <select id="specialisationFilter" class="form-select form-select-sm" onchange="updateSpecialisationFilter(this.value)">
                                    <option value="">Toutes</option>
                                    <?php $selectedSpec = isset($_GET['specialisation']) ? $_GET['specialisation'] : ''; ?>
                                    <?php foreach ($specialisationsHierarchy as $sectionId => $section): ?>
                                        <optgroup label="<?php echo htmlspecialchars($section['nom']); ?>">
                                            <?php foreach ($section['orientations'] as $orientationId => $orientation): ?>
                                        <optgroup label="&nbsp;&nbsp;→ <?php echo htmlspecialchars($orientation['nom']); ?>">
                                            <?php foreach ($orientation['specialisations'] as $specId => $specName): ?>
                                                <option value="<?php echo $specId; ?>" <?php echo ($selectedSpec == $specId) ? 'selected' : ''; ?>>&nbsp;&nbsp;&nbsp;&nbsp;• <?php echo htmlspecialchars($specName); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-4">
                                <label for="cycleFilter">Cycle:</label>
                                <select id="cycleFilter" class="form-select form-select-sm" onchange="updateCycleFilter(this.value)">
                                    <option value="">Tous</option>
                                    <option value="Premier" <?php echo ($selectedCycle === 'Premier') ? 'selected' : ''; ?>>Premier</option>
                                    <option value="Deuxieme" <?php echo ($selectedCycle === 'Deuxieme') ? 'selected' : ''; ?>>Deuxième</option>
                                    <option value="Troisieme" <?php echo ($selectedCycle === 'Troisieme') ? 'selected' : ''; ?>>Troisième</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="searchFilter">Rechercher (nom/matricule):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="searchFilter" class="form-control" placeholder="Tapez pour rechercher..." autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display: none;" title="Effacer">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                <small class="text-muted" id="searchResultsInfo" style="font-size: 0.7rem;"></small>
                            </div>
                            <div class="col-md-2 col-2">
                                <button id="clearFilters" class="btn btn-secondary btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Reset</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Barre d'actions pour sélection multiple -->
            <div class="col-lg-12 mb-3" id="bulkActionsBar" style="display: none;">
                <div class="card bg-primary text-white">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-check2-square me-2"></i>
                                <span id="selectedCount">0</span> travaux sélectionnés
                            </div>
                            <div>
                                <button type="button" class="btn btn-light btn-sm me-2" onclick="openBulkProgramModal()">
                                    <i class="bi bi-calendar-plus me-1"></i> Programmer soutenances
                                </button>
                                <button type="button" class="btn btn-warning btn-sm me-2" onclick="openBulkAssignLecteurModal()">
                                    <i class="bi bi-person-plus me-1"></i> Assigner un lecteur
                                </button>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="clearSelection()">
                                    <i class="bi bi-x-circle me-1"></i> Annuler
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold" style="font-size: 0.9rem;">Travaux - Programmation des Soutenances</span>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleSelectionMode()" style="font-size: 0.75rem;">
                            <i class="bi bi-check2-square me-1"></i>Sélection
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover compact-table mb-0" id="travauxTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="selection-col" style="display: none; width: 30px;">
                                            <input type="checkbox" id="selectAll" class="form-check-input" onclick="toggleSelectAll(this)">
                                        </th>
                                        <th style="width: 80px;">Matricule</th>
                                        <th style="min-width: 120px;">Étudiant</th>
                                        <th>Titre</th>
                                        <th style="width: 100px;">Spéc.</th>
                                        <th style="width: 60px;">Cycle</th>
                                        <th style="width: 100px;">Directeur</th>
                                        <th style="width: 60px;">Dépôt</th>
                                        <th style="width: 50px;">Lect.</th>
                                        <th style="width: 100px;">Date Sout.</th>
                                        <th style="width: 80px;">Lieu</th>
                                        <th style="width: 70px;">Statut</th>
                                        <th style="width: 90px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($allTravaux)) {
                                        foreach ($allTravaux as $travail):
                                            $hasSoutenance = !empty($travail['idsoutenance']);
                                            $hasDepot = !empty($travail['idDepot']);
                                            $nbLecteurs = $travail['nb_lecteurs'] ?? 0;
                                    ?>
                                            <tr>
                                                <td class="selection-col" style="display: none;">
                                                    <input type="checkbox" class="form-check-input travail-checkbox"
                                                        value="<?php echo $travail['idsujets']; ?>"
                                                        data-etudiant="<?php echo htmlspecialchars($travail['etudiant_nom'], ENT_QUOTES); ?>"
                                                        data-soutenance="<?php echo $travail['idsoutenance'] ?? ''; ?>"
                                                        data-has-soutenance="<?php echo $hasSoutenance ? '1' : '0'; ?>"
                                                        data-nb-lecteurs="<?php echo $nbLecteurs; ?>"
                                                        onchange="updateSelection()">
                                                </td>
                                                <td><?php echo htmlspecialchars($travail['matricule'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($travail['etudiant_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($travail['sujet_titre']); ?></td>
                                                <td><?php echo htmlspecialchars($travail['specialisation'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($travail['cycle'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($travail['directeur_nom'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($hasDepot): ?>
                                                        <span class="badge bg-success" title="Déposé le <?php echo date('d/m/Y', strtotime($travail['dateDepot'])); ?>">
                                                            <i class="bi bi-check-circle me-1"></i>Déposé
                                                        </span>
                                                        <a href="<?php echo htmlspecialchars($travail['memoire_fichier']); ?>" class="btn btn-sm btn-outline-primary ms-1" target="_blank" download title="Télécharger">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-dash-circle me-1"></i>Non déposé
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hasSoutenance): ?>
                                                        <span class="badge bg-<?php echo $nbLecteurs == 0 ? 'danger' : ($nbLecteurs == 1 ? 'warning' : 'success'); ?>">
                                                            <?php echo $nbLecteurs; ?>/2
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hasSoutenance && $travail['date_soutenance']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($travail['date_soutenance'])); ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Non programmée</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $hasSoutenance ? htmlspecialchars($travail['lieu'] ?? '-') : '-'; ?></td>
                                                <td>
                                                    <?php if ($hasSoutenance): ?>
                                                        <span class="badge bg-<?php
                                                                                echo ($travail['statut'] == 'Programmée') ? 'success' : (($travail['statut'] == 'Terminée') ? 'primary' : 'secondary');
                                                                                ?>">
                                                            <?php echo htmlspecialchars($travail['statut'] ?? 'Non programmée'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pas de soutenance</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-nowrap">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if (!$hasSoutenance): ?>
                                                            <button class="btn btn-primary btn-sm" onclick="createAndAssignLecteurs(<?php echo $travail['idsujets']; ?>, '<?php echo htmlspecialchars($travail['etudiant_nom'], ENT_QUOTES); ?>')" title="Programmer soutenance">
                                                                <i class="bi bi-plus-circle"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <?php if ($nbLecteurs < 2): ?>
                                                                <button class="btn btn-warning btn-sm" onclick="openAssignModal(<?php echo $travail['idsoutenance']; ?>)" title="Assigner lecteurs (<?php echo $nbLecteurs; ?>/2)">
                                                                    <i class="bi bi-person-plus"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-success btn-sm" onclick="viewLecteurs(<?php echo $travail['idsoutenance']; ?>)" title="Voir lecteurs">
                                                                    <i class="bi bi-person-check"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-info btn-sm" onclick="openEditSoutenanceModal(<?php echo $travail['idsoutenance']; ?>)" title="Modifier soutenance">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                        endforeach;
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="13" class="text-center">
                                                <div class="py-4">
                                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                                    <h5 class="mt-3 text-muted">Aucun travail trouvé</h5>
                                                    <p class="text-muted">
                                                        <?php if ($selectedSpecialisation || $selectedCycle): ?>
                                                            Aucun travail ne correspond aux filtres sélectionnés.
                                                            <br>Essayez de modifier vos critères de recherche.
                                                        <?php elseif ($selectedYearId): ?>
                                                            Aucun travail enregistré pour l'année académique sélectionnée.
                                                        <?php else: ?>
                                                            Aucun travail disponible. Assurez-vous qu'une année académique est active.
                                                        <?php endif; ?>
                                                    </p>
                                                    <a href="?view=memoire/assign_lecteurs&debug=1" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-bug me-1"></i> Activer le mode debug
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <!-- Indicateurs d'infinite scroll -->
                            <div id="loadingIndicator" class="text-center my-3" style="display: none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="text-muted mt-2">Chargement d'autres travaux...</p>
                            </div>
                            <div id="noMoreData" class="text-center my-3 text-muted" style="display: none;">
                                <p><i class="bi bi-check-circle me-1"></i> Tous les travaux ont été chargés</p>
                            </div>
                            <div id="loadingSentinel" style="height: 20px; background-color: transparent;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal de programmation en lot -->
<div class="modal fade" id="bulkProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>Programmation Multiple de Soutenances
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkProgramForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Programmation en lot</strong> - Les soutenances seront créées pour <span id="bulkCount">0</span> travaux sélectionnés avec les mêmes informations.
                    </div>

                    <div id="selectedStudentsList" class="mb-3">
                        <label class="form-label"><i class="bi bi-people me-1"></i>Étudiants sélectionnés:</label>
                        <div id="studentsListContainer" class="border rounded p-2" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bulk_date_soutenance" class="form-label required">
                                    <i class="bi bi-calendar-event me-1"></i>Date & Heure de début
                                </label>
                                <input type="datetime-local" class="form-control" id="bulk_date_soutenance" name="date_soutenance" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bulk_duree_soutenance" class="form-label required">
                                    <i class="bi bi-clock me-1"></i>Durée par soutenance
                                </label>
                                <select class="form-select" id="bulk_duree_soutenance" name="duree_soutenance">
                                    <option value="15">15 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">1 heure</option>
                                    <option value="90">1h30</option>
                                    <option value="120">2 heures</option>
                                </select>
                                <small class="text-muted">Les heures seront échelonnées automatiquement</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bulk_lieu_soutenance" class="form-label required">
                                    <i class="bi bi-building me-1"></i>Lieu de Soutenance
                                </label>
                                <input type="text" class="form-control" id="bulk_lieu_soutenance" name="lieu_soutenance" placeholder="Exemple: Amphi A, Salle 101" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mb-3" id="schedulePreview" style="display: none;">
                        <i class="bi bi-calendar-range me-2"></i>
                        <strong>Aperçu des horaires:</strong>
                        <span id="schedulePreviewText"></span>
                    </div>

                    <hr>
                    <h6><i class="bi bi-person-badge me-2"></i>Lecteurs (optionnel)</h6>
                    <p class="text-muted small">Les lecteurs peuvent être assignés ultérieurement si vous ne les définissez pas maintenant.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_lecteur1" class="form-label">
                                    <i class="bi bi-person-badge me-1"></i>Premier Lecteur
                                </label>
                                <select class="form-select" id="bulk_lecteur1" name="lecteur1_id">
                                    <option value="">Sélectionner (optionnel)...</option>
                                    <?php foreach ($enseignants as $ens): ?>
                                        <option value="<?= $ens['idAgent'] ?>">
                                            <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_lecteur2" class="form-label">
                                    <i class="bi bi-person-badge me-1"></i>Deuxième Lecteur
                                </label>
                                <select class="form-select" id="bulk_lecteur2" name="lecteur2_id">
                                    <option value="">Sélectionner (optionnel)...</option>
                                    <?php foreach ($enseignants as $ens): ?>
                                        <option value="<?= $ens['idAgent'] ?>">
                                            <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6><i class="bi bi-people me-2"></i>Jury (optionnel)</h6>
                    <p class="text-muted small">Le jury sera assigné à toutes les soutenances créées.</p>

                    <div class="mb-3">
                        <label for="bulk_jury_id" class="form-label">
                            <i class="bi bi-people me-1"></i>Sélectionner un Jury
                        </label>
                        <select class="form-select" id="bulk_jury_id" name="jury_id">
                            <option value="">-- Aucun jury (optionnel) --</option>
                            <?php foreach ($jurysDisponibles as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?>
                                    <?php if ($jury['president_nom']): ?>
                                        (Président: <?= htmlspecialchars($jury['president_nom']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($jurysDisponibles)): ?>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun jury disponible. <a href="?view=recherche/gestion_jurys">Créer un jury</a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Programmer les soutenances
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'assignation d'un lecteur à plusieurs étudiants -->
<div class="modal fade" id="bulkAssignLecteurModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Assigner un Lecteur à Plusieurs Étudiants
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkAssignLecteurForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Assignation en lot</strong> - Le lecteur sélectionné sera assigné à <span id="bulkLecteurCount">0</span> étudiants.
                    </div>

                    <div id="selectedStudentsForLecteur" class="mb-3">
                        <label class="form-label"><i class="bi bi-people me-1"></i>Étudiants sélectionnés:</label>
                        <div id="studentsLecteurListContainer" class="border rounded p-2" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="bulk_single_lecteur" class="form-label required">
                                    <i class="bi bi-person-badge me-1"></i>Lecteur à assigner
                                </label>
                                <select class="form-select" id="bulk_single_lecteur" name="lecteur_id" required>
                                    <option value="">Sélectionner un enseignant...</option>
                                    <?php foreach ($enseignants as $ens): ?>
                                        <option value="<?= $ens['idAgent'] ?>">
                                            <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bulk_lecteur_position" class="form-label required">
                                    <i class="bi bi-sort-numeric-down me-1"></i>Position
                                </label>
                                <select class="form-select" id="bulk_lecteur_position" name="position" required>
                                    <option value="1">Premier Lecteur</option>
                                    <option value="2">Deuxième Lecteur</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Si un lecteur existe déjà à cette position, il sera remplacé.
                        Les soutenances seront créées automatiquement pour les travaux qui n'en ont pas encore.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Assigner le lecteur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'assignation/modification -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i><span id="modalTitle">Assigner des Lecteurs</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignForm">
                <div class="modal-body">
                    <input type="hidden" id="soutenance_id" name="soutenance_id">
                    <input type="hidden" id="is_new_soutenance" name="is_new_soutenance" value="0">

                    <div class="alert alert-warning">
                        <small><i class="bi bi-exclamation-triangle me-2"></i>Les deux lecteurs doivent être différents</small>
                    </div>

                    <div class="mb-3">
                        <label for="lecteur1" class="form-label required">
                            <i class="bi bi-person-badge me-1"></i>Premier Lecteur
                        </label>
                        <select class="form-select" id="lecteur1" name="lecteur1_id" required>
                            <option value="">Sélectionner le premier lecteur...</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="lecteur2" class="form-label required">
                            <i class="bi bi-person-badge me-1"></i>Deuxième Lecteur
                        </label>
                        <select class="form-select" id="lecteur2" name="lecteur2_id" required>
                            <option value="">Sélectionner le deuxième lecteur...</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="jury_id" class="form-label">
                            <i class="bi bi-people me-1"></i>Jury (optionnel)
                        </label>
                        <select class="form-select" id="jury_id" name="jury_id">
                            <option value="">-- Sélectionner un jury --</option>
                            <?php foreach ($jurysDisponibles as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?>
                                    <?php if ($jury['president_nom']): ?>
                                        (Président: <?= htmlspecialchars($jury['president_nom']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($jurysDisponibles)): ?>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun jury disponible. <a href="?view=recherche/gestion_jurys">Créer un jury</a>
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="date_soutenance" class="form-label required">
                            <i class="bi bi-calendar-event me-1"></i>Date de Soutenance
                        </label>
                        <input type="datetime-local" class="form-control" id="date_soutenance" name="date_soutenance" required>
                    </div>

                    <div class="mb-3">
                        <label for="lieu_soutenance" class="form-label required">
                            <i class="bi bi-building me-1"></i>Lieu de Soutenance
                        </label>
                        <input type="text" class="form-control" id="lieu_soutenance" name="lieu_soutenance" placeholder="Exemple: Amphi A, Salle 101" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de modification de soutenance -->
<div class="modal fade" id="editSoutenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Modifier la Programmation de Soutenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSoutenanceForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_soutenance_id" name="soutenance_id">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Modification de la programmation</strong> - Mettez à jour les informations de soutenance
                    </div>

                    <div class="mb-3">
                        <label for="edit_date_soutenance" class="form-label required">
                            <i class="bi bi-calendar-event me-1"></i>Date de Soutenance
                        </label>
                        <input type="datetime-local" class="form-control" id="edit_date_soutenance" name="date_soutenance" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_lieu_soutenance" class="form-label required">
                            <i class="bi bi-building me-1"></i>Lieu de Soutenance
                        </label>
                        <input type="text" class="form-control" id="edit_lieu_soutenance" name="lieu_soutenance" placeholder="Exemple: Amphi A, Salle 101" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_statut_soutenance" class="form-label">
                            <i class="bi bi-flag me-1"></i>Statut
                        </label>
                        <select class="form-select" id="edit_statut_soutenance" name="statut">
                            <option value="Non programmée">Non programmée</option>
                            <option value="Programmée">Programmée</option>
                            <option value="Réalisée">Réalisée</option>
                            <option value="Reportée">Reportée</option>
                        </select>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3">
                        <i class="bi bi-people me-2"></i>Lecteurs (Optionnel)
                    </h6>

                    <div class="mb-3">
                        <label for="edit_lecteur1" class="form-label">
                            <i class="bi bi-person-badge me-1"></i>Premier Lecteur
                        </label>
                        <select class="form-select select2" id="edit_lecteur1" name="lecteur1">
                            <option value="">-- Sélectionner un lecteur --</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_lecteur2" class="form-label">
                            <i class="bi bi-person-badge me-1"></i>Deuxième Lecteur
                        </label>
                        <select class="form-select select2" id="edit_lecteur2" name="lecteur2">
                            <option value="">-- Sélectionner un lecteur --</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir les lecteurs -->
<div class="modal fade" id="lecteursModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lecteurs Assignés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="lecteursContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
    // Filtres
    function updateYearFilter(value) {
        let url = new URL(window.location);
        url.searchParams.set('annee_acad', value);
        window.location.href = url.toString();
    }

    function updateSpecialisationFilter(value) {
        let url = new URL(window.location);
        if (value) {
            url.searchParams.set('specialisation', value);
        } else {
            url.searchParams.delete('specialisation');
        }
        window.location.href = url.toString();
    }

    function updateCycleFilter(value) {
        let url = new URL(window.location);
        if (value) {
            url.searchParams.set('cycle', value);
        } else {
            url.searchParams.delete('cycle');
        }
        window.location.href = url.toString();
    }

    // Effacer les filtres
    document.addEventListener('DOMContentLoaded', function() {
        const clearFiltersBtn = document.getElementById('clearFilters');
        const searchFilter = document.getElementById('searchFilter');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const searchResultsInfo = document.getElementById('searchResultsInfo');

        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (searchFilter) searchFilter.value = '';
                let url = new URL(window.location);
                url.searchParams.delete('specialisation');
                url.searchParams.delete('cycle');
                window.location.href = url.toString();
            });
        }

        // Recherche côté serveur via AJAX - cherche dans TOUTE la base de données
        if (searchFilter) {
            const table = document.getElementById('travauxTable');
            const tbody = table ? table.getElementsByTagName('tbody')[0] : null;
            let searchTimeout;
            let isSearching = false;
            let lastSearchTerm = '';

            // Fonction de recherche AJAX côté serveur
            function performServerSearch() {
                const searchValue = searchFilter.value.trim();
                
                // Afficher/masquer le bouton d'effacement
                if (clearSearchBtn) {
                    clearSearchBtn.style.display = searchValue ? 'block' : 'none';
                }

                // Si recherche vide, recharger la page sans paramètre search
                if (!searchValue) {
                    if (lastSearchTerm) {
                        // Retirer le paramètre search de l'URL et recharger
                        let url = new URL(window.location);
                        url.searchParams.delete('search');
                        window.location.href = url.toString();
                    }
                    if (searchResultsInfo) searchResultsInfo.textContent = '';
                    return;
                }

                // Éviter les recherches dupliquées
                if (searchValue === lastSearchTerm || isSearching) return;
                lastSearchTerm = searchValue;
                isSearching = true;

                // Afficher indicateur de chargement
                if (searchResultsInfo) {
                    searchResultsInfo.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Recherche...';
                    searchResultsInfo.style.color = '#6c757d';
                }

                // Construire les paramètres de requête
                const params = new URLSearchParams(window.location.search);
                params.set('search', searchValue);
                params.set('page', 1);
                params.set('limit', 100); // Limite plus élevée pour la recherche

                fetch('controller/ajax_get_travaux_memoire.php?' + params.toString())
                    .then(response => response.json())
                    .then(data => {
                        isSearching = false;
                        if (!data.success) {
                            throw new Error(data.message || 'Erreur de recherche');
                        }

                        // Mettre à jour le tableau avec les résultats
                        updateTableWithResults(data.travaux, searchValue);
                        
                        // Afficher le nombre de résultats
                        if (searchResultsInfo) {
                            const count = data.travaux.length;
                            searchResultsInfo.textContent = count + ' résultat' + (count !== 1 ? 's' : '') + ' trouvé' + (count !== 1 ? 's' : '');
                            searchResultsInfo.style.color = count > 0 ? '#198754' : '#dc3545';
                        }
                    })
                    .catch(error => {
                        isSearching = false;
                        console.error('Erreur recherche:', error);
                        if (searchResultsInfo) {
                            searchResultsInfo.textContent = 'Erreur de recherche';
                            searchResultsInfo.style.color = '#dc3545';
                        }
                    });
            }

            // Mettre à jour le tableau avec les résultats de recherche
            function updateTableWithResults(travaux, searchTerm) {
                if (!tbody) return;

                // Vider le tableau
                tbody.innerHTML = '';

                if (travaux.length === 0) {
                    tbody.innerHTML = '<tr class="no-results-message"><td colspan="13" class="text-center text-muted py-3"><i class="bi bi-search me-2"></i>Aucun résultat pour "<strong>' + htmlEscape(searchTerm) + '</strong>"</td></tr>';
                    return;
                }

                // Générer les lignes du tableau
                travaux.forEach(t => {
                    const hasSoutenance = !!t.idsoutenance;
                    const hasDepot = !!t.idDepot;
                    const nbLecteurs = parseInt(t.nb_lecteurs) || 0;

                    let actionsHtml = '';
                    if (!hasSoutenance) {
                        actionsHtml = `<button class="btn btn-primary btn-sm" onclick="createAndAssignLecteurs(${t.idsujets}, '${escapeJs(t.etudiant_nom)}')" title="Programmer soutenance"><i class="bi bi-plus-circle"></i></button>`;
                    } else {
                        if (nbLecteurs < 2) {
                            actionsHtml = `<button class="btn btn-warning btn-sm" onclick="openAssignModal(${t.idsoutenance})" title="Assigner lecteurs (${nbLecteurs}/2)"><i class="bi bi-person-plus"></i></button>`;
                        } else {
                            actionsHtml = `<button class="btn btn-success btn-sm" onclick="viewLecteurs(${t.idsoutenance})" title="Voir lecteurs"><i class="bi bi-person-check"></i></button>`;
                        }
                        actionsHtml += `<button class="btn btn-info btn-sm" onclick="openEditSoutenanceModal(${t.idsoutenance})" title="Modifier soutenance"><i class="bi bi-pencil"></i></button>`;
                    }

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="selection-col" style="display: none;"><input type="checkbox" class="form-check-input travail-checkbox" value="${t.idsujets}" data-etudiant="${htmlEscape(t.etudiant_nom)}" data-soutenance="${t.idsoutenance || ''}" data-has-soutenance="${hasSoutenance ? '1' : '0'}" data-nb-lecteurs="${nbLecteurs}" onchange="updateSelection()"></td>
                        <td>${htmlEscape(t.matricule || '-')}</td>
                        <td>${htmlEscape(t.etudiant_nom)}</td>
                        <td>${htmlEscape(t.sujet_titre)}</td>
                        <td>${htmlEscape(t.specialisation || '-')}</td>
                        <td>${htmlEscape(t.cycle || '-')}</td>
                        <td>${htmlEscape(t.directeur_nom || '-')}</td>
                        <td>${hasDepot ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Déposé</span>' : '<span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Non</span>'}</td>
                        <td>${hasSoutenance ? '<span class="badge bg-' + (nbLecteurs === 0 ? 'danger' : (nbLecteurs === 1 ? 'warning' : 'success')) + '">' + nbLecteurs + '/2</span>' : '<span class="badge bg-secondary">-</span>'}</td>
                        <td>${hasSoutenance && t.date_soutenance ? formatDate(t.date_soutenance) : '<span class="badge bg-danger">Non programmée</span>'}</td>
                        <td>${hasSoutenance ? htmlEscape(t.lieu || '-') : '-'}</td>
                        <td>${hasSoutenance ? '<span class="badge bg-' + (t.statut === 'Programmée' ? 'success' : 'secondary') + '">' + htmlEscape(t.statut || 'Non programmée') + '</span>' : '<span class="badge bg-secondary">Pas de sout.</span>'}</td>
                        <td class="text-nowrap"><div class="btn-group btn-group-sm" role="group">${actionsHtml}</div></td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Debounce pour la recherche serveur (500ms car plus lent)
            searchFilter.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performServerSearch, 500);
            });
            
            // Recherche immédiate sur Enter
            searchFilter.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    performServerSearch();
                }
            });

            // Gestion du bouton d'effacement
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchFilter.value = '';
                    lastSearchTerm = '';
                    searchFilter.focus();
                    // Recharger la page sans search
                    let url = new URL(window.location);
                    url.searchParams.delete('search');
                    window.location.href = url.toString();
                });
            }
        }

        // Fonctions utilitaires
        function htmlEscape(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        function escapeJs(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }
    });

    // Créer soutenance et assigner lecteurs
    function createAndAssignLecteurs(sujetId, studentName) {
        Swal.fire({
            title: 'Créer une soutenance',
            html: `<p>Vous allez créer une soutenance pour <strong>${studentName}</strong></p>
                   <p>Vous pourrez ensuite assigner les lecteurs et programmer la date.</p>`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Continuer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('controller/get_or_create_soutenance.php?sujet_id=' + sujetId, {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.soutenance_id) {
                            document.getElementById('is_new_soutenance').value = '1';
                            openAssignModal(data.soutenance_id);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Erreur lors de la création de la soutenance'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de communiquer avec le serveur'
                        });
                    });
            }
        });
    }

    // Modal Assignation Lecteurs
    function openAssignModal(soutenanceId) {
        document.getElementById('soutenance_id').value = soutenanceId;
        document.getElementById('lecteur1').value = '';
        document.getElementById('lecteur2').value = '';
        document.getElementById('jury_id').value = '';
        document.getElementById('date_soutenance').value = '';
        document.getElementById('lieu_soutenance').value = '';

        // Réinitialiser Select2 si initialisé
        if ($('#jury_id').data('select2')) {
            $('#jury_id').val('').trigger('change');
        }

        const modal = new bootstrap.Modal(document.getElementById('assignModal'));
        modal.show();
    }

    // Modal Modification Soutenance
    function openEditSoutenanceModal(soutenanceId) {
        // Réinitialiser les lecteurs
        document.getElementById('edit_lecteur1').value = '';
        document.getElementById('edit_lecteur2').value = '';

        // Charger les données de la soutenance et les lecteurs
        Promise.all([
                fetch('controller/get_soutenance_details.php?id=' + soutenanceId).then(r => r.json()),
                fetch('controller/get_soutenance_lecteurs.php?id=' + soutenanceId).then(r => r.json())
            ])
            .then(([soutenanceData, lecteursData]) => {
                if (!soutenanceData.success || !soutenanceData.soutenance) {
                    throw new Error(soutenanceData.message || 'Impossible de charger les données de la soutenance.');
                }

                const soutenance = soutenanceData.soutenance;
                document.getElementById('edit_soutenance_id').value = soutenanceId;

                if (soutenance.date_soutenance) {
                    const date = new Date(soutenance.date_soutenance);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    document.getElementById('edit_date_soutenance').value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }

                document.getElementById('edit_lieu_soutenance').value = soutenance.lieu || '';
                document.getElementById('edit_statut_soutenance').value = soutenance.statut || 'Non programmée';

                // Charger les lecteurs existants
                if (lecteursData.success && lecteursData.lecteurs && lecteursData.lecteurs.length > 0) {
                    lecteursData.lecteurs.forEach(lecteur => {
                        if (lecteur.est_premier_lecteur) {
                            document.getElementById('edit_lecteur1').value = lecteur.idAgent;
                        } else {
                            document.getElementById('edit_lecteur2').value = lecteur.idAgent;
                        }
                    });
                }

                // Mettre à jour Select2 s'il est déjà initialisé
                $('#edit_lecteur1').val(document.getElementById('edit_lecteur1').value).trigger('change');
                $('#edit_lecteur2').val(document.getElementById('edit_lecteur2').value).trigger('change');

                const modal = new bootstrap.Modal(document.getElementById('editSoutenanceModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de connexion',
                    text: error.message || 'Impossible de communiquer avec le serveur.'
                });
            });
    }

    // Voir les lecteurs
    function viewLecteurs(idSoutenance) {
        const modalContent = document.getElementById('lecteursContent');
        modalContent.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des lecteurs...</p>
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('lecteursModal'));
        modal.show();

        fetch('controller/get_soutenance_lecteurs.php?id=' + idSoutenance)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.lecteurs.length > 0) {
                    let html = '';
                    data.lecteurs.forEach(lecteur => {
                        html += `
                            <div class="card mb-2">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="bi bi-person-badge me-2"></i>
                                                ${lecteur.noms}
                                            </h6>
                                            <small class="text-muted">
                                                ${lecteur.est_premier_lecteur ? 'Premier Lecteur' : 'Deuxième Lecteur'}
                                            </small>
                                        </div>
                                        <span class="badge bg-info">${lecteur.grade || ''}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    modalContent.innerHTML = html;
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Aucun lecteur assigné à cette soutenance.
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur de connexion au serveur
                    </div>
                `;
            });
    }

    // Form Assignation Lecteurs
    document.addEventListener('DOMContentLoaded', function() {
        const assignForm = document.getElementById('assignForm');

        if (assignForm) {
            assignForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const lecteur1 = document.getElementById('lecteur1').value;
                const lecteur2 = document.getElementById('lecteur2').value;

                if (!lecteur1 || !lecteur2) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner les deux lecteurs.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                if (lecteur1 === lecteur2) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Les deux lecteurs doivent être différents.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                const formData = new FormData(this);

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Attribution des lecteurs en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('controller/assign_lecteurs_soutenance.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès!',
                                text: data.message || 'Les lecteurs ont été assignés avec succès.',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de l\'assignation.',
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de communiquer avec le serveur.',
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }

        // Form Modification Soutenance
        const editForm = document.getElementById('editSoutenanceForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const dateSoutenance = document.getElementById('edit_date_soutenance').value;
                const lieuSoutenance = document.getElementById('edit_lieu_soutenance').value;

                if (!dateSoutenance || !lieuSoutenance) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez remplir tous les champs requis.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                const formData = new FormData(this);

                // Ajouter les lecteurs s'ils sont sélectionnés
                const lecteur1 = document.getElementById('edit_lecteur1').value;
                const lecteur2 = document.getElementById('edit_lecteur2').value;

                if (lecteur1) {
                    formData.append('lecteur1', lecteur1);
                }
                if (lecteur2) {
                    formData.append('lecteur2', lecteur2);
                }

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Mise à jour de la soutenance en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('controller/update_soutenance.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers.get('content-type'));
                        return response.text().then(text => {
                            console.log('Raw response:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                throw new Error('Response is not valid JSON: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Soutenance mise à jour!',
                                text: data.message || 'Les données de soutenance ont été mises à jour avec succès.',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de la mise à jour.',
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de communiquer avec le serveur.',
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }
    });

    // =====================================================
    // GESTION DE LA SÉLECTION MULTIPLE
    // =====================================================

    let selectionMode = false;

    function toggleSelectionMode() {
        selectionMode = !selectionMode;
        const selectionCols = document.querySelectorAll('.selection-col');
        const bulkBar = document.getElementById('bulkActionsBar');

        selectionCols.forEach(col => {
            col.style.display = selectionMode ? '' : 'none';
        });

        if (!selectionMode) {
            clearSelection();
            bulkBar.style.display = 'none';
        }
    }

    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.travail-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateSelection();
    }

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.travail-checkbox:checked');
        const count = checkboxes.length;
        const bulkBar = document.getElementById('bulkActionsBar');

        document.getElementById('selectedCount').textContent = count;
        bulkBar.style.display = count > 0 ? '' : 'none';

        // Mettre à jour le checkbox "selectAll"
        const allCheckboxes = document.querySelectorAll('.travail-checkbox');
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
        }
    }

    function clearSelection() {
        const checkboxes = document.querySelectorAll('.travail-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false;
        });
        document.getElementById('selectedCount').textContent = '0';
        document.getElementById('bulkActionsBar').style.display = 'none';

        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    }

    function openBulkProgramModal() {
        const checkboxes = document.querySelectorAll('.travail-checkbox:checked');
        const count = checkboxes.length;

        if (count === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Aucune sélection',
                text: 'Veuillez sélectionner au moins un travail à programmer.'
            });
            return;
        }

        // Mettre à jour le compteur dans le modal
        document.getElementById('bulkCount').textContent = count;

        // Afficher la liste des étudiants
        let studentsList = '';
        checkboxes.forEach(cb => {
            const etudiant = cb.getAttribute('data-etudiant');
            studentsList += `<span class="badge bg-secondary me-1 mb-1">${etudiant}</span>`;
        });
        document.getElementById('studentsListContainer').innerHTML = studentsList;

        // Réinitialiser le formulaire
        document.getElementById('bulk_date_soutenance').value = '';
        document.getElementById('bulk_lieu_soutenance').value = '';
        document.getElementById('bulk_duree_soutenance').value = '30';
        document.getElementById('bulk_lecteur1').value = '';
        document.getElementById('bulk_lecteur2').value = '';
        document.getElementById('schedulePreview').style.display = 'none';

        const modal = new bootstrap.Modal(document.getElementById('bulkProgramModal'));
        modal.show();
    }

    // Aperçu des horaires échelonnés
    function updateSchedulePreview() {
        const dateInput = document.getElementById('bulk_date_soutenance').value;
        const duree = parseInt(document.getElementById('bulk_duree_soutenance').value);
        const count = document.querySelectorAll('.travail-checkbox:checked').length;
        const previewDiv = document.getElementById('schedulePreview');
        const previewText = document.getElementById('schedulePreviewText');

        if (!dateInput || count === 0) {
            previewDiv.style.display = 'none';
            return;
        }

        const startDate = new Date(dateInput);
        const endDate = new Date(startDate.getTime() + (count - 1) * duree * 60000);

        const formatTime = (date) => {
            return date.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        const formatDate = (date) => {
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        };

        previewText.innerHTML = `De <strong>${formatTime(startDate)}</strong> à <strong>${formatTime(endDate)}</strong> 
            (${count} soutenances × ${duree} min) le ${formatDate(startDate)}`;
        previewDiv.style.display = '';
    }

    // Ajouter les écouteurs pour l'aperçu
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('bulk_date_soutenance');
        const dureeInput = document.getElementById('bulk_duree_soutenance');

        if (dateInput) dateInput.addEventListener('change', updateSchedulePreview);
        if (dureeInput) dureeInput.addEventListener('change', updateSchedulePreview);
    });

    // Gestion du formulaire de programmation en lot
    document.addEventListener('DOMContentLoaded', function() {
        const bulkForm = document.getElementById('bulkProgramForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const checkboxes = document.querySelectorAll('.travail-checkbox:checked');
                const sujetIds = Array.from(checkboxes).map(cb => cb.value);

                if (sujetIds.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Aucun travail sélectionné.'
                    });
                    return;
                }

                const dateSoutenance = document.getElementById('bulk_date_soutenance').value;
                const lieuSoutenance = document.getElementById('bulk_lieu_soutenance').value;
                const dureeSoutenance = document.getElementById('bulk_duree_soutenance').value;
                const lecteur1 = document.getElementById('bulk_lecteur1').value;
                const lecteur2 = document.getElementById('bulk_lecteur2').value;

                if (!dateSoutenance || !lieuSoutenance) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez remplir la date et le lieu de soutenance.'
                    });
                    return;
                }

                // Vérifier que les lecteurs sont différents si tous deux sélectionnés
                if (lecteur1 && lecteur2 && lecteur1 === lecteur2) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Les deux lecteurs doivent être différents.'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Programmation en cours...',
                    text: `Création de ${sujetIds.length} soutenances`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('sujet_ids', JSON.stringify(sujetIds));
                formData.append('date_soutenance', dateSoutenance);
                formData.append('duree_soutenance', dureeSoutenance);
                formData.append('lieu_soutenance', lieuSoutenance);
                if (lecteur1) formData.append('lecteur1_id', lecteur1);
                if (lecteur2) formData.append('lecteur2_id', lecteur2);

                // Ajouter le jury si sélectionné
                const juryId = document.getElementById('bulk_jury_id').value;
                if (juryId) formData.append('jury_id', juryId);

                fetch('controller/bulk_create_soutenances.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès!',
                                html: data.message || `${data.created || sujetIds.length} soutenances créées avec succès.`,
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de la programmation.',
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de communiquer avec le serveur.',
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }
    });

    // =====================================================
    // ASSIGNATION D'UN LECTEUR À PLUSIEURS ÉTUDIANTS
    // =====================================================

    function openBulkAssignLecteurModal() {
        const checkboxes = document.querySelectorAll('.travail-checkbox:checked');
        const count = checkboxes.length;

        if (count === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Aucune sélection',
                text: 'Veuillez sélectionner au moins un travail.'
            });
            return;
        }

        // Mettre à jour le compteur dans le modal
        document.getElementById('bulkLecteurCount').textContent = count;

        // Afficher la liste des étudiants avec leur statut
        let studentsList = '';
        checkboxes.forEach(cb => {
            const etudiant = cb.getAttribute('data-etudiant');
            const hasSoutenance = cb.getAttribute('data-has-soutenance') === '1';
            const nbLecteurs = parseInt(cb.getAttribute('data-nb-lecteurs') || '0');

            let badgeClass = 'bg-secondary';
            let badgeText = 'Nouvelle';
            if (hasSoutenance) {
                badgeClass = nbLecteurs >= 2 ? 'bg-success' : 'bg-warning';
                badgeText = `${nbLecteurs}/2 lecteurs`;
            }

            studentsList += `<div class="d-inline-block me-2 mb-1">
                <span class="badge bg-dark">${etudiant}</span>
                <span class="badge ${badgeClass}">${badgeText}</span>
            </div>`;
        });
        document.getElementById('studentsLecteurListContainer').innerHTML = studentsList;

        // Réinitialiser le formulaire
        document.getElementById('bulk_single_lecteur').value = '';
        document.getElementById('bulk_lecteur_position').value = '1';

        const modal = new bootstrap.Modal(document.getElementById('bulkAssignLecteurModal'));
        modal.show();
    }

    // Gestion du formulaire d'assignation de lecteur en lot
    document.addEventListener('DOMContentLoaded', function() {
        const bulkLecteurForm = document.getElementById('bulkAssignLecteurForm');
        if (bulkLecteurForm) {
            bulkLecteurForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const checkboxes = document.querySelectorAll('.travail-checkbox:checked');
                const sujetIds = Array.from(checkboxes).map(cb => cb.value);

                if (sujetIds.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Aucun travail sélectionné.'
                    });
                    return;
                }

                const lecteurId = document.getElementById('bulk_single_lecteur').value;
                const position = document.getElementById('bulk_lecteur_position').value;

                if (!lecteurId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un lecteur.'
                    });
                    return;
                }

                const positionText = position === '1' ? 'premier lecteur' : 'deuxième lecteur';

                Swal.fire({
                    title: 'Confirmation',
                    html: `Vous êtes sur le point d'assigner ce lecteur comme <strong>${positionText}</strong> à <strong>${sujetIds.length}</strong> étudiants.<br><br>Continuer ?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, assigner',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Assignation en cours...',
                            text: `Assignation à ${sujetIds.length} étudiants`,
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        const formData = new FormData();
                        formData.append('sujet_ids', JSON.stringify(sujetIds));
                        formData.append('lecteur_id', lecteurId);
                        formData.append('position', position);

                        fetch('controller/bulk_assign_lecteur.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Succès!',
                                        html: data.message || 'Lecteur assigné avec succès.',
                                        confirmButtonColor: '#4CAF50'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Erreur',
                                        text: data.message || 'Une erreur est survenue.',
                                        confirmButtonColor: '#d33'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Erreur:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur de connexion',
                                    text: 'Impossible de communiquer avec le serveur.',
                                    confirmButtonColor: '#d33'
                                });
                            });
                    }
                });
            });
        }
    });
</script>

<!-- Script Infinite Scroll avec IntersectionObserver -->
<script>
    (function() {
        'use strict';

        let currentPage = 1;
        const limit = 50;
        let isLoading = false;
        let hasMore = <?= $initialCount >= 50 ? 'true' : 'false' ?>;
        let rowIndex = <?= $initialCount + 1 ?>;

        function initInfiniteScroll() {
            const sentinel = document.getElementById('loadingSentinel');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const noMoreData = document.getElementById('noMoreData');
            const tableBody = document.querySelector('#travauxTable tbody');

            if (!sentinel || !tableBody) {
                console.error('Infinite scroll: éléments requis non trouvés');
                return;
            }

            console.log('Infinite scroll initialisé pour les travaux de mémoire');

            function safeHtml(str) {
                if (str === null || str === undefined) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            function escapeJs(str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
            }

            function formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${day}/${month}/${year} ${hours}:${minutes}`;
            }

            function loadMoreTravaux() {
                if (isLoading || !hasMore) {
                    return;
                }

                isLoading = true;
                loadingIndicator.style.display = 'block';

                const params = new URLSearchParams(window.location.search);
                params.set('page', currentPage + 1);
                params.set('limit', limit);

                const fetchUrl = 'controller/ajax_get_travaux_memoire.php?' + params.toString();

                fetch(fetchUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Erreur inconnue');
                        }

                        const travaux = data.travaux || [];

                        if (travaux.length > 0) {
                            travaux.forEach(travail => {
                                const tr = document.createElement('tr');
                                const hasSoutenance = !!travail.idsoutenance;
                                const hasDepot = !!travail.idDepot;
                                const nbLecteurs = parseInt(travail.nb_lecteurs) || 0;

                                let depotHtml = hasDepot ?
                                    `<span class="badge bg-success" title="Déposé le ${formatDate(travail.dateDepot)}">
                                       <i class="bi bi-check-circle me-1"></i>Déposé
                                   </span>
                                   <a href="${safeHtml(travail.memoire_fichier)}" class="btn btn-sm btn-outline-primary ms-1" target="_blank" download title="Télécharger">
                                       <i class="bi bi-download"></i>
                                   </a>` :
                                    `<span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Non déposé</span>`;

                                let lecteursHtml = hasSoutenance ?
                                    `<span class="badge bg-${nbLecteurs == 0 ? 'danger' : (nbLecteurs == 1 ? 'warning' : 'success')}">${nbLecteurs}/2</span>` :
                                    `<span class="badge bg-secondary">-</span>`;

                                let dateSoutenanceHtml = (hasSoutenance && travail.date_soutenance) ?
                                    formatDate(travail.date_soutenance) :
                                    `<span class="badge bg-danger">Non programmée</span>`;

                                let statutHtml = hasSoutenance ?
                                    `<span class="badge bg-${travail.statut == 'Programmée' ? 'success' : (travail.statut == 'Terminée' ? 'primary' : 'secondary')}">${safeHtml(travail.statut || 'Non programmée')}</span>` :
                                    `<span class="badge bg-secondary">Pas de soutenance</span>`;

                                let actionsHtml = '';
                                if (!hasSoutenance) {
                                    actionsHtml = `<button class="btn btn-primary mb-1" onclick="createAndAssignLecteurs(${travail.idsujets}, '${escapeJs(travail.etudiant_nom)}')">
                                    <i class="bi bi-plus-circle me-1"></i> Programmer
                                </button>`;
                                } else {
                                    if (nbLecteurs < 2) {
                                        actionsHtml += `<button class="btn btn-warning mb-1" onclick="openAssignModal(${travail.idsoutenance})">
                                        <i class="bi bi-person-plus me-1"></i> Lecteurs (${nbLecteurs}/2)
                                    </button>`;
                                    } else {
                                        actionsHtml += `<button class="btn btn-success mb-1" onclick="viewLecteurs(${travail.idsoutenance})">
                                        <i class="bi bi-person-check me-1"></i> Lecteurs OK
                                    </button>`;
                                    }
                                    actionsHtml += `<button class="btn btn-info" onclick="openEditSoutenanceModal(${travail.idsoutenance})">
                                    <i class="bi bi-pencil me-1"></i> Modifier
                                </button>`;
                                }

                                tr.innerHTML = `
                                <td class="selection-col" style="display: none;">
                                    <input type="checkbox" class="form-check-input travail-checkbox" 
                                           value="${travail.idsujets}" 
                                           data-etudiant="${safeHtml(travail.etudiant_nom)}"
                                           data-soutenance="${travail.idsoutenance || ''}"
                                           data-has-soutenance="${hasSoutenance ? '1' : '0'}"
                                           data-nb-lecteurs="${nbLecteurs}"
                                           onchange="updateSelection()">
                                </td>
                                <td>${safeHtml(travail.matricule || '-')}</td>
                                <td>${safeHtml(travail.etudiant_nom)}</td>
                                <td>${safeHtml(travail.sujet_titre)}</td>
                                <td>${safeHtml(travail.specialisation || '-')}</td>
                                <td>${safeHtml(travail.cycle || '-')}</td>
                                <td>${safeHtml(travail.directeur_nom || '-')}</td>
                                <td>${depotHtml}</td>
                                <td>${lecteursHtml}</td>
                                <td>${dateSoutenanceHtml}</td>
                                <td>${hasSoutenance ? safeHtml(travail.lieu || '-') : '-'}</td>
                                <td>${statutHtml}</td>
                                <td><div class="btn-group-vertical btn-group-sm" role="group">${actionsHtml}</div></td>
                            `;

                                tableBody.appendChild(tr);
                                rowIndex++;
                            });

                            currentPage++;

                            if (travaux.length < limit) {
                                hasMore = false;
                                noMoreData.style.display = 'block';
                            }
                        } else {
                            hasMore = false;
                            noMoreData.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur infinite scroll:', error);
                    })
                    .finally(() => {
                        isLoading = false;
                        loadingIndicator.style.display = 'none';
                    });
            }

            const observerOptions = {
                root: null,
                rootMargin: '100px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMoreTravaux();
                }
            }, observerOptions);

            observer.observe(sentinel);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initInfiniteScroll);
        } else {
            initInfiniteScroll();
        }
    })();
</script>

<?php include "./views/include/footer_file.php"; ?>