<?php
//error_reporting(E_ALL); ini_set("display_errors", 1);
include "./views/include/header.php";
$universite = new Universite();

// Connexion à la base de données
$db = Connexion::getInstance()->getPDO();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;

// Récupérer toutes les années académiques
$allAcademicYears = $universite->getAllAcademicYears();

// Vérifier si la colonne est_active existe
$checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
$stmtCheck = $db->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
}

$stmtAnnee = $db->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYearActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYearActive) {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
    $stmtAnnee = $db->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYearActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer l'année académique sélectionnée
$selectedYear = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

if ($selectedYear == 0 && $currentYearActive) {
    $selectedYear = $currentYearActive['idannee_acad'];
} elseif ($selectedYear == 0 && !empty($allAcademicYears)) {
    $selectedYear = $allAcademicYears[0]['idannee_acad'];
}

// Récupérer les détails de l'année académique sélectionnée
$currentYear = null;
foreach ($allAcademicYears as $year) {
    if ($year['idannee_acad'] == $selectedYear) {
        $currentYear = $year;
        break;
    }
}

if (!$currentYear && !empty($allAcademicYears)) {
    $currentYear = $allAcademicYears[0];
}

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId = null) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = ?";
    
    $params = [$userId];
    
    if ($anneeAcadId) {
        $query .= " AND annee_acad_idannee_acad = ?";
        $params[] = $anneeAcadId;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Vérification des responsabilités de l'utilisateur connecté
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
$userSections = [];
$isResponsableSection = false;

if (!$hasFullAccess) {
    // Responsable de section - vérifier ses sections
    $userSections = getUserSections($db, $currentUserId, $selectedYear);
    $isResponsableSection = !empty($userSections);
    
    if (!$isResponsableSection) {
        // Rediriger l'utilisateur sans accès
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
}

// Récupérer les sections selon les droits d'accès (filtrées par année sélectionnée)
if ($hasFullAccess) {
    // Admin - toutes les sections de l'année sélectionnée
    $stmt = $db->prepare("
        SELECT s.idsection, s.designationSection, s.dateCreation, s.idAnnee, a.designation as anneeDesignation
        FROM section s
        LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
        WHERE a.idannee_acad = ?
        ORDER BY s.designationSection
    ");
    $stmt->execute([$selectedYear]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement ses sections de l'année sélectionnée
    if (empty($userSections)) {
        $sections = [];
    } else {
        $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
        $stmt = $db->prepare("
            SELECT s.idsection, s.designationSection, s.dateCreation, s.idAnnee, a.designation as anneeDesignation
            FROM section s
            LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
            WHERE s.idsection IN ($sectionsParams) AND a.idannee_acad = ?
            ORDER BY s.designationSection
        ");

        $params = $userSections;
        $params[] = $selectedYear;
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
        }

        $stmt->execute();
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les orientations selon les droits d'accès
if ($hasFullAccess) {
    // Admin - toutes les orientations
    $stmt = $db->prepare("
        SELECT o.idorientation, o.designationOrientation, o.dateCreation, 
               s.idsection, s.designationSection, s.idAnnee, a.designation as anneeDesignation 
        FROM orientation o
        JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
        WHERE a.idannee_acad = ?
        ORDER BY s.designationSection, o.designationOrientation
    ");
    $stmt->execute([$selectedYear]);
    $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les orientations de ses sections
    if (empty($userSections)) {
        $orientations = [];
    } else {
        $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
        $stmt = $db->prepare("
            SELECT o.idorientation, o.designationOrientation, o.dateCreation, 
                   s.idsection, s.designationSection, s.idAnnee, a.designation as anneeDesignation 
            FROM orientation o
            JOIN section s ON o.section_idsection = s.idsection
            LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
            WHERE s.idsection IN ($sectionsParams) AND a.idannee_acad = ?
            ORDER BY s.designationSection, o.designationOrientation
        ");
        
        $params = $userSections;
        $params[] = $selectedYear;
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $orientations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les unités de recherche selon les droits d'accès
if ($hasFullAccess) {
    // Admin - toutes les unités de recherche
    $queryUR = "SELECT DISTINCT ur.idunite_recherche as idunite_recherche, ur.designation_UR, ur.description 
                FROM unite_recherche ur
                LEFT JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
                LEFT JOIN section s ON urs.idsection = s.idsection
                WHERE s.idAnnee = ?";
    $params = [$selectedYear];

    if ($selectedSection > 0) {
        $queryUR .= " AND urs.idsection = ?";
        $params[] = $selectedSection;
    }

    if (!empty($search)) {
        $queryUR .= " AND (ur.designation_UR LIKE ? OR ur.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $queryUR .= " ORDER BY ur.designation_UR";
    $stmtUR = $db->prepare($queryUR);
    $stmtUR->execute($params);
    $researchUnits = $stmtUR->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les unités de recherche liées à ses sections
    if (empty($userSections)) {
        $researchUnits = [];
    } else {
        $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
        $queryUR = "
            SELECT DISTINCT ur.idunite_recherche as idunite_recherche, ur.designation_UR, ur.description 
            FROM unite_recherche ur
            INNER JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
            INNER JOIN section s ON urs.idsection = s.idsection
            WHERE s.idAnnee = ? AND urs.idsection IN ($sectionsParams)
        ";
        
        $params = [$selectedYear];
        $paramIndex = 1;
        
        // Ajouter les paramètres pour les sections
        foreach ($userSections as $sectionId) {
            $params[$paramIndex] = $sectionId;
            $paramIndex++;
        }

        if ($selectedSection > 0 && in_array($selectedSection, $userSections)) {
            $queryUR .= " AND urs.idsection = ?";
            $params[$paramIndex] = $selectedSection;
            $paramIndex++;
        }

        if (!empty($search)) {
            $queryUR .= " AND (ur.designation_UR LIKE ? OR ur.description LIKE ?)";
            $searchParam = "%$search%";
            $params[$paramIndex] = $searchParam;
            $params[$paramIndex + 1] = $searchParam;
        }

        $queryUR .= " ORDER BY ur.designation_UR";
        $stmtUR = $db->prepare($queryUR);
        
        foreach ($params as $i => $value) {
            $stmtUR->bindValue($i + 1, $value);
        }
        
        $stmtUR->execute();
        $researchUnits = $stmtUR->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<?php
// Compter les specialisations pour les stats
$totalSpecialisations = 0;
foreach ($researchUnits as $unit) {
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM specialisation WHERE idUnite_recherche = ?");
    $stmtCount->execute([$unit['idunite_recherche']]);
    $totalSpecialisations += $stmtCount->fetchColumn();
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>UNITÉS DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Unités de Recherche</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <?php if ($isResponsableSection && !$hasFullAccess): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Accès restreint :</strong> Vous visualisez uniquement les unités de recherche des sections dont vous êtes responsable.
            <?php 
            $sectionNames = [];
            if (!empty($userSections)) {
                $placeholders = implode(',', array_fill(0, count($userSections), '?'));
                $querySections = "SELECT designationSection FROM section WHERE idsection IN ($placeholders)";
                $stmtSections = $db->prepare($querySections);
                $stmtSections->execute($userSections);
                $sectionNames = $stmtSections->fetchAll(PDO::FETCH_COLUMN);
            }
            ?>
            <?php if (!empty($sectionNames)): ?>
                <br><small><strong>Sections :</strong> <?= implode(', ', $sectionNames) ?></small>
            <?php endif; ?>
        </div>
        <?php elseif ($hasFullAccess): ?>
        <div class="alert alert-success">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Accès administrateur :</strong> Vous avez accès à toutes les sections.
        </div>
        <?php endif; ?>

        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Unités de Recherche</h6>
                                <h2 class="mb-0"><?= count($researchUnits) ?></h2>
                            </div>
                            <div class="fs-1 opacity-75">
                                <i class="bi bi-collection"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Spécialisations</h6>
                                <h2 class="mb-0"><?= $totalSpecialisations ?></h2>
                            </div>
                            <div class="fs-1 opacity-75">
                                <i class="bi bi-diagram-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Sections</h6>
                                <h2 class="mb-0"><?= count($sections) ?></h2>
                            </div>
                            <div class="fs-1 opacity-75">
                                <i class="bi bi-layout-text-window-reverse"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Année Acad.</h6>
                                <h7 class="mb-0"><?= $currentYear ? htmlspecialchars($currentYear['designation']) : 'N/A' ?></h7>
                            </div>
                            <div class="fs-1 opacity-75">
                                <i class="bi bi-calendar3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-collection me-2"></i>Gestion des unités de recherche
                            </h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createResearchUnitModal">
                                <i class="bi bi-plus-circle-fill me-1"></i> Nouvelle UR
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="mb-4" id="filterForm">
                            <input type="hidden" name="view" value="ur/unite_recherche">
                            <div class="row g-3 align-items-end">
                                <!-- Année académique -->
                                <div class="col-md-2">
                                    <label for="annee_acad" class="form-label fw-semibold small">Année académique</label>
                                    <select name="annee_acad" id="annee_acad" class="form-select" onchange="this.form.submit()">
                                        <option value="0">-- Année --</option>
                                        <?php foreach ($allAcademicYears as $year): ?>
                                            <option value="<?= $year['idannee_acad'] ?>" <?= $selectedYear == $year['idannee_acad'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($year['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (!empty($sections)): ?>
                                <!-- Section -->
                                <div class="col-md-3">
                                    <label for="section" class="form-label fw-semibold small">Section</label>
                                    <select name="section" id="section" class="form-select" onchange="this.form.submit()">
                                        <option value="0"><?= $hasFullAccess ? 'Toutes sections' : 'Mes sections' ?></option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($section['designationSection']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Recherche -->
                                <div class="col-md-4">
                                    <label for="search" class="form-label fw-semibold small">Recherche</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Désignation ou description...">
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search me-1"></i> Rechercher
                                        </button>
                                        <a href="?view=ur/unite_recherche&annee_acad=<?= $selectedYear ?>" class="btn btn-outline-secondary" title="Réinitialiser">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>

                                <div class="table-responsive">
                                <table class="table table-hover table-bordered" id="researchUnitsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="text-center" style="width: 50px;">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Description</th>
                                            <th scope="col">Sections</th>
                                            <th scope="col" class="text-center" style="width: 200px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        if (empty($researchUnits)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                    Aucune unité de recherche trouvée
                                                </td>
                                            </tr>
                                        <?php else:
                                        foreach ($researchUnits as $unit) {
                                            // Récupérer les sections associées à cette unité de recherche
                                            $stmtSections = $db->prepare("
                                                SELECT s.idsection, s.designationSection, a.designation as anneeDesignation
                                                FROM unite_recherche_section urs
                                                JOIN section s ON urs.idsection = s.idsection
                                                LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
                                                WHERE urs.idunite_recherche = ?
                                            ");

                                            $stmtSections->execute([$unit['idunite_recherche']]);
                                            $unitSections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            $sectionNames = [];
                                            foreach ($unitSections as $section) {
                                                $sectionNames[] = $section['designationSection'];
                                            }
                                            $sectionsList = implode(', ', $sectionNames);
                                            
                                            // Compter les specialisations
                                            $stmtSpecCount = $db->prepare("SELECT COUNT(*) FROM specialisation WHERE idUnite_recherche = ?");
                                            $stmtSpecCount->execute([$unit['idunite_recherche']]);
                                            $specCount = $stmtSpecCount->fetchColumn();
                                            ?>
                                            <tr>
                                                <td class="text-center"><?= $i ?></td>
                                                <td><strong><?= htmlspecialchars($unit['designation_UR']) ?></strong></td>
                                                <td><?= empty($unit['description']) ? '<span class="text-muted">-</span>' : htmlspecialchars($unit['description']) ?></td>
                                                <td style="white-space: normal; max-width: 300px;">
                                                    <?php 
                                                    $sectionArray = explode(', ', $sectionsList);
                                                    foreach ($sectionArray as $secName) {
                                                        echo '<span class="badge bg-secondary me-1 mb-1">' . htmlspecialchars(trim($secName)) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-warning" onclick='openEditResearchUnitModal(<?= $unit['idunite_recherche'] ?>, "<?= addslashes($unit['designation_UR']) ?>", "<?= addslashes($unit['description'] ?? '') ?>")' title="Modifier">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteResearchUnit(<?= $unit['idunite_recherche'] ?>)" title="Supprimer">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" title="Spécialisations">
                                                                <i class="bi bi-diagram-3"></i> <span class="badge bg-danger"><?= $specCount ?></span>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li><a class="dropdown-item" href="#" onclick="toggleSpecialisations(<?= $unit['idunite_recherche'] ?>); return false;">
                                                                    <i class="bi bi-list me-1"></i> Voir Spécialisations
                                                                </a></li>
                                                                <li><a class="dropdown-item" href="#" onclick="openSpecialisationModal(<?= $unit['idunite_recherche'] ?>); return false;">
                                                                    <i class="bi bi-plus me-1"></i> Ajouter Spécialisation
                                                                </a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='specialisationsList<?= $unit['idunite_recherche'] ?>'>
                                                <td colspan='5' class="bg-light p-0">
                                                    <div class="card border-0">
                                                        <div class="card-body py-2">
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="table-secondary">
                                                                    <tr>
                                                                        <th>Orientation</th>
                                                                        <th>Section</th>
                                                                        <th>Spécialisation</th>
                                                                        <th class="text-center" style="width: 150px;">Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    foreach ($unitSections as $section) {
                                                                        $stmtOrientations = $db->prepare("
                                                                            SELECT o.idorientation, o.designationOrientation
                                                                            FROM orientation o
                                                                            WHERE o.section_idsection = ?
                                                                        ");
                                                                        $stmtOrientations->execute([$section['idsection']]);
                                                                        $sectionOrientations = $stmtOrientations->fetchAll(PDO::FETCH_ASSOC);
                                                                        
                                                                        foreach ($sectionOrientations as $orientation) {
                                                                            $stmtSpec = $db->prepare("
                                                                                SELECT s.*
                                                                                FROM specialisation s
                                                                                WHERE s.idUnite_recherche = ? AND s.idorientation = ?
                                                                            ");
                                                                            $stmtSpec->execute([$unit['idunite_recherche'], $orientation['idorientation']]);
                                                                            $specialisations = $stmtSpec->fetchAll(PDO::FETCH_ASSOC);
                                                                            
                                                                            if (count($specialisations) > 0) {
                                                                                foreach ($specialisations as $specialisation) {
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td><?= htmlspecialchars($orientation['designationOrientation']) ?></td>
                                                                                        <td><?= htmlspecialchars($section['designationSection']) ?></td>
                                                                                        <td><strong><?= htmlspecialchars($specialisation['designation']) ?></strong></td>
                                                                                        <td class="text-center">
                                                                                            <button class="btn btn-sm btn-outline-warning" onclick='openEditSpecialisationModal(<?= $specialisation['idSpecialisation'] ?>, "<?= addslashes($specialisation['designation']) ?>", <?= $unit['idunite_recherche'] ?>, <?= $orientation['idorientation'] ?>)' title="Modifier">
                                                                                                <i class="bi bi-pencil"></i>
                                                                                            </button>
                                                                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteSpecialisation(<?= $specialisation['idSpecialisation'] ?>)" title="Supprimer">
                                                                                                <i class="bi bi-trash"></i>
                                                                                            </button>
                                                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTeachersBySpecialisation(<?= $specialisation['idSpecialisation'] ?>, '<?= addslashes($specialisation['designation']) ?>')" title="Enseignants">
                                                                                                <i class="bi bi-people"></i>
                                                                                            </button>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <tr>
                                                                                    <td><?= htmlspecialchars($orientation['designationOrientation']) ?></td>
                                                                                    <td><?= htmlspecialchars($section['designationSection']) ?></td>
                                                                                    <td colspan="2" class="text-muted text-center">Aucune spécialisation</td>
                                                                                </tr>
                                                                                <?php
                                                                            }
                                                                        }
                                                                    }
                                                                    ?>
                                                                    </tbody>
</table>
</div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                            $i++;
                                        }
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter une unité de recherche -->
<div class="modal fade" id="createResearchUnitModal" tabindex="-1" role="dialog" aria-labelledby="createResearchUnitModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Unité de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="researchUnitForm" method="POST" action="controller/create_unite_recherche.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationUR" class="form-label">Désignation</label>
                            <input type="text" name="designationUR" id="designationUR" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="idSection" class="form-label">Section(s)</label>
                            <select name="idSection[]" id="idSection" class="form-select" multiple required>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= $section['designationSection'] ?> (<?= $section['anneeDesignation'] ?? 'Année non définie' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs sections.</small>
                            <div class="invalid-                            feedback">Veuillez sélectionner au moins une section.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addResearchUnitBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une unité de recherche -->
<div class="modal fade" id="editResearchUnitModal" tabindex="-1" role="dialog" aria-labelledby="editResearchUnitModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Unité de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editResearchUnitForm" method="POST" action="controller/edit_unite_recherche.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editIdUniteRecherche" id="editIdUniteRecherche">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editDesignationUR" class="form-label">Désignation</label>
                            <input type="text" name="editDesignationUR" id="editDesignationUR" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea name="editDescription" id="editDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="editIdSection" class="form-label">Section(s)</label>
                            <select name="editIdSection[]" id="editIdSection" class="form-select" multiple required>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= $section['designationSection'] ?> (<?= $section['anneeDesignation'] ?? 'Année non définie' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs sections.</small>
                            <div class="invalid-feedback">Veuillez sélectionner au moins une section.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editResearchUnitBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une spécialisation -->
<div class="modal fade" id="createSpecialisationModal" tabindex="-1" role="dialog" aria-labelledby="createSpecialisationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Spécialisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="specialisationForm" method="POST" action="controller/create_specialisation.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idUniteRecherche" id="idUniteRecherche">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="idSections" class="form-label">Section</label>
                            <select name="idSections" id="sectionForSpecialisation" class="form-select" required onchange="loadOrientations()">
                                <option value="">Chargement des sections...</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                        <div class="col-md-12 mt-3">
                            <label for="idOrientation" class="form-label">Orientation(s)</label>
                            <select name="idOrientation[]" id="orientationForSpecialisation" class="form-select" multiple required>
                                <option value="">Sélectionnez d'abord une section</option>
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs orientations.</small>
                            <div class="invalid-feedback">Veuillez sélectionner au moins une orientation.</div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSpecialisationBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier une spécialisation -->
<div class="modal fade" id="editSpecialisationModal" tabindex="-1" role="dialog" aria-labelledby="editSpecialisationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Spécialisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSpecialisationForm" method="POST" action="controller/edit_specialisation.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editIdSpecialisation" id="editIdSpecialisation">
                    <input type="hidden" name="editIdUniteRecherche" id="editIdUniteRecherche3"> <!-- Hidden field for research unit ID -->
                    <input type="hidden" name="editIdOrientation" id="editIdOrientation"> <!-- Hidden field for orientation ID -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editDesignation" class="form-label">Désignation</label>
                            <input type="text" name="editDesignation" id="editDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSpecialisationBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les enseignants par spécialisation -->
<div class="modal fade" id="teachersBySpecialisationModal" tabindex="-1" role="dialog" aria-labelledby="teachersBySpecialisationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enseignants par Spécialisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="specialisationTitle" class="mb-3"></h6>
                <div id="teachersList">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSpecialisations(idUniteRecherche) {
        const targetRow = document.getElementById('specialisationsList' + idUniteRecherche);
        if (targetRow) {
            if (targetRow.classList.contains('show')) {
                targetRow.classList.remove('show');
            } else {
                targetRow.classList.add('show');
            }
        }
    }

    function openEditResearchUnitModal(id, designation, description) {
        document.getElementById('editIdUniteRecherche').value = id;
        document.getElementById('editDesignationUR').value = designation;
        document.getElementById('editDescription').value = description;
        
        // Charger les sections associées à cette unité de recherche
        fetch(`controller/get_sections_by_research_unit.php?idUniteRecherche=${id}`)
            .then(response => response.json())
            .then(data => {
                const sectionSelect = document.getElementById('editIdSection');
                
                // Désélectionner toutes les options
                Array.from(sectionSelect.options).forEach(option => {
                    option.selected = false;
                });
                
                // Sélectionner les sections associées
                data.forEach(section => {
                    Array.from(sectionSelect.options).forEach(option => {
                        if (option.value == section.idsection) {
                            option.selected = true;
                        }
                    });
                });
                
                new bootstrap.Modal(document.getElementById('editResearchUnitModal')).show();
            })
            .catch(error => {
                console.error('Erreur lors du chargement des sections:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger les sections associées à cette unité de recherche.'
                });
            });
    }

    function confirmDeleteResearchUnit(idUniteRecherche) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible ! Toutes les spécialisations associées seront également supprimées.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_unite_recherche.php?idunite_recherche=' + idUniteRecherche;
            }
        });
    }

    function loadOrientations() {
    const sectionId = document.getElementById('sectionForSpecialisation').value;
    const orientationSelect = document.getElementById('orientationForSpecialisation');
    
    // Vider le sélecteur d'orientations
    orientationSelect.innerHTML = '<option value="">Chargement des orientations...</option>';
    
    if (!sectionId) {
        orientationSelect.innerHTML = '<option value="">Sélectionnez d\'abord une section</option>';
        return;
    }
    
    // Charger les orientations pour cette section
    fetch(`controller/get_orientations_by_section.php?idSection=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            orientationSelect.innerHTML = '';
            
            if (data.length === 0) {
                orientationSelect.innerHTML = '<option value="">Aucune orientation disponible</option>';
                return;
            }
            
            // Ajouter les orientations sans option par défaut puisque c'est un select multiple
            data.forEach(orientation => {
                const option = document.createElement('option');
                option.value = orientation.idorientation;
                option.textContent = orientation.designationOrientation;
                orientationSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Erreur lors du chargement des orientations:', error);
            orientationSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
        });
}


    function openSpecialisationModal(idUniteRecherche) {
        // Validation de l'ID
        if (!idUniteRecherche || idUniteRecherche <= 0) {
            console.error('ID unité de recherche invalide:', idUniteRecherche);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de l\'unité de recherche invalide.'
            });
            return;
        }
        
        document.getElementById('idUniteRecherche').value = idUniteRecherche;
        document.getElementById('designation').value = '';
        
        var sectionSelect = document.getElementById('sectionForSpecialisation');
        sectionSelect.innerHTML = '<option value="">Chargement des sections...</option>';
        document.getElementById('orientationForSpecialisation').innerHTML = '<option value="">Sélectionnez d\'abord une section</option>';
        
        // Charger les sections liées à cette unité de recherche (filtrées par année académique)
        var selectedYear = document.getElementById('annee_acad') ? document.getElementById('annee_acad').value : 0;
        var url = 'controller/get_sections_by_research_unit.php?idUniteRecherche=' + encodeURIComponent(idUniteRecherche);
        if (selectedYear > 0) {
            url += '&annee_acad=' + encodeURIComponent(selectedYear);
        }
        console.log('Fetching sections from:', url);
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                sectionSelect.innerHTML = '<option value="">Sélectionnez une section</option>';
                
                // Vérifier si data est un tableau
                if (!Array.isArray(data)) {
                    console.error('Réponse inattendue:', data);
                    sectionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    return;
                }
                
                if (data.length === 0) {
                    sectionSelect.innerHTML = '<option value="">Aucune section liée à cette UR</option>';
                    return;
                }
                
                for (var i = 0; i < data.length; i++) {
                    var section = data[i];
                    var option = document.createElement('option');
                    option.value = section.idsection;
                    option.textContent = section.designationSection + ' (' + (section.anneeDesignation || 'Année non définie') + ')';
                    sectionSelect.appendChild(option);
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des sections:', error);
                sectionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
        
        new bootstrap.Modal(document.getElementById('createSpecialisationModal')).show();
    }

    function openEditSpecialisationModal(id, designation, idUniteRecherche, idOrientation) {
        document.getElementById('editIdSpecialisation').value = id;
        document.getElementById('editDesignation').value = designation;
        document.getElementById('editIdUniteRecherche3').value = idUniteRecherche;
        document.getElementById('editIdOrientation').value = idOrientation;
        
        new bootstrap.Modal(document.getElementById('editSpecialisationModal')).show();
    }

    function confirmDeleteSpecialisation(idSpecialisation) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible ! Les enseignants ne seront plus affectés à cette spécialisation.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_specialisation.php?idSpecialisation=' + idSpecialisation;
            }
        });
    }
    
    function viewTeachersBySpecialisation(idSpecialisation, designation) {
        document.getElementById('specialisationTitle').textContent = `Enseignants pour la spécialisation: ${designation}`;
        document.getElementById('teachersList').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;
        
        // Ouvrir le modal
        new bootstrap.Modal(document.getElementById('teachersBySpecialisationModal')).show();
        
        // Charger les enseignants pour cette spécialisation
        fetch(`controller/get_teachers_by_specialisation.php?idSpecialisation=${idSpecialisation}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                
                if (data.length === 0) {
                    html = `<div class="alert alert-info">Aucun enseignant n'est affecté à cette spécialisation.</div>`;
                } else {
                    html = `
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Grade</th>
                                    <th>Date d'affectation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    data.forEach(teacher => {
                        const dateAffectation = new Date(teacher.dateAffectation).toLocaleDateString('fr-FR');
                        html += `
                            <tr>
                                <td>${teacher.noms}</td>
                                <td>${teacher.gradeDesignation || ''}</td>
                                <td>${dateAffectation}</td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="confirmRemoveTeacher(${teacher.idAffectation})">
                                        <i class="bi bi-trash"></i> Retirer
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                            </tbody>
                        </table>
                    `;
                }
                
                document.getElementById('teachersList').innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des enseignants:', error);
                document.getElementById('teachersList').innerHTML = `
                    <div class="alert alert-danger">
                        Une erreur est survenue lors du chargement des enseignants.
                    </div>
                `;
            });
    }
    
    function confirmRemoveTeacher(idAffectation) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Voulez-vous vraiment retirer cet enseignant de cette spécialisation ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, retirer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/remove_teacher_from_specialisation.php?idAffectation=${idAffectation}`;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>


