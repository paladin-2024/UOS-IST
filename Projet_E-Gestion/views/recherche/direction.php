<?php
include "./views/include/header.php";

// Initialiser la connexion
$connexion = Connexion::getInstance()->getPDO();

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer les paramètres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedYear = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

// Récupérer toutes les années académiques
$queryAnnees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC";
$stmtAnnees = $connexion->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année courante
if ($selectedYear > 0) {
    // Utiliser l'année sélectionnée
    $queryCurrentYear = "SELECT idannee_acad, designation FROM annee_acad WHERE idannee_acad = :id";
    $stmtCurrentYear = $connexion->prepare($queryCurrentYear);
    $stmtCurrentYear->bindParam(':id', $selectedYear, PDO::PARAM_INT);
} else {
    // Récupérer l'année active par défaut
    $queryCurrentYear = "SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmtCurrentYear = $connexion->prepare($queryCurrentYear);
}
$stmtCurrentYear->execute();
$currentYear = $stmtCurrentYear->fetch(PDO::FETCH_ASSOC);

// Si aucune année active, prendre la plus récente
if (!$currentYear && count($academicYears) > 0) {
    $currentYear = $academicYears[0];
    $selectedYear = $currentYear['idannee_acad'];
}

// Vérifier si l'utilisateur est administrateur
$isAdmin = $_SESSION['idRole'] == 1;

// Récupérer les sections dont l'utilisateur est responsable
if ($currentYear) {
    $query = "SELECT section_idsection 
              FROM responsable_section 
              WHERE idUser = :userId 
              AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':userId', $currentUserId);
    $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si l'utilisateur est admin, il n'est pas considéré comme responsable de section limité
    $isResponsableSection = !$isAdmin && !empty($userSections);
}

// Récupérer les enseignants (agents de type "Enseignant")
// Si l'utilisateur est responsable de section, filtrer par les enseignants de ses sections
if ($isResponsableSection && !empty($userSections)) {
    // Pour les responsables de section : seulement les enseignants de leurs sections
    $sectionPlaceholders = implode(',', array_fill(0, count($userSections), '?'));
    $queryEnseignants = "SELECT DISTINCT a.idAgent, a.noms, g.designation as gradeDesignation,
                                GROUP_CONCAT(DISTINCT s.designationSection SEPARATOR ', ') as sections_list
                         FROM agent a 
                         LEFT JOIN grade g ON a.grade_id = g.idgrade 
                         INNER JOIN agent_section ags ON a.idAgent = ags.idAgent
                         INNER JOIN section s ON ags.idsection = s.idsection
                         WHERE a.type_agent = 'Enseignant' 
                         AND ags.idsection IN ($sectionPlaceholders) ";
    
    $params = $userSections;
    
    if (!empty($search)) {
        $queryEnseignants .= "AND (a.noms LIKE ?) ";
        $params[] = "%$search%";
    }
    
    $queryEnseignants .= "GROUP BY a.idAgent, a.noms, g.designation ORDER BY a.noms ASC";
    
    $stmtEnseignants = $connexion->prepare($queryEnseignants);
    $stmtEnseignants->execute($params);
    
} else {
    // Pour les administrateurs : tous les enseignants
    $queryEnseignants = "SELECT a.idAgent, a.noms, g.designation as gradeDesignation 
                         FROM agent a 
                         LEFT JOIN grade g ON a.grade_id = g.idgrade 
                         WHERE a.type_agent = 'Enseignant' ";
                         
    if (!empty($search)) {
        $queryEnseignants .= "AND (a.noms LIKE :search) ";
    }

    $queryEnseignants .= "ORDER BY a.noms ASC";

    $stmtEnseignants = $connexion->prepare($queryEnseignants);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmtEnseignants->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    $stmtEnseignants->execute();
}

$enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);

// Vérifier les droits d'accès
if ($isResponsableSection) {
    // L'utilisateur est responsable de section - il voit seulement ses sections
    // Aucune action supplémentaire nécessaire, la logique est dans getSujetsByEnseignant
} else {
    // Vérifier si l'utilisateur a le droit d'accéder à toutes les données
    $hasFullAccess = $_SESSION['idRole'] == 1; // Supposons que le rôle 1 est administrateur

    if (!$hasFullAccess) {
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

// Fonction pour récupérer les sujets par enseignant
function getSujetsByEnseignant($connexion, $enseignantId, $anneeId = null, $userSections = []) {
    $query = "SELECT s.*, 
                     a.designation as annee, 
                     e.noms as etudiant_nom,
                     p.designationPromotion as promotion,
                     s.etatSujet,
                     spec.designation as specialisation,
                     o.designationOrientation as orientation,
                     sec.designationSection as section
              FROM sujets s
              LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE (s.idDirecteur = :enseignantId OR s.idEncadreur = :enseignantId) ";
    
    $params = [':enseignantId' => $enseignantId];
    
    // Filtrer par sections si l'utilisateur est responsable de section
    if (!empty($userSections) && is_array($userSections)) {
        $sectionParams = [];
        foreach ($userSections as $i => $section) {
            if (!empty($section)) {
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= "AND o.section_idsection IN ($placeholders) ";
        }
    }
    
    if ($anneeId) {
        $query .= "AND s.annee_acad_idannee_acad = :anneeId ";
        $params[':anneeId'] = $anneeId;
    }
    
    $query .= "ORDER BY p.designationPromotion, s.intitule";
    
    $stmt = $connexion->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour compter les travaux par année
function countTravauxParAnnee($sujets, $anneeDesignation) {
    $count = 0;
    foreach ($sujets as $sujet) {
        if ($sujet['annee'] === $anneeDesignation) {
            $count++;
        }
    }
    return $count;
}

// Fonction pour regrouper les sujets par promotion
function groupByPromotion($sujets) {
    $grouped = [];
    foreach ($sujets as $sujet) {
        $promotion = $sujet['promotion'] ?? 'Non définie';
        if (!isset($grouped[$promotion])) {
            $grouped[$promotion] = [];
        }
        $grouped[$promotion][] = $sujet;
    }
    return $grouped;
}
?>

<style>
    .accordion-header {
        position: relative;
        background-color: #fff;
    }
    
    .accordion-header .btn-success {
        position: absolute;
        right: 50px; /* Ajustez selon vos besoins */
        top: 50%;
        transform: translateY(-50%);
    }

    .accordion-button::after {
        margin-right: 60px; /* Pour éviter que la flèche ne chevauche le bouton */
    }

    /* Pour gérer l'espacement sur mobile */
    @media (max-width: 768px) {
        .accordion-header .btn-success {
            right: 40px;
        }
        .accordion-button::after {
            margin-right: 50px;
        }
        .accordion-button .badge.bg-info {
        position: static;
        margin-left: 10px;
    }
    }

    .badge.bg-secondary {
    font-size: 0.9em;
    padding: 0.4em 0.6em;
}

.badge.bg-info {
    font-size: 0.8em;
    white-space: nowrap;
}

.accordion-button {
    padding-right: 4rem; /* Pour laisser de l'espace pour le bouton d'exportation */
}
</style>

<style>
    /* Styles existants... */
    
    /* Styles pour le bouton d'exportation */
    .accordion-button {
        overflow: visible !important; /* Important pour que le bouton ne soit pas caché */
    }
    
    .export-btn {
        z-index: 10; /* Assurez-vous que le bouton est au-dessus des autres éléments */
    }
    
    /* Pour éviter que le clic sur le bouton n'affecte l'accordéon */
    .export-btn:focus,
    .export-btn:active {
        outline: none !important;
        box-shadow: none !important;
    }
    
    /* Ajustement pour mobile */
    @media (max-width: 576px) {
        .export-btn {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
    }
</style>


<main id="main" class="main">
    <div class="pagetitle">
        <h1>TRAVAUX PAR ENSEIGNANT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Travaux par Enseignant</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Travaux par Enseignant</h5>

                        <!-- Sélecteur d'année académique -->
                        <div class="mb-4">
                            <form id="yearForm" method="GET" action="" class="row g-3">
                                <input type="hidden" name="view" value="recherche/direction">
                                <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <label for="annee_id" class="form-label me-2">Année académique:</label>
                                        <select name="annee_id" id="annee_id" class="form-select" onchange="document.getElementById('yearForm').submit();">
                                            <option value="">Toutes les années</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year['idannee_acad'] ?>" <?= $selectedYear == $year['idannee_acad'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($year['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Affichage de l'année sélectionnée -->
                        <?php if ($selectedYear): ?>
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Filtrage des travaux pour l'année académique: <strong><?= htmlspecialchars($currentYear['designation']) ?></strong>
                        </div>
                        <?php endif; ?>

                        <!-- Informations sur les sections gérées -->
                        <?php if ($isResponsableSection): ?>
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Vous visualisez uniquement les <strong>enseignants et travaux de recherche</strong> des sections où vous avez des responsabilités.
                            <?php if (count($userSections) > 0): ?>
                                <?php
                                // Récupérer les noms des sections
                                if (!empty($userSections)) {
                                    $placeholders = implode(',', array_fill(0, count($userSections), '?'));
                                    $querySecNames = "SELECT designationSection FROM section WHERE idsection IN ($placeholders)";
                                    $stmtSecNames = $connexion->prepare($querySecNames);
                                    $stmtSecNames->execute($userSections);
                                    $sectionNames = $stmtSecNames->fetchAll(PDO::FETCH_COLUMN);
                                }
                                ?>
                                <strong>Vos sections:</strong> <?= implode(', ', $sectionNames) ?>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($isAdmin): ?>
                        <div class="alert alert-success mb-4">
                            <i class="bi bi-shield-check me-2"></i>
                            <strong>Mode Administrateur:</strong> Vous avez accès à tous les enseignants et travaux de recherche du système.
                        </div>
                        <?php endif; ?>

                        <!-- Formulaire de recherche -->
                        <form method="GET" action="" class="mb-4">
                            <input type="hidden" name="view" value="recherche/direction">
                            <?php if ($selectedYear): ?>
                            <input type="hidden" name="annee_id" value="<?= $selectedYear ?>">
                            <?php endif; ?>
                            
                            <div class="input-group">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un enseignant...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <!-- Accordéon des enseignants -->
                        <div class="accordion" id="enseignantsAccordion">
                            <?php 
                            $numeroEnseignant = 1;
                            foreach ($enseignants as $index => $enseignant): 
                                // Récupérer les sujets où l'agent est directeur ou encadreur
                                // Si l'utilisateur est responsable de section, filtrer par ses sections
                                $sectionsFilter = $isResponsableSection ? $userSections : [];
                                $sujetsDirecteur = getSujetsByEnseignant($connexion, $enseignant['idAgent'], $selectedYear, $sectionsFilter);
                                $sujetsByPromotion = groupByPromotion($sujetsDirecteur);

                                // Compter les travaux de l'année en cours
                                $travauxAnneeEnCours = countTravauxParAnnee($sujetsDirecteur, $currentYear['designation']);
                            ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header d-flex justify-content-between align-items-center" id="heading<?= $index ?>">
                                        <button class="accordion-button collapsed flex-grow-1" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?= $index ?>" 
                                                aria-expanded="false" 
                                                aria-controls="collapse<?= $index ?>">
                                            <div class="d-flex align-items-center w-100">
                                                <span class="badge bg-secondary me-2"><?= $numeroEnseignant ?></span>
                                                <div class="d-flex justify-content-between w-100 align-items-center">
                                                    <div>
                                                        <strong><?= htmlspecialchars($enseignant['noms']) ?></strong>
                                                        <?php if (!empty($enseignant['gradeDesignation'])): ?>
                                                            <small class="text-muted">(<?= htmlspecialchars($enseignant['gradeDesignation']) ?>)</small>
                                                        <?php endif; ?>
                                                        <?php if ($isResponsableSection && !empty($enseignant['sections_list'])): ?>
                                                            <br><small class="text-info">Sections: <?= htmlspecialchars($enseignant['sections_list']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-info me-4">
                                                        Total: <?= count($sujetsDirecteur) ?> travaux
                                                        <?php if (count($sujetsDirecteur) > 0): ?>
                                                            (<?= $travauxAnneeEnCours ?> cette année)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                        <?php if (count($sujetsDirecteur) > 0): ?>
                                        <a href="controller/export_travaux_enseignant.php?enseignant_id=<?= $enseignant['idAgent'] ?>&annee_academique=<?= $selectedYear ?? $currentYear ?>" class="btn btn-sm btn-success me-3 export-btn">
                                            <i class="bi bi-file-excel"></i> Exporter
                                        </a>
                                        <?php endif; ?>
                                    </h2>
                                    <div id="collapse<?= $index ?>" class="accordion-collapse collapse" 
                                         aria-labelledby="heading<?= $index ?>" 
                                         data-bs-parent="#enseignantsAccordion">
                                        <div class="accordion-body">
                                            <?php if (empty($sujetsDirecteur)): ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    Aucun travail trouvé pour cet enseignant.
                                                </div>
                                            <?php else: ?>
                                                <!-- Statistiques -->
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h5 class="card-title">Statistiques des travaux</h5>
                                                                
                                                                <div class="row">
                                                                    <!-- Compteur par rôle -->
                                                                    <?php
                                                                    $roleCount = ['directeur' => 0, 'encadreur' => 0];
                                                                    foreach ($sujetsDirecteur as $sujet) {
                                                                        if ($sujet['idDirecteur'] == $enseignant['idAgent']) {
                                                                            $roleCount['directeur']++;
                                                                        }
                                                                        if ($sujet['idEncadreur'] == $enseignant['idAgent']) {
                                                                            $roleCount['encadreur']++;
                                                                        }
                                                                    }
                                                                    ?>
                                                                    <div class="col-md-4 mb-3">
                                                                        <div class="card">
                                                                            <div class="card-body">
                                                                                <h5 class="card-title text-success">Directeur</h5>
                                                                                <div class="d-flex align-items-center">
                                                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                                                        <i class="bi bi-person"></i>
                                                                                    </div>
                                                                                    <div class="ps-3">
                                                                                        <h6><?= $roleCount['directeur'] ?> travaux</h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="col-md-4 mb-3">
                                                                        <div class="card">
                                                                            <div class="card-body">
                                                                                <h5 class="card-title text-info">Co-encadreur</h5>
                                                                                <div class="d-flex align-items-center">
                                                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                                                        <i class="bi bi-person-check"></i>
                                                                                    </div>
                                                                                    <div class="ps-3">
                                                                                        <h6><?= $roleCount['encadreur'] ?> travaux</h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="col-md-4 mb-3">
                                                                        <div class="card">
                                                                            <div class="card-body">
                                                                                <h5 class="card-title text-primary">Total</h5>
                                                                                <div class="d-flex align-items-center">
                                                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                                                        <i class="bi bi-people"></i>
                                                                                    </div>
                                                                                    <div class="ps-3">
                                                                                        <h6><?= count($sujetsDirecteur) ?> travaux</h6>
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
                                                
                                                <!-- Travaux par promotion -->
                                                <?php foreach ($sujetsByPromotion as $promotion => $sujets): ?>
                                                    <div class="card mb-3">
                                                        <div class="card-header bg-light">
                                                            <h5><?= htmlspecialchars($promotion) ?> - <?= count($sujets) ?> travaux</h5>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-striped">
                                                                    <thead>
                                                                        <tr>
                                                                            <th width="5%">N°</th>
                                                                            <th width="25%">Intitulé</th>
                                                                            <th width="20%">Étudiant</th>
                                                                            <th width="15%">Section</th>
                                                                            <th width="10%">Rôle</th>
                                                                            <th width="15%">État</th>
                                                                            <th width="10%">Année</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php 
                                                                        $countSujet = 1;
                                                                        foreach ($sujets as $sujet): 
                                                                            // Déterminer le rôle de l'enseignant
                                                                            $role = [];
                                                                            if ($sujet['idDirecteur'] == $enseignant['idAgent']) {
                                                                                $role[] = 'Directeur';
                                                                            }
                                                                            if ($sujet['idEncadreur'] == $enseignant['idAgent']) {
                                                                                $role[] = 'Co-encadreur';
                                                                            }
                                                                            $roleStr = implode(' & ', $role);
                                                                        ?>
                                                                            <tr>
                                                                                <td><?= $countSujet++ ?></td>
                                                                                <td>
                                                                                    <a href="?view=recherche/sujet_details&id=<?= $sujet['idsujets'] ?>" class="fw-bold">
                                                                                        <?= htmlspecialchars($sujet['intitule']) ?>
                                                                                    </a>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if (!empty($sujet['etudiant_nom'])): ?>
                                                                                        <?= htmlspecialchars($sujet['etudiant_nom']) ?>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted">Non assigné</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if (!empty($sujet['section'])): ?>
                                                                                        <span class="badge bg-secondary"><?= htmlspecialchars($sujet['section']) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted">Non définie</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if (in_array('Directeur', $role)): ?>
                                                                                        <span class="badge bg-success">Directeur</span>
                                                                                    <?php endif; ?>
                                                                                    <?php if (in_array('Co-encadreur', $role)): ?>
                                                                                        <span class="badge bg-info">Co-encadreur</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    $badgeClass = 'secondary';
                                                                                    switch($sujet['etatSujet']) {
                                                                                        case 'En cours':
                                                                                            $badgeClass = 'primary';
                                                                                            break;
                                                                                        case 'Terminé':
                                                                                            $badgeClass = 'success';
                                                                                            break;
                                                                                        case 'Abandonné':
                                                                                            $badgeClass = 'danger';
                                                                                            break;
                                                                                        case 'En attente':
                                                                                            $badgeClass = 'warning';
                                                                                            break;
                                                                                    }
                                                                                    ?>
                                                                                    <span class="badge bg-<?= $badgeClass ?>">
                                                                                        <?= htmlspecialchars($sujet['etatSujet'] ?? 'Non défini') ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td><?= htmlspecialchars($sujet['annee']) ?></td>
                                                                            </tr>
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
                            <?php 
                            $numeroEnseignant++;
                            endforeach; 
                            ?>
                        </div>
                        
                        <?php if (empty($enseignants)): ?>
                            <div class="alert alert-info mt-4">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun enseignant trouvé.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Script pour l exportation Excel -->
<script>
function exportEnseignantData(event, enseignantId, nomEnseignant) {
    
    
    // Construire l'URL correctement
    let url = `./controller/export_travaux_enseignant.php?id=${enseignantId}&nom=${encodeURIComponent(nomEnseignant)}`;
    
    <?php if ($selectedYear): ?>
    // Ajouter l'année académique sélectionnée si elle existe
    url += `&annee_id=<?= $selectedYear ?>`;
    <?php endif; ?>
    
    window.open(url, '_blank');
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit le formulaire quand l'année change
    document.getElementById('annee_id').addEventListener('change', function() {
        document.getElementById('yearForm').submit();
    });
});
</script>


<?php include "./views/include/footer_file.php"; ?>

