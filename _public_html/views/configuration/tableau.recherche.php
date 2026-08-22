<?php
include "./views/include/header.php";

// Obtenir l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

// Variables pour les filtres
$filtreOrientation = isset($_GET['orientation']) ? $_GET['orientation'] : '';
$filtreSection = isset($_GET['section']) ? $_GET['section'] : '';
$filtreSpecialisation = isset($_GET['specialisation']) ? $_GET['specialisation'] : '';
$filtreAnnee = isset($_GET['annee']) ? $_GET['annee'] : '';
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';

try {
    // Requête pour récupérer les enseignants par unité de recherche avec spécialisations
   $sqlEnseignantsUR = '
        SELECT
            ur.idunite_recherche,
            ur."designation_UR",
            ur.description as description_ur,
            COUNT(DISTINCT es."idAgent") as nombre_enseignants,
            COUNT(DISTINCT sp."idSpecialisation") as nombre_specialisations,
            string_agg(DISTINCT sp.designation, \', \') as liste_specialisations,
            string_agg(DISTINCT sec."designationSection", \', \') as sections_associees
        FROM unite_recherche ur
        LEFT JOIN specialisation sp ON ur.idunite_recherche = sp."idUnite_recherche"
        LEFT JOIN orientation ori ON sp.idorientation = ori.idorientation
        LEFT JOIN section sec ON ori.section_idsection = sec.idsection
        LEFT JOIN enseignant_specialisation es ON sp."idSpecialisation" = es."idSpecialisation"
        WHERE 1=1
    ';
    
    $params = [];
    
    if (!empty($filtreSection)) {
        $sqlEnseignantsUR .= " AND sec.idsection = :section";
        $params[':section'] = $filtreSection;
    }
    
    if (!empty($filtreSpecialisation)) {
        $sqlEnseignantsUR .= ' AND sp."idSpecialisation" = :specialisation';
        $params[':specialisation'] = $filtreSpecialisation;
    }

    if (!empty($recherche)) {
        $sqlEnseignantsUR .= ' AND (ur."designation_UR" LIKE :recherche OR ur.description LIKE :recherche2 OR sp.designation LIKE :recherche3)';
        $params[':recherche'] = '%' . $recherche . '%';
        $params[':recherche2'] = '%' . $recherche . '%';
        $params[':recherche3'] = '%' . $recherche . '%';
    }

    $sqlEnseignantsUR .= ' GROUP BY ur.idunite_recherche, ur."designation_UR", ur.description
                          ORDER BY ur."designation_UR"';
    
    $stmtEnseignantsUR = $pdo->prepare($sqlEnseignantsUR);
    $stmtEnseignantsUR->execute($params);
    $enseignantsByUR = $stmtEnseignantsUR->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour récupérer les étudiants par directeur et spécialisation
    $sqlEtudiantsProf = '
        SELECT
            a."idAgent",
            a.noms as nomDirecteur,
            a.email,
            g.designation as grade,
            sp.designation as specialisation,
            ur."designation_UR",
            sec."designationSection",
            aa.designation as annee_acad,
            COUNT(DISTINCT CASE WHEN suj.statut_validation = \'Validé\' THEN suj.idsujets END) as sujets_valides,
            COUNT(DISTINCT CASE WHEN suj.statut_validation = \'En attente\' THEN suj.idsujets END) as sujets_en_attente,
            COUNT(DISTINCT CASE WHEN suj.statut_validation = \'A reformulé\' THEN suj.idsujets END) as sujets_a_reformuler,
            COUNT(DISTINCT suj.idsujets) as total_sujets,
            COUNT(DISTINCT e.idetudiant) as total_etudiants
        FROM agent a
        LEFT JOIN grade g ON a.grade_id = g.idgrade
        LEFT JOIN enseignant_specialisation es ON a."idAgent" = es."idAgent"
        LEFT JOIN specialisation sp ON es."idSpecialisation" = sp."idSpecialisation"
        LEFT JOIN unite_recherche ur ON sp."idUnite_recherche" = ur.idunite_recherche
        LEFT JOIN orientation ori ON sp.idorientation = ori.idorientation
        LEFT JOIN section sec ON ori.section_idsection = sec.idsection
        LEFT JOIN sujets suj ON a."idAgent" = suj."idDirecteur"
        LEFT JOIN etudiant e ON suj.etudiant_idetudiant = e.idetudiant
        LEFT JOIN annee_acad aa ON suj.annee_acad_idannee_acad = aa.idannee_acad
        WHERE a.type_agent = \'Enseignant\'
    ';
    
    $paramsProf = [];
    
    if (!empty($filtreSection)) {
        $sqlEtudiantsProf .= " AND sec.idsection = :section";
        $paramsProf[':section'] = $filtreSection;
    }
    
    if (!empty($filtreSpecialisation)) {
        $sqlEtudiantsProf .= ' AND sp."idSpecialisation" = :specialisation';
        $paramsProf[':specialisation'] = $filtreSpecialisation;
    }

    if (!empty($filtreAnnee)) {
        $sqlEtudiantsProf .= ' AND aa.idannee_acad = :annee';
        $paramsProf[':annee'] = $filtreAnnee;
    }

    if (!empty($recherche)) {
        $sqlEtudiantsProf .= ' AND (a.noms LIKE :recherche OR sp.designation LIKE :recherche2 OR ur."designation_UR" LIKE :recherche3)';
        $paramsProf[':recherche'] = '%' . $recherche . '%';
        $paramsProf[':recherche2'] = '%' . $recherche . '%';
        $paramsProf[':recherche3'] = '%' . $recherche . '%';
    }

    $sqlEtudiantsProf .= ' GROUP BY a."idAgent", a.noms, a.email, g.designation, sp.designation, ur."designation_UR", sec."designationSection", aa.designation
                          HAVING COUNT(DISTINCT suj.idsujets) > 0
                          ORDER BY ur."designation_UR", a.noms';
    
    $stmtEtudiantsProf = $pdo->prepare($sqlEtudiantsProf);
    $stmtEtudiantsProf->execute($paramsProf);
    $etudiantsByProf = $stmtEtudiantsProf->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour récupérer les sections
    $sqlSections = 'SELECT idsection, "designationSection" FROM section ORDER BY "designationSection"';
    $stmtSections = $pdo->prepare($sqlSections);
    $stmtSections->execute();
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour récupérer les orientations
    $sqlOrientations = 'SELECT idorientation, "designationOrientation" FROM orientation ORDER BY "designationOrientation"';
    $stmtOrientations = $pdo->prepare($sqlOrientations);
    $stmtOrientations->execute();
    $orientations = $stmtOrientations->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour récupérer les spécialisations
    $sqlSpecialisations = '
        SELECT DISTINCT sp."idSpecialisation", sp.designation, ur."designation_UR", s."designationSection"
        FROM specialisation sp
        LEFT JOIN unite_recherche ur ON sp."idUnite_recherche" = ur.idunite_recherche
        LEFT JOIN section s ON sp.idorientation = s.idsection
        ORDER BY ur."designation_UR", sp.designation
    ';
    $stmtSpecialisations = $pdo->prepare($sqlSpecialisations);
    $stmtSpecialisations->execute();
    $specialisations = $stmtSpecialisations->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour récupérer les années académiques
    $sqlAnnees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC";
    $stmtAnnees = $pdo->prepare($sqlAnnees);
    $stmtAnnees->execute();
    $annees = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour obtenir l'année académique active
    $sqlCurrentAnnee = "SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmtCurrentAnnee = $pdo->prepare($sqlCurrentAnnee);
    $stmtCurrentAnnee->execute();
    $currentAnnee = $stmtCurrentAnnee->fetch(PDO::FETCH_ASSOC);

    // Statistiques générales
    // Nombre total d'enseignants chercheurs
    $sqlTeacherCount = '
        SELECT COUNT(DISTINCT a."idAgent") as count
        FROM agent a
        INNER JOIN enseignant_specialisation es ON a."idAgent" = es."idAgent"
        WHERE a.type_agent = \'Enseignant\'
    ';
    $stmtTeacherCount = $pdo->prepare($sqlTeacherCount);
    $stmtTeacherCount->execute();
    $teacherCount = $stmtTeacherCount->fetch(PDO::FETCH_ASSOC)['count'];

    // Nombre total d'unités de recherche
    $sqlURCount = "SELECT COUNT(*) as count FROM unite_recherche";
    $stmtURCount = $pdo->prepare($sqlURCount);
    $stmtURCount->execute();
    $urCount = $stmtURCount->fetch(PDO::FETCH_ASSOC)['count'];

    // Nombre total de spécialisations
    $sqlSpecCount = "SELECT COUNT(*) as count FROM specialisation";
    $stmtSpecCount = $pdo->prepare($sqlSpecCount);
    $stmtSpecCount->execute();
    $specCount = $stmtSpecCount->fetch(PDO::FETCH_ASSOC)['count'];

    // Nombre total de sujets par statut avec application des filtres
    $sqlSubjectsStats = '
        SELECT
            suj.statut_validation,
            COUNT(*) as count
        FROM sujets suj
        LEFT JOIN specialisation sp ON suj."idSpecialisation" = sp."idSpecialisation"
        LEFT JOIN orientation ori ON sp.idorientation = ori.idorientation
        LEFT JOIN section sec ON ori.section_idsection = sec.idsection
        LEFT JOIN annee_acad aa ON suj.annee_acad_idannee_acad = aa.idannee_acad
        WHERE 1=1
    ';

    $paramsStats = [];

    if (!empty($filtreSection)) {
        $sqlSubjectsStats .= ' AND sec.idsection = :section';
        $paramsStats[':section'] = $filtreSection;
    }

    if (!empty($filtreSpecialisation)) {
        $sqlSubjectsStats .= ' AND sp."idSpecialisation" = :specialisation';
        $paramsStats[':specialisation'] = $filtreSpecialisation;
    }
    
    if (!empty($filtreAnnee)) {
        $sqlSubjectsStats .= " AND aa.idannee_acad = :annee";
        $paramsStats[':annee'] = $filtreAnnee;
    }
    
    $sqlSubjectsStats .= " GROUP BY suj.statut_validation";
    
    $stmtSubjectsStats = $pdo->prepare($sqlSubjectsStats);
    $stmtSubjectsStats->execute($paramsStats);
    $subjectsStats = $stmtSubjectsStats->fetchAll(PDO::FETCH_ASSOC);
    
    $validatedSubjectsCount = 0;
    $pendingSubjectsCount = 0;
    $aReformulerSubjectsCount = 0;
    $totalSubjectsCount = 0;
    
    foreach ($subjectsStats as $stat) {
        $totalSubjectsCount += $stat['count'];
        switch ($stat['statut_validation']) {
            case 'Validé':
                $validatedSubjectsCount = $stat['count'];
                break;
            case 'En attente':
                $pendingSubjectsCount = $stat['count'];
                break;
            case 'A reformulé':
                $aReformulerSubjectsCount = $stat['count'];
                break;
        }
    }

    // Nombre total d'étudiants avec sujets
    $sqlStudentCount = '
        SELECT COUNT(DISTINCT e.idetudiant) as count
        FROM etudiant e
        INNER JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
    ';
    $stmtStudentCount = $pdo->prepare($sqlStudentCount);
    $stmtStudentCount->execute();
    $studentCount = $stmtStudentCount->fetch(PDO::FETCH_ASSOC)['count'];

    // Statistiques des sujets par section avec application des filtres
    $sqlSubjectsBySection = '
        SELECT
            sec.idsection,
            sec."designationSection",
            COUNT(CASE WHEN suj.statut_validation = \'Validé\' THEN 1 END) as sujets_valides,
            COUNT(CASE WHEN suj.statut_validation = \'En attente\' THEN 1 END) as sujets_en_attente,
            COUNT(CASE WHEN suj.statut_validation = \'A reformulé\' THEN 1 END) as sujets_a_reformuler,
            COUNT(CASE WHEN suj.statut_validation = \'Modifié\' THEN 1 END) as sujets_modifies,
            COUNT(suj.idsujets) as total_sujets
        FROM section sec
        LEFT JOIN orientation ori ON sec.idsection = ori.section_idsection
        LEFT JOIN specialisation sp ON ori.idorientation = sp.idorientation
        LEFT JOIN sujets suj ON sp."idSpecialisation" = suj."idSpecialisation"
        LEFT JOIN annee_acad aa ON suj.annee_acad_idannee_acad = aa.idannee_acad
        WHERE 1=1
    ';

    $paramsBySection = [];

    if (!empty($filtreSection)) {
        $sqlSubjectsBySection .= ' AND sec.idsection = :section';
        $paramsBySection[':section'] = $filtreSection;
    }

    if (!empty($filtreSpecialisation)) {
        $sqlSubjectsBySection .= ' AND sp."idSpecialisation" = :specialisation';
        $paramsBySection[':specialisation'] = $filtreSpecialisation;
    }

    if (!empty($filtreAnnee)) {
        $sqlSubjectsBySection .= ' AND aa.idannee_acad = :annee';
        $paramsBySection[':annee'] = $filtreAnnee;
    }

    $sqlSubjectsBySection .= ' GROUP BY sec.idsection, sec."designationSection"
                               HAVING COUNT(suj.idsujets) > 0
                               ORDER BY sec."designationSection"';
    
    $stmtSubjectsBySection = $pdo->prepare($sqlSubjectsBySection);
    $stmtSubjectsBySection->execute($paramsBySection);
    $subjectsBySection = $stmtSubjectsBySection->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'erreur, initialiser des valeurs par défaut
    $enseignantsByUR = [];
    $etudiantsByProf = [];
    $sections = [];
    $orientations = [];
    $specialisations = [];
    $annees = [];
    $currentAnnee = null;
    $teacherCount = 0;
    $urCount = 0;
    $specCount = 0;
    $studentCount = 0;
    $validatedSubjectsCount = 0;
    $pendingSubjectsCount = 0;
    $aReformulerSubjectsCount = 0;
    $totalSubjectsCount = 0;
    $subjectsBySection = [];
    
    // Log de l'erreur
    error_log("Erreur dans tableau.recherche.php: " . $e->getMessage());
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Tableau de Bord de la Recherche</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Accueil</a></li>
                <li class="breadcrumb-item active">Tableau de Bord Recherche</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Cartes de résumé -->
        <div class="row mb-3">
            <!-- Nombre total d'enseignants chercheurs -->
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="card info-card sales-card h-100">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1">Enseignants Chercheurs</h6>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= $teacherCount ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nombre total d'unités de recherche -->
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="card info-card revenue-card h-100">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1">Unités de Recherche</h6>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= $urCount ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nombre total de spécialisations -->
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="card info-card customers-card h-100">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1">Spécialisations</h6>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= $specCount ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nombre total de sujets validés -->
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="card info-card sales-card h-100">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1">Sujets Validés</h6>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= $validatedSubjectsCount ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques détaillées des sujets -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-2">Répartition des Sujets</h6>
                        <div class="row">
                            <div class="col-md-3 col-6 mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2 fs-6"><?= $validatedSubjectsCount ?></span>
                                    <small>Validés</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-warning me-2 fs-6"><?= $pendingSubjectsCount ?></span>
                                    <small>En attente</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-danger me-2 fs-6"><?= $aReformulerSubjectsCount ?></span>
                                    <small>À reformuler</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary me-2 fs-6"><?= $totalSubjectsCount ?></span>
                                    <small>Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtre global -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-2">Filtres globaux</h6>
                        <form id="globalFilterForm" method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="configuration/tableau.recherche">
                            
                            <div class="col-md-3">
                                <label for="annee" class="form-label">
                                    <i class="bi bi-calendar3 me-1"></i>Année Académique
                                    <span class="text-danger">*</span>
                                </label>
                                <select id="annee" name="annee" class="form-select" onchange="loadSectionsByAnnee(this.value)">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= $filtreAnnee == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="section" class="form-label">
                                    <i class="bi bi-diagram-3 me-1"></i>Section
                                </label>
                                <select id="section" name="section" class="form-select" onchange="loadSpecialisationsBySection(this.value)" disabled>
                                    <option value="">Choisir d'abord une année</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="specialisation" class="form-label">
                                    <i class="bi bi-gear me-1"></i>Spécialisation
                                </label>
                                <select id="specialisation" name="specialisation" class="form-select" disabled>
                                    <option value="">Choisir d'abord une section</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="globalSearch" class="form-label">
                                    <i class="bi bi-search me-1"></i>Recherche globale
                                </label>
                                <input type="text" class="form-control" id="globalSearch" name="recherche" 
                                       placeholder="Rechercher..." value="<?= htmlspecialchars($recherche) ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-filter"></i> Appliquer filtres
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetGlobalFilters()">
                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                </button>
                                <?php if (!empty($filtreSection) || !empty($filtreSpecialisation) || !empty($filtreAnnee) || !empty($recherche)): ?>
                                <span class="badge bg-info ms-2">
                                    <i class="bi bi-funnel"></i> Filtres actifs
                                </span>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets pour séparer les sections -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs nav-tabs-bordered" id="researchTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="units-tab" data-bs-toggle="tab" data-bs-target="#units" 
                                        type="button" role="tab" aria-controls="units" aria-selected="true">
                                    Unités de Recherche
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="directors-tab" data-bs-toggle="tab" data-bs-target="#directors" 
                                        type="button" role="tab" aria-controls="directors" aria-selected="false">
                                    Directeurs et Sujets
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" 
                                        type="button" role="tab" aria-controls="stats" aria-selected="false">
                                    Statistiques
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-3" id="researchTabContent">
                            <!-- Onglet Unités de Recherche -->
                            <div class="tab-pane fade show active" id="units" role="tabpanel" aria-labelledby="units-tab">
                                <!-- Filtres spécifiques aux unités de recherche -->
                                <div class="mb-3">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" id="searchUR" placeholder="Rechercher une unité..." 
                                                   value="<?= htmlspecialchars($recherche) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="filterURSection">
                                                <option value="">Toutes les sections</option>
                                                <!-- Les sections seront chargées dynamiquement selon l'année -->
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-primary" id="searchURBtn">Filtrer</button>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-secondary" id="resetURBtn">Réinitialiser</button>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-success" onclick="exportURToExcel()">
                                                <i class="bi bi-file-excel"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tableau des unités de recherche -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tableUR">
                                        <thead>
                                            <tr>
                                                <th>Unité de Recherche</th>
                                                <th>Sections Associées</th>
                                                <th>Description</th>
                                                <th>Spécialisations</th>
                                                <th>Enseignants</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enseignantsByUR as $ur): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($ur['designation_UR']) ?></strong></td>
                                                <td>
                                                    <?php if (!empty($ur['sections_associees'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($ur['sections_associees']) ?></small>
                                                    <?php else: ?>
                                                        <em class="text-muted">Aucune section</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($ur['description_ur'])): ?>
                                                        <?= strlen($ur['description_ur']) > 80 ? 
                                                              htmlspecialchars(substr($ur['description_ur'], 0, 80)) . '...' : 
                                                              htmlspecialchars($ur['description_ur']) ?>
                                                    <?php else: ?>
                                                        <em class="text-muted">Pas de description</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $ur['nombre_specialisations'] ?></span>
                                                    <?php if (!empty($ur['liste_specialisations'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($ur['liste_specialisations']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-primary"><?= $ur['nombre_enseignants'] ?></span></td>
                                                <td>
                                                                                                        <button class="btn btn-sm btn-info" onclick="viewURDetails(<?= $ur['idunite_recherche'] ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Onglet Directeurs et Sujets -->
                            <div class="tab-pane fade" id="directors" role="tabpanel" aria-labelledby="directors-tab">
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Note :</strong> Un directeur peut apparaître plusieurs fois s'il encadre des sujets dans différentes spécialisations ou sections.
                                </div>
                                
                                <!-- Filtres spécifiques aux directeurs -->
                                <div class="mb-3">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" id="searchDirector" placeholder="Rechercher un directeur..." 
                                                   value="<?= htmlspecialchars($recherche) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="filterDirectorSection">
                                                <option value="">Toutes les sections</option>
                                                <!-- Les sections seront chargées dynamiquement selon l'année -->
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="filterDirectorSpec">
                                                <option value="">Toutes les spécialisations</option>
                                                <!-- Les spécialisations seront chargées dynamiquement selon la section -->
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-primary" id="searchDirectorBtn">Filtrer</button>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-secondary" id="resetDirectorBtn">Reset</button>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-success" onclick="exportDirectorsToExcel()">
                                                <i class="bi bi-file-excel"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tableau des directeurs et sujets -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tableDirectors">
                                        <thead>
                                            <tr>
                                                <th>Directeur</th>
                                                <th>Grade</th>
                                                <th>Spécialisation</th>
                                                <th>Unité de Recherche</th>
                                                <th>Section</th>
                                                <th>Sujets Validés</th>
                                                <th>Sujets En Attente</th>
                                                <th>Sujets À reformuler</th>
                                                <th>Total Étudiants</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiantsByProf as $prof): ?>
                                            <tr data-section="<?= $prof['idsection'] ?? '' ?>" data-specialisation="<?= $prof['idSpecialisation'] ?? '' ?>">
                                                <td>
                                                    <strong><?= htmlspecialchars($prof['nomDirecteur']) ?></strong>
                                                    <?php if (!empty($prof['email'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($prof['email']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($prof['grade'] ?? 'Non défini') ?></td>
                                                <td><?= htmlspecialchars($prof['specialisation'] ?? 'Non définie') ?></td>
                                                <td><?= htmlspecialchars($prof['designation_UR'] ?? 'Non définie') ?></td>
                                                <td><?= htmlspecialchars($prof['designationSection'] ?? 'Non définie') ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= $prof['sujets_valides'] ?? 0 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <?= $prof['sujets_en_attente'] ?? 0 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?= $prof['sujets_a_reformuler'] ?? 0 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= $prof['total_etudiants'] ?? 0 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewDirectorDetails(<?= $prof['idAgent'] ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <td colspan="5">
                                                    <strong>Total des lignes affichées</strong>
                                                    <small class="text-muted">(peut inclure des doublons)</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= array_sum(array_column($etudiantsByProf, 'sujets_valides')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <?= array_sum(array_column($etudiantsByProf, 'sujets_en_attente')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?= array_sum(array_column($etudiantsByProf, 'sujets_a_reformuler')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= array_sum(array_column($etudiantsByProf, 'total_etudiants')) ?>
                                                    </span>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Onglet Statistiques -->
                            <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                                <!-- Statistiques des sujets par section pour l'autorité -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-bar-chart me-2"></i>
                                                    Statistiques des sujets par section
                                                    <small class="text-muted">(pour la prise de décision de l'autorité)</small>
                                                    <?php if (!empty($filtreSection) || !empty($filtreSpecialisation) || !empty($filtreAnnee)): ?>
                                                    <span class="badge bg-primary ms-2">Filtré</span>
                                                    <?php endif; ?>
                                                </h5>
                                                
                                                <?php if (!empty($subjectsBySection)): ?>
                                                
                                                <!-- Filtres rapides pour les statistiques -->
                                                <div class="mb-3">
                                                    <small class="text-muted">Filtrage rapide :</small>
                                                    <div class="btn-group btn-group-sm ms-2" role="group">
                                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="filterTableByDecision('bg-success')">
                                                            Excellentes
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterTableByDecision('bg-warning')">
                                                            À surveiller
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="filterTableByDecision('bg-danger')">
                                                            Urgentes
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filterTableByDecision('')">
                                                            Toutes
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th>Section</th>
                                                                <th class="text-center">
                                                                    <i class="bi bi-check-circle text-success"></i>
                                                                    Validés
                                                                </th>
                                                                <th class="text-center">
                                                                    <i class="bi bi-clock text-warning"></i>
                                                                    En attente
                                                                </th>
                                                                <th class="text-center">
                                                                    <i class="bi bi-arrow-clockwise text-danger"></i>
                                                                    À reformuler
                                                                </th>
                                                                <th class="text-center">
                                                                    <i class="bi bi-arrow-repeat text-info"></i>
                                                                    Modifiés
                                                                </th>
                                                                <th class="text-center">
                                                                    <i class="bi bi-list-ol"></i>
                                                                    Total
                                                                </th>
                                                                <th class="text-center">Progression</th>
                                                                <th class="text-center">Décision</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $totalValides = 0;
                                                            $totalEnAttente = 0;
                                                            $totalAReformuler = 0;
                                                            $totalModifies = 0;
                                                            $totalGeneral = 0;
                                                            
                                                            foreach ($subjectsBySection as $section): 
                                                                $totalValides += $section['sujets_valides'];
                                                                $totalEnAttente += $section['sujets_en_attente'];
                                                                $totalAReformuler += $section['sujets_a_reformuler'];
                                                                $totalModifies += $section['sujets_modifies'];
                                                                $totalGeneral += $section['total_sujets'];
                                                                
                                                                $pourcentageValide = $section['total_sujets'] > 0 ? 
                                                                    round(($section['sujets_valides'] / $section['total_sujets']) * 100, 1) : 0;
                                                                $pourcentageEnAttente = $section['total_sujets'] > 0 ? 
                                                                    round(($section['sujets_en_attente'] / $section['total_sujets']) * 100, 1) : 0;
                                                                $pourcentageAReformuler = $section['total_sujets'] > 0 ? 
                                                                    round(($section['sujets_a_reformuler'] / $section['total_sujets']) * 100, 1) : 0;
                                                                
                                                                // Déterminer le statut de décision
                                                                $statutDecision = '';
                                                                $classDecision = '';
                                                                if ($pourcentageValide >= 80) {
                                                                    $statutDecision = 'Excellente progression';
                                                                    $classDecision = 'bg-success';
                                                                } elseif ($pourcentageValide >= 60) {
                                                                    $statutDecision = 'Bonne progression';
                                                                    $classDecision = 'bg-primary';
                                                                } elseif ($pourcentageValide >= 40) {
                                                                    $statutDecision = 'Progression modérée';
                                                                    $classDecision = 'bg-warning';
                                                                } elseif ($pourcentageAReformuler >= 40) {
                                                                    $statutDecision = 'Reformulation nécessaire';
                                                                    $classDecision = 'bg-danger';
                                                                } elseif ($pourcentageEnAttente >= 60) {
                                                                    $statutDecision = 'Suivi requis';
                                                                    $classDecision = 'bg-info';
                                                                } else {
                                                                    $statutDecision = 'Intervention urgente';
                                                                    $classDecision = 'bg-danger';
                                                                }
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?= htmlspecialchars($section['designationSection']) ?></strong>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-success fs-6"><?= $section['sujets_valides'] ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-warning fs-6"><?= $section['sujets_en_attente'] ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-danger fs-6"><?= $section['sujets_a_reformuler'] ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-info fs-6"><?= $section['sujets_modifies'] ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-primary fs-6"><?= $section['total_sujets'] ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <div class="progress" style="height: 25px;">
                                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                                             style="width: <?= $pourcentageValide ?>%" 
                                                                             title="<?= $pourcentageValide ?>% validés">
                                                                            <?= $pourcentageValide ?>%
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?= $classDecision ?> fs-6" 
                                                                          title="<?= $pourcentageValide ?>% validés, <?= $pourcentageEnAttente ?>% en attente, <?= $pourcentageAReformuler ?>% à reformuler">
                                                                        <?= $statutDecision ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot class="table-secondary">
                                                            <tr>
                                                                <th>TOTAL GÉNÉRAL</th>
                                                                <th class="text-center">
                                                                    <span class="badge bg-success fs-6"><?= $totalValides ?></span>
                                                                </th>
                                                                <th class="text-center">
                                                                    <span class="badge bg-warning fs-6"><?= $totalEnAttente ?></span>
                                                                </th>
                                                                <th class="text-center">
                                                                    <span class="badge bg-danger fs-6"><?= $totalAReformuler ?></span>
                                                                </th>
                                                                <th class="text-center">
                                                                    <span class="badge bg-info fs-6"><?= $totalModifies ?></span>
                                                                </th>
                                                                <th class="text-center">
                                                                    <span class="badge bg-primary fs-6"><?= $totalGeneral ?></span>
                                                                </th>
                                                                <th class="text-center">
                                                                    <div class="progress" style="height: 25px;">
                                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                                             style="width: <?= $totalGeneral > 0 ? round(($totalValides / $totalGeneral) * 100, 1) : 0 ?>%">
                                                                            <?= $totalGeneral > 0 ? round(($totalValides / $totalGeneral) * 100, 1) : 0 ?>%
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th class="text-center">
                                                                    <strong>Vue d'ensemble</strong>
                                                                </th>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                                
                                                <!-- Légende pour l'aide à la décision -->
                                                <div class="mt-3">
                                                    <h6><i class="bi bi-info-circle me-2"></i>Guide de décision pour l'autorité :</h6>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <ul class="list-unstyled">
                                                                <li><span class="badge bg-success me-2">Excellente progression</span> ≥ 80% validés - Section performante</li>
                                                                <li><span class="badge bg-primary me-2">Bonne progression</span> 60-79% validés - Bon suivi</li>
                                                                <li><span class="badge bg-warning me-2">Progression modérée</span> 40-59% validés - Surveillance</li>
                                                            </ul>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <ul class="list-unstyled">
                                                                <li><span class="badge bg-danger me-2">Reformulation nécessaire</span> ≥ 40% à reformuler - Accompagnement requis</li>
                                                                <li><span class="badge bg-info me-2">Suivi requis</span> Beaucoup en attente - Vérifier processus</li>
                                                                <li><span class="badge bg-danger me-2">Intervention urgente</span> < 40% validés - Action immédiate</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    Aucune donnée de sujets par section disponible.
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Répartition des sujets par statut -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Répartition des sujets par statut</h5>
                                                <div id="subjectStatusChart" style="min-height: 300px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Top 5 des directeurs par nombre d'étudiants -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Top 5 des directeurs</h5>
                                                <div id="topDirectorsChart" style="min-height: 300px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Répartition par unité de recherche -->
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Répartition par Unité de Recherche</h5>
                                                <div id="urDistributionChart" style="min-height: 350px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Évolution par Section -->
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    Évolution par Section
                                                    <div class="float-end">
                                                        <select id="sectionSelect" class="form-select form-select-sm" style="width: 200px;" onchange="loadEvolutionData(this.value)">
                                                            <option value="">Sélectionner une section</option>
                                                            <!-- Les sections seront chargées dynamiquement selon l'année -->
                                                        </select>
                                                    </div>
                                                </h5>
                                                <div id="evolutionChart" style="min-height: 350px;"></div>
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
    </section>
</main>

<!-- Modal pour afficher les détails d'une unité de recherche -->
<div class="modal fade" id="urDetailsModal" tabindex="-1" aria-labelledby="urDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="urDetailsModalLabel">Détails de l'unité de recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="urDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour afficher les détails d'un directeur -->
<div class="modal fade" id="directorDetailsModal" tabindex="-1" aria-labelledby="directorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="directorDetailsModalLabel">Détails du directeur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="directorDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver/dist/FileSaver.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des éléments DOM
    const searchURBtn = document.getElementById('searchURBtn');
    const resetURBtn = document.getElementById('resetURBtn');
    const searchUR = document.getElementById('searchUR');
    const filterURSection = document.getElementById('filterURSection');
    const tableUR = document.getElementById('tableUR');
    
    const searchDirectorBtn = document.getElementById('searchDirectorBtn');
    const resetDirectorBtn = document.getElementById('resetDirectorBtn');
    const searchDirector = document.getElementById('searchDirector');
    const filterDirectorSection = document.getElementById('filterDirectorSection');
    const filterDirectorSpec = document.getElementById('filterDirectorSpec');
    const tableDirectors = document.getElementById('tableDirectors');
    
    const sectionSelect = document.getElementById('sectionSelect');
    
    // Fonctions de filtrage pour le tableau des unités de recherche
    function filterTableUR() {
        const searchText = searchUR.value.toLowerCase();
        const section = filterURSection.value;
        const rows = tableUR.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const unite = row.cells[0].textContent.toLowerCase();
            const sectionText = row.cells[1].textContent.toLowerCase();
            const description = row.cells[2].textContent.toLowerCase();
            const specialisations = row.cells[3].textContent.toLowerCase();
            
            const matchSearch = searchText === '' || 
                             unite.includes(searchText) || 
                             description.includes(searchText) ||
                             specialisations.includes(searchText);
            
            const matchSection = section === '' || row.dataset.section === section;
            
            row.style.display = (matchSearch && matchSection) ? '' : 'none';
        });
    }
    
        // Fonctions de filtrage pour le tableau des directeurs
    function filterTableDirectors() {
        const searchText = searchDirector.value.toLowerCase();
        const section = filterDirectorSection.value;
        const specialisation = filterDirectorSpec.value;
        const rows = tableDirectors.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const directeur = row.cells[0].textContent.toLowerCase();
            const grade = row.cells[1].textContent.toLowerCase();
            const spec = row.cells[2].textContent.toLowerCase();
            const unite = row.cells[3].textContent.toLowerCase();
            
            const matchSearch = searchText === '' || 
                             directeur.includes(searchText) || 
                             grade.includes(searchText) ||
                             spec.includes(searchText) ||
                             unite.includes(searchText);
            
            const matchSection = section === '' || row.dataset.section === section;
            const matchSpecialisation = specialisation === '' || row.dataset.specialisation === specialisation;
            
            row.style.display = (matchSearch && matchSection && matchSpecialisation) ? '' : 'none';
        });
        
        // Mettre à jour les totaux
        updateDirectorTotals();
    }
    
    // Mise à jour des totaux dans le tableau des directeurs après filtrage
    function updateDirectorTotals() {
        const rows = Array.from(tableDirectors.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
        let totalValidated = 0;
        let totalPending = 0;
        let totalRejected = 0;
        let totalStudents = 0;
        
        rows.forEach(row => {
            const validatedSpan = row.cells[5]?.querySelector('span');
            const pendingSpan = row.cells[6]?.querySelector('span');
            const rejectedSpan = row.cells[7]?.querySelector('span');
            const studentsSpan = row.cells[8]?.querySelector('span');
            
            totalValidated += parseInt(validatedSpan?.textContent.trim() || '0') || 0;
            totalPending += parseInt(pendingSpan?.textContent.trim() || '0') || 0;
            totalRejected += parseInt(rejectedSpan?.textContent.trim() || '0') || 0;
            totalStudents += parseInt(studentsSpan?.textContent.trim() || '0') || 0;
        });
        
        const tfoot = tableDirectors.querySelector('tfoot');
        if (tfoot && tfoot.rows[0]) {
            const validatedFooterSpan = tfoot.rows[0].cells[5]?.querySelector('span');
            const pendingFooterSpan = tfoot.rows[0].cells[6]?.querySelector('span');
            const rejectedFooterSpan = tfoot.rows[0].cells[7]?.querySelector('span');
            const studentsFooterSpan = tfoot.rows[0].cells[8]?.querySelector('span');
            
            if (validatedFooterSpan) validatedFooterSpan.textContent = totalValidated;
            if (pendingFooterSpan) pendingFooterSpan.textContent = totalPending;
            if (rejectedFooterSpan) rejectedFooterSpan.textContent = totalRejected;
            if (studentsFooterSpan) studentsFooterSpan.textContent = totalStudents;
        }
    }
    
    // Fonction pour réinitialiser les filtres globaux
    window.resetGlobalFilters = function() {
        document.getElementById('section').value = '';
        document.getElementById('specialisation').value = '';
        document.getElementById('annee').value = '';
        document.getElementById('globalSearch').value = '';
        document.getElementById('globalFilterForm').submit();
    }
    
    // Gestionnaires d'événements pour les unités de recherche
    if (searchURBtn) {
        searchURBtn.addEventListener('click', filterTableUR);
    }
    
    if (resetURBtn) {
        resetURBtn.addEventListener('click', function() {
            searchUR.value = '';
            filterURSection.selectedIndex = 0;
            filterTableUR();
        });
    }
    
    // Gestionnaires d'événements pour les directeurs
    if (searchDirectorBtn) {
        searchDirectorBtn.addEventListener('click', filterTableDirectors);
    }
    
    if (resetDirectorBtn) {
        resetDirectorBtn.addEventListener('click', function() {
            searchDirector.value = '';
            filterDirectorSection.selectedIndex = 0;
            filterDirectorSpec.selectedIndex = 0;
            filterTableDirectors();
        });
    }
    
    // Recherche en temps réel
    if (searchUR) {
        searchUR.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                filterTableUR();
            }
        });
    }
    
    if (searchDirector) {
        searchDirector.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                filterTableDirectors();
            }
        });
    }
    
    // Chargement du graphique d'évolution
    if (sectionSelect) {
        sectionSelect.onchange = function(e) {
            loadEvolutionData(this.value);
        };
    }
    
    // Charger les graphiques
    loadSubjectStatusChart();
    loadTopDirectorsChart();
    loadURDistributionChart();
    
    // Initialiser le graphique d'évolution
    loadEvolutionData('');
    
    // Appliquer les filtres au chargement initial
    filterTableUR();
    filterTableDirectors();
    
    // Initialiser les filtres hiérarchiques au chargement
    initializeHierarchicalFilters();
});

// Fonction pour initialiser les filtres hiérarchiques
function initializeHierarchicalFilters() {
    const anneeSelect = document.getElementById('annee');
    const sectionSelect = document.getElementById('section');
    const specialisationSelect = document.getElementById('specialisation');
    
    // Si une année est déjà sélectionnée, charger les sections
    if (anneeSelect.value) {
        loadSectionsByAnnee(anneeSelect.value, '<?= $filtreSection ?>');
        
        // Synchroniser avec les filtres des onglets
        syncTabFilters(anneeSelect.value, '<?= $filtreSection ?>');
    }
    
    // Si une section est déjà sélectionnée, charger les spécialisations
    if (sectionSelect.value) {
        loadSpecialisationsBySection(sectionSelect.value, '<?= $filtreSpecialisation ?>');
    }
}

// Fonction pour synchroniser les filtres des onglets avec le filtre global
function syncTabFilters(anneeId, selectedSection = '') {
    // Synchroniser le filtre des unités de recherche
    const filterURSection = document.getElementById('filterURSection');
    if (filterURSection && anneeId) {
        loadSectionsForTab(filterURSection, anneeId, selectedSection);
    }
    
    // Synchroniser le filtre des directeurs
    const filterDirectorSection = document.getElementById('filterDirectorSection');
    if (filterDirectorSection && anneeId) {
        loadSectionsForTab(filterDirectorSection, anneeId, selectedSection);
    }
    
    // Synchroniser le sélecteur d'évolution par section
    const sectionSelect = document.getElementById('sectionSelect');
    if (sectionSelect && anneeId) {
        loadSectionsForTab(sectionSelect, anneeId, selectedSection);
    }
}

// Fonction pour charger les sections dans un filtre d'onglet
function loadSectionsForTab(selectElement, anneeId, selectedSection = '') {
    if (!anneeId) {
        selectElement.innerHTML = '<option value="">Toutes les sections</option>';
        return;
    }
    
    fetch(`controller/get_sections_by_annee.php?annee_id=${anneeId}`)
        .then(response => response.json())
        .then(data => {
            selectElement.innerHTML = '<option value="">Toutes les sections</option>';
            
            if (data.success && data.sections && data.sections.length > 0) {
                data.sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.idsection;
                    option.textContent = section.designationSection;
                    if (selectedSection && selectedSection == section.idsection) {
                        option.selected = true;
                    }
                    selectElement.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des sections pour l\'onglet:', error);
        });
}

// Fonction pour charger les sections d'une année académique
function loadSectionsByAnnee(anneeId, selectedSection = '') {
    const sectionSelect = document.getElementById('section');
    const specialisationSelect = document.getElementById('specialisation');
    
    // Réinitialiser la spécialisation
    specialisationSelect.innerHTML = '<option value="">Choisir d\'abord une section</option>';
    specialisationSelect.disabled = true;
    
    if (!anneeId) {
        sectionSelect.innerHTML = '<option value="">Choisir d\'abord une année</option>';
        sectionSelect.disabled = true;
        return;
    }
    
    // Afficher un loader
    sectionSelect.innerHTML = '<option value="">Chargement...</option>';
    sectionSelect.disabled = false;
    
    fetch(`controller/get_sections_by_annee.php?annee_id=${anneeId}`)
        .then(response => response.json())
        .then(data => {
            sectionSelect.innerHTML = '<option value="">Toutes les sections</option>';
            
            if (data.success && data.sections && data.sections.length > 0) {
                data.sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.idsection;
                    option.textContent = section.designationSection;
                    if (selectedSection && selectedSection == section.idsection) {
                        option.selected = true;
                    }
                    sectionSelect.appendChild(option);
                });
                
                // Si une section était sélectionnée, charger ses spécialisations
                if (selectedSection) {
                    loadSpecialisationsBySection(selectedSection, '<?= $filtreSpecialisation ?>');
                }
                
                // Synchroniser avec les filtres des onglets
                syncTabFilters(anneeId, selectedSection);
            } else {
                sectionSelect.innerHTML = '<option value="">Aucune section trouvée</option>';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des sections:', error);
            sectionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

// Fonction pour charger les spécialisations d'une section
function loadSpecialisationsBySection(sectionId, selectedSpecialisation = '') {
    const specialisationSelect = document.getElementById('specialisation');
    
    if (!sectionId) {
        specialisationSelect.innerHTML = '<option value="">Choisir d\'abord une section</option>';
        specialisationSelect.disabled = true;
        return;
    }
    
    // Afficher un loader
    specialisationSelect.innerHTML = '<option value="">Chargement...</option>';
    specialisationSelect.disabled = false;
    
    fetch(`controller/get_specialisations_by_section.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            specialisationSelect.innerHTML = '<option value="">Toutes les spécialisations</option>';
            
            if (data.success && data.specialisations && data.specialisations.length > 0) {
                data.specialisations.forEach(spec => {
                    const option = document.createElement('option');
                    option.value = spec.idSpecialisation;
                    option.textContent = spec.designation + (spec.designation_UR ? ' (' + spec.designation_UR + ')' : '');
                    if (selectedSpecialisation && selectedSpecialisation == spec.idSpecialisation) {
                        option.selected = true;
                    }
                    specialisationSelect.appendChild(option);
                });
            } else {
                specialisationSelect.innerHTML = '<option value="">Aucune spécialisation trouvée</option>';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des spécialisations:', error);
            specialisationSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

// Fonction pour filtrer le tableau des statistiques par décision
function filterTableByDecision(decisionClass) {
    const tbody = document.querySelector('#stats .table-responsive tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!decisionClass) {
            // Afficher toutes les lignes
            row.style.display = '';
        } else {
            // Filtrer par classe de décision
            const decisionBadge = row.querySelector('td:last-child .badge');
            if (decisionBadge && decisionBadge.classList.contains(decisionClass)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Fonction pour charger le graphique d'évolution par section
function loadEvolutionData(sectionId) {
    const chartDiv = document.getElementById('evolutionChart');
    chartDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    if (!sectionId) {
        chartDiv.innerHTML = '<div class="alert alert-info">Veuillez sélectionner une section</div>';
        return;
    }

    fetch(`controller/get_evolution_data_recherche.php?sectionId=${sectionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data); // Debug
            
            if (data.error) {
                chartDiv.innerHTML = `<div class="alert alert-danger">Erreur: ${data.error}</div>`;
                return;
            }
            
            if (!data || data.length === 0) {
                chartDiv.innerHTML = '<div class="alert alert-warning">Aucune donnée disponible pour cette section</div>';
                return;
            }

            const options = {
                series: [{
                    name: 'Total Sujets',
                    type: 'column',
                    data: data.map(d => parseInt(d.total_sujets) || 0)
                }, {
                    name: 'Sujets Validés',
                    type: 'column',
                    data: data.map(d => parseInt(d.sujets_valides) || 0)
                }, {
                    name: 'Taux de validation',
                    type: 'line',
                    data: data.map(d => {
                        const total = parseInt(d.total_sujets) || 0;
                        const valides = parseInt(d.sujets_valides) || 0;
                        return total > 0 ? Math.round((valides / total) * 100) : 0;
                    })
                }],
                chart: {
                    height: 350,
                    type: 'line',
                    toolbar: {
                        show: true
                    }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 3,
                        dataLabels: {
                            position: 'top'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, { seriesIndex }) {
                        if (seriesIndex === 2) return val + '%';
                        return val;
                    },
                    offsetY: -20,
                    style: {
                        fontSize: '12px',
                        colors: ["#304758"]
                    }
                },
                stroke: {
                    width: [1, 1, 3]
                },
                xaxis: {
                    categories: data.map(d => d.annee),
                    title: {
                        text: "Années Académiques"
                    }
                },
                yaxis: [
                    {
                        title: {
                            text: "Nombre de sujets"
                        }
                    },
                    {
                        opposite: true,
                        title: {
                            text: "Taux de validation (%)"
                        },
                        min: 0,
                        max: 100
                    }
                ],
                colors: ['#435ebe', '#198754', '#dc3545'],
                title: {
                    text: 'Évolution des sujets par année',
                    align: 'center'
                },
                legend: {
                    position: 'bottom'
                }
            };

            if (window.evolutionChart && typeof window.evolutionChart.destroy === 'function') {
                window.evolutionChart.destroy();
            }
            
            chartDiv.innerHTML = '';
            window.evolutionChart = new ApexCharts(document.querySelector("#evolutionChart"), options);
            window.evolutionChart.render();
        })
        .catch(error => {
            console.error('Erreur lors du chargement des données d\'évolution:', error);
            chartDiv.innerHTML = `<div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Erreur lors du chargement des données: ${error.message}
                <br><small>Vérifiez la console pour plus de détails</small>
            </div>`;
        });
}

// Fonction pour charger le graphique de la répartition des sujets par statut
function loadSubjectStatusChart() {
    const statusData = [
        { status: 'Validés', count: <?= $validatedSubjectsCount ?> },
        { status: 'En attente', count: <?= $pendingSubjectsCount ?> },
        { status: 'À reformuler', count: <?= $aReformulerSubjectsCount ?> }
    ];

    const labels = statusData.map(item => item.status);
    const values = statusData.map(item => item.count);
    const colors = ['#198754', '#ffc107', '#dc3545'];

    const options = {
        series: values,
        chart: {
            type: 'donut',
            height: 300
        },
        labels: labels,
        colors: colors,
        legend: {
            position: 'bottom'
        },
        dataLabels: {
            enabled: true,
            formatter: function (val, opts) {
                return opts.w.config.series[opts.seriesIndex];
            }
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return value + ' sujets';
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        }
    };

    const chart = new ApexCharts(document.querySelector("#subjectStatusChart"), options);
    chart.render();
}

// Fonction pour charger le graphique des top 5 directeurs
function loadTopDirectorsChart() {
    const directorsData = <?= json_encode(array_slice($etudiantsByProf, 0, 5)) ?>;
    
    if (!directorsData || directorsData.length === 0) {
        document.getElementById('topDirectorsChart').innerHTML = '<div class="alert alert-warning">Aucune donnée disponible</div>';
        return;
    }

    const sortedData = directorsData.sort((a, b) => b.total_etudiants - a.total_etudiants);

    const options = {
        series: [{
            name: 'Étudiants encadrés',
            data: sortedData.map(item => parseInt(item.total_etudiants) || 0)
        }],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '70%',
                distributed: true
            }
        },
        colors: ['#435ebe', '#198754', '#ffc107', '#6c757d', '#dc3545'],
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val;
            }
        },
        xaxis: {
            categories: sortedData.map(item => item.nomDirecteur),
            title: {
                text: 'Nombre d\'étudiants'
            }
        },
        title: {
            text: 'Top 5 des directeurs par nombre d\'étudiants',
            align: 'center'
        }
    };

    const chart = new ApexCharts(document.querySelector("#topDirectorsChart"), options);
    chart.render();
}

// Fonction pour charger le graphique de répartition par unité de recherche
function loadURDistributionChart() {
    const urData = <?= json_encode($enseignantsByUR) ?>;
    
    if (!urData || urData.length === 0) {
        document.getElementById('urDistributionChart').innerHTML = '<div class="alert alert-warning">Aucune donnée disponible</div>';
        return;
    }

    const options = {
        series: [{
            name: 'Enseignants',
            data: urData.map(item => parseInt(item.nombre_enseignants) || 0)
        }, {
            name: 'Spécialisations',
            data: urData.map(item => parseInt(item.nombre_specialisations) || 0)
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: urData.map(item => item.designation_UR),
            title: {
                text: 'Unités de Recherche'
            }
        },
        yaxis: {
            title: {
                text: 'Nombre'
            }
        },
        fill: {
            opacity: 1
        },
        colors: ['#435ebe', '#198754'],
        title: {
            text: 'Répartition par Unité de Recherche',
            align: 'center'
        },
        legend: {
            position: 'bottom'
        }
    };

    const chart = new ApexCharts(document.querySelector("#urDistributionChart"), options);
    chart.render();
}

// Fonction pour afficher les détails d'une unité de recherche
function viewURDetails(urId) {
    const modal = new bootstrap.Modal(document.getElementById('urDetailsModal'));
    const modalContent = document.getElementById('urDetailsContent');
    
    modalContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    modal.show();
    
    fetch(`controller/get_ur_details_recherche.php?id=${urId}`)
        .then(response => response.json())
        .then(data => {
            if (!data) {
                modalContent.innerHTML = '<div class="alert alert-warning">Aucune donnée disponible</div>';
                return;
            }
            
                        let specialisationsList = '';
            if (data.specialisations && data.specialisations.length > 0) {
                specialisationsList = '<div class="mt-3"><h6>Spécialisations:</h6><ul class="list-group">';
                data.specialisations.forEach(spec => {
                    specialisationsList += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${spec.designation}
                        <span class="badge bg-primary rounded-pill">${spec.nombre_enseignants || 0} enseignants</span>
                    </li>`;
                });
                specialisationsList += '</ul></div>';
            } else {
                specialisationsList = '<div class="alert alert-info mt-3">Aucune spécialisation associée</div>';
            }
            
            let enseignantsList = '';
            if (data.enseignants && data.enseignants.length > 0) {
                enseignantsList = '<div class="mt-3"><h6>Enseignants:</h6><ul class="list-group">';
                data.enseignants.forEach(enseignant => {
                    enseignantsList += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${enseignant.noms}
                        <div>
                            <span class="badge bg-secondary me-1">${enseignant.grade || 'N/A'}</span>
                            <span class="badge bg-info">${enseignant.specialisation || 'N/A'}</span>
                        </div>
                    </li>`;
                });
                enseignantsList += '</ul></div>';
            } else {
                enseignantsList = '<div class="alert alert-info mt-3">Aucun enseignant associé</div>';
            }
            
            modalContent.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">${data.designation_UR}</h5>
                        
                        <div class="mt-3">
                            <h6>Description:</h6>
                            <p>${data.description || 'Aucune description disponible'}</p>
                        </div>
                        
                        ${specialisationsList}
                        ${enseignantsList}
                        
                        <div class="mt-3 d-flex justify-content-between">
                            <small class="text-muted">Créée le: ${new Date(data.dateCreation).toLocaleDateString()}</small>
                            <div>
                                <span class="badge bg-info me-2">${data.nombre_specialisations || 0} spécialisations</span>
                                <span class="badge bg-primary">${data.nombre_enseignants || 0} enseignants</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            modalContent.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des données: ${error.message}</div>`;
        });
}

// Fonction pour afficher les détails d'un directeur
function viewDirectorDetails(directorId) {
    const modal = new bootstrap.Modal(document.getElementById('directorDetailsModal'));
    const modalContent = document.getElementById('directorDetailsContent');
    
    modalContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    modal.show();
    
    fetch(`controller/get_director_details_recherche.php?id=${directorId}`)
        .then(response => response.json())
        .then(data => {
            if (!data) {
                modalContent.innerHTML = '<div class="alert alert-warning">Aucune donnée disponible</div>';
                return;
            }
            
            let sujetsList = '';
            if (data.sujets && data.sujets.length > 0) {
                sujetsList = '<div class="table-responsive mt-3"><table class="table table-sm table-striped">';
                sujetsList += '<thead><tr><th>Étudiant</th><th>Sujet</th><th>Statut</th><th>Année</th></tr></thead><tbody>';
                
                data.sujets.forEach(sujet => {
                    const statusClass = sujet.statut_validation === 'Validé' ? 'bg-success' : 
                                       sujet.statut_validation === 'Rejeté' ? 'bg-danger' : 
                                       sujet.statut_validation === 'Modifié' ? 'bg-warning' : 'bg-secondary';
                    
                    sujetsList += `<tr>
                        <td>${sujet.nom_etudiant}</td>
                        <td>${sujet.intitule.length > 40 ? sujet.intitule.substring(0, 40) + '...' : sujet.intitule}</td>
                        <td><span class="badge ${statusClass}">${sujet.statut_validation}</span></td>
                        <td>${sujet.annee_acad || 'N/A'}</td>
                    </tr>`;
                });
                
                sujetsList += '</tbody></table></div>';
            } else {
                sujetsList = '<div class="alert alert-info mt-3">Aucun sujet dirigé</div>';
            }
            
            let specialisationsHtml = '';
            if (data.specialisations && data.specialisations.length > 0) {
                specialisationsHtml = '<div class="mt-3"><h6>Spécialisations:</h6><div class="d-flex flex-wrap gap-2">';
                data.specialisations.forEach(spec => {
                    specialisationsHtml += `<span class="badge bg-secondary">${spec.designation}</span>`;
                });
                specialisationsHtml += '</div></div>';
            }
            
            modalContent.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            ${data.photo ? `<img src="uploads/agents/${data.photo}" alt="Photo" class="rounded-circle me-3" style="width: 64px; height: 64px;">` : ''}
                            <div>
                                <h5 class="card-title mb-0">${data.noms}</h5>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary me-2">${data.grade || 'N/A'}</span>
                                    <small class="text-muted">${data.matricule || 'Matricule non défini'}</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> ${data.email || 'Non défini'}</p>
                                <p><strong>Téléphone:</strong> ${data.telephone || 'Non défini'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Service:</strong> ${data.service || 'Non défini'}</p>
                                <p><strong>Niveau d'étude:</strong> ${data.niveauEtude || 'Non défini'}</p>
                            </div>
                        </div>
                        
                        ${specialisationsHtml}
                        
                        <div class="mt-3">
                            <h6>Résumé de l'encadrement:</h6>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-success">${data.sujets_valides || 0}</h3>
                                            <p class="mb-0">Sujets validés</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-warning">${data.sujets_en_attente || 0}</h3>
                                            <p class="mb-0">En attente</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-danger">${data.sujets_rejetes || 0}</h3>
                                            <p class="mb-0">Rejetés</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h3 class="text-primary">${data.total_etudiants || 0}</h3>
                                            <p class="mb-0">Total étudiants</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Sujets dirigés:</h6>
                            ${sujetsList}
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            modalContent.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des données: ${error.message}</div>`;
        });
}

// Fonction pour exporter les données des unités de recherche en Excel
function exportURToExcel() {
    const tableUR = document.getElementById('tableUR');
    const rows = Array.from(tableUR.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
    
    if (rows.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucune donnée',
            text: 'Aucune donnée à exporter'
        });
        return;
    }
    
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Unités de Recherche');
    
    // Définir les en-têtes
    worksheet.columns = [
        { header: 'Unité de Recherche', key: 'unite', width: 30 },
        { header: 'Section', key: 'section', width: 25 },
        { header: 'Description', key: 'description', width: 50 },
        { header: 'Spécialisations', key: 'specialisations', width: 40 },
        { header: 'Nombre d\'Enseignants', key: 'nombre', width: 20 }
    ];
    
    // Ajouter les données
    rows.forEach(row => {
        worksheet.addRow({
            unite: row.cells[0].textContent.trim(),
            section: row.cells[1].textContent.trim(),
            description: row.cells[2].textContent.trim(),
            specialisations: row.cells[3].textContent.replace(/\d+/g, '').trim(),
            nombre: row.cells[4].textContent.trim()
        });
    });
    
    // Style du titre
    worksheet.getRow(1).font = { bold: true };
    worksheet.getRow(1).alignment = { vertical: 'middle', horizontal: 'center' };
    
    // Générer le fichier
    workbook.xlsx.writeBuffer().then(buffer => {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        saveAs(blob, 'unites_recherche_' + new Date().toISOString().split('T')[0] + '.xlsx');
    });
}

// Fonction pour exporter les données des directeurs en Excel
function exportDirectorsToExcel() {
    const tableDirectors = document.getElementById('tableDirectors');
    const rows = Array.from(tableDirectors.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
    
    if (rows.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucune donnée',
            text: 'Aucune donnée à exporter'
        });
        return;
    }
    
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Directeurs et Sujets');
    
    // Définir les en-têtes
    worksheet.columns = [
        { header: 'Directeur', key: 'directeur', width: 30 },
        { header: 'Grade', key: 'grade', width: 20 },
        { header: 'Spécialisation', key: 'specialisation', width: 30 },
        { header: 'Unité de Recherche', key: 'unite', width: 30 },
        { header: 'Section', key: 'section', width: 25 },
        { header: 'Sujets Validés', key: 'valides', width: 15 },
        { header: 'Sujets En Attente', key: 'attente', width: 15 },
        { header: 'Sujets Rejetés', key: 'rejetes', width: 15 },
        { header: 'Total Étudiants', key: 'total', width: 15 }
    ];
    
    // Ajouter les données
    rows.forEach(row => {
        worksheet.addRow({
            directeur: row.cells[0].textContent.split('\n')[0].trim(),
            grade: row.cells[1].textContent.trim(),
            specialisation: row.cells[2].textContent.trim(),
            unite: row.cells[3].textContent.trim(),
            section: row.cells[4].textContent.trim(),
            valides: row.cells[5].textContent.trim(),
            attente: row.cells[6].textContent.trim(),
            rejetes: row.cells[7].textContent.trim(),
            total: row.cells[8].textContent.trim()
        });
    });
    
    // Ajouter une ligne pour les totaux
    const tfoot = tableDirectors.querySelector('tfoot');
    if (tfoot) {
        worksheet.addRow({});
        worksheet.addRow({
            directeur: 'TOTAL',
            grade: '',
            specialisation: '',
            unite: '',
            section: '',
            valides: tfoot.rows[0].cells[5].textContent.trim(),
            attente: tfoot.rows[0].cells[6].textContent.trim(),
            rejetes: tfoot.rows[0].cells[7].textContent.trim(),
            total: tfoot.rows[0].cells[8].textContent.trim()
        });
    }
    
        // Style du titre
    worksheet.getRow(1).font = { bold: true };
    worksheet.getRow(1).alignment = { vertical: 'middle', horizontal: 'center' };
    
    // Générer le fichier
    workbook.xlsx.writeBuffer().then(buffer => {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        saveAs(blob, 'directeurs_sujets_' + new Date().toISOString().split('T')[0] + '.xlsx');
    });
}
</script>

<?php include "./views/include/footer_file.php"; ?>
