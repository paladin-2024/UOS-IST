<?php
include "./views/include/header.php";

// Obtenir l'ID de l'utilisateur connecté
$idUser = isset($_SESSION['id']) ? $_SESSION['id'] : 0;

// Connexion à la base de données
$db = Connexion::getInstance()->getPDO();

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId = null) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE \"idUser\" = :userId";
    
    $params = ['userId' => $userId];
    
    if ($anneeAcadId) {
        $query .= " AND annee_acad_idannee_acad = :anneeId";
        $params['anneeId'] = $anneeAcadId;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

// Vérification des responsabilités de l'utilisateur connecté
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
$currentAcademicYear = getCurrentAcademicYear($db);
$userSections = [];
$isResponsableSection = false;

// Récupérer l'année académique et la section depuis les paramètres GET
$selectedAnnee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer toutes les années académiques, triées par désignation (la plus récente en premier)
$stmtAnnees = $db->query("SELECT * FROM annee_acad ORDER BY designation DESC");
$annees = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Si aucune année n'est sélectionnée, prendre l'année active ou la première année disponible
if ($selectedAnnee == 0 && !empty($annees)) {
    // Rechercher d'abord une année active
    $stmtAnneeActive = $db->query("SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1");
    $anneeActive = $stmtAnneeActive->fetch(PDO::FETCH_ASSOC);
    
    if ($anneeActive) {
        $selectedAnnee = $anneeActive['idannee_acad'];
    } else {
        // Sinon prendre la première année de la liste (la plus récente)
        $selectedAnnee = $annees[0]['idannee_acad'];
    }
}

// Récupérer les sections selon les droits d'accès
if ($hasFullAccess) {
    // Admin - toutes les sections
    $querySections = "SELECT DISTINCT s.* FROM section s";
    if ($selectedAnnee > 0) {
        $querySections .= " WHERE s.\"idAnnee\" = :idAnnee";
    }
    $querySections .= " ORDER BY s.\"designationSection\"";
    $stmtSections = $db->prepare($querySections);
    if ($selectedAnnee > 0) {
        $stmtSections->bindParam(':idAnnee', $selectedAnnee, PDO::PARAM_INT);
    }
    $stmtSections->execute();
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement ses sections
    $userSections = getUserSections($db, $currentUserId, $currentAcademicYear);
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
    
    // Récupérer les sections où l'utilisateur est responsable, filtrées par année si sélectionnée
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $querySections = "SELECT DISTINCT s.* FROM section s WHERE s.idsection IN ($sectionsParams)";
    
    $params = [];
    $paramIndex = 0;
    
    // Ajouter les paramètres pour les sections
    foreach ($userSections as $sectionId) {
        $params[$paramIndex] = $sectionId;
        $paramIndex++;
    }
    
    if ($selectedAnnee > 0) {
        $querySections .= " AND s.\"idAnnee\" = ?";
        $params[$paramIndex] = $selectedAnnee;
    }
    
    $querySections .= " ORDER BY s.\"designationSection\"";
    $stmtSections = $db->prepare($querySections);
    
    foreach ($params as $i => $value) {
        $stmtSections->bindValue($i + 1, $value, PDO::PARAM_INT);
    }
    
    $stmtSections->execute();
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
}

// Si aucune section n'est sélectionnée et qu'il y a des sections disponibles, sélectionner la première
if ($selectedSection == 0 && !empty($sections)) {
    $selectedSection = $sections[0]['idsection'];
}

// Initialiser les variables
$uniteRecherches = [];
$enseignants = [];
$orientations = [];
$specialisationsMap = [];

// Si une section est sélectionnée, récupérer les unités de recherche associées
if ($selectedSection > 0) {
    // Récupérer les unités de recherche associées à la section sélectionnée
    $stmtUnites = $db->prepare("
        SELECT ur.*
        FROM unite_recherche ur
        JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
        WHERE urs.idsection = :idsection
        ORDER BY ur.\"designation_UR\"
    ");
    $stmtUnites->bindParam(':idsection', $selectedSection, PDO::PARAM_INT);
    $stmtUnites->execute();
    $uniteRecherches = $stmtUnites->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les enseignants selon les permissions d'accès
    if ($hasFullAccess) {
        // Admin - tous les enseignants
        $queryEnseignants = "
            SELECT a.*, g.designation as gradeDesignation, s.designation as serviceDesignation
            FROM agent a
            LEFT JOIN grade g ON a.grade_id = g.idgrade
            LEFT JOIN service s ON a.\"idService\" = s.idservice
            WHERE a.type_agent = 'Enseignant'
        ";
        
        $paramsEnseignants = [];
        if (!empty($search)) {
            $queryEnseignants .= " AND (a.noms LIKE :search OR g.designation LIKE :search OR s.designation LIKE :search)";
            $paramsEnseignants[':search'] = "%$search%";
        }
        
        $queryEnseignants .= " ORDER BY a.noms ASC";
        $stmtEnseignants = $db->prepare($queryEnseignants);
        
        foreach ($paramsEnseignants as $key => $value) {
            $stmtEnseignants->bindValue($key, $value);
        }
        
        $stmtEnseignants->execute();
        $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Responsable de section - uniquement les enseignants affectés aux sections où il a des droits
        $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
        $queryEnseignants = "
            SELECT DISTINCT a.*, g.designation as gradeDesignation, s.designation as serviceDesignation
            FROM agent a
            LEFT JOIN grade g ON a.grade_id = g.idgrade
            LEFT JOIN service s ON a.\"idService\" = s.idservice
            INNER JOIN enseignant_specialisation es ON a.\"idAgent\" = es.\"idAgent\"
            INNER JOIN specialisation sp ON es.\"idSpecialisation\" = sp.\"idSpecialisation\"
            INNER JOIN orientation o ON sp.idorientation = o.idorientation
            INNER JOIN section sec ON o.section_idsection = sec.idsection
            WHERE a.type_agent = 'Enseignant' AND sec.idsection IN ($sectionsParams)
        ";
        
        $paramsEnseignants = [];
        $paramIndex = 0;
        
        // Ajouter les paramètres pour les sections
        foreach ($userSections as $sectionId) {
            $paramsEnseignants[$paramIndex] = $sectionId;
            $paramIndex++;
        }
        
        // Ajouter la condition de recherche si nécessaire
        if (!empty($search)) {
            $queryEnseignants .= " AND (a.noms LIKE ? OR g.designation LIKE ? OR s.designation LIKE ?)";
            $searchParam = "%$search%";
            $paramsEnseignants[$paramIndex] = $searchParam;
            $paramsEnseignants[$paramIndex + 1] = $searchParam;
            $paramsEnseignants[$paramIndex + 2] = $searchParam;
        }
        
        $queryEnseignants .= " ORDER BY a.noms ASC";
        $stmtEnseignants = $db->prepare($queryEnseignants);
        
        foreach ($paramsEnseignants as $i => $value) {
            $stmtEnseignants->bindValue($i + 1, $value);
        }
        
        $stmtEnseignants->execute();
        $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer toutes les orientations pour la section sélectionnée
    $stmtOrientations = $db->prepare("
        SELECT o.*
        FROM orientation o
        WHERE o.section_idsection = :idSection
        ORDER BY o.\"designationOrientation\"
    ");
    $stmtOrientations->bindParam(':idSection', $selectedSection, PDO::PARAM_INT);
    $stmtOrientations->execute();
    $orientations = $stmtOrientations->fetchAll(PDO::FETCH_ASSOC);
    
    // Charger toutes les spécialisations pour les unités de recherche disponibles et les orientations
    foreach ($uniteRecherches as $ur) {
        $specialisationsMap[$ur['idunite_recherche']] = [];
        
        foreach ($orientations as $orientation) {
            $stmtAllSpecs = $db->prepare("
                SELECT s.*, o.\"designationOrientation\"
                FROM specialisation s
                JOIN orientation o ON s.idorientation = o.idorientation
                WHERE s.\"idUnite_recherche\" = :idUr AND s.idorientation = :idOrientation
                ORDER BY s.designation
            ");
            $stmtAllSpecs->bindParam(':idUr', $ur['idunite_recherche'], PDO::PARAM_INT);
            $stmtAllSpecs->bindParam(':idOrientation', $orientation['idorientation'], PDO::PARAM_INT);
            $stmtAllSpecs->execute();
            $specs = $stmtAllSpecs->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($specs)) {
                if (!isset($specialisationsMap[$ur['idunite_recherche']][$orientation['idorientation']])) {
                    $specialisationsMap[$ur['idunite_recherche']][$orientation['idorientation']] = [
                        'orientation' => $orientation,
                        'specialisations' => []
                    ];
                }
                
                $specialisationsMap[$ur['idunite_recherche']][$orientation['idorientation']]['specialisations'] = array_merge(
                    $specialisationsMap[$ur['idunite_recherche']][$orientation['idorientation']]['specialisations'] ?? [],
                    $specs
                );
            }
        }
    }
    
    // Préparer les données des spécialisations des enseignants pour éviter les requêtes AJAX
    $teacherSpecialisationsMap = [];
    foreach ($enseignants as $ens) {
        $stmtSpecs = $db->prepare("
            SELECT es.id as \"idAffectation\", ur.\"designation_UR\", ur.idunite_recherche, 
                   s.designation, s.\"idSpecialisation\", s.idorientation, 
                   o.\"designationOrientation\",
                   sec.\"designationSection\", es.\"dateAffectation\"
            FROM enseignant_specialisation es
            JOIN specialisation s ON es.\"idSpecialisation\" = s.\"idSpecialisation\"
            JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
            JOIN orientation o ON s.idorientation = o.idorientation
            JOIN section sec ON o.section_idsection = sec.idsection
            WHERE es.\"idAgent\" = :idAgent AND sec.idsection = :idSection
            ORDER BY ur.\"designation_UR\", o.\"designationOrientation\", s.designation
        ");
        $stmtSpecs->bindParam(':idAgent', $ens['idAgent'], PDO::PARAM_INT);
        $stmtSpecs->bindParam(':idSection', $selectedSection, PDO::PARAM_INT);
        $stmtSpecs->execute();
        $teacherSpecialisationsMap[$ens['idAgent']] = $stmtSpecs->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>AFFECTATION DES ENSEIGNANTS AUX UNITÉS DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Affectation des Enseignants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Formulaire de filtrage -->
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionner une section</h5>
                        
                        <form id="filterForm" method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="ur/affecation_ur">
                            
                            <div class="col-md-5">
                                <label for="annee" class="form-label">Année académique</label>
                                <select name="annee" id="annee" class="form-select" onchange="document.getElementById('filterForm').submit();">
                                    <option value="0">Toutes les années</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($selectedAnnee == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= $annee['designation'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="section" class="form-label">Section</label>
                                <select name="section" id="section" class="form-select" required>
                                    <option value="">Sélectionner une section</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section['idsection'] ?>" <?= ($selectedSection == $section['idsection']) ? 'selected' : '' ?>>
                                            <?= $section['designationSection'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($selectedSection > 0): ?>
            <!-- Recherche d'enseignants -->
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Rechercher un enseignant
                            <span class="float-end">
                                <button class="btn btn-sm btn-success" onclick="openBatchAffectationModal()">
                                <i class="bi bi-people-fill"></i> Affectation par lot
                            </button>
                            </span>
                        </h5>
                        
                        <form method="GET" action="" class="mb-3">
                            <input type="hidden" name="view" value="ur/affecation_ur">
                            <input type="hidden" name="annee" value="<?= $selectedAnnee ?>">
                            <input type="hidden" name="section" value="<?= $selectedSection ?>">
                            
                            <div class="input-group">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un enseignant par nom, grade...">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tableau des enseignants -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des enseignants</h5>
                        
                        <?php if (empty($enseignants)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun enseignant trouvé avec les critères sélectionnés.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Nom</th>
                                            <th scope="col">Grade</th>
                                            <th scope="col">Service</th>
                                            <th scope="col">Spécialisations</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($enseignants as $enseignant):
                                            $teacherSpecs = $teacherSpecialisationsMap[$enseignant['idAgent']] ?? [];
                                        ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= htmlspecialchars($enseignant['noms']) ?></td>
                                            <td><?= htmlspecialchars($enseignant['gradeDesignation'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($enseignant['serviceDesignation'] ?? '-') ?></td>
                                            <td>
                                                <?php if (empty($teacherSpecs)): ?>
                                                    <span class="badge bg-secondary">Aucune spécialisation</span>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#specsFor<?= $enseignant['idAgent'] ?>">
                                                        <i class="bi bi-list"></i> <?= count($teacherSpecs) ?> spécialisation(s)
                                                    </button>
                                                    <div class="collapse mt-2" id="specsFor<?= $enseignant['idAgent'] ?>">
                                                        <div class="card card-body p-2">
                                                            <ul class="list-group list-group-flush">
                                                                <?php foreach ($teacherSpecs as $spec): ?>
                                                                    <li class="list-group-item py-1 px-2">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <strong><?= htmlspecialchars($spec['designation_UR']) ?></strong> » 
                                                                                <span class="text-muted"><?= htmlspecialchars($spec['designationOrientation']) ?></span> » 
                                                                                <?= htmlspecialchars($spec['designation']) ?>
                                                                            </div>
                                                                            <button class="btn btn-sm btn-danger" onclick="confirmRemoveSpecialisation(<?= $spec['idAffectation'] ?>, '<?= addslashes($spec['designation']) ?>')">
                                                                                <i class="bi bi-trash"></i>
                                                                            </button>
                                                                        </div>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="openAffectationModal(<?= $enseignant['idAgent'] ?>, '<?= addslashes($enseignant['noms']) ?>')">
                                                    <i class="bi bi-plus-circle"></i> Affecter
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-lg-12">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Veuillez sélectionner une section pour afficher la liste des enseignants et des unités de recherche.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main><!-- End #main -->

<!-- Modal pour affecter un enseignant à plusieurs spécialisations -->
<div class="modal fade" id="affectationModal" tabindex="-1" aria-labelledby="affectationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="affectationModalLabel">Affecter un enseignant à des spécialisations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="affectationForm" method="POST" action="controller/create_affectation.php" class="needs-validation" novalidate>
                    <input type="hidden" id=idAgent name=idAgent>
                    <input type="hidden" id=idSection name=idSection value="<?= $selectedSection ?>">
                    
                    <div class="mb-3">
                        <label for=nomEnseignant class="form-label">Enseignant</label>
                        <input type="text" id=nomEnseignant class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="idUniteRecherche" class="form-label">Unités de Recherche</label>
                        <select id="idUniteRecherche" name="idUniteRecherche[]" class="form-select" required multiple onchange="updateSpecialisationsList()">
                            <?php foreach ($uniteRecherches as $ur): ?>
                                <option value="<?= $ur['idunite_recherche'] ?>"><?= htmlspecialchars($ur['designation_UR']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs unités de recherche.</small>
                        <div class="invalid-feedback">Veuillez sélectionner au moins une unité de recherche.</div>
                    </div>


                    <div id="specialisationsContainer" class="mb-3 d-none">
                        <label class="form-label">Spécialisations disponibles</label>
                        <div class="card">
                            <div class="card-body">
                                <div id="specialisationsList">
                                    <div class="text-center py-3">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                        <div class="mt-2">Veuillez sélectionner une unité de recherche</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Sélectionnez une ou plusieurs spécialisations</div>
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

<!-- Modal pour l'affectation par lot -->
<div class="modal fade" id="batchAffectationModal" tabindex="-1" aria-labelledby="batchAffectationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchAffectationModalLabel">Affectation par lot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="batchAffectationForm" method="POST" action="controller/create_batch_affectation.php" class="needs-validation" novalidate>
                    <input type="hidden" name=idSection value="<?= $selectedSection ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="batchIdUniteRecherche" class="form-label">Unité de Recherche</label>
                            <select id="batchIdUniteRecherche" name="idUniteRecherche" class="form-select" required onchange="updateBatchSpecialisationsList()">
                                <option value="">Sélectionner une unité de recherche</option>
                                <?php foreach ($uniteRecherches as $ur): ?>
                                    <option value="<?= $ur['idunite_recherche'] ?>"><?= htmlspecialchars($ur['designation_UR']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une unité de recherche.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="batchIdSpecialisation" class="form-label">Spécialisation</label>
                            <select id="batchIdSpecialisation" name="idSpecialisation[]" class="form-select" required multiple>
                                <option value="">Sélectionner d'abord une unité de recherche</option>
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs spécialisations.</small>
                            <div class="invalid-feedback">Veuillez sélectionner au moins une spécialisation.</div>
                        </div>
                    </div>

                    
                    <div class="mb-3">
                        <label class="form-label">Enseignants disponibles</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <div class="row">
                                    <?php foreach ($enseignants as $ens): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="enseignants[]" value="<?= $ens['idAgent'] ?>" id="ens<?= $ens['idAgent'] ?>">
                                                <label class="form-check-label" for="ens<?= $ens['idAgent'] ?>">
                                                    <?= htmlspecialchars($ens['noms']) ?>
                                                    <?php if (!empty($ens['gradeDesignation'])): ?>
                                                        <span class="text-muted">(<?= htmlspecialchars($ens['gradeDesignation']) ?>)</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllEnseignants(true)">Sélectionner tous</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllEnseignants(false)">Désélectionner tous</button>
                        </div>
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

<script>
// Variable globale pour stocker les données des spécialisations par unité de recherche et orientation
const specialisationsData = <?= json_encode($specialisationsMap) ?>;

// Fonction pour ouvrir le modal d'affectation pour un enseignant
function openAffectationModal(idAgent, nomEnseignant) {
    document.getElementById('idAgent').value = idAgent;
    document.getElementById('nomEnseignant').value = nomEnseignant;
    document.getElementById('idUniteRecherche').value = '';
    document.getElementById('specialisationsContainer').classList.add('d-none');
    document.getElementById('specialisationsList').innerHTML = '<div class="text-center py-3"><div class="mt-2">Veuillez sélectionner une unité de recherche</div></div>';
        
    // Afficher le modal
    new bootstrap.Modal(document.getElementById('affectationModal')).show();
}

// Fonction pour mettre à jour la liste des spécialisations en fonction des unités de recherche sélectionnées
function updateSpecialisationsList() {
    const select = document.getElementById('idUniteRecherche');
    const selectedUnits = Array.from(select.selectedOptions).map(option => option.value);
    const specialisationsContainer = document.getElementById('specialisationsContainer');
    const specialisationsList = document.getElementById('specialisationsList');
    
    // Masquer le conteneur si aucune unité n'est sélectionnée
    if (selectedUnits.length === 0) {
        specialisationsContainer.classList.add('d-none');
        return;
    }
    
    // Afficher le conteneur
    specialisationsContainer.classList.remove('d-none');
    
    // Générer le HTML pour les spécialisations regroupées par unité et orientation
    let html = '';
    let hasSpecialisations = false;
    
    selectedUnits.forEach(idUniteRecherche => {
        // Si aucune spécialisation n'est disponible pour cette unité
        if (!specialisationsData[idUniteRecherche] || Object.keys(specialisationsData[idUniteRecherche]).length === 0) {
            return;
        }
        
        // Récupérer le nom de l'unité de recherche
        const unitName = select.querySelector(`option[value="${idUniteRecherche}"]`).textContent;
        
        html += `<div class="card mb-3">
            <div class="card-header bg-light">
                <strong>${unitName}</strong>
            </div>
            <div class="card-body">`;
        
        let hasUnitSpecialisations = false;
        
        for (const [idOrientation, data] of Object.entries(specialisationsData[idUniteRecherche])) {
            if (!data.specialisations || data.specialisations.length === 0) continue;
            
            hasUnitSpecialisations = true;
            hasSpecialisations = true;
            
            html += `<div class="mb-3">
                <h6 class="text-primary">${data.orientation.designationOrientation}</h6>
                <div class="ms-3">`;
            
            data.specialisations.forEach(spec => {
                html += `<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="specialisations[]" value="${spec.idSpecialisation}" id="spec${spec.idSpecialisation}">
                    <label class="form-check-label" for="spec${spec.idSpecialisation}">
                        ${spec.designation}
                    </label>
                </div>`;
            });
            
            html += `</div></div>`;
        }
        
        if (!hasUnitSpecialisations) {
            html += `<div class="alert alert-info mb-0">Aucune spécialisation disponible pour cette unité de recherche.</div>`;
        }
        
        html += `</div></div>`;
    });
    
    if (!hasSpecialisations) {
        html = '<div class="alert alert-info">Aucune spécialisation disponible pour les unités de recherche sélectionnées.</div>';
    }
    
    specialisationsList.innerHTML = html;
}


// Fonction pour ouvrir le modal d'affectation par lot
function openBatchAffectationModal() {
    // Réinitialiser le formulaire
    document.getElementById('batchAffectationForm').reset();
    document.getElementById('batchIdUniteRecherche').value = '';
    updateBatchSpecialisationsList();
    
    // Afficher le modal
    new bootstrap.Modal(document.getElementById('batchAffectationModal')).show();
}

// Fonction pour mettre à jour la liste des spécialisations dans le formulaire d'affectation par lot
function updateBatchSpecialisationsList() {
    const idUniteRecherche = document.getElementById('batchIdUniteRecherche').value;
    const specialisationSelect = document.getElementById('batchIdSpecialisation');
    
    // Vider la liste des spécialisations
    specialisationSelect.innerHTML = '<option value="">Sélectionner une spécialisation</option>';
    
    // Si aucune unité n'est sélectionnée
    if (!idUniteRecherche) {
        specialisationSelect.innerHTML = '<option value="">Sélectionner d\'abord une unité de recherche</option>';
        return;
    }
    
    // Si aucune spécialisation n'est disponible pour cette unité
    if (!specialisationsData[idUniteRecherche] || Object.keys(specialisationsData[idUniteRecherche]).length === 0) {
        specialisationSelect.innerHTML = '<option value="">Aucune spécialisation disponible</option>';
        return;
    }
    
    // Ajouter les spécialisations regroupées par orientation
    for (const [idOrientation, data] of Object.entries(specialisationsData[idUniteRecherche])) {
        if (!data.specialisations || data.specialisations.length === 0) continue;
        
        // Créer un groupe d'options pour cette orientation
        const optgroup = document.createElement('optgroup');
        optgroup.label = data.orientation.designationOrientation;
        
        data.specialisations.forEach(spec => {
            const option = document.createElement('option');
            option.value = spec.idSpecialisation;
            option.textContent = spec.designation;
            optgroup.appendChild(option);
        });
        
        specialisationSelect.appendChild(optgroup);
    }
}

// Fonction pour sélectionner ou désélectionner tous les enseignants
function selectAllEnseignants(select) {
    const checkboxes = document.querySelectorAll('input[name="enseignants[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = select;
    });
}

// Fonction pour confirmer la suppression d'une affectation
function confirmRemoveSpecialisation(idAffectation, designation) {
    Swal.fire({
        title: 'Confirmer la suppression',
        text: `Voulez-vous vraiment retirer l'enseignant de la spécialisation "${designation}" ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, retirer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/delete_affectation.php?id=${idAffectation}&section=${<?= $selectedSection ?>}&annee=${<?= $selectedAnnee ?>}`;
        }
    });
}

// Validation des formulaires Bootstrap
(function () {
    'use strict';
    
    // Récupérer tous les formulaires auxquels nous voulons appliquer des styles de validation Bootstrap personnalisés
    var forms = document.querySelectorAll('.needs-validation');
    
    // Boucle pour empêcher la soumission du formulaire et appliquer la validation
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Changer automatiquement de section quand on change d'année
document.getElementById('annee').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

// Changer automatiquement de paramètres quand on change de section
document.getElementById('section').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
</script>

<?php include "./views/include/footer.php"; ?>


