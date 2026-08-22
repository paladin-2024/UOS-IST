<?php
// Définir la base URL pour les appels AJAX
//$baseUrl = '/e_gestion';
include "./views/include/header.php";

$soutenance = new Soutenance();

// Vérification des droits d'accès
$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Récupérer les responsabilités de l'utilisateur (seulement si pas admin)
$userResponsibilities = [];
if (!$hasFullAccess) {
    try {
        // Récupérer les sections gérées par l'utilisateur
        $connexion = Connexion::getInstance()->getPDO();
        $query = "SELECT DISTINCT section_idsection FROM responsable_section 
                  WHERE \"idUser\" = :userId";
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

// Récupérer l'année académique active
$connexion = Connexion::getInstance()->getPDO();
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
    $query = "SELECT s.\"idSpecialisation\", s.designation as spec_designation, 
                     o.idorientation, o.\"designationOrientation\" as orientation_designation,
                     sec.idsection, sec.\"designationSection\" as section_designation,
                     ur.\"designation_UR\" as unite_recherche
              FROM specialisation s
              LEFT JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              LEFT JOIN orientation o ON s.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              ORDER BY sec.\"designationSection\", o.\"designationOrientation\", s.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les spécialisations de ses sections
    if (!empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query = "SELECT s.\"idSpecialisation\", s.designation as spec_designation, 
                         o.idorientation, o.\"designationOrientation\" as orientation_designation,
                         sec.idsection, sec.\"designationSection\" as section_designation,
                         ur.\"designation_UR\" as unite_recherche
                  FROM specialisation s
                  LEFT JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                  LEFT JOIN orientation o ON s.idorientation = o.idorientation
                  LEFT JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE sec.idsection IN ($placeholders)
                  ORDER BY sec.\"designationSection\", o.\"designationOrientation\", s.designation";
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

// Récupérer les soutenances programmées
$soutenances = [];
try {
    $query = "SELECT s.*, s.note_finale,
                      sj.intitule as sujet_titre, sj.idsujets, sj.cycle,
                      e.noms as etudiant_nom, e.matricule,
                      d.noms as directeur_nom,
                      sp.designation as specialisation, sp.\"idSpecialisation\",
                      dm.\"idDepot\", dm.fichier as memoire_fichier, dm.\"dateDepot\"
               FROM soutenance s
               JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
               JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
               LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
               LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
               LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
               WHERE s.annee_acad_idannee_acad = ?";

    $executeParams = [$selectedYearId];

    // Filtrer par spécialisation si sélectionnée
    if ($selectedSpecialisation) {
        $query .= " AND sp.idSpecialisation = ?";
        $executeParams[] = $selectedSpecialisation;
    }

    // Filtrer par cycle si sélectionné
    if ($selectedCycle) {
        $query .= " AND sj.cycle = ?";
        $executeParams[] = $selectedCycle;
    }

    // Filtrer par sections de l'utilisateur si pas admin
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query .= " AND sp.section_idsection IN ($placeholders)";
        $executeParams = array_merge($executeParams, $userResponsibilities);
    }

    $query .= " ORDER BY s.date_soutenance DESC";

    $stmt = $connexion->prepare($query);
    $stmt->execute($executeParams);
    $soutenances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des soutenances: " . $e->getMessage());
    $soutenances = [];
}

// Récupérer les lecteurs (enseignants)
$enseignants = [];
try {
    if ($hasFullAccess) {
        // Admin - tous les enseignants
        if ($selectedSpecialisation) {
            // Filtrer par spécialisation
            $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                       FROM agent a
                       LEFT JOIN grade g ON a.grade_id = g.idgrade
                       INNER JOIN sujets sj ON sj.\"idDirecteur\" = a.\"idAgent\" OR sj.idEncadrant = a.\"idAgent\"
                       WHERE a.type_agent = 'Enseignant' AND sj.\"idSpecialisation\" = :specialisationId
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
            $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
            if ($selectedSpecialisation) {
                $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                           FROM agent a
                           LEFT JOIN grade g ON a.grade_id = g.idgrade
                           LEFT JOIN agent_section ag_s ON ag_s.\"idAgent\" = a.\"idAgent\"
                           INNER JOIN sujets sj ON sj.\"idDirecteur\" = a.\"idAgent\" OR sj.idEncadrant = a.\"idAgent\"
                           WHERE a.type_agent = 'Enseignant' AND ag_s.idsection IN ($placeholders) AND sj.\"idSpecialisation\" = :specialisationId
                           ORDER BY a.noms";
                $stmt = $connexion->prepare($query);
                $executeParams = array_merge($userResponsibilities, ['specialisationId' => $selectedSpecialisation]);
                $stmt->execute($executeParams);
                $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
                           FROM agent a
                           LEFT JOIN grade g ON a.grade_id = g.idgrade
                           LEFT JOIN agent_section ag_s ON ag_s.\"idAgent\" = a.\"idAgent\"
                           WHERE a.type_agent = 'Enseignant' AND ag_s.idsection IN ($placeholders)
                           ORDER BY a.noms";
                $stmt = $connexion->prepare($query);
                $stmt->execute($userResponsibilities);
                $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    $enseignants = [];
}

// Récupérer les jurys pour les filtres d'export
$jurysExport = [];
try {
    $queryJurys = "SELECT DISTINCT j.idjury, j.designation 
                   FROM jury j 
                   INNER JOIN soutenance s ON s.jury_id = j.idjury
                   WHERE s.annee_acad_idannee_acad = ?
                   ORDER BY j.designation";
    $stmtJurys = $connexion->prepare($queryJurys);
    $stmtJurys->execute([$selectedYearId]);
    $jurysExport = $stmtJurys->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération jurys export: " . $e->getMessage());
    $jurysExport = [];
}

// Récupérer les promotions pour les filtres d'export
$promotionsExport = [];
try {
    $queryPromos = "SELECT DISTINCT p.idpromotion, p.\"designationPromotion\" as designation 
                    FROM promotion p
                    INNER JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion
                    INNER JOIN sujets sj ON sj.etudiant_idetudiant = e.idetudiant
                    INNER JOIN soutenance s ON s.sujets_idsujets = sj.idsujets
                    WHERE s.annee_acad_idannee_acad = ?
                    ORDER BY p.\"designationPromotion\"";
    $stmtPromos = $connexion->prepare($queryPromos);
    $stmtPromos->execute([$selectedYearId]);
    $promotionsExport = $stmtPromos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération promotions export: " . $e->getMessage());
    $promotionsExport = [];
}

// Récupérer les cycles distincts pour les filtres d'export
$cyclesExport = [];
try {
    $queryCycles = "SELECT DISTINCT sj.cycle 
                    FROM sujets sj
                    INNER JOIN soutenance s ON s.sujets_idsujets = sj.idsujets
                    WHERE s.annee_acad_idannee_acad = ? AND sj.cycle IS NOT NULL AND sj.cycle != ''
                    ORDER BY sj.cycle";
    $stmtCycles = $connexion->prepare($queryCycles);
    $stmtCycles->execute([$selectedYearId]);
    $cyclesExport = $stmtCycles->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Erreur récupération cycles export: " . $e->getMessage());
    $cyclesExport = [];
}

// Récupérer tous les dépôts de mémoires
$depots = [];
try {
    $query = "SELECT dm.*, 
                       sj.intitule as sujet_titre, sj.idsujets, sj.cycle,
                       e.noms as etudiant_nom, e.matricule, e.idetudiant,
                       d.noms as directeur_nom,
                       sp.designation as specialisation, sp.\"idSpecialisation\",
                       s.idsoutenance, s.statut as soutenance_statut,
                       s.date_soutenance, s.lieu as lieu_soutenance,
                       j.idjury, j.designation as jury_designation
                FROM depot_memoire dm
                JOIN sujets sj ON dm.sujets_idsujets = sj.idsujets
                JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
                LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
                LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
                LEFT JOIN jury j ON s.jury_id = j.idjury
                WHERE (s.annee_acad_idannee_acad = ? OR s.idsoutenance IS NULL)";

    $executeParams = [$selectedYearId];

    // Filtrer par spécialisation si sélectionnée
    if ($selectedSpecialisation) {
        $query .= " AND sp.idSpecialisation = ?";
        $executeParams[] = $selectedSpecialisation;
    }

    // Filtrer par cycle si sélectionné
    if ($selectedCycle) {
        $query .= " AND sj.cycle = ?";
        $executeParams[] = $selectedCycle;
    }

    // Filtrer par sections de l'utilisateur si pas admin
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query .= " AND sp.section_idsection IN ($placeholders)";
        $executeParams = array_merge($executeParams, $userResponsibilities);
    }

    $query .= " ORDER BY dm.\"dateDepot\" DESC LIMIT 50";

    $stmt = $connexion->prepare($query);
    $stmt->execute($executeParams);
    $depots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $initialDepotsCount = count($depots);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des dépôts: " . $e->getMessage());
    $depots = [];
    $initialDepotsCount = 0;
}

// Récupérer tous les jurys disponibles pour l'année académique
$jurysDisponibles = [];
try {
    $query = "SELECT j.idjury, j.designation, j.date_creation, a1.noms as president_nom, a2.noms as secretaire_nom
              FROM jury j
              LEFT JOIN agent a1 ON j.id_president = a1.\"idAgent\"
              LEFT JOIN agent a2 ON j.id_secretaire = a2.\"idAgent\"
              WHERE j.annee_acad_id = :yearId AND j.est_actif = 1
              ORDER BY j.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute(['yearId' => $selectedYearId]);
    $jurysDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des jurys: " . $e->getMessage());
    $jurysDisponibles = [];
}

// Récupérer TOUS les travaux (sujets) - avec ou sans dépôt
$allTravaux = [];
try {
    $query = "SELECT DISTINCT sj.idsujets, sj.intitule as sujet_titre, sj.cycle, sj.\"idSpecialisation\",
                     e.noms as etudiant_nom, e.matricule, e.idetudiant,
                     d.noms as directeur_nom,
                     sp.designation as specialisation,
                     o.idorientation, o.\"designationOrientation\" as orientation_designation,
                     sec.idsection as section_idsection, sec.\"designationSection\" as section_designation,
                     s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                     dm.\"idDepot\", dm.fichier as memoire_fichier, dm.\"dateDepot\",
                     j.idjury, j.designation as jury_designation,
                     (SELECT COUNT(*) FROM lecteurs_soutenance WHERE idsoutenance = s.idsoutenance) as nb_lecteurs
              FROM sujets sj
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
              LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
              LEFT JOIN orientation o ON sp.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
              LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
              LEFT JOIN jury j ON s.jury_id = j.idjury
              WHERE sj.annee_acad_idannee_acad = ?";

    $executeParams = [$selectedYearId];

    // Filtrer par spécialisation si sélectionnée
    if ($selectedSpecialisation) {
        $query .= " AND sp.idSpecialisation = ?";
        $executeParams[] = $selectedSpecialisation;
    }

    // Filtrer par cycle si sélectionné
    if ($selectedCycle) {
        $query .= " AND sj.cycle = ?";
        $executeParams[] = $selectedCycle;
    }

    // Filtrer par sections de l'utilisateur si pas admin
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query .= " AND sec.idsection IN ($placeholders)";
        $executeParams = array_merge($executeParams, $userResponsibilities);
    }

    $query .= " ORDER BY CASE 
                    WHEN dm.\"idDepot\" IS NULL THEN 0 
                    WHEN s.idsoutenance IS NULL THEN 1 
                    ELSE 2 
                END, 
                e.noms ASC
                LIMIT 50";

    $stmt = $connexion->prepare($query);
    $stmt->execute($executeParams);
    $allTravaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $initialTravauxCount = count($allTravaux);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de tous les travaux: " . $e->getMessage());
    $allTravaux = [];
    $initialTravauxCount = 0;
}

// Statistiques pour tous les travaux (requête COUNT séparée sans LIMIT)
try {
    $statsQuery = "SELECT 
                    COUNT(DISTINCT sj.idsujets) as total,
                    COUNT(DISTINCT CASE WHEN dm.\"idDepot\" IS NULL THEN sj.idsujets END) as sans_depot,
                    COUNT(DISTINCT CASE WHEN dm.\"idDepot\" IS NOT NULL THEN sj.idsujets END) as avec_depot,
                    COUNT(DISTINCT CASE WHEN j.idjury IS NOT NULL THEN sj.idsujets END) as avec_jury
                   FROM sujets sj
                   JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                   LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
                   LEFT JOIN orientation o ON sp.idorientation = o.idorientation
                   LEFT JOIN section sec ON o.section_idsection = sec.idsection
                   LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
                   LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
                   LEFT JOIN jury j ON s.jury_id = j.idjury
                   WHERE sj.annee_acad_idannee_acad = ?";
    
    $statsParams = [$selectedYearId];
    
    if ($selectedSpecialisation) {
        $statsQuery .= " AND sp.idSpecialisation = ?";
        $statsParams[] = $selectedSpecialisation;
    }
    if ($selectedCycle) {
        $statsQuery .= " AND sj.cycle = ?";
        $statsParams[] = $selectedCycle;
    }
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $placeholders = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $statsQuery .= " AND sec.idsection IN ($placeholders)";
        $statsParams = array_merge($statsParams, $userResponsibilities);
    }
    
    $stmtStats = $connexion->prepare($statsQuery);
    $stmtStats->execute($statsParams);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
    $totalTravaux = (int)($stats['total'] ?? 0);
    $travauxSansDepot = (int)($stats['sans_depot'] ?? 0);
    $travauxAvecDepot = (int)($stats['avec_depot'] ?? 0);
    $travauxAvecJury = (int)($stats['avec_jury'] ?? 0);
} catch (Exception $e) {
    error_log("Erreur statistiques: " . $e->getMessage());
    $totalTravaux = count($allTravaux);
    $travauxSansDepot = 0;
    $travauxAvecDepot = 0;
    $travauxAvecJury = 0;
}
?>

<style>
    .compact-stats { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .compact-stats .stat-item { padding: 0.2rem 0.6rem; border-radius: 0.25rem; font-size: 0.8rem; }
    .compact-table th, .compact-table td { padding: 0.3rem 0.4rem !important; font-size: 0.82rem; vertical-align: middle; }
    .compact-table .btn-sm { padding: 0.15rem 0.35rem; font-size: 0.72rem; }
    .compact-table .badge { font-size: 0.68rem; padding: 0.2rem 0.4rem; }
    .compact-filters .form-select, .compact-filters .form-control { padding: 0.2rem 0.4rem; font-size: 0.82rem; }
    .compact-filters label { font-size: 0.75rem; margin-bottom: 0.1rem; }
    .nav-tabs .nav-link { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
</style>

<?php
$totalSoutenances = count($soutenances);
$programmees = count(array_filter($soutenances, function ($s) { return $s['statut'] == 'Programmée'; }));
?>

<main id="main" class="main pt-2">
    <div class="pagetitle mb-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="mb-0" style="font-size: 1.25rem;">GESTION DES MÉMOIRES</h1>
            <div class="compact-stats">
                <span class="stat-item bg-primary text-white"><i class="bi bi-journal-text me-1"></i>Travaux: <strong><?php echo $totalTravaux; ?></strong></span>
                <span class="stat-item bg-warning text-dark"><i class="bi bi-clock me-1"></i>Sans dépôt: <strong><?php echo $travauxSansDepot; ?></strong></span>
                <span class="stat-item bg-success text-white"><i class="bi bi-cloud-upload me-1"></i>Déposés: <strong><?php echo $travauxAvecDepot; ?></strong></span>
                <span class="stat-item bg-info text-white"><i class="bi bi-people me-1"></i>Avec jury: <strong><?php echo $travauxAvecJury; ?></strong></span>
                <span class="stat-item bg-secondary text-white"><i class="bi bi-calendar-event me-1"></i>Sout.: <strong><?php echo $totalSoutenances; ?></strong></span>
                <span class="stat-item bg-dark text-white"><i class="bi bi-check-circle me-1"></i>Prog.: <strong><?php echo $programmees; ?></strong></span>
            </div>
        </div>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Filtres compacts + Actions -->
            <div class="col-lg-12 mb-2">
                <div class="card compact-filters">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2 col-6">
                                <label for="yearFilter">Année:</label>
                                <select id="yearFilter" class="form-select form-select-sm" onchange="updateYearFilter(this.value)">
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?php echo $year['idannee_acad']; ?>" <?php echo ($selectedYearId == $year['idannee_acad']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year['designation']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-6">
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
                            <div class="col-md-1 col-4">
                                <label for="cycleFilter">Cycle:</label>
                                <select id="cycleFilter" class="form-select form-select-sm" onchange="updateCycleFilter(this.value)">
                                    <option value="">Tous</option>
                                    <option value="Premier" <?php echo ($selectedCycle === 'Premier') ? 'selected' : ''; ?>>1er</option>
                                    <option value="Deuxieme" <?php echo ($selectedCycle === 'Deuxieme') ? 'selected' : ''; ?>>2e</option>
                                    <option value="Troisieme" <?php echo ($selectedCycle === 'Troisieme') ? 'selected' : ''; ?>>3e</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-6">
                                <label for="searchFilter">Rechercher:</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="searchFilter" class="form-control" placeholder="Nom/matricule..." autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display: none;"><i class="bi bi-x"></i></button>
                                </div>
                                <small class="text-muted" id="searchResultsInfo" style="font-size: 0.7rem;"></small>
                            </div>
                            <div class="col-md-1 col-2">
                                <button id="clearFilters" class="btn btn-secondary btn-sm w-100" title="Reset filtres"><i class="bi bi-x-circle"></i></button>
                            </div>
                            <?php if ($hasFullAccess): ?>
                            <div class="col-md-4 col-12 text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-success" onclick="openAssignJuryBulkModal()" title="Assigner au Jury"><i class="bi bi-people-fill me-1"></i>Jury</button>
                                    <a href="memoire/assign_lecteurs" class="btn btn-primary" title="Assigner Lecteurs"><i class="bi bi-person-badge me-1"></i>Lecteurs</a>
                                    <a href="memoire/fees" class="btn btn-warning" title="Configurer Frais"><i class="bi bi-cash"></i></a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglets compacts -->
            <div class="col-lg-12 mb-2">
                <ul class="nav nav-tabs nav-fill" id="memoireTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1" id="all-tab" data-bs-toggle="tab" data-bs-target="#allTravaux" type="button" role="tab">
                            <i class="bi bi-list-ul me-1"></i>Travaux <span class="badge bg-primary"><?php echo $totalTravaux; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1" id="depots-tab" data-bs-toggle="tab" data-bs-target="#depotsTab" type="button" role="tab">
                            <i class="bi bi-cloud-upload me-1"></i>Dépôts <span class="badge bg-success"><?php echo count($depots); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1" id="soutenances-tab" data-bs-toggle="tab" data-bs-target="#soutenancesTab" type="button" role="tab">
                            <i class="bi bi-calendar-event me-1"></i>Soutenances <span class="badge bg-info"><?php echo count($soutenances); ?></span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="memoireTabsContent">
                <!-- Onglet Tous les Travaux -->
                <div class="tab-pane fade show active" id="allTravaux" role="tabpanel">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold" style="font-size: 0.9rem;"><i class="bi bi-list-ul me-1"></i>Tous les Travaux</span>
                                <div>
                                    <span class="badge bg-secondary"><?php echo $travauxSansDepot; ?> sans dépôt</span>
                                    <span class="badge bg-success"><?php echo $travauxAvecDepot; ?> déposés</span>
                                    <span class="badge bg-primary"><?php echo $travauxAvecJury; ?> avec jury</span>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover compact-table mb-0" id="allTravauxTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 75px;">Matr.</th>
                                                <th style="min-width: 110px;">Étudiant</th>
                                                <th>Titre</th>
                                                <th style="width: 90px;">Directeur</th>
                                                <th style="width: 90px;">Spéc.</th>
                                                <th style="width: 50px;">Cycle</th>
                                                <th style="width: 65px;">Dépôt</th>
                                                <th style="width: 80px;">Jury</th>
                                                <th style="width: 90px;">Sout.</th>
                                                <th style="width: 100px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($allTravaux)): ?>
                                                <?php foreach ($allTravaux as $travail): 
                                                    $hasDepot = !empty($travail['idDepot']);
                                                    $hasSoutenance = !empty($travail['idsoutenance']);
                                                    $hasJury = !empty($travail['idjury']);
                                                    $nbLecteurs = $travail['nb_lecteurs'] ?? 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($travail['matricule'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($travail['etudiant_nom']); ?></td>
                                                        <td><?php echo htmlspecialchars($travail['sujet_titre']); ?></td>
                                                        <td><?php echo htmlspecialchars($travail['directeur_nom'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($travail['specialisation'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($travail['cycle'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php if ($hasDepot): ?>
                                                                <span class="badge bg-success" title="Déposé le <?php echo date('d/m/Y', strtotime($travail['dateDepot'])); ?>">
                                                                    <i class="bi bi-check-circle me-1"></i>Déposé
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="bi bi-clock me-1"></i>Non déposé
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($hasJury): ?>
                                                                <span class="badge bg-success"><?php echo htmlspecialchars($travail['jury_designation']); ?></span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Non assigné</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($hasSoutenance): ?>
                                                                <?php if ($travail['date_soutenance']): ?>
                                                                    <span class="badge bg-info"><?php echo date('d/m/Y H:i', strtotime($travail['date_soutenance'])); ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark">Non programmée</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <?php if (!$hasJury): ?>
                                                                    <button type="button" class="btn btn-success btn-sm" onclick="assignJuryToWork(<?php echo $travail['idsujets']; ?>, '<?php echo htmlspecialchars($travail['etudiant_nom'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($travail['sujet_titre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($travail['directeur_nom'] ?? '-', ENT_QUOTES); ?>')" title="Assigner jury">
                                                                        <i class="bi bi-people-fill"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if (!$hasSoutenance): ?>
                                                                    <button class="btn btn-primary btn-sm" onclick="assignLecteursFromDepot(<?php echo $travail['idsujets']; ?>, '<?php echo htmlspecialchars($travail['etudiant_nom'], ENT_QUOTES); ?>')" title="Programmer">
                                                                        <i class="bi bi-calendar-plus"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <a href="?view=memoire/assign_lecteurs" class="btn btn-info btn-sm" title="Modifier"><i class="bi bi-pencil"></i></a>
                                                                <?php endif; ?>
                                                                <?php if ($hasDepot): ?>
                                                                    <a href="<?php echo htmlspecialchars($travail['memoire_fichier']); ?>" class="btn btn-outline-success btn-sm" target="_blank" download title="Télécharger"><i class="bi bi-download"></i></a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <i class="bi bi-inbox" style="font-size: 2rem; color: #ccc;"></i>
                                                        <p class="text-muted mt-2">Aucun travail trouvé pour cette année académique.</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    <!-- Indicateurs d'infinite scroll pour allTravauxTable -->
                                    <div id="loadingIndicatorTravaux" class="text-center my-3" style="display: none;">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                        <p class="text-muted mt-2">Chargement d'autres travaux...</p>
                                    </div>
                                    <div id="noMoreDataTravaux" class="text-center my-3 text-muted" style="display: none;">
                                        <p><i class="bi bi-check-circle me-1"></i> Tous les travaux ont été chargés</p>
                                    </div>
                                    <div id="loadingSentinelTravaux" style="height: 20px; background-color: transparent;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglet Dépôts de mémoires -->
                <div class="tab-pane fade" id="depotsTab" role="tabpanel">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold" style="font-size: 0.9rem;"><i class="bi bi-cloud-upload me-1"></i>Dépôts de Mémoires</span>
                        <?php if ($hasFullAccess): ?>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info btn-sm" id="toggleSelectDepo" onclick="toggleSelectAllDepots()"><i class="bi bi-check-square me-1"></i>Sélect.</button>
                                <button class="btn btn-success btn-sm" id="assignSelectedJury" onclick="assignMultipleJuriesToSubs()" style="display:none;"><i class="bi bi-people-fill me-1"></i>Jury</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover compact-table mb-0" id="depotsTable">
                                <thead class="table-light">
                                    <tr>
                                        <?php if ($hasFullAccess): ?>
                                            <th style="width: 30px;"><input type="checkbox" id="selectAllDepots" onchange="toggleSelectAllDepots()"></th>
                                        <?php endif; ?>
                                        <th style="width: 70px;">Matr.</th>
                                        <th style="min-width: 100px;">Étudiant</th>
                                        <th>Titre</th>
                                        <th style="width: 85px;">Directeur</th>
                                        <th style="width: 85px;">Spéc.</th>
                                        <th style="width: 80px;">Dépôt</th>
                                        <th style="width: 80px;">Jury</th>
                                        <th style="width: 45px;">PDF</th>
                                        <th style="width: 90px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($depots)) {
                                        foreach ($depots as $depot):
                                    ?>
                                            <tr data-depot-id="<?php echo htmlspecialchars($depot['idDepot']); ?>" data-sujet-id="<?php echo htmlspecialchars($depot['idsujets']); ?>">
                                                <?php if ($hasFullAccess): ?>
                                                    <td>
                                                        <input type="checkbox" class="depot-checkbox" value="<?php echo htmlspecialchars($depot['idsujets']); ?>">
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($depot['matricule'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($depot['etudiant_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($depot['sujet_titre']); ?></td>
                                                <td><?php echo htmlspecialchars($depot['directeur_nom'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($depot['specialisation'] ?? '-'); ?></td>
                                                <td><?php echo $depot['dateDepot'] ? date('d/m/Y H:i', strtotime($depot['dateDepot'])) : '-'; ?></td>
                                                <td>
                                                    <?php if ($depot['idjury']): ?>
                                                        <span class="badge bg-success"><?php echo htmlspecialchars($depot['jury_designation']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Non assigné</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($depot['fichier']); ?>" class="btn btn-sm btn-success" target="_blank" download title="Télécharger le mémoire">
                                                        <i class="bi bi-download me-1"></i>PDF
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php
                                                        // Vérifier si une soutenance existe déjà
                                                        $hasSoutenance = !empty($depot['idsoutenance']);
                                                        ?>
                                                        <?php if ($hasFullAccess): ?>
                                                            <?php if (!$hasSoutenance): ?>
                                                                <?php if (!$depot['idjury']): ?>
                                                                    <button type="button" class="btn btn-success" onclick="assignJuryToWork(<?php echo $depot['idsujets']; ?>, '<?php echo htmlspecialchars($depot['etudiant_nom']); ?>', '<?php echo htmlspecialchars($depot['sujet_titre']); ?>', '<?php echo htmlspecialchars($depot['directeur_nom'] ?? '-'); ?>')" title="Assigner au jury">
                                                                        <i class="bi bi-people-fill"></i> Jury
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success">Jury assigné</span>
                                                                <?php endif; ?>
                                                                <button class="btn btn-primary" onclick="assignLecteursFromDepot(<?php echo $depot['idsujets']; ?>, '<?php echo htmlspecialchars($depot['etudiant_nom']); ?>')" title="Assigner lecteurs et programmer la soutenance">
                                                                    <i class="bi bi-person-plus"></i> Lecteurs
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-info" onclick="editSoutenance(<?php echo $depot['idsoutenance']; ?>)" title="Modifier la soutenance">
                                                                    <i class="bi bi-pencil"></i> Modifier
                                                                </button>
                                                                <?php if (!$depot['idjury']): ?>
                                                                    <button class="btn btn-warning" onclick="assignJuryToDefense(<?php echo $depot['idsoutenance']; ?>, '<?php echo htmlspecialchars($depot['etudiant_nom']); ?>')" title="Assigner un jury">
                                                                        <i class="bi bi-link"></i> Jury
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($depot['soutenance_statut'] === 'Programmée'): ?>
                                                                    <button class="btn btn-success" disabled title="Soutenance déjà programmée">
                                                                        <i class="bi bi-check-circle"></i> Programmée
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        endforeach;
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <!-- Indicateurs d'infinite scroll pour depotsTable -->
                            <div id="loadingIndicatorDepots" class="text-center my-3" style="display: none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="text-muted mt-2">Chargement d'autres dépôts...</p>
                            </div>
                            <div id="noMoreDataDepots" class="text-center my-3 text-muted" style="display: none;">
                                <p><i class="bi bi-check-circle me-1"></i> Tous les dépôts ont été chargés</p>
                            </div>
                            <div id="loadingSentinelDepots" style="height: 20px; background-color: transparent;"></div>
                        </div><!-- End table-responsive -->
                    </div><!-- End card-body -->
                </div><!-- End card -->
            </div><!-- End col-lg-12 -->
                </div><!-- End depotsTab -->

                <!-- Onglet Soutenances -->
                <div class="tab-pane fade" id="soutenancesTab" role="tabpanel">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold" style="font-size: 0.9rem;"><i class="bi bi-calendar-event me-1"></i>Soutenances</span>
                        <?php if ($hasFullAccess): ?>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-success btn-sm" onclick="openExportFicheModal()" title="Exporter fiche"><i class="bi bi-file-earmark-excel me-1"></i>Export</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openImportNotesModal()" title="Importer notes"><i class="bi bi-upload me-1"></i>Import</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover compact-table mb-0" id="memoiresTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 70px;">Matr.</th>
                                        <th style="min-width: 100px;">Étudiant</th>
                                        <th>Titre</th>
                                        <th style="width: 85px;">Directeur</th>
                                        <th style="width: 60px;">Dépôt</th>
                                        <th style="width: 70px;">Statut</th>
                                        <th style="width: 85px;">Date Sout.</th>
                                        <th style="width: 70px;">Lieu</th>
                                        <th style="width: 55px;">Note</th>
                                        <th style="width: 55px;">Lect.</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($soutenances)) {
                                        foreach ($soutenances as $soutenance):
                                    ?>
                                            <tr data-soutenance-id="<?php echo htmlspecialchars($soutenance['idsoutenance']); ?>">
                                                <td><?php echo htmlspecialchars($soutenance['matricule'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($soutenance['etudiant_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($soutenance['sujet_titre']); ?></td>
                                                <td><?php echo htmlspecialchars($soutenance['directeur_nom'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if (!empty($soutenance['idDepot']) && !empty($soutenance['memoire_fichier'])): ?>
                                                        <span class="badge bg-success" title="Dépôt le <?php echo date('d/m/Y', strtotime($soutenance['dateDepot'])); ?>">
                                                            <i class="bi bi-check-circle me-1"></i>Déposé
                                                        </span>
                                                        <a href="<?php echo htmlspecialchars($soutenance['memoire_fichier']); ?>" class="btn btn-sm btn-outline-info ms-2" target="_blank" title="Télécharger le mémoire">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-clock me-1"></i>En attente
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo ($soutenance['statut'] == 'Programmée') ? 'success' : (($soutenance['statut'] == 'Terminée') ? 'primary' :
                                                                                'secondary');
                                                                            ?>">
                                                        <?php echo htmlspecialchars($soutenance['statut'] ?? 'Non programmée'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $soutenance['date_soutenance'] ? date('d/m/Y', strtotime($soutenance['date_soutenance'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($soutenance['lieu'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if (!empty($soutenance['note_finale'])): ?>
                                                        <span class="badge bg-success fs-6"><?php echo number_format($soutenance['note_finale'], 2); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                    <?php if ($hasFullAccess): ?>
                                                        <button class="btn btn-sm btn-outline-primary ms-1" onclick="openEncoderNote(<?php echo $soutenance['idsoutenance']; ?>, '<?php echo htmlspecialchars($soutenance['etudiant_nom']); ?>', <?php echo $soutenance['note_finale'] ?? 'null'; ?>)" title="Encoder/Modifier la note">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewLecteurs(<?php echo $soutenance['idsoutenance']; ?>)" title="Voir les lecteurs">
                                                        <i class="bi bi-person-check"></i> Lecteurs
                                                    </button>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewMemoireDetails(<?php echo $soutenance['idsoutenance']; ?>)" title="Voir détails">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($hasFullAccess): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="assignLecteurs(<?php echo $soutenance['idsoutenance']; ?>)" title="Assigner lecteurs">
                                                            <i class="bi bi-person-plus"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning" onclick="editSoutenance(<?php echo $soutenance['idsoutenance']; ?>)" title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php
                                        endforeach;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div><!-- End table-responsive -->
                    </div><!-- End card-body -->
                </div><!-- End card -->
            </div><!-- End col-lg-12 -->
                </div><!-- End soutenancesTab -->
            </div><!-- End tab-content -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour assigner un jury -->
<div class="modal fade" id="assignJuryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-link me-2"></i>Assigner un Jury
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignJuryForm">
                <div class="modal-body">
                    <input type="hidden" id="soutenance_id_jury" name="soutenance_id">

                    <div class="mb-3">
                        <label for="jury_select" class="form-label required">
                            <i class="bi bi-people me-1"></i>Sélectionner un Jury
                        </label>
                        <select class="form-select" id="jury_select" name="jury_id" required>
                            <option value="">Choisir un jury...</option>
                            <?php foreach ($jurysDisponibles as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?>
                                    (Président: <?= htmlspecialchars($jury['president_nom'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un jury.</div>
                    </div>

                    <?php if (empty($jurysDisponibles)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Aucun jury disponible pour cette année académique.
                            <a href="?view=recherche/gestion_jurys">Créer un jury</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-info" <?= empty($jurysDisponibles) ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle me-1"></i>Assigner Jury
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour assigner travaux au jury (affectation multiple) -->
<div class="modal fade" id="assignJuryBulkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-people-fill me-2"></i>Assigner Travaux au Jury
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignJuryBulkForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Sélection:</strong> <span id="selectedCount">0</span> travail(x) sélectionné(s)
                    </div>

                    <div class="mb-3">
                        <label for="jury_select_bulk" class="form-label required">
                            <i class="bi bi-people me-1"></i>Sélectionner un Jury
                        </label>
                        <select class="form-select" id="jury_select_bulk" name="jury_id" required>
                            <option value="">Choisir un jury...</option>
                            <?php foreach ($jurysDisponibles as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?>
                                    (Président: <?= htmlspecialchars($jury['president_nom'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un jury.</div>
                    </div>

                    <div id="selectedMemoires">
                        <h6 class="text-muted">Travaux sélectionnés:</h6>
                        <div id="selectedMemoiresList" style="max-height: 300px; overflow-y: auto;">
                            <!-- Filled by JS -->
                        </div>
                    </div>

                    <?php if (empty($jurysDisponibles)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Aucun jury disponible pour cette année académique.
                            <a href="?view=recherche/gestion_jurys">Créer un jury</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success" <?= empty($jurysDisponibles) ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle me-1"></i>Assigner au Jury
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour assigner travail au jury (affectation unique) -->
<div class="modal fade" id="assignJuryToWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-people-fill me-2"></i>Assigner Travail au Jury
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignJuryToWorkForm">
                <div class="modal-body">
                    <input type="hidden" id="work_sujet_id" name="sujet_id">

                    <div class="mb-3" id="workDetailsCard" style="display:none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <p class="mb-1"><strong>Étudiant:</strong> <span id="workStudentName"></span></p>
                                <p class="mb-1"><strong>Titre:</strong> <span id="workTitle"></span></p>
                                <p class="mb-0"><strong>Directeur:</strong> <span id="workDirector"></span></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="jury_select_single" class="form-label required">
                            <i class="bi bi-people me-1"></i>Sélectionner un Jury
                        </label>
                        <select class="form-select" id="jury_select_single" name="jury_id" required>
                            <option value="">Choisir un jury...</option>
                            <?php foreach ($jurysDisponibles as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?>
                                    (Président: <?= htmlspecialchars($jury['president_nom'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un jury.</div>
                    </div>

                    <?php if (empty($jurysDisponibles)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Aucun jury disponible.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success" <?= empty($jurysDisponibles) ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle me-1"></i>Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour assigner des lecteurs -->
<div class="modal fade" id="assignLecteursModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Assigner des Lecteurs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignLecteursForm">
                <div class="modal-body">
                    <input type="hidden" id="soutenance_id_lecteurs" name="soutenance_id">

                    <div class="mb-3">
                        <label for="lecteur1_id" class="form-label required">
                            <i class="bi bi-person-badge me-1"></i>Premier Lecteur
                        </label>
                        <select class="form-select" id="lecteur1_id" name="lecteur1_id" required>
                            <option value="">Sélectionner le premier lecteur...</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner le premier lecteur.</div>
                    </div>

                    <div class="mb-3">
                        <label for="lecteur2_id" class="form-label required">
                            <i class="bi bi-person-badge me-1"></i>Deuxième Lecteur
                        </label>
                        <select class="form-select" id="lecteur2_id" name="lecteur2_id" required>
                            <option value="">Sélectionner le deuxième lecteur...</option>
                            <?php foreach ($enseignants as $ens): ?>
                                <option value="<?= $ens['idAgent'] ?>">
                                    <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner le deuxième lecteur.</div>
                    </div>

                    <div class="mb-3">
                        <label for="date_soutenance" class="form-label required">
                            <i class="bi bi-calendar-event me-1"></i>Date de Soutenance
                        </label>
                        <input type="datetime-local" class="form-control" id="date_soutenance" name="date_soutenance" required>
                        <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                    </div>

                    <div class="mb-3">
                        <label for="lieu_soutenance" class="form-label required">
                            <i class="bi bi-building me-1"></i>Lieu de Soutenance
                        </label>
                        <input type="text" class="form-control" id="lieu_soutenance" name="lieu_soutenance" placeholder="Exemple: Amphi A, Salle 101, etc." required>
                        <div class="invalid-feedback">Veuillez spécifier un lieu.</div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Les deux lecteurs auront accès aux évaluations du mémoire.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir les détails d'un mémoire -->
<div class="modal fade" id="memoireDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Mémoire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="memoireDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
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

<!-- Modal pour encoder la note de soutenance -->
<div class="modal fade" id="encoderNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Encoder la Note de Soutenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="encoderNoteForm">
                <div class="modal-body">
                    <input type="hidden" id="note_soutenance_id" name="idsoutenance">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-person me-2"></i>
                        <strong>Étudiant:</strong> <span id="note_etudiant_nom"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_finale" class="form-label required">
                            <i class="bi bi-award me-1"></i>Note obtenue (sur 20)
                        </label>
                        <input type="number" class="form-control form-control-lg text-center" id="note_finale" name="note_finale" 
                               min="0" max="20" step="0.25" required placeholder="Ex: 15.50">
                        <div class="form-text">Entrez une note entre 0 et 20 (décimales autorisées)</div>
                        <div class="invalid-feedback">La note doit être comprise entre 0 et 20.</div>
                    </div>
                    
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="setNote(10)">10</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="setNote(14)">14</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="setNote(16)">16</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer la Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour exporter la fiche de cotation avec filtres -->
<div class="modal fade" id="exportFicheModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-excel me-2"></i>Exporter la Fiche de Cotation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="exportFicheForm" method="GET" action="controller/export_fiche_cotation.php" target="_blank">
                <div class="modal-body">
                    <input type="hidden" name="annee_acad" value="<?= $selectedYearId ?>">
                    
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Sélectionnez les filtres pour personnaliser votre export.
                        <br><small class="text-muted">Laissez vide pour exporter toutes les données.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_jury" class="form-label">
                            <i class="bi bi-people me-1"></i>Jury
                        </label>
                        <select class="form-select" id="export_jury" name="jury">
                            <option value="">-- Tous les jurys --</option>
                            <?php foreach ($jurysExport as $jury): ?>
                                <option value="<?= $jury['idjury'] ?>"><?= htmlspecialchars($jury['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_cycle" class="form-label">
                            <i class="bi bi-mortarboard me-1"></i>Cycle
                        </label>
                        <select class="form-select" id="export_cycle" name="cycle">
                            <option value="">-- Tous les cycles --</option>
                            <?php foreach ($cyclesExport as $cycle): ?>
                                <option value="<?= htmlspecialchars($cycle) ?>"><?= htmlspecialchars($cycle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_promotion" class="form-label">
                            <i class="bi bi-diagram-3 me-1"></i>Promotion
                        </label>
                        <select class="form-select" id="export_promotion" name="promotion">
                            <option value="">-- Toutes les promotions --</option>
                            <?php foreach ($promotionsExport as $promo): ?>
                                <option value="<?= $promo['idpromotion'] ?>"><?= htmlspecialchars($promo['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_specialisation" class="form-label">
                            <i class="bi bi-bookmark me-1"></i>Spécialisation
                        </label>
                        <select class="form-select" id="export_specialisation" name="specialisation">
                            <option value="">-- Toutes les spécialisations --</option>
                            <?php foreach ($specialisationsHierarchy as $sectionId => $section): ?>
                                <optgroup label="<?= htmlspecialchars($section['nom']) ?>">
                                    <?php foreach ($section['orientations'] as $orientationId => $orientation): ?>
                                        <?php foreach ($orientation['specialisations'] as $specId => $specName): ?>
                                            <option value="<?= $specId ?>"><?= htmlspecialchars($specName) ?></option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-download me-1"></i>Exporter Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour importer les notes -->
<div class="modal fade" id="importNotesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-upload me-2"></i>Importer les Notes de Soutenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="importNotesForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Format attendu:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Colonne A: Matricule de l'étudiant</li>
                            <li>Colonne B: Note (sur 20)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fichier_notes" class="form-label required">
                            <i class="bi bi-file-earmark-excel me-1"></i>Fichier Excel (.xlsx)
                        </label>
                        <input type="file" class="form-control" id="fichier_notes" name="fichier_notes" 
                               accept=".xlsx,.xls" required>
                        <div class="invalid-feedback">Veuillez sélectionner un fichier Excel.</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention:</strong> Les notes existantes seront remplacées par les nouvelles valeurs.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ===== GESTION DES NOTES DE SOUTENANCE =====
    function openEncoderNote(soutenanceId, etudiantNom, noteActuelle) {
        document.getElementById('note_soutenance_id').value = soutenanceId;
        document.getElementById('note_etudiant_nom').textContent = etudiantNom;
        document.getElementById('note_finale').value = noteActuelle !== null ? noteActuelle : '';
        const modal = new bootstrap.Modal(document.getElementById('encoderNoteModal'));
        modal.show();
    }
    
    function setNote(valeur) {
        document.getElementById('note_finale').value = valeur;
    }
    
    function openImportNotesModal() {
        document.getElementById('importNotesForm').reset();
        const modal = new bootstrap.Modal(document.getElementById('importNotesModal'));
        modal.show();
    }
    
    function openExportFicheModal() {
        document.getElementById('exportFicheForm').reset();
        // Conserver l'année académique
        document.querySelector('#exportFicheForm input[name="annee_acad"]').value = '<?= $selectedYearId ?>';
        const modal = new bootstrap.Modal(document.getElementById('exportFicheModal'));
        modal.show();
    }
    
    // Formulaire d'encodage de note
    document.addEventListener('DOMContentLoaded', function() {
        const encoderNoteForm = document.getElementById('encoderNoteForm');
        if (encoderNoteForm) {
            encoderNoteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const soutenanceId = document.getElementById('note_soutenance_id').value;
                const noteFinale = parseFloat(document.getElementById('note_finale').value);
                
                if (isNaN(noteFinale) || noteFinale < 0 || noteFinale > 20) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'La note doit être comprise entre 0 et 20.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }
                
                const formData = new FormData();
                formData.append('idsoutenance', soutenanceId);
                formData.append('note_finale', noteFinale);
                
                Swal.fire({
                    title: 'Enregistrement...',
                    text: 'Sauvegarde de la note en cours',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                fetch('controller/save_note_soutenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Note enregistrée!',
                            text: data.message || 'La note a été enregistrée avec succès.',
                            confirmButtonColor: '#4CAF50'
                        }).then(() => { location.reload(); });
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
            });
        }
        
        // Formulaire d'import de notes
        const importNotesForm = document.getElementById('importNotesForm');
        if (importNotesForm) {
            importNotesForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const fichier = document.getElementById('fichier_notes').files[0];
                if (!fichier) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un fichier Excel.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }
                
                const formData = new FormData();
                formData.append('fichier_notes', fichier);
                
                Swal.fire({
                    title: 'Importation...',
                    text: 'Traitement du fichier en cours',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                fetch('controller/import_notes_soutenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let message = `${data.imported} note(s) importée(s) avec succès.`;
                        if (data.errors && data.errors.length > 0) {
                            message += `\n\nErreurs (${data.errors.length}):\n` + data.errors.slice(0, 5).join('\n');
                            if (data.errors.length > 5) {
                                message += `\n... et ${data.errors.length - 5} autres erreurs`;
                            }
                        }
                        Swal.fire({
                            icon: data.errors && data.errors.length > 0 ? 'warning' : 'success',
                            title: 'Import terminé',
                            text: message,
                            confirmButtonColor: '#4CAF50'
                        }).then(() => { location.reload(); });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de l\'import.',
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

    function viewMemoireDetails(idSoutenance) {
        const modalContent = document.getElementById('memoireDetailsContent');
        modalContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;

        const modal = new bootstrap.Modal(document.getElementById('memoireDetailsModal'));
        modal.show();

        fetch('controller/get_soutenance_details.php?id=' + idSoutenance)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const soutenance = data.soutenance;
                    modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-person me-2"></i>Étudiant</h6>
                            <p><strong>${soutenance.matricule || '-'}</strong><br>${soutenance.etudiant_nom || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-book me-2"></i>Titre du Mémoire</h6>
                            <p>${soutenance.sujet_titre || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-person-badge me-2"></i>Directeur</h6>
                            <p>${soutenance.directeur_nom || 'Non attribué'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-calendar-range me-2"></i>Date de Soutenance</h6>
                            <p>${soutenance.date_soutenance ? new Date(soutenance.date_soutenance).toLocaleDateString('fr-FR', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'}) : '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-building me-2"></i>Lieu</h6>
                            <p>${soutenance.lieu || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-info-circle me-2"></i>Statut</h6>
                            <p><span class="badge bg-${soutenance.statut == 'Programmée' ? 'success' : 'secondary'}">${soutenance.statut || 'Non programmée'}</span></p>
                        </div>
                    </div>
                `;
                } else {
                    modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.message || 'Erreur lors du chargement des détails'}
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

    function assignLecteurs(soutenanceId) {
        document.getElementById('soutenance_id_lecteurs').value = soutenanceId;
        const modal = new bootstrap.Modal(document.getElementById('assignLecteursModal'));
        modal.show();
    }

    function assignLecteursFromDepot(sujetId, studentName) {
        // Créer une soutenance d'abord si elle n'existe pas
        Swal.fire({
            title: 'Créer une soutenance',
            html: `
                 <p>Vous êtes sur le point de créer une soutenance pour <strong>${studentName}</strong></p>
                 <p>Veuillez assigner les lecteurs et programmer la soutenance.</p>
             `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Continuer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Récupérer l'ID de la soutenance ou créer une nouvelle
                fetch('controller/get_or_create_soutenance.php?sujet_id=' + sujetId, {
                        method: 'GET'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Réponse serveur: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Réponse du serveur:', data);
                        if (data.success && data.soutenance_id) {
                            assignLecteurs(data.soutenance_id);
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
                            text: 'Impossible de communiquer avec le serveur: ' + error.message
                        });
                    });
            }
        });
    }

    function editSoutenance(soutenanceId) {
        window.location.href = 'memoire/edit&id=' + soutenanceId;
    }

    function assignJuryToWork(sujetId, studentName, title, director) {
        document.getElementById('work_sujet_id').value = sujetId;
        document.getElementById('workStudentName').textContent = studentName;
        document.getElementById('workTitle').textContent = title;
        document.getElementById('workDirector').textContent = director;
        document.getElementById('workDetailsCard').style.display = 'block';
        const modal = new bootstrap.Modal(document.getElementById('assignJuryToWorkModal'));
        modal.show();
    }

    function openAssignJuryBulkModal() {
        const checkboxes = document.querySelectorAll('.depot-checkbox:checked');

        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Aucune sélection',
                text: 'Veuillez sélectionner au moins un travail à assigner.',
                confirmButtonColor: '#f39c12'
            });
            return;
        }

        document.getElementById('selectedCount').textContent = checkboxes.length;
        let memoiresList = '';

        checkboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const studentName = row.querySelector('td:nth-child(<?php echo $hasFullAccess ? 3 : 2; ?>)').textContent;
            const title = row.querySelector('td:nth-child(<?php echo $hasFullAccess ? 4 : 3; ?>)').textContent;
            memoiresList += `<div class="badge bg-secondary me-2 mb-2">${studentName}: ${title}</div>`;
        });

        document.getElementById('selectedMemoiresList').innerHTML = memoiresList;
        const modal = new bootstrap.Modal(document.getElementById('assignJuryBulkModal'));
        modal.show();
    }

    function toggleSelectAllDepots() {
        const checkboxAll = document.getElementById('selectAllDepots');
        const checkboxes = document.querySelectorAll('.depot-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = checkboxAll ? checkboxAll.checked : !checkbox.checked;
        });

        updateDepotButtonState();
    }

    function updateDepotButtonState() {
        const checkboxes = document.querySelectorAll('.depot-checkbox:checked');
        const assignBtn = document.getElementById('assignSelectedJury');

        if (assignBtn) {
            assignBtn.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
        }
    }

    function assignMultipleJuriesToSubs() {
        openAssignJuryBulkModal();
    }

    function assignJuryToDefense(soutenanceId, studentName) {
        document.getElementById('soutenance_id_jury').value = soutenanceId;
        const modal = new bootstrap.Modal(document.getElementById('assignJuryModal'));
        modal.show();
    }

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

    // Gestion du formulaire d'assignation de jury
    document.addEventListener('DOMContentLoaded', function() {
        const assignJuryForm = document.getElementById('assignJuryForm');

        if (assignJuryForm) {
            assignJuryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const soutenanceId = document.getElementById('soutenance_id_jury').value;
                const juryId = document.getElementById('jury_select').value;

                if (!juryId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un jury.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('soutenance_id', soutenanceId);
                formData.append('jury_id', juryId);

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Assignation du jury en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('./controller/assign_jury_to_defense.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur HTTP ' + response.status);
                        }
                        return response.text();
                    })
                    .then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Réponse brute:', text);
                            throw new Error('Réponse serveur invalide: ' + text.substring(0, 100));
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Jury assigné!',
                                text: data.message || 'Le jury a été assigné avec succès.',
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
                            text: 'Impossible de communiquer avec le serveur: ' + error.message,
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }

        // Gestion du formulaire d'assignation de jury (affectation unique)
        const assignJuryToWorkForm = document.getElementById('assignJuryToWorkForm');

        if (assignJuryToWorkForm) {
            assignJuryToWorkForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const sujetId = document.getElementById('work_sujet_id').value;
                const juryId = document.getElementById('jury_select_single').value;

                if (!juryId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un jury.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('sujet_id', sujetId);
                formData.append('jury_id', juryId);

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Assignation du jury en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('./controller/assign_jury_to_work.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Réponse reçue:', text);
                        throw new Error('Réponse invalide: ' + text.substring(0, 200));
                    }
                })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Jury assigné!',
                                text: data.message || 'Le jury a été assigné avec succès.',
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
                            text: 'Impossible de communiquer avec le serveur: ' + error.message,
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }

        // Gestion du formulaire d'assignation de jury (affectation multiple)
        const assignJuryBulkForm = document.getElementById('assignJuryBulkForm');

        if (assignJuryBulkForm) {
            assignJuryBulkForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const checkboxes = document.querySelectorAll('.depot-checkbox:checked');
                const juryId = document.getElementById('jury_select_bulk').value;

                if (!juryId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un jury.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                if (checkboxes.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Aucun travail sélectionné.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                const sujetIds = Array.from(checkboxes).map(cb => cb.value);
                const formData = new FormData();
                formData.append('sujet_ids', JSON.stringify(sujetIds));
                formData.append('jury_id', juryId);

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Assignation des travaux au jury en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('./controller/assign_multiple_jurys_to_works.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Réponse reçue:', text);
                        throw new Error('Réponse invalide: ' + text.substring(0, 200));
                    }
                })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Jury assigné!',
                                text: data.message || `${data.count || checkboxes.length} travail(x) assigné(s) avec succès.`,
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
                            text: 'Impossible de communiquer avec le serveur: ' + error.message,
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }

        // Écouter les changements de checkbox pour mettre à jour l'état du bouton
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('depot-checkbox')) {
                updateDepotButtonState();
            }
        });

        // Gestion du formulaire d'attribution de lecteurs
        const assignLecteursForm = document.getElementById('assignLecteursForm');

        if (assignLecteursForm) {
            assignLecteursForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Vérifier que les deux lecteurs sont différents
                const lecteur1 = document.getElementById('lecteur1_id').value;
                const lecteur2 = document.getElementById('lecteur2_id').value;

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
                                title: 'Lecteurs assignés!',
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

        // Filtrage amélioré sur tous les tableaux - recherche par nom/matricule
        const searchFilter = document.getElementById('searchFilter');
        const clearFilters = document.getElementById('clearFilters');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const searchResultsInfo = document.getElementById('searchResultsInfo');
        
        // Tous les tableaux à filtrer
        const tableIds = ['allTravauxTable', 'depotsTable', 'memoiresTable'];
        let searchTimeout;

        function performSearch() {
            if (!searchFilter) return;
            const searchValue = searchFilter.value.toLowerCase().trim();
            const searchTerms = searchValue.split(/\s+/).filter(t => t.length > 0);
            
            // Afficher/masquer bouton effacement
            if (clearSearchBtn) {
                clearSearchBtn.style.display = searchValue ? 'block' : 'none';
            }

            let totalVisible = 0;
            let totalRows = 0;

            tableIds.forEach(tableId => {
                const table = document.getElementById(tableId);
                if (!table) return;
                const tbody = table.getElementsByTagName('tbody')[0];
                if (!tbody) return;
                
                const rows = tbody.querySelectorAll('tr:not(.no-results-message)');
                
                rows.forEach(row => {
                    totalRows++;
                    if (!searchValue) {
                        row.style.display = '';
                        totalVisible++;
                        return;
                    }
                    
                    // Rechercher dans matricule (col 0 ou 1) et nom (col 1 ou 2)
                    let searchableText = '';
                    // Colonnes à chercher: on prend les 3 premières colonnes visibles
                    for (let i = 0; i < Math.min(4, row.cells.length); i++) {
                        searchableText += ' ' + row.cells[i].textContent.toLowerCase();
                    }
                    
                    const matches = searchTerms.every(term => searchableText.includes(term));
                    
                    if (matches) {
                        row.style.display = '';
                        totalVisible++;
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Afficher résultats
            if (searchResultsInfo) {
                if (searchValue) {
                    searchResultsInfo.textContent = totalVisible + '/' + totalRows + ' résultat' + (totalVisible !== 1 ? 's' : '');
                    searchResultsInfo.style.color = totalVisible > 0 ? '#198754' : '#dc3545';
                } else {
                    searchResultsInfo.textContent = '';
                }
            }
        }

        if (searchFilter) {
            searchFilter.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 150);
            });
            searchFilter.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    performSearch();
                }
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                searchFilter.value = '';
                searchFilter.focus();
                performSearch();
            });
        }

        if (clearFilters) {
            clearFilters.addEventListener('click', function() {
                if (searchFilter) searchFilter.value = '';
                performSearch();
                // Reset URL params
                let url = new URL(window.location);
                url.searchParams.delete('specialisation');
                url.searchParams.delete('cycle');
                window.location.href = url.toString();
            });
        }
    });
</script>

<!-- Script Infinite Scroll avec IntersectionObserver pour allTravauxTable -->
<script>
(function() {
    'use strict';
    
    let currentPageTravaux = 1;
    const limit = 50;
    let isLoadingTravaux = false;
    let hasMoreTravaux = <?= $initialTravauxCount >= 50 ? 'true' : 'false' ?>;
    
    function initInfiniteScrollTravaux() {
        const sentinel = document.getElementById('loadingSentinelTravaux');
        const loadingIndicator = document.getElementById('loadingIndicatorTravaux');
        const noMoreData = document.getElementById('noMoreDataTravaux');
        const tableBody = document.querySelector('#allTravauxTable tbody');

        if (!sentinel || !tableBody) {
            return;
        }
        
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
            if (isLoadingTravaux || !hasMoreTravaux) return;
            
            isLoadingTravaux = true;
            loadingIndicator.style.display = 'block';
            
            const params = new URLSearchParams(window.location.search);
            params.set('page', currentPageTravaux + 1);
            params.set('limit', limit);
            
            fetch('controller/ajax_get_travaux_memoire.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Erreur');
                    
                    const travaux = data.travaux || [];
                    
                    if (travaux.length > 0) {
                        travaux.forEach(travail => {
                            const tr = document.createElement('tr');
                            const hasDepot = !!travail.idDepot;
                            const hasSoutenance = !!travail.idsoutenance;
                            const hasJury = !!travail.idjury;
                            
                            let depotHtml = hasDepot 
                                ? `<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Déposé</span>`
                                : `<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Non déposé</span>`;
                            
                            let juryHtml = hasJury
                                ? `<span class="badge bg-success">${safeHtml(travail.jury_designation)}</span>`
                                : `<span class="badge bg-secondary">Non assigné</span>`;
                            
                            let soutenanceHtml = hasSoutenance
                                ? (travail.date_soutenance 
                                    ? `<span class="badge bg-info">${formatDate(travail.date_soutenance)}</span>`
                                    : `<span class="badge bg-warning text-dark">Non programmée</span>`)
                                : `<span class="badge bg-secondary">-</span>`;
                            
                            let actionsHtml = '<div class="btn-group btn-group-sm" role="group">';
                            if (!hasJury) {
                                actionsHtml += `<button type="button" class="btn btn-success" 
                                    onclick="assignJuryToWork(${travail.idsujets}, '${escapeJs(travail.etudiant_nom)}', '${escapeJs(travail.sujet_titre)}', '${escapeJs(travail.directeur_nom || '-')}')" 
                                    title="Assigner au jury">
                                    <i class="bi bi-people-fill"></i> Jury
                                </button>`;
                            }
                            if (!hasSoutenance) {
                                actionsHtml += `<button class="btn btn-primary" 
                                    onclick="assignLecteursFromDepot(${travail.idsujets}, '${escapeJs(travail.etudiant_nom)}')" 
                                    title="Programmer la soutenance">
                                    <i class="bi bi-calendar-plus"></i> Programmer
                                </button>`;
                            } else {
                                actionsHtml += `<a href="?view=memoire/assign_lecteurs" class="btn btn-info" title="Gérer la soutenance">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>`;
                            }
                            if (hasDepot) {
                                actionsHtml += `<a href="${safeHtml(travail.memoire_fichier)}" class="btn btn-outline-success" target="_blank" download title="Télécharger">
                                    <i class="bi bi-download"></i>
                                </a>`;
                            }
                            actionsHtml += '</div>';
                            
                            tr.innerHTML = `
                                <td>${safeHtml(travail.matricule || '-')}</td>
                                <td>${safeHtml(travail.etudiant_nom)}</td>
                                <td>${safeHtml(travail.sujet_titre)}</td>
                                <td>${safeHtml(travail.directeur_nom || '-')}</td>
                                <td>${safeHtml(travail.specialisation || '-')}</td>
                                <td>${safeHtml(travail.cycle || '-')}</td>
                                <td>${depotHtml}</td>
                                <td>${juryHtml}</td>
                                <td>${soutenanceHtml}</td>
                                <td>${actionsHtml}</td>
                            `;
                            
                            tableBody.appendChild(tr);
                        });
                        
                        currentPageTravaux++;
                        if (travaux.length < limit) {
                            hasMoreTravaux = false;
                            noMoreData.style.display = 'block';
                        }
                    } else {
                        hasMoreTravaux = false;
                        noMoreData.style.display = 'block';
                    }
                })
                .catch(error => console.error('Erreur infinite scroll:', error))
                .finally(() => {
                    isLoadingTravaux = false;
                    loadingIndicator.style.display = 'none';
                });
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) loadMoreTravaux();
        }, { root: null, rootMargin: '100px', threshold: 0 });

        observer.observe(sentinel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInfiniteScrollTravaux);
    } else {
        initInfiniteScrollTravaux();
    }
})();
</script>

<?php include "./views/include/footer_file.php"; ?>