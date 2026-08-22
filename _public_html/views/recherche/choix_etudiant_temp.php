<?php
include "./views/include/header.php";

// VÃ©rification des responsabilitÃ©s de l'utilisateur connectÃ©
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// RÃ©cupÃ©rer l'annÃ©e acadÃ©mique en cours
$pdo = Connexion::getInstance()->getPDO();

// VÃ©rifier si la colonne est_active existe
$checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// VÃ©rifier si l'utilisateur est administrateur
$isAdmin = $_SESSION['idRole'] == 1;

// RÃ©cupÃ©rer les sections dont l'utilisateur est responsable
// Nous utilisons idUser pour identifier le responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE idUser = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Si l'utilisateur est admin, il n'est pas considÃ©rÃ© comme responsable de section limitÃ©
$isResponsableSection = !$isAdmin && !empty($userSections);

$search = isset($_GET['search']) ? $_GET['search'] : '';

// RÃ©cupÃ©rer tous les paramÃ¨tres de filtrage
$status = isset($_GET['status']) ? $_GET['status'] : '';
$cycle = isset($_GET['cycle']) ? $_GET['cycle'] : '';
$specialisation = isset($_GET['specialisation']) ? $_GET['specialisation'] : '';
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$hasStudent = isset($_GET['has_student']) ? $_GET['has_student'] : null;
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;

// CrÃ©er un tableau de filtres pour les logs ou rÃ©fÃ©rences futures
// Dans $filters, modifiez la ligne pour annee
$filters = [
    'status' => $status,
    'cycle' => $cycle,
    'specialisation' => $specialisation,
    'annee' => ($anneeId > 0) ? $anneeId : null,  // Utiliser null au lieu de 0
    'has_student' => $hasStudent
];




function getSujets($pdo, $search, $sections = [], $filters = []) {
    $params = [];
    
    // Construction de la requÃªte de base avec les bonnes jointures
    $query = "SELECT s.*, 
                a.designation as annee, 
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                e.idetudiant as etudiant_idetudiant,
                d.noms as directeur,
                d.idAgent as idDirecteur,
                gr_d.designation as grade_directeur,
                enc.noms as encadreur,
                enc.idAgent as idEncadreur,
                gr_e.designation as grade_encadreur,
                spec.designation as specialisation,
                sec.designationSection as section,
                o.designationOrientation as orientation
            FROM sujets s
            LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
            LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
            LEFT JOIN agent d ON s.idDirecteur = d.idAgent
            LEFT JOIN grade gr_d ON d.grade_id = gr_d.idgrade
            LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
            LEFT JOIN grade gr_e ON enc.grade_id = gr_e.idgrade
            LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
            LEFT JOIN orientation o ON spec.idorientation = o.idorientation
            LEFT JOIN section sec ON o.section_idsection = sec.idsection
            WHERE 1=1";
    
    // Filtrer par sections si spÃ©cifiÃ© ET si des sections sont fournies
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) { // VÃ©rifier que la section n'est pas vide
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= " AND o.section_idsection IN ($placeholders)";
        }
    }
    
    // Filtrage par recherche textuelle
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                        OR e.noms LIKE :search 
                        OR e.matricule LIKE :search 
                        OR d.noms LIKE :search 
                        OR spec.designation LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Filtrage par statut
    if (!empty($filters['status'])) {
        $query .= " AND s.statut_validation = :status";
        $params[':status'] = $filters['status'];
    }
    
    // Filtrage par cycle
    if (!empty($filters['cycle'])) {
        $query .= " AND s.cycle = :cycle";
        $params[':cycle'] = $filters['cycle'];
    }
    
    // Filtrage par spÃ©cialisation
    if (!empty($filters['specialisation'])) {
        $query .= " AND s.idSpecialisation = :specialisation";
        $params[':specialisation'] = $filters['specialisation'];
    }
    
    // Filtrage par annÃ©e acadÃ©mique
    if (isset($filters['annee']) && $filters['annee'] !== null) {
        $query .= " AND s.annee_acad_idannee_acad = :annee";
        $params[':annee'] = $filters['annee'];
    }
    
    // Filtrage par prÃ©sence d'Ã©tudiant
    if (isset($filters['has_student'])) {
        if ($filters['has_student'] === '1') {
            $query .= " AND s.etudiant_idetudiant IS NOT NULL";
        } elseif ($filters['has_student'] === '0') {
            $query .= " AND s.etudiant_idetudiant IS NULL";
        }
    }
    
    // Ajouter l'ordre
    $query .= " ORDER BY spec.designation, s.intitule";
    
    // DEBUG - Afficher la requÃªte (temporaire pour vÃ©rification)
    if (!empty($sections)) {
        echo "<!-- DEBUG getSujets - Sections filtrÃ©es: " . implode(', ', $sections) . " -->";
        echo "<!-- Query: $query -->";
        echo "<!-- Params: " . print_r($params, true) . " -->";
    }
    
    try {
        // ExÃ©cuter la requÃªte
        $stmt = $pdo->prepare($query);
        
        // Lier les paramÃ¨tres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG
        // echo "<!-- RÃ©sultats trouvÃ©s: " . count($result) . " -->";
        
        return $result;
    } catch (PDOException $e) {
        // Log l'erreur
        error_log("Erreur dans getSujets: " . $e->getMessage());
        echo "<!-- Erreur SQL: " . $e->getMessage() . " -->";
        return [];
    }
}


function countSujetsByStatus($pdo, $status, $sections = [], $anneeId = null) {
    $query = "SELECT COUNT(*) as count 
              FROM sujets s
              JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              JOIN orientation o ON spec.idorientation = o.idorientation
              WHERE s.statut_validation = :status";
    
    $params = [':status' => $status];
    
    // Filtrer par sections si spÃ©cifiÃ© ET si des sections sont fournies
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) {
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= " AND o.section_idsection IN ($placeholders)";
        }
    }
    
    // Filtrer par annÃ©e acadÃ©mique seulement si $anneeId > 0
    if (!empty($anneeId) && $anneeId > 0) {
        $query .= " AND s.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetchColumn();
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur dans countSujetsByStatus: " . $e->getMessage());
        echo "<!-- Erreur SQL dans countSujetsByStatus: " . $e->getMessage() . " -->";
        return 0;
    }
}





// RÃ©cupÃ©rer les sujets en fonction des droits de l'utilisateur
if ($isResponsableSection) {
    // Si une section spÃ©cifique est sÃ©lectionnÃ©e, vÃ©rifier que l'utilisateur y a accÃ¨s
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            // L'utilisateur a accÃ¨s Ã  cette section
            $sujets = getSujets($pdo, $search, [$sectionFilter], $filters);
        } else {
            // L'utilisateur n'a pas accÃ¨s Ã  cette section - afficher aucun rÃ©sultat
            $sujets = [];
        }
    } else {
        // Aucune section spÃ©cifique sÃ©lectionnÃ©e - afficher toutes les sections autorisÃ©es
        $sujets = getSujets($pdo, $search, $userSections, $filters);
    }
} else {
    // VÃ©rifier si l'utilisateur a le droit d'accÃ©der Ã  toutes les donnÃ©es
    $hasFullAccess = $_SESSION['idRole'] == 1; // Supposons que le rÃ´le 1 est administrateur

    if ($hasFullAccess) {
        // Appliquer les filtres mÃªme pour l'administrateur
        if ($sectionFilter > 0) {
            $sujets = getSujets($pdo, $search, [$sectionFilter], $filters);
        } else {
            // CORRECTION : Ne pas passer de sections pour voir tous les sujets
            $sujets = getSujets($pdo, $search, [], $filters);
        }
    } else {
        // Rediriger l'utilisateur sans accÃ¨s
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'AccÃ¨s refusÃ©',
                text: 'Vous n\'avez pas les droits pour accÃ©der Ã  cette page.'
            }).then(() => {
                window.location.href = 'index';
            });
        </script>";
        include "./views/include/footer.php"; 
        exit;
    }
}


// RÃ©cupÃ©rer les statistiques filtrÃ©es par les sections de l'utilisateur si nÃ©cessaire
if ($isResponsableSection) {
    // Pour les responsables de section, filtrer par leurs sections
    $sectionsForStats = [];
    
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            // L'utilisateur a accÃ¨s Ã  cette section
            $sectionsForStats = [$sectionFilter];
        } else {
            // L'utilisateur n'a pas accÃ¨s Ã  cette section - statistiques Ã  zÃ©ro
            $sectionsForStats = [];
        }
    } else {
        // Toutes les sections de l'utilisateur
        $sectionsForStats = $userSections;
    }
    
    $statsAttente = countSujetsByStatus($pdo, 'En attente', $sectionsForStats, $anneeId);
    $statsValides = countSujetsByStatus($pdo, 'ValidÃ©', $sectionsForStats, $anneeId);
    $statsRejetes = countSujetsByStatus($pdo, 'A reformulÃ©', $sectionsForStats, $anneeId);
    $statsModifies = countSujetsByStatus($pdo, 'ModifiÃ©', $sectionsForStats, $anneeId);
} else {
    // Pour les autres utilisateurs (admin), voir toutes les statistiques
    // CORRECTION : VÃ©rifier si l'utilisateur a accÃ¨s complet
    $hasFullAccess = $_SESSION['idRole'] == 1;
    
    if ($hasFullAccess) {
        // Si une section spÃ©cifique est sÃ©lectionnÃ©e, filtrer par cette section
        $sectionsForStats = ($sectionFilter > 0) ? [$sectionFilter] : [];
        
        $statsAttente = countSujetsByStatus($pdo, 'En attente', $sectionsForStats, $anneeId);
        $statsValides = countSujetsByStatus($pdo, 'ValidÃ©', $sectionsForStats, $anneeId);
        $statsRejetes = countSujetsByStatus($pdo, 'A reformulÃ©', $sectionsForStats, $anneeId);
        $statsModifies = countSujetsByStatus($pdo, 'ModifiÃ©', $sectionsForStats, $anneeId);
    } else {
        // Pas d'accÃ¨s, statistiques Ã  zÃ©ro
        $statsAttente = $statsValides = $statsRejetes = $statsModifies = 0;
    }
}




$totalSujets = $statsAttente + $statsValides + $statsRejetes + $statsModifies;

// RÃ©cupÃ©rer les donnÃ©es nÃ©cessaires
// AnnÃ©es acadÃ©miques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// SpÃ©cialisations
$querySpec = "SELECT * FROM specialisation ORDER BY designation";
$stmtSpec = $pdo->prepare($querySpec);
$stmtSpec->execute();
$specialisations = $stmtSpec->fetchAll(PDO::FETCH_ASSOC);

// Si l'utilisateur est responsable de section, limiter les sections disponibles
if ($isResponsableSection) {
    $sections = [];
    $sectionPlaceholders = implode(',', array_fill(0, count($userSections), '?'));
    $querySection = "SELECT * FROM section WHERE idsection IN ($sectionPlaceholders) ORDER BY designationSection";
    $stmtSection = $pdo->prepare($querySection);
    foreach ($userSections as $i => $section) {
        $stmtSection->bindValue($i+1, $section);
    }
    $stmtSection->execute();
    $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
} else {
    // RÃ©cupÃ©rer toutes les sections
    $querySection = "SELECT * FROM section ORDER BY designationSection";
    $stmtSection = $pdo->prepare($querySection);
    $stmtSection->execute();
    $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
}




?>

<!-- DÃ©but du HTML -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>VALIDATION DES SUJETS PAR LA COMMISSION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Validation des Sujets</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">

        <?php if ($anneeId): ?>
        <div class="alert alert-primary">
            <i class="bi bi-calendar-check me-2"></i>
            Vous consultez les donnÃ©es de l'annÃ©e acadÃ©mique: 
            <strong>
                <?php 
                $selectedYear = array_filter($academicYears, function($year) use ($anneeId) {
                    return $year['idannee_acad'] == $anneeId;
                });
                echo !empty($selectedYear) ? reset($selectedYear)['designation'] : '';
                ?>
            </strong>
            <a href="?view=recherche/choix_etudiant" class="btn btn-sm btn-outline-primary float-end">
                <i class="bi bi-x-circle"></i> RÃ©initialiser
            </a>
        </div>
        <?php endif; ?>

        <!-- Informations sur les sections gÃ©rÃ©es -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Vous visualisez uniquement les sujets des Ã©tudiants relevant de votre responsabilitÃ©.
            <?php if (count($userSections) > 0): ?>
                <strong>Sections:</strong> 
                <?php 
                $sectionNames = [];
                foreach ($sections as $section) {
                    $sectionNames[] = $section['designationSection'];
                }
                echo implode(', ', $sectionNames);
                ?>
            <?php endif; ?>
        </div>
        <?php elseif ($isAdmin): ?>
        <div class="alert alert-success">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Mode Administrateur:</strong> Vous avez accÃ¨s Ã  toutes les sections et donnÃ©es du systÃ¨me.
        </div>
        <?php endif; ?>


        

        <!-- Statistiques globales -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques gÃ©nÃ©rales des sujets</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total des sujets</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-journal-text"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $totalSujets ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Sujets validÃ©s</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-check-circle"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsValides ?></h6>
                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= $totalSujets > 0 ? round(($statsValides / $totalSujets) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">En attente</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-hourglass-split"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsAttente ?></h6>
                                                <span class="text-warning small pt-1 fw-bold">
                                                    <?= $totalSujets > 0 ? round(($statsAttente / $totalSujets) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Sujets Ã  reformuler</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-x-circle"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsRejetes ?></h6>
                                                <span class="text-danger small pt-1 fw-bold">
                                                    <?= $totalSujets > 0 ? round(($statsRejetes / $totalSujets) * 100) : 0 ?>%
                                                </span>
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

        <!-- Statistiques par cycle -->
        <?php 
        // Fonction pour rÃ©cupÃ©rer les statistiques par cycle (rÃ©utilise la logique existante)
        function countSujetsByStatusAndCycle($pdo, $status, $cycle, $sections = [], $anneeId = null) {
            $query = "SELECT COUNT(*) as count 
                      FROM sujets s
                      JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
                      JOIN orientation o ON spec.idorientation = o.idorientation
                      WHERE s.statut_validation = :status AND s.cycle = :cycle";
            
            $params = [':status' => $status, ':cycle' => $cycle];
            
            // Filtrer par sections si spÃ©cifiÃ© ET si des sections sont fournies
            if (!empty($sections) && is_array($sections)) {
                $sectionParams = [];
                foreach ($sections as $i => $section) {
                    if (!empty($section)) {
                        $paramName = ":section{$i}";
                        $sectionParams[] = $paramName;
                        $params[$paramName] = $section;
                    }
                }
                
                if (!empty($sectionParams)) {
                    $placeholders = implode(',', $sectionParams);
                    $query .= " AND o.section_idsection IN ($placeholders)";
                }
            }
            
            // Filtrer par annÃ©e acadÃ©mique seulement si $anneeId > 0
            if (!empty($anneeId) && $anneeId > 0) {
                $query .= " AND s.annee_acad_idannee_acad = :anneeId";
                $params[':anneeId'] = $anneeId;
            }
            
            try {
                $stmt = $pdo->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $result = $stmt->fetchColumn();
                
                return $result;
            } catch (PDOException $e) {
                error_log("Erreur dans countSujetsByStatusAndCycle: " . $e->getMessage());
                return 0;
            }
        }

        function getStatistiquesParCycle($pdo, $sections = [], $anneeId = null) {
            $cycles = ['Premier', 'Deuxieme', 'Troisieme'];
            $statuts = ['En attente', 'ValidÃ©', 'A reformulÃ©', 'ModifiÃ©'];
            $statistiques = [];
            
            foreach ($cycles as $cycleValue) {
                $stats = [];
                $total = 0;
                
                foreach ($statuts as $statut) {
                    $count = countSujetsByStatusAndCycle($pdo, $statut, $cycleValue, $sections, $anneeId);
                    $stats[strtolower(str_replace(['Ã©', 'Ã‰', ' '], ['e', 'E', '_'], $statut))] = $count;
                    $total += $count;
                }
                
                $statistiques[$cycleValue] = [
                    'total' => $total,
                    'valides' => $stats['valide'],
                    'attente' => $stats['en_attente'],
                    'rejetes' => $stats['a_reformule'],
                    'modifies' => $stats['modifie']
                ];
            }
            
            return $statistiques;
        }

        // RÃ©cupÃ©rer les statistiques par cycle
        if ($isResponsableSection) {
            $sectionsForStats = [];
            if ($sectionFilter > 0) {
                if (in_array($sectionFilter, $userSections)) {
                    $sectionsForStats = [$sectionFilter];
                }
            } else {
                $sectionsForStats = $userSections;
            }
            $statsCycles = getStatistiquesParCycle($pdo, $sectionsForStats, $anneeId);
        } else {
            $hasFullAccess = $_SESSION['idRole'] == 1;
            if ($hasFullAccess) {
                $sectionsForStats = ($sectionFilter > 0) ? [$sectionFilter] : [];
                $statsCycles = getStatistiquesParCycle($pdo, $sectionsForStats, $anneeId);
            } else {
                $statsCycles = [
                    'Premier' => ['total' => 0, 'valides' => 0, 'attente' => 0, 'rejetes' => 0, 'modifies' => 0],
                    'Deuxieme' => ['total' => 0, 'valides' => 0, 'attente' => 0, 'rejetes' => 0, 'modifies' => 0],
                    'Troisieme' => ['total' => 0, 'valides' => 0, 'attente' => 0, 'rejetes' => 0, 'modifies' => 0]
                ];
            }
        }
        ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques par cycle</h5>
                        <div class="row">
                            <!-- Licence (Premier cycle) -->
                            <div class="col-md-4">
                                <div class="card border-primary mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Licence (Premier cycle)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?= $statsCycles['Premier']['total'] ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?= $statsCycles['Premier']['valides'] ?></h4>
                                                <small class="text-muted">ValidÃ©s</small>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-4">
                                                <span class="text-warning"><?= $statsCycles['Premier']['attente'] ?></span>
                                                <br><small>Attente</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-danger"><?= $statsCycles['Premier']['rejetes'] ?></span>
                                                <br><small>A reformuler</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-info"><?= $statsCycles['Premier']['modifies'] ?></span>
                                                <br><small>ModifiÃ©s</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Master (DeuxiÃ¨me cycle) -->
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-award me-2"></i>Master (DeuxiÃ¨me cycle)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?= $statsCycles['Deuxieme']['total'] ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?= $statsCycles['Deuxieme']['valides'] ?></h4>
                                                <small class="text-muted">ValidÃ©s</small>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-4">
                                                <span class="text-warning"><?= $statsCycles['Deuxieme']['attente'] ?></span>
                                                <br><small>Attente</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-danger"><?= $statsCycles['Deuxieme']['rejetes'] ?></span>
                                                <br><small>A reformuler</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-info"><?= $statsCycles['Deuxieme']['modifies'] ?></span>
                                                <br><small>ModifiÃ©s</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Doctorat (TroisiÃ¨me cycle) -->
                            <div class="col-md-4">
                                <div class="card border-warning mb-3">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="bi bi-journal-bookmark me-2"></i>Doctorat (TroisiÃ¨me cycle)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?= $statsCycles['Troisieme']['total'] ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?= $statsCycles['Troisieme']['valides'] ?></h4>
                                                <small class="text-muted">ValidÃ©s</small>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-4">
                                                <span class="text-warning"><?= $statsCycles['Troisieme']['attente'] ?></span>
                                                <br><small>Attente</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-danger"><?= $statsCycles['Troisieme']['rejetes'] ?></span>
                                                <br><small>A reformuler</small>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-info"><?= $statsCycles['Troisieme']['modifies'] ?></span>
                                                <br><small>ModifiÃ©s</small>
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Validation des sujets de recherche
                            <div class="float-end">
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="bi bi-file-excel"></i> Exporter
                                </button>
                            </div>
                        </h5>

                        <!-- Remplacer le formulaire de filtrage existant par celui-ci -->
                        <div class="row mb-4">
                            <div class="col-lg-12">
                                <form method="GET" action="" class="row g-3" id="filterForm">
                                    <input type="hidden" name="view" value="recherche/choix_etudiant">
                                    
                                    <!-- Nouveau sÃ©lecteur d'annÃ©e acadÃ©mique -->
                                    <div class="col-md-2">
                                        <select name="annee" class="form-select" id="anneeFilter">
                                            <option value="">Toutes les annÃ©es</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId == $year['idannee_acad'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($year['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher...">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="status" class="form-select">
                                            <option value="">Tous les statuts</option>
                                            <option value="En attente" <?= $status == 'En attente' ? 'selected' : '' ?>>En attente</option>
                                            <option value="ValidÃ©" <?= $status == 'ValidÃ©' ? 'selected' : '' ?>>ValidÃ©</option>
                                            <option value="A reformulÃ©" <?= $status == 'A reformulÃ©' ? 'selected' : '' ?>>A reformulÃ©</option>
                                            <option value="ModifiÃ©" <?= $status == 'ModifiÃ©' ? 'selected' : '' ?>>ModifiÃ©</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="cycle" class="form-select">
                                            <option value="">Tous les cycles</option>
                                            <option value="Premier" <?= $cycle == 'Premier' ? 'selected' : '' ?>>Licence</option>
                                            <option value="Deuxieme" <?= $cycle == 'Deuxieme' ? 'selected' : '' ?>>Master</option>
                                            <option value="Troisieme" <?= $cycle == 'Troisieme' ? 'selected' : '' ?>>Doctorat</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="section" class="form-select">
                                            <option value="">Toutes les sections</option>
                                            <?php foreach ($sections as $sec): ?>
                                                <option value="<?= $sec['idsection'] ?>" <?= $sectionFilter == $sec['idsection'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sec['designationSection']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="has_student" class="form-select">
                                            <option value="">Tous les sujets</option>
                                            <option value="1" <?= $hasStudent === '1' ? 'selected' : '' ?>>Avec Ã©tudiant</option>
                                            <option value="0" <?= $hasStudent === '0' ? 'selected' : '' ?>>Sans Ã©tudiant</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>


                        <!-- Tableau des sujets organisÃ©s par cycle -->
                        <div class="table-responsive">
                            <?php if (empty($sujets)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucun sujet trouvÃ© avec les critÃ¨res spÃ©cifiÃ©s.
                                </div>
                            <?php else: 
                                // Organiser les sujets par cycle
                                $sujetsParCycle = [
                                    'Premier' => [],
                                    'Deuxieme' => [],
                                    'Troisieme' => []
                                ];
                                
                                foreach ($sujets as $sujet) {
                                    $cycle = $sujet['cycle'] ?? 'Premier';
                                    $sujetsParCycle[$cycle][] = $sujet;
                                }
                                
                                $cycleNames = [
                                    'Premier' => 'Licence (Premier cycle)',
                                    'Deuxieme' => 'Master (DeuxiÃ¨me cycle)', 
                                    'Troisieme' => 'Doctorat (TroisiÃ¨me cycle)'
                                ];
                                
                                $cycleIcons = [
                                    'Premier' => 'bi-mortarboard',
                                    'Deuxieme' => 'bi-award',
                                    'Troisieme' => 'bi-journal-bookmark'
                                ];
                                
                                $cycleColors = [
                                    'Premier' => 'primary',
                                    'Deuxieme' => 'success',
                                    'Troisieme' => 'warning'
                                ];
                                
                                $globalIndex = 1;
                                
                                foreach ($sujetsParCycle as $cycleKey => $sujetsOfCycle):
                                    if (empty($sujetsOfCycle)) continue;
                            ?>
                                <div class="mb-4">
                                    <div class="card border-<?= $cycleColors[$cycleKey] ?>">
                                        <div class="card-header bg-<?= $cycleColors[$cycleKey] ?> text-white">
                                            <h5 class="mb-0">
                                                <i class="<?= $cycleIcons[$cycleKey] ?> me-2"></i>
                                                <?= $cycleNames[$cycleKey] ?>
                                                <span class="badge bg-light text-dark ms-2"><?= count($sujetsOfCycle) ?> sujet(s)</span>
                                            </h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-striped table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>IntitulÃ©</th>
                                                        <th>SpÃ©cialisation</th>
                                                        <th>Ã‰tat</th>
                                                        <th>Ã‰tudiant</th>
                                                        <th>Directeur</th>
                                                        <th>AnnÃ©e</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($sujetsOfCycle as $sujet): 
                                                        // DÃ©finir la classe de la cellule Ã‰tat selon le statut
                                                        $etatClass = '';
                                                        switch ($sujet['statut_validation']) {
                                                            case 'En attente':
                                                                $etatClass = 'text-warning';
                                                                break;
                                                            case 'ValidÃ©':
                                                                $etatClass = 'text-success';
                                                                break;
                                                            case 'A reformulÃ©':
                                                                $etatClass = 'text-danger';
                                                                break;
                                                            case 'ModifiÃ©':
                                                                $etatClass = 'text-primary';
                                                                break;
                                                        }

                                                        // VÃ©rifier s'il y a des propositions de reformulation pour ce sujet
                                                        $hasReformulationProposals = false;
                                                        if ($sujet['statut_validation'] == 'A reformulÃ©') {
                                                            $queryReformulations = "SELECT COUNT(*) as count FROM sujet_reformulations 
                                                                                   WHERE idsujets = :sujet_id AND statut_reformulation = 'En attente'";
                                                            $stmtReformulations = $pdo->prepare($queryReformulations);
                                                            $stmtReformulations->execute(['sujet_id' => $sujet['idsujets']]);
                                                            $reformulationCount = $stmtReformulations->fetchColumn();
                                                            $hasReformulationProposals = $reformulationCount > 0;
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td><?= $globalIndex ?></td>
                                                            <td><?= htmlspecialchars($sujet['intitule']) ?></td>
                                                            <td><?= htmlspecialchars($sujet['specialisation']) ?></td>
                                                            <td class="<?= $etatClass ?>">
                                                                <?= htmlspecialchars($sujet['statut_validation']) ?>
                                                                <?php if ($hasReformulationProposals): ?>
                                                                    <span class='badge bg-info ms-2' title='Proposition de reformulation en attente'>
                                                                        <i class='bi bi-lightbulb'></i> Nouvelle proposition
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                if (!empty($sujet['etudiant'])) {
                                                                    echo htmlspecialchars($sujet['etudiant']);
                                                                    if (!empty($sujet['matricule_etudiant'])) {
                                                                        echo ' (' . htmlspecialchars($sujet['matricule_etudiant']) . ')';
                                                                    }
                                                                } else {
                                                                    echo '<span class="text-muted">Non assignÃ©</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                if (!empty($sujet['directeur'])) {
                                                                    echo (!empty($sujet['grade_directeur']) ? htmlspecialchars($sujet['grade_directeur']) . ' ' : '') . 
                                                                        htmlspecialchars($sujet['directeur']);
                                                                } else {
                                                                    echo '<span class="text-muted">Non assignÃ©</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($sujet['annee']) ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info" onclick="viewDetails(<?= $sujet['idsujets'] ?>)">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                
                                                                <?php if ($hasReformulationProposals): ?>
                                                                <button class="btn btn-sm btn-warning" onclick="viewReformulationProposals(<?= $sujet['idsujets'] ?>)" title="Voir les propositions de reformulation">
                                                                    <i class="bi bi-lightbulb"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($sujet['statut_validation'] == 'En attente' || $sujet['statut_validation'] == 'ModifiÃ©'): ?>
                                                                <button class="btn btn-sm btn-success" onclick="validateSujet(<?= $sujet['idsujets'] ?>, '<?= htmlspecialchars(addslashes($sujet['intitule']), ENT_QUOTES) ?>')">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" onclick="rejectSujet(<?= $sujet['idsujets'] ?>, '<?= htmlspecialchars(addslashes($sujet['intitule']), ENT_QUOTES) ?>')">
                                                                    <i class="bi bi-x"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php $globalIndex++; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour l'exportation -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exporter les Sujets de Recherche                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/export_sujets.php">
                    <div class="mb-3">
                        <label for="annee_export" class="form-label">SÃ©lectionner l'annÃ©e acadÃ©mique</label>
                        <select name="annee_export" id="annee_export" class="form-control" required>
                            <option value="">SÃ©lectionner une annÃ©e acadÃ©mique</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($sections)): ?>
                    <div class="mb-3">
                        <label for="section_export" class="form-label">Section</label>
                        <select name="section_export[]" id="section_export" class="form-control" multiple>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['idsection'] ?>"><?= htmlspecialchars($sec['designationSection']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Maintenez la touche Ctrl (ou Cmd) pour sÃ©lectionner plusieurs sections.<br>
                            <strong>Si aucune section n'est sÃ©lectionnÃ©e, toutes vos sections autorisÃ©es seront exportÃ©es.</strong>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="status_export" class="form-label">Statut des sujets</label>
                        <select name="status_export" id="status_export" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="En attente">En attente</option>
                            <option value="ValidÃ©">ValidÃ©s</option>
                            <option value="A reformulÃ©">A reformulÃ©</option>
                            <option value="ModifiÃ©">ModifiÃ©s</option>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-file-excel"></i> Exporter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les dÃ©tails du sujet -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">DÃ©tails du sujet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sujetDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p>Chargement des dÃ©tails...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour valider un sujet -->
<div class="modal fade" id="validateModal" tabindex="-1" role="dialog" aria-labelledby="validateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valider ce sujet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="validateForm" method="POST" action="controller/validation_sujets.php">
                <input type="hidden" name="action" value="validate">
                <input type="hidden" name="sujet_id" id="validate_sujet_id">
                
                <div class="modal-body">
                    <p>Vous Ãªtes sur le point de valider le sujet : <strong id="validate_sujet_title"></strong></p>
                    
                    <div class="mb-3">
                        <label for="validate_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="validate_comment" name="commentaire" rows="3" placeholder="Ajouter un commentaire facultatif..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Valider le sujet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour rejeter un sujet -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Demander une reformulation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectForm" method="POST" action="controller/validation_sujets.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="sujet_id" id="reject_sujet_id">
                
                <div class="modal-body">
                    <p>Vous Ãªtes sur le point de demander une reformulation pour le sujet : <strong id="reject_sujet_title"></strong></p>
                    
                    <div class="mb-3">
                        <label for="reject_comment" class="form-label">Motif de la reformulation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_comment" name="commentaire" rows="3" required placeholder="Expliquez les points Ã  reformuler..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Demander une reformulation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir les propositions de reformulation -->
<div class="modal fade" id="reformulationProposalsModal" tabindex="-1" role="dialog" aria-labelledby="reformulationProposalsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightbulb text-info me-2"></i>
                    Propositions de reformulation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="reformulationProposalsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p>Chargement des propositions...</p>
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
// Fonction pour voir les dÃ©tails d'un sujet
function viewDetails(sujetId) {
    // Afficher le modal et son loader
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    detailsModal.show();
    
    // RÃ©cupÃ©rer les dÃ©tails via AJAX
    fetch(`controller/get_sujet_detail.php?id=${sujetId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur rÃ©seau');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Formater et afficher les donnÃ©es
            let html = `
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(data.intitule)}</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Cycle :</strong> ${formatCycle(data.cycle)}</p>
                                <p><strong>SpÃ©cialisation :</strong> ${escapeHtml(data.specialisation)}</p>
                                <p><strong>Section :</strong> ${escapeHtml(data.section)}</p>
                                <p><strong>AnnÃ©e acadÃ©mique :</strong> ${escapeHtml(data.annee)}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Statut :</strong> <span class="${getStatusClass(data.statut_validation)}">${escapeHtml(data.statut_validation)}</span></p>
                                <p><strong>Date de crÃ©ation :</strong> ${data.date_creation || 'Non disponible'}</p>
                                <p><strong>DerniÃ¨re modification :</strong> ${data.date_validation || 'Non disponible'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Ã‰tudiant</h6>
                            </div>
                            <div class="card-body">
                                ${data.etudiant 
                                    ? `<p><strong>Nom :</strong> ${escapeHtml(data.etudiant)}</p>
                                       ${data.matricule_etudiant ? `<p><strong>Matricule :</strong> ${escapeHtml(data.matricule_etudiant)}</p>` : ''}` 
                                    : '<p class="text-muted">Aucun Ã©tudiant assignÃ©</p>'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Directeur</h6>
                            </div>
                            <div class="card-body">
                                ${data.directeur 
                                    ? `<p><strong>Nom :</strong> ${data.grade_directeur ? escapeHtml(data.grade_directeur) + ' ' : ''}${escapeHtml(data.directeur)}</p>` 
                                    : '<p class="text-muted">Aucun directeur assignÃ©</p>'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Encadreur</h6>
                            </div>
                            <div class="card-body">
                                ${data.encadreur 
                                    ? `<p><strong>Nom :</strong> ${data.grade_encadreur ? escapeHtml(data.grade_encadreur) + ' ' : ''}${escapeHtml(data.encadreur)}</p>` 
                                    : '<p class="text-muted">Aucun encadreur assignÃ©</p>'}
                            </div>
                        </div>
                    </div>
                </div>
                
                ${data.commentaire_commission 
                    ? `<div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Commentaire de la commission</h6>
                        </div>
                        <div class="card-body">
                            <p>${escapeHtml(data.commentaire_commission)}</p>
                        </div>
                       </div>` 
                    : ''}
            `;
            
            document.getElementById('sujetDetails').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('sujetDetails').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Erreur lors du chargement des dÃ©tails: ${error.message}
                </div>
            `;
        });
}

// Fonctions pour les modals de validation et de rejet
function validateSujet(sujetId, titre) {
    document.getElementById('validate_sujet_id').value = sujetId;
    document.getElementById('validate_sujet_title').textContent = titre;
    const validateModal = new bootstrap.Modal(document.getElementById('validateModal'));
    validateModal.show();
}

function rejectSujet(sujetId, titre) {
    document.getElementById('reject_sujet_id').value = sujetId;
    document.getElementById('reject_sujet_title').textContent = titre;
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModal.show();
}

// Fonctions utilitaires
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatCycle(cycle) {
    switch(cycle) {
        case 'Premier': return 'Licence';
        case 'Deuxieme': return 'Master';
        case 'Troisieme': return 'Doctorat';
        default: return cycle;
    }
}

function getStatusClass(status) {
    switch(status) {
        case 'En attente': return 'text-warning';
        case 'ValidÃ©': return 'text-success';
        case 'A reformulÃ©': return 'text-danger';
        case 'ModifiÃ©': return 'text-primary';
        default: return '';
    }
}

// Initialiser les sÃ©lecteurs multiples avec Select2
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('#section_export').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'SÃ©lectionner une ou plusieurs sections'
        });
    }
});

// Ajouter Ã  l'intÃ©rieur de la balise script existante
document.addEventListener('DOMContentLoaded', function() {
    // Soumettre automatiquement le formulaire lorsque l'annÃ©e acadÃ©mique change
    document.getElementById('anneeFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

</script>



<?php include "./views/include/footer_file.php"; ?>
 
 < s c r i p t > 
 / /   F o n c t i o n   p o u r   v o i r   l e s   p r o p o s i t i o n s   d e   r e f o r m u l a t i o n 
 f u n c t i o n   v i e w R e f o r m u l a t i o n P r o p o s a l s ( s u j e t I d )   { 
         / /   A f f i c h e r   l e   m o d a l   e t   s o n   l o a d e r 
         c o n s t   r e f o r m u l a t i o n M o d a l   =   n e w   b o o t s t r a p . M o d a l ( d o c u m e n t . g e t E l e m e n t B y I d ( " r e f o r m u l a t i o n P r o p o s a l s M o d a l " ) ) ; 
         r e f o r m u l a t i o n M o d a l . s h o w ( ) ; 
         
         / /   R é c u p é r e r   l e s   p r o p o s i t i o n s   v i a   A J A X 
         f e t c h ( ` c o n t r o l l e r / g e t _ s u j e t _ r e f o r m u l a t i o n s . p h p ? s u j e t _ i d = $ { s u j e t I d } ` ) 
                 . t h e n ( r e s p o n s e   = >   { 
                         i f   ( ! r e s p o n s e . o k )   { 
                                 t h r o w   n e w   E r r o r ( " E r r e u r   r é s e a u " ) ; 
                         } 
                         r e t u r n   r e s p o n s e . j s o n ( ) ; 
                 } ) 
                 . t h e n ( d a t a   = >   { 
                         i f   ( d a t a . e r r o r )   { 
                                 t h r o w   n e w   E r r o r ( d a t a . e r r o r ) ; 
                         } 
                         
                         / /   F o r m a t e r   e t   a f f i c h e r   l e s   d o n n é e s 
                         l e t   h t m l   =   " " ; 
                         
                         i f   ( d a t a . r e f o r m u l a t i o n s   & &   d a t a . r e f o r m u l a t i o n s . l e n g t h   >   0 )   { 
                                 h t m l   + =   " < h 6   c l a s s = \ " m b - 3 \ " > < i   c l a s s = \ " b i   b i - l i g h t b u l b   m e - 2 \ " > < / i > P r o p o s i t i o n s   d e   r e f o r m u l a t i o n < / h 6 > " ; 
                                 
                                 d a t a . r e f o r m u l a t i o n s . f o r E a c h ( r e f o r m u l a t i o n   = >   { 
                                         c o n s t   s t a t u s C l a s s   =   { 
                                                 " E n   a t t e n t e " :   " w a r n i n g " , 
                                                 " A c c e p t é e " :   " s u c c e s s " , 
                                                 " R e f u s é e " :   " d a n g e r " 
                                         } [ r e f o r m u l a t i o n . s t a t u t _ r e f o r m u l a t i o n ]   | |   " s e c o n d a r y " ; 
                                         
                                         c o n s t   d a t e P r o p o s i t i o n   =   n e w   D a t e ( r e f o r m u l a t i o n . d a t e _ p r o p o s i t i o n ) . t o L o c a l e D a t e S t r i n g ( " f r - F R " ,   { 
                                                 y e a r :   " n u m e r i c " , 
                                                 m o n t h :   " l o n g " , 
                                                 d a y :   " n u m e r i c " , 
                                                 h o u r :   " 2 - d i g i t " , 
                                                 m i n u t e :   " 2 - d i g i t " 
                                         } ) ; 
                                         
                                         h t m l   + =   ` 
                                                 < d i v   c l a s s = " c a r d   m b - 3   b o r d e r - $ { s t a t u s C l a s s } " > 
                                                         < d i v   c l a s s = " c a r d - h e a d e r   d - f l e x   j u s t i f y - c o n t e n t - b e t w e e n   a l i g n - i t e m s - c e n t e r " > 
                                                                 < h 6   c l a s s = " m b - 0 " > P r o p o s i t i o n   d u   $ { d a t e P r o p o s i t i o n } < / h 6 > 
                                                                 < s p a n   c l a s s = " b a d g e   b g - $ { s t a t u s C l a s s } " > $ { r e f o r m u l a t i o n . s t a t u t _ r e f o r m u l a t i o n } < / s p a n > 
                                                         < / d i v > 
                                                         < d i v   c l a s s = " c a r d - b o d y " > 
                                                                 < d i v   c l a s s = " r o w " > 
                                                                         < d i v   c l a s s = " c o l - m d - 6 " > 
                                                                                 < s t r o n g > N o u v e l   i n t i t u l é   p r o p o s é : < / s t r o n g > < b r > 
                                                                                 < p   c l a s s = " t e x t - p r i m a r y " > $ { r e f o r m u l a t i o n . i n t i t u l e _ p r o p o s e } < / p > 
                                                                         < / d i v > 
                                                                         < d i v   c l a s s = " c o l - m d - 6 " > 
                                                                                 $ { r e f o r m u l a t i o n . s p e c i a l i s a t i o n _ n o m   ?   ` 
                                                                                         < s t r o n g > S p é c i a l i s a t i o n : < / s t r o n g > < b r > 
                                                                                         < p > $ { r e f o r m u l a t i o n . s p e c i a l i s a t i o n _ n o m } < / p > 
                                                                                 `   :   " " } 
                                                                         < / d i v > 
                                                                 < / d i v > 
                                                                 
                                                                 $ { r e f o r m u l a t i o n . d i r e c t e u r _ n o m   ?   ` 
                                                                         < d i v   c l a s s = " r o w " > 
                                                                                 < d i v   c l a s s = " c o l - m d - 6 " > 
                                                                                         < s t r o n g > D i r e c t e u r   p r o p o s é : < / s t r o n g > < b r > 
                                                                                         < p > $ { r e f o r m u l a t i o n . d i r e c t e u r _ n o m } < / p > 
                                                                                 < / d i v > 
                                                                                 $ { r e f o r m u l a t i o n . e n c a d r e u r _ n o m   ?   ` 
                                                                                         < d i v   c l a s s = " c o l - m d - 6 " > 
                                                                                                 < s t r o n g > E n c a d r e u r   p r o p o s é : < / s t r o n g > < b r > 
                                                                                                 < p > $ { r e f o r m u l a t i o n . e n c a d r e u r _ n o m } < / p > 
                                                                                         < / d i v > 
                                                                                 `   :   " " } 
                                                                         < / d i v > 
                                                                 `   :   " " } 
                                                                 
                                                                 < d i v   c l a s s = " m t - 3 " > 
                                                                         < s t r o n g > J u s t i f i c a t i o n   d e   l   é t u d i a n t : < / s t r o n g > 
                                                                         < d i v   c l a s s = " p - 2   b g - l i g h t   r o u n d e d   m t - 1 " > 
                                                                                 $ { r e f o r m u l a t i o n . j u s t i f i c a t i o n _ e t u d i a n t . r e p l a c e ( / \ n / g ,   " < b r > " ) } 
                                                                         < / d i v > 
                                                                 < / d i v > 
                                                                 
                                                                 $ { r e f o r m u l a t i o n . c o m m e n t a i r e _ r e p o n s e   ?   ` 
                                                                         < d i v   c l a s s = " a l e r t   a l e r t - $ { s t a t u s C l a s s   = = =   " s u c c e s s "   ?   " s u c c e s s "   :   " d a n g e r " }   m t - 3 " > 
                                                                                 < s t r o n g > R é p o n s e   d e   l   a d m i n i s t r a t i o n : < / s t r o n g > < b r > 
                                                                                 $ { r e f o r m u l a t i o n . c o m m e n t a i r e _ r e p o n s e . r e p l a c e ( / \ n / g ,   " < b r > " ) } 
                                                                                 $ { r e f o r m u l a t i o n . d a t e _ t r a i t e m e n t   ?   ` 
                                                                                         < b r > < s m a l l   c l a s s = " t e x t - m u t e d " > L e   $ { n e w   D a t e ( r e f o r m u l a t i o n . d a t e _ t r a i t e m e n t ) . t o L o c a l e D a t e S t r i n g ( " f r - F R " ) } < / s m a l l > 
                                                                                 `   :   " " } 
                                                                         < / d i v > 
                                                                 `   :   " " } 
                                                                 
                                                                 $ { r e f o r m u l a t i o n . s t a t u t _ r e f o r m u l a t i o n   = = =   " E n   a t t e n t e "   ?   ` 
                                                                         < d i v   c l a s s = " m t - 3 " > 
                                                                                 < b u t t o n   c l a s s = " b t n   b t n - s u c c e s s   b t n - s m   m e - 2 "   o n c l i c k = " a p p r o v e R e f o r m u l a t i o n ( $ { r e f o r m u l a t i o n . i d _ r e f o r m u l a t i o n } ) " > 
                                                                                         < i   c l a s s = " b i   b i - c h e c k - c i r c l e " > < / i >   A p p r o u v e r 
                                                                                 < / b u t t o n > 
                                                                                 < b u t t o n   c l a s s = " b t n   b t n - d a n g e r   b t n - s m "   o n c l i c k = " r e j e c t R e f o r m u l a t i o n ( $ { r e f o r m u l a t i o n . i d _ r e f o r m u l a t i o n } ) " > 
                                                                                         < i   c l a s s = " b i   b i - x - c i r c l e " > < / i >   R e f u s e r 
                                                                                 < / b u t t o n > 
                                                                         < / d i v > 
                                                                 `   :   " " } 
                                                         < / d i v > 
                                                 < / d i v > 
                                         ` ; 
                                 } ) ; 
                         }   e l s e   { 
                                 h t m l   =   " < d i v   c l a s s = \ " a l e r t   a l e r t - i n f o \ " > < i   c l a s s = \ " b i   b i - i n f o - c i r c l e   m e - 2 \ " > < / i > A u c u n e   p r o p o s i t i o n   d e   r e f o r m u l a t i o n   t r o u v é e   p o u r   c e   s u j e t . < / d i v > " ; 
                         } 
                         
                         d o c u m e n t . g e t E l e m e n t B y I d ( " r e f o r m u l a t i o n P r o p o s a l s C o n t e n t " ) . i n n e r H T M L   =   h t m l ; 

<script>
// Fonction pour voir les propositions de reformulation
function viewReformulationProposals(sujetId) {
    // Afficher le modal et son loader
    const reformulationModal = new bootstrap.Modal(document.getElementById("reformulationProposalsModal"));
    reformulationModal.show();
    
    // Récupérer les propositions via AJAX
    fetch(`controller/get_sujet_reformulations.php?sujet_id=${sujetId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error("Erreur réseau");
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Formater et afficher les données
            let html = "";
            
            if (data.reformulations && data.reformulations.length > 0) {
                html += "<h6 class=\"mb-3\"><i class=\"bi bi-lightbulb me-2\"></i>Propositions de reformulation</h6>";
                
                data.reformulations.forEach(reformulation => {
                    const statusClass = {
                        "En attente": "warning",
                        "Acceptée": "success",
                        "Refusée": "danger"
                    }[reformulation.statut_reformulation] || "secondary";
                    
                    const dateProposition = new Date(reformulation.date_proposition).toLocaleDateString("fr-FR", {
                        year: "numeric",
                        month: "long",
                        day: "numeric",
                        hour: "2-digit",
                        minute: "2-digit"
                    });
                    
                    html += `
                        <div class="card mb-3 border-${statusClass}">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Proposition du ${dateProposition}</h6>
                                <span class="badge bg-${statusClass}">${reformulation.statut_reformulation}</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Nouvel intitulé proposé:</strong><br>
                                        <p class="text-primary">${reformulation.intitule_propose}</p>
                                    </div>
                                    <div class="col-md-6">
                                        ${reformulation.specialisation_nom ? `
                                            <strong>Spécialisation:</strong><br>
                                            <p>${reformulation.specialisation_nom}</p>
                                        ` : ""}
                                    </div>
                                </div>
                                
                                ${reformulation.directeur_nom ? `
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Directeur proposé:</strong><br>
                                            <p>${reformulation.directeur_nom}</p>
                                        </div>
                                        ${reformulation.encadreur_nom ? `
                                            <div class="col-md-6">
                                                <strong>Encadreur proposé:</strong><br>
                                                <p>${reformulation.encadreur_nom}</p>
                                            </div>
                                        ` : ""}
                                    </div>
                                ` : ""}
                                
                                <div class="mt-3">
                                    <strong>Justification de l'étudiant:</strong>
                                    <div class="p-2 bg-light rounded mt-1">
                                        ${reformulation.justification_etudiant.replace(/\n/g, "<br>")}
                                    </div>
                                </div>
                                
                                ${reformulation.commentaire_reponse ? `
                                    <div class="alert alert-${statusClass === "success" ? "success" : "danger"} mt-3">
                                        <strong>Réponse de l'administration:</strong><br>
                                        ${reformulation.commentaire_reponse.replace(/\n/g, "<br>")}
                                        ${reformulation.date_traitement ? `
                                            <br><small class="text-muted">Le ${new Date(reformulation.date_traitement).toLocaleDateString("fr-FR")}</small>
                                        ` : ""}
                                    </div>
                                ` : ""}
                                
                                ${reformulation.statut_reformulation === "En attente" ? `
                                    <div class="mt-3">
                                        <button class="btn btn-success btn-sm me-2" onclick="approveReformulation(${reformulation.id_reformulation})">
                                            <i class="bi bi-check-circle"></i> Approuver
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="rejectReformulation(${reformulation.id_reformulation})">
                                            <i class="bi bi-x-circle"></i> Refuser
                                        </button>
                                    </div>
                                ` : ""}
                            </div>
                        </div>
                    `;
                });
            } else {
                html = "<div class=\"alert alert-info\"><i class=\"bi bi-info-circle me-2\"></i>Aucune proposition de reformulation trouvée pour ce sujet.</div>";
            }
            
            document.getElementById("reformulationProposalsContent").innerHTML = html;
        })
        .catch(error => {
            document.getElementById("reformulationProposalsContent").innerHTML = 
                `<div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Erreur lors du chargement des propositions: ${error.message}
                </div>`
            ;
        });
}

// Fonction pour approuver une reformulation
function approveReformulation(reformulationId) {
    Swal.fire({
        title: "Approuver cette reformulation ?",
        text: "Le sujet sera mis à jour avec les nouvelles informations proposées.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Oui, approuver",
        cancelButtonText: "Annuler"
    }).then((result) => {
        if (result.isConfirmed) {
            // Envoyer la requête d'approbation
            fetch("controller/traiter_reformulation.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=approve&reformulation_id=${reformulationId}
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire("Approuvé !", "La reformulation a été approuvée avec succès.", "success")
                    .then(() => {
                        location.reload(); // Recharger la page pour voir les changements
                    });
                } else {
                    Swal.fire("Erreur", data.message || "Une erreur est survenue.", "error");
                }
            })
            .catch(error => {
                Swal.fire("Erreur", "Une erreur est survenue lors du traitement.", "error");
            });
        }
    });
}

// Fonction pour refuser une reformulation
function rejectReformulation(reformulationId) {
    Swal.fire({
        title: "Refuser cette reformulation",
        input: "textarea",
        inputLabel: "Motif du refus",
        inputPlaceholder: "Expliquez pourquoi cette reformulation est refusée...",
        inputAttributes: {
            "aria-label": "Motif du refus"
        },
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Refuser",
        cancelButtonText: "Annuler",
        inputValidator: (value) => {
            if (!value) {
                return "Vous devez fournir un motif de refus !";
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Envoyer la requête de refus
            fetch("controller/traiter_reformulation.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=reject&reformulation_id=${reformulationId}&commentaire=${encodeURIComponent(result.value)}
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire("Refusé !", "La reformulation a été refusée.", "success")
                    .then(() => {
                        location.reload(); // Recharger la page pour voir les changements
                    });
                } else {
                    Swal.fire("Erreur", data.message || "Une erreur est survenue.", "error");
                }
            })
            .catch(error => {
                Swal.fire("Erreur", "Une erreur est survenue lors du traitement.", "error");
            });
        }
    });
}
</script>

<?php include "./views/include/footer_file.php"; ?>
