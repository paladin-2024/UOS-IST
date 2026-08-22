<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
$checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
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

// Vérifier si l'utilisateur est administrateur
$isAdmin = $_SESSION['idRole'] == 1;

// Récupérer les sections dont l'utilisateur est responsable
// Nous utilisons idUser pour identifier le responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE \"idUser\" = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Si l'utilisateur est admin, il n'est pas considéré comme responsable de section limité
$isResponsableSection = !$isAdmin && !empty($userSections);

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer tous les paramàtres de filtrage
$status = isset($_GET['status']) ? $_GET['status'] : '';
$cycle = isset($_GET['cycle']) ? $_GET['cycle'] : '';
$specialisation = isset($_GET['specialisation']) ? $_GET['specialisation'] : '';
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$hasStudent = isset($_GET['has_student']) ? $_GET['has_student'] : null;
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;

// Créer un tableau de filtres pour les logs ou références futures
// Dans $filters, modifiez la ligne pour annee
$filters = [
    'status' => $status,
    'cycle' => $cycle,
    'specialisation' => $specialisation,
    'annee' => ($anneeId > 0) ? $anneeId : null,  // Utiliser null au lieu de 0
    'has_student' => $hasStudent
];



$pageSize = 20;


function getSujets($pdo, $search, $sections = [], $filters = []) {
    $params = [];
    
    // Construction de la requǦte de base avec les bonnes jointures
    $query = "SELECT s.*, 
                a.designation as annee, 
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                e.idetudiant as etudiant_idetudiant,
                d.noms as directeur,
                d.\"idAgent\" as idDirecteur,
                gr_d.designation as grade_directeur,
                enc.noms as encadreur,
                enc.\"idAgent\" as idEncadreur,
                gr_e.designation as grade_encadreur,
                spec.designation as specialisation,
                sec.\"designationSection\" as section,
                o.\"designationOrientation\" as orientation
            FROM sujets s
            LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
            LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
            LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
            LEFT JOIN grade gr_d ON d.grade_id = gr_d.idgrade
            LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
            LEFT JOIN grade gr_e ON enc.grade_id = gr_e.idgrade
            LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
            LEFT JOIN orientation o ON spec.idorientation = o.idorientation
            LEFT JOIN section sec ON o.section_idsection = sec.idsection
            WHERE 1=1";
    
    // Filtrer par sections si spécifié ET si des sections sont fournies
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) { // Vérifier que la section n'est pas vide
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
    
    // Filtrage par spécialisation
    if (!empty($filters['specialisation'])) {
        $query .= " AND s.idSpecialisation = :specialisation";
        $params[':specialisation'] = $filters['specialisation'];
    }
    
    // Filtrage par année académique
    if (isset($filters['annee']) && $filters['annee'] !== null) {
        $query .= " AND s.annee_acad_idannee_acad = :annee";
        $params[':annee'] = $filters['annee'];
    }
    
    // Filtrage par présence d'étudiant
    if (isset($filters['has_student'])) {
        if ($filters['has_student'] === '1') {
            $query .= " AND s.etudiant_idetudiant IS NOT NULL";
        } elseif ($filters['has_student'] === '0') {
            $query .= " AND s.etudiant_idetudiant IS NULL";
        }
    }
    
    // Ajouter l'ordre
    $query .= " ORDER BY spec.designation, s.intitule";
    
    // DEBUG - Afficher la requǦte (temporaire pour Vérification)
    if (!empty($sections)) {
        echo "<!-- DEBUG getSujets - Sections filtrées: " . implode(', ', $sections) . " -->";
        echo "<!-- Query: $query -->";
        echo "<!-- Params: " . print_r($params, true) . " -->";
    }
    
    try {
        // Exécuter la requǦte
        $stmt = $pdo->prepare($query);
        
        // Lier les paramàtres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG
        // echo "<!-- résultats trouvés: " . count($result) . " -->";
        
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
              JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              JOIN orientation o ON spec.idorientation = o.idorientation
              WHERE s.statut_validation = :status";
    
    $params = [':status' => $status];
    
    // Filtrer par sections si spécifié ET si des sections sont fournies
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
    
    // Filtrer par année académique seulement si $anneeId > 0
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





// Recuperer les sujets en fonction des droits de l'utilisateur
$sujets = [];
$canLoadSubjects = true;
$hasFullAccess = $isAdmin;

if ($isResponsableSection) {
    if ($sectionFilter > 0 && !in_array($sectionFilter, $userSections)) {
        $canLoadSubjects = false;
    }
} else {
    if (!$hasFullAccess) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Acces refuse',
                text: 'Vous n\\'avez pas les droits pour acceder a cette page.'
            }).then(() => {
                window.location.href = 'index';
            });
        </script>";
        include './views/include/footer.php';
        exit;
    }
}


// Récupérer les statistiques filtrées par les sections de l'utilisateur si nécessaire
if ($isResponsableSection) {
    // Pour les responsables de section, filtrer par leurs sections
    $sectionsForStats = [];
    
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            // L'utilisateur a accàs à cette section
            $sectionsForStats = [$sectionFilter];
        } else {
            // L'utilisateur n'a pas accàs à cette section - statistiques à zéro
            $sectionsForStats = [];
        }
    } else {
        // Toutes les sections de l'utilisateur
        $sectionsForStats = $userSections;
    }
    
    $statsAttente = countSujetsByStatus($pdo, 'En attente', $sectionsForStats, $anneeId);
    $statsValides = countSujetsByStatus($pdo, 'Validé', $sectionsForStats, $anneeId);
    $statsRejetes = countSujetsByStatus($pdo, 'A reformulé', $sectionsForStats, $anneeId);
    $statsModifies = countSujetsByStatus($pdo, 'Modifié', $sectionsForStats, $anneeId);
} else {
    // Pour les autres utilisateurs (admin), voir toutes les statistiques
    // CORRECTION : Vérifier si l'utilisateur a accàs complet
    $hasFullAccess = $_SESSION['idRole'] == 1;
    
    if ($hasFullAccess) {
        // Si une section spécifique est sélectionnée, filtrer par cette section
        $sectionsForStats = ($sectionFilter > 0) ? [$sectionFilter] : [];
        
        $statsAttente = countSujetsByStatus($pdo, 'En attente', $sectionsForStats, $anneeId);
        $statsValides = countSujetsByStatus($pdo, 'Validé', $sectionsForStats, $anneeId);
        $statsRejetes = countSujetsByStatus($pdo, 'A reformulé', $sectionsForStats, $anneeId);
        $statsModifies = countSujetsByStatus($pdo, 'Modifié', $sectionsForStats, $anneeId);
    } else {
        // Pas d'accàs, statistiques à zéro
        $statsAttente = $statsValides = $statsRejetes = $statsModifies = 0;
    }
}




$totalSujets = $statsAttente + $statsValides + $statsRejetes + $statsModifies;

// Récupérer les données nécessaires
// années académiques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// spécialisations
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
    // Récupérer toutes les sections
    $querySection = "SELECT * FROM section ORDER BY \"designationSection\"";
    $stmtSection = $pdo->prepare($querySection);
    $stmtSection->execute();
    $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
}




?>

<!-- Début du HTML -->
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
            Vous consultez les données de l'année académique: 
            <strong>
                <?php 
                $selectedYear = array_filter($academicYears, function($year) use ($anneeId) {
                    return $year['idannee_acad'] == $anneeId;
                });
                echo !empty($selectedYear) ? reset($selectedYear)['designation'] : '';
                ?>
            </strong>
            <a href="?view=recherche/choix_etudiant" class="btn btn-sm btn-outline-primary float-end">
                <i class="bi bi-x-circle"></i> Réinitialiser
            </a>
        </div>
        <?php endif; ?>

        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Vous visualisez uniquement les sujets des étudiants relevant de votre responsabilité.
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
            <strong>Mode Administrateur:</strong> Vous avez accès à toutes les sections et données du système.
        </div>
        <?php endif; ?>


        

        <!-- Statistiques globales -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques générales des sujets</h5>
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
                                        <h5 class="card-title">Sujets Validés</h5>
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
                                        <h5 class="card-title">Sujets à reformuler</h5>
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
        // Fonction pour Récupérer les statistiques par cycle (réutilise la logique existante)
        function countSujetsByStatusAndCycle($pdo, $status, $cycle, $sections = [], $anneeId = null) {
            $query = "SELECT COUNT(*) as count 
                      FROM sujets s
                      JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                      JOIN orientation o ON spec.idorientation = o.idorientation
                      WHERE s.statut_validation = :status AND s.cycle = :cycle";
            
            $params = [':status' => $status, ':cycle' => $cycle];
            
            // Filtrer par sections si spécifié ET si des sections sont fournies
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
            
            // Filtrer par année académique seulement si $anneeId > 0
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
            $statuts = ['En attente', 'Validé', 'A reformulé', 'Modifié'];
            $statistiques = [];
            
            foreach ($cycles as $cycleValue) {
                $stats = [];
                $total = 0;
                
                foreach ($statuts as $statut) {
                    $count = countSujetsByStatusAndCycle($pdo, $statut, $cycleValue, $sections, $anneeId);
                    $stats[strtolower(str_replace(['é', 'é', ' '], ['e', 'E', '_'], $statut))] = $count;
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

        // Récupérer les statistiques par cycle
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
                                                <small class="text-muted">Validés</small>
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
                                                <br><small>Modifiés</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Master (Deuxième cycle) -->
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-award me-2"></i>Master (Deuxième cycle)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?= $statsCycles['Deuxieme']['total'] ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?= $statsCycles['Deuxieme']['valides'] ?></h4>
                                                <small class="text-muted">Validés</small>
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
                                                <br><small>Modifiés</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Doctorat (Troisième cycle) -->
                            <div class="col-md-4">
                                <div class="card border-warning mb-3">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="bi bi-journal-bookmark me-2"></i>Doctorat (Troisième cycle)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?= $statsCycles['Troisieme']['total'] ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?= $statsCycles['Troisieme']['valides'] ?></h4>
                                                <small class="text-muted">Validés</small>
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
                                                <br><small>Modifiés</small>
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
                                    
                                    <!-- Nouveau sélecteur d'année académique -->
                                    <div class="col-md-2">
                                        <select name="annee" class="form-select" id="anneeFilter">
                                            <option value="">Toutes les années</option>
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
                                            <option value="Validé" <?= $status == 'Validé' ? 'selected' : '' ?>>Validé</option>
                                            <option value="A reformulé" <?= $status == 'A reformulé' ? 'selected' : '' ?>>A reformulé</option>
                                            <option value="Modifié" <?= $status == 'Modifié' ? 'selected' : '' ?>>Modifié</option>
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
                                            <option value="1" <?= $hasStudent === '1' ? 'selected' : '' ?>>Avec étudiant</option>
                                            <option value="0" <?= $hasStudent === '0' ? 'selected' : '' ?>>Sans étudiant</option>
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


                        <!-- Tableau des sujets organises par cycle -->
                        <div class="table-responsive" id="sujetsTableContainer" data-can-load="<?= $canLoadSubjects ? '1' : '0' ?>" data-page-size="<?= $pageSize ?>">
                            <?php if (!$canLoadSubjects): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    Vous n'avez pas les droits pour consulter cette section.
                                </div>
                            <?php else: ?>
                                <?php
                                    $cycleCards = [
                                        'Premier' => ['label' => 'Licence (Premier cycle)', 'icon' => 'bi-mortarboard', 'color' => 'primary'],
                                        'Deuxieme' => ['label' => 'Master (Deuxieme cycle)', 'icon' => 'bi-award', 'color' => 'success'],
                                        'Troisieme' => ['label' => 'Doctorat (Troisieme cycle)', 'icon' => 'bi-journal-bookmark', 'color' => 'warning'],
                                    ];
                                ?>
                                <div id="noSujetsMessage" class="alert alert-info d-none">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucun sujet trouve avec les criteres specifiques.
                                </div>

                                <?php foreach ($cycleCards as $cycleKey => $meta): ?>
                                <div class="mb-4 cycle-wrapper d-none" data-cycle="<?= $cycleKey ?>">
                                    <div class="card border-<?= $meta['color'] ?>">
                                        <div class="card-header bg-<?= $meta['color'] ?> text-white d-flex align-items-center justify-content-between">
                                            <h5 class="mb-0">
                                                <i class="<?= $meta['icon'] ?> me-2"></i>
                                                <?= $meta['label'] ?>
                                            </h5>
                                            <span class="badge bg-light text-dark" id="cycle-count-<?= strtolower($cycleKey) ?>">0 sujet(s)</span>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-striped table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Intitule</th>
                                                        <th>Specialisation</th>
                                                        <th>Etat</th>
                                                        <th>Etudiant</th>
                                                        <th>Directeur</th>
                                                        <th>Annee</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cycle-body-<?= strtolower($cycleKey) ?>"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <div id="sujetsPager" class="text-center my-3">
                                    <div id="sujetsSpinner" class="spinner-border text-primary d-none" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <button id="loadMoreSujets" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-down-circle me-1"></i>Charger plus
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div>
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
                        <label for="annee_export" class="form-label">sélectionner l'année académique</label>
                        <select name="annee_export" id="annee_export" class="form-select" required>
                            <option value="">sélectionner une année académique</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($sections)): ?>
                    <div class="mb-3">
                        <label for="section_export" class="form-label">Section</label>
                        <select name="section_export[]" id="section_export" class="form-select" multiple></select>
                        <div id="sections_export_loading" class="d-none mt-2">
                            <div class="d-flex align-items-center text-muted small">
                                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                Chargement des sections...
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            Maintenez la touche Ctrl (ou Cmd) pour sélectionner plusieurs sections.<br>
                            <strong>Si aucune section n'est sélectionnée, toutes vos sections autorisées seront exportées.</strong>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="status_export" class="form-label">Statut des sujets</label>
                        <select name="status_export" id="status_export" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="En attente">En attente</option>
                            <option value="Validé">Validés</option>
                            <option value="A reformulé">A reformulé</option>
                            <option value="Modifié">Modifiés</option>
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

<!-- Modal pour voir les détails du sujet -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du sujet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sujetDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p>Chargement des détails...</p>
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
                    <p>Vous êtes sur le point de valider le sujet : <strong id="validate_sujet_title"></strong></p>
                    
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
                    <p>Vous êtes sur le point de demander une reformulation pour le sujet : <strong id="reject_sujet_title"></strong></p>
                    
                    <div class="mb-3">
                        <label for="reject_comment" class="form-label">Motif de la reformulation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_comment" name="commentaire" rows="3" required placeholder="Expliquez les points à reformuler..."></textarea>
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
// Fonction pour échapper les caractères HTML
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById('sujetsTableContainer');
    if (!tableContainer) {
        return;
    }

    const canLoad = tableContainer.dataset.canLoad === '1';
    if (!canLoad) {
        return;
    }

    const pageSize = parseInt(tableContainer.dataset.pageSize || '20', 10);
    const cycleWrappers = {
        Premier: {
            wrapper: document.querySelector('.cycle-wrapper[data-cycle="Premier"]'),
            body: document.getElementById('cycle-body-premier'),
            count: document.getElementById('cycle-count-premier'),
        },
        Deuxieme: {
            wrapper: document.querySelector('.cycle-wrapper[data-cycle="Deuxieme"]'),
            body: document.getElementById('cycle-body-deuxieme'),
            count: document.getElementById('cycle-count-deuxieme'),
        },
        Troisieme: {
            wrapper: document.querySelector('.cycle-wrapper[data-cycle="Troisieme"]'),
            body: document.getElementById('cycle-body-troisieme'),
            count: document.getElementById('cycle-count-troisieme'),
        },
    };

    const cycleCounts = { Premier: 0, Deuxieme: 0, Troisieme: 0 };
    const noResultsMessage = document.getElementById('noSujetsMessage');
    const loadMoreButton = document.getElementById('loadMoreSujets');
    const spinner = document.getElementById('sujetsSpinner');
    const defaultNoResultHtml = noResultsMessage ? noResultsMessage.innerHTML : '';
    let largestIndex = 0;

    const state = {
        page: 1,
        limit: pageSize,
        loading: false,
        hasMore: true,
    };

    function resetResults() {
        state.page = 1;
        state.hasMore = true;
        largestIndex = 0;
        Object.keys(cycleCounts).forEach(function(key) {
            cycleCounts[key] = 0;
            const config = cycleWrappers[key];
            if (config && config.body) {
                config.body.innerHTML = '';
            }
            if (config && config.wrapper) {
                config.wrapper.classList.add('d-none');
            }
            if (config && config.count) {
                config.count.textContent = '0 sujet(s)';
            }
        });
        if (noResultsMessage) {
            noResultsMessage.classList.add('d-none');
            noResultsMessage.classList.remove('alert-danger');
            noResultsMessage.classList.add('alert-info');
            noResultsMessage.innerHTML = defaultNoResultHtml;
        }
        if (loadMoreButton) {
            loadMoreButton.classList.remove('d-none');
            loadMoreButton.disabled = false;
        }
    }

    function toggleLoading(isLoading) {
        state.loading = isLoading;
        if (spinner) {
            spinner.classList.toggle('d-none', !isLoading);
        }
        if (loadMoreButton) {
            loadMoreButton.disabled = isLoading;
        }
    }

    function updateCycleVisibility(cycleKey) {
        const config = cycleWrappers[cycleKey];
        if (!config) {
            return;
        }
        if (cycleCounts[cycleKey] > 0) {
            if (config.wrapper) {
                config.wrapper.classList.remove('d-none');
            }
            if (config.count) {
                const total = cycleCounts[cycleKey];
                config.count.textContent = total === 1 ? '1 sujet' : `${total} sujets`;
            }
        } else if (config.wrapper) {
            config.wrapper.classList.add('d-none');
        }
    }

    function buildActions(subject, safeTitle) {
        let actions = `<button class="btn btn-sm btn-info" onclick="viewDetails(${subject.id})"><i class="bi bi-eye"></i></button>`;
        if (subject.has_reformulation_pending) {
            actions += `<button class="btn btn-sm btn-warning ms-1" onclick="viewReformulationProposals(${subject.id})" title="Voir les propositions de reformulation"><i class="bi bi-lightbulb"></i></button>`;
        }
        let normalizedStatus = (subject.statut || '').toLowerCase();
        if (typeof normalizedStatus.normalize === 'function') {
            normalizedStatus = normalizedStatus.normalize('NFD').replace(/[̀-ͯ]/g, '');
        }
        if (subject.can_edit && (normalizedStatus === 'en attente' || normalizedStatus === 'modifie')) {
            actions += `<button class="btn btn-sm btn-success ms-1" data-sujet-id="${subject.id}" data-sujet-title="${safeTitle}" onclick="validateSujetData(this)"><i class="bi bi-check"></i></button>`;
            actions += `<button class="btn btn-sm btn-danger ms-1" data-sujet-id="${subject.id}" data-sujet-title="${safeTitle}" onclick="rejectSujetData(this)"><i class="bi bi-x"></i></button>`;
        }
        return actions;
    }

    function appendSubject(subject) {
        const cycleKey = cycleWrappers[subject.cycle] ? subject.cycle : 'Premier';
        const config = cycleWrappers[cycleKey];
        if (!config || !config.body) {
            return;
        }

        let rowNumber = Number(subject.index);
        if (!Number.isFinite(rowNumber)) {
            rowNumber = largestIndex + 1;
        }
        largestIndex = Math.max(largestIndex, rowNumber);

        const safeTitle = escapeHtml(subject.intitule || '');
        const specialisationLabel = subject.specialisation && subject.specialisation.label
            ? escapeHtml(subject.specialisation.label)
            : '<span class="text-muted">Non defini</span>';

        const statutClass = getStatusClass(subject.statut || '');
        const statutLabel = escapeHtml(subject.statut || 'Non defini');

        let etudiantHtml = '<span class="text-muted">Non assigne</span>';
        if (subject.etudiant && subject.etudiant.id) {
            const matricule = subject.etudiant.matricule ? `<br><small class="text-muted">${escapeHtml(subject.etudiant.matricule)}</small>` : '';
            etudiantHtml = `<span class="fw-semibold">${escapeHtml(subject.etudiant.nom)}</span>${matricule}`;
        }

        let directeurHtml = '<span class="text-muted">Non assigne</span>';
        if (subject.directeur && subject.directeur.id) {
            directeurHtml = escapeHtml(subject.directeur.nom);
        }

        const anneeLabel = subject.annee && subject.annee.label ? escapeHtml(subject.annee.label) : '';

        const reformBadge = subject.has_reformulation_pending
            ? "<span class='badge bg-info ms-2' title='Proposition de reformulation en attente'><i class='bi bi-lightbulb'></i> Nouvelle proposition</span>"
            : '';

        const actionsHtml = buildActions(subject, safeTitle);

        const rowHtml = `
            <tr>
                <td>${rowNumber}</td>
                <td>${safeTitle} ${reformBadge}</td>
                <td>${specialisationLabel}</td>
                <td><span class="${statutClass}">${statutLabel}</span></td>
                <td>${etudiantHtml}</td>
                <td>${directeurHtml}</td>
                <td>${anneeLabel}</td>
                <td class="text-nowrap">${actionsHtml}</td>
            </tr>
        `;
        config.body.insertAdjacentHTML('beforeend', rowHtml);
        cycleCounts[cycleKey] += 1;
        updateCycleVisibility(cycleKey);
    }

    function handleHasMore(hasMore) {
        state.hasMore = hasMore;
        if (!hasMore && loadMoreButton) {
            loadMoreButton.classList.add('d-none');
        }
    }

    function fetchSubjects(reset) {
        if (state.loading) {
            return;
        }
        if (!state.hasMore && !reset) {
            return;
        }
        if (reset) {
            resetResults();
        }
        toggleLoading(true);

        const params = new URLSearchParams(window.location.search);
        params.set('context', 'choix');
        params.set('page', state.page.toString());
        params.set('limit', state.limit.toString());

        fetch(`controller/load_more_sujets.php?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Erreur lors du chargement des sujets.');
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload.success) {
                    throw new Error(payload.error || 'Chargement impossible.');
                }
                const subjects = Array.isArray(payload.data) ? payload.data : [];

                if (state.page === 1 && subjects.length === 0) {
                    handleHasMore(false);
                    if (noResultsMessage) {
                        noResultsMessage.classList.remove('d-none');
                        noResultsMessage.classList.remove('alert-danger');
                        noResultsMessage.classList.add('alert-info');
                        noResultsMessage.innerHTML = defaultNoResultHtml;
                    }
                    return;
                }

                if (noResultsMessage) {
                    noResultsMessage.classList.add('d-none');
                }

                subjects.forEach(function(subject) {
                    appendSubject(subject);
                });

                state.page += 1;
                handleHasMore(Boolean(payload.hasMore) && subjects.length > 0);
                if (state.hasMore && loadMoreButton) {
                    loadMoreButton.classList.remove('d-none');
                }
            })
            .catch(function(error) {
                console.error(error);
                if (noResultsMessage && state.page === 1) {
                    noResultsMessage.classList.remove('d-none');
                    noResultsMessage.classList.remove('alert-info');
                    noResultsMessage.classList.add('alert-danger');
                    noResultsMessage.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(error.message || 'Une erreur est survenue.')}`;
                } else {
                    Swal.fire('Erreur', error.message || 'Une erreur est survenue lors du chargement des sujets.', 'error');
                }
                handleHasMore(false);
            })
            .finally(function() {
                toggleLoading(false);
            });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', function() {
            fetchSubjects(false);
        });
    }

    fetchSubjects(true);
});

// Fonction pour voir les détails d'un sujet
function viewDetails(sujetId) {
    console.log('viewDetails called with ID:', sujetId);
    
    // Afficher le modal et son loader
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    detailsModal.show();
    
    // Utiliser le chemin absolu depuis la racine du site
    const url = '/e_gestion/controller/get_sujet_detail.php?id=' + sujetId;
    console.log('Fetching URL:', url);
    
    // Récupérer les détails via AJAX
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Erreur réseau (status: ' + response.status + ')');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.error) {
                throw new Error(data.error);
            }
            
// Formater et afficher les données
            let html = `
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(data.intitule)}</h5>
                        ${data.resume 
                            ? `<div class="alert alert-info mb-3">
                                <h6><i class="bi bi-text-paragraph me-2"></i>Résumé / Problématique</h6>
                                <p class="mb-0">${escapeHtml(data.resume)}</p>
                               </div>` 
                            : ''}
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Cycle :</strong> ${formatCycle(data.cycle)}</p>
                                <p><strong>Spécialisation :</strong> ${escapeHtml(data.specialisation)}</p>
                                <p><strong>Section :</strong> ${escapeHtml(data.section)}</p>
                                <p><strong>année académique :</strong> ${escapeHtml(data.annee)}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Statut :</strong> <span class="${getStatusClass(data.statut_validation)}">${escapeHtml(data.statut_validation)}</span></p>
                                <p><strong>Date de création :</strong> ${data.date_creation || 'Non disponible'}</p>
                                <p><strong>Derniàre modification :</strong> ${data.date_validation || 'Non disponible'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">étudiant</h6>
                            </div>
                            <div class="card-body">
                                ${data.etudiant 
                                    ? `<p><strong>Nom :</strong> ${escapeHtml(data.etudiant)}</p>
                                       ${data.matricule_etudiant ? `<p><strong>Matricule :</strong> ${escapeHtml(data.matricule_etudiant)}</p>` : ''}` 
                                    : '<p class="text-muted">Aucun étudiant assigné</p>'}
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
                                    : '<p class="text-muted">Aucun directeur assigné</p>'}
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
                                    : '<p class="text-muted">Aucun encadreur assigné</p>'}
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
                    Erreur lors du chargement des détails: ${error.message}
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

// Nouvelles fonctions sécurisées utilisant les attributs data
function validateSujetData(button) {
    const sujetId = button.getAttribute('data-sujet-id');
    const titre = button.getAttribute('data-sujet-title');
    validateSujet(sujetId, titre);
}

function rejectSujetData(button) {
    const sujetId = button.getAttribute('data-sujet-id');
    const titre = button.getAttribute('data-sujet-title');
    rejectSujet(sujetId, titre);
}

// Fonctions utilitaires

function formatCycle(cycle) {
    switch(cycle) {
        case 'Premier': return 'Licence';
        case 'Deuxieme': return 'Master';
        case 'Troisieme': return 'Doctorat';
        default: return cycle;
    }
}

function getStatusClass(status) {
    if (!status) {
        return '';
    }
    let normalized = String(status).toLowerCase();
    if (typeof normalized.normalize === 'function') {
        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    switch (normalized) {
        case 'en attente':
            return 'text-warning';
        case 'valide':
            return 'text-success';
        case 'a reformule':
            return 'text-danger';
        case 'modifie':
            return 'text-primary';
        default:
            return '';
    }
}
// Initialiser les select2 du modal export avec chargement AJAX des sections
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
        return;
    }

    const $exportModal = $('#exportModal');
    const $anneeExport = $('#annee_export');
    const $statusExport = $('#status_export');
    const $sectionExport = $('#section_export');
    const $sectionLoading = $('#sections_export_loading');

    if (!$anneeExport.length || !$sectionExport.length) {
        return;
    }

    const toggleSectionLoading = function(isLoading) {
        if (!$sectionLoading.length) {
            return;
        }
        if (isLoading) {
            $sectionLoading.removeClass('d-none');
        } else {
            $sectionLoading.addClass('d-none');
        }
    };

    $anneeExport.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $exportModal,
        placeholder: 'sélectionner une année académique',
        minimumResultsForSearch: 8
    });

    $statusExport.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $exportModal,
        placeholder: 'Tous les statuts',
        minimumResultsForSearch: Infinity
    });

    const currentSections = [];

    const clearSectionOptions = function() {
        currentSections.length = 0;
        $sectionExport.empty();
        $sectionExport.val(null).trigger('change.select2');
    };

    const loadSectionsForYear = function(openAfterLoad) {
        const anneeId = $anneeExport.val();

        clearSectionOptions();

        if (!anneeId) {
            return;
        }

        toggleSectionLoading(true);
        $.ajax({
            url: 'controller/get_sections_by_annee.php',
            dataType: 'json',
            method: 'GET',
            data: { annee_id: anneeId }
        }).done(function(payload) {
            if (!payload || !payload.success || !Array.isArray(payload.sections)) {
                return;
            }

            const seen = {};
            payload.sections.forEach(function(section) {
                const id = String(section.idsection || '').trim();
                if (!id || seen[id]) {
                    return;
                }
                seen[id] = true;
                currentSections.push({
                    id: id,
                    text: section.designationSection || ('Section ' + id)
                });
            });

            if (currentSections.length > 0) {
                const optionsHtml = currentSections.map(function(item) {
                    return '<option value="' + item.id + '">' + item.text + '</option>';
                }).join('');
                $sectionExport.html(optionsHtml);
                $sectionExport.trigger('change.select2');
            }

            if (openAfterLoad && currentSections.length > 0) {
                $sectionExport.select2('open');
            }
        }).always(function() {
            toggleSectionLoading(false);
        });
    };

    $sectionExport.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $exportModal,
        placeholder: 'sélectionner une ou plusieurs sections',
        closeOnSelect: false,
        data: currentSections,
        matcher: function(params, data) {
            const term = $.trim(params.term || '').toLowerCase();
            if (term === '') {
                return data;
            }
            if ((data.text || '').toLowerCase().indexOf(term) > -1) {
                return data;
            }
            return null;
        }
    });

    $anneeExport.on('change', function() {
        loadSectionsForYear(true);
    });

    $exportModal.on('shown.bs.modal', function() {
        if ($anneeExport.val()) {
            loadSectionsForYear(false);
        } else {
            clearSectionOptions();
        }
    });
});

// Ajouter à l'intérieur de la balise script existante
document.addEventListener('DOMContentLoaded', function() {
    // Soumettre automatiquement le formulaire lorsque l'année académique change
    document.getElementById('anneeFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

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
                                        <p class="text-primary">${escapeHtml(reformulation.intitule_propose)}</p>
                                    </div>
                                    <div class="col-md-6">
                                        ${reformulation.specialisation_nom ? `
                                            <strong>Spécialisation:</strong><br>
                                            <p>${escapeHtml(reformulation.specialisation_nom)}</p>
                                        ` : ""}
                                    </div>
                                </div>
                                
                                ${reformulation.directeur_nom ? `
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Directeur proposé:</strong><br>
                                            <p>${escapeHtml(reformulation.directeur_nom)}</p>
                                        </div>
                                        ${reformulation.encadreur_nom ? `
                                            <div class="col-md-6">
                                                <strong>Encadreur proposé:</strong><br>
                                                <p>${escapeHtml(reformulation.encadreur_nom)}</p>
                                            </div>
                                        ` : ""}
                                    </div>
                                ` : ""}
                                
                                <div class="mt-3">
                                    <strong>Justification de l'étudiant:</strong>
                                    <div class="p-2 bg-light rounded mt-1">
                                        ${escapeHtml(reformulation.justification_etudiant).replace(/\n/g, "<br>")}
                                    </div>
                                </div>
                                
                                ${reformulation.commentaire_reponse ? `
                                    <div class="alert alert-${statusClass === "success" ? "success" : "danger"} mt-3">
                                        <strong>Réponse de l'administration:</strong><br>
                                        ${escapeHtml(reformulation.commentaire_reponse).replace(/\n/g, "<br>")}
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
                body: `action=approve&reformulation_id=${reformulationId}`
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
                body: `action=reject&reformulation_id=${reformulationId}&commentaire=${encodeURIComponent(result.value)}`
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


