<?php
include "./views/include/header.php";

// Initialiser la connexion
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours
$checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND table_schema = 'public' AND column_name = 'est_active'";
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

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterSection = isset($_GET['section']) ? $_GET['section'] : '';
$filterPromotion = isset($_GET['promotion']) ? $_GET['promotion'] : '';
$filterCycle = isset($_GET['cycle']) ? $_GET['cycle'] : '';
$filterEtat = isset($_GET['etat']) ? $_GET['etat'] : '';
$filterAnnee = isset($_GET['annee']) ? $_GET['annee'] : $currentYear['idannee_acad'];
$filterSpecialisation = isset($_GET['specialisation']) ? $_GET['specialisation'] : '';

$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole'];

// Vérifier les responsabilités de l'utilisateur
$userSections = [];
$isResponsableSection = false;
$isEnseignant = false;
$idEnseignant = 0;

// Récupérer les sections dont l'utilisateur est responsable
if ($userRole != 1) { // Si pas admin
    $query = "SELECT section_idsection 
              FROM responsable_section 
              WHERE \"idUser\" = :userId 
              AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $isResponsableSection = !empty($userSections);
    
    // Vérifier si l'utilisateur est un enseignant
    $queryEnseignant = "SELECT a.\"idAgent\" FROM agent a 
                        INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
                        WHERE u.\"idUser\" = :userId AND a.type_agent = 'Enseignant'";
    $stmtEnseignant = $pdo->prepare($queryEnseignant);
    $stmtEnseignant->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmtEnseignant->execute();
    $enseignant = $stmtEnseignant->fetch(PDO::FETCH_ASSOC);
    
    if ($enseignant) {
        $isEnseignant = true;
        $idEnseignant = $enseignant['idAgent'];
    }
}

// Récupérer les données pour les filtres
// Années académiques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Sections (selon les droits)
$querySections = "SELECT DISTINCT s.idsection, s.\"designationSection\" 
                  FROM section s";
if ($isResponsableSection && !empty($userSections)) {
    $placeholders = str_repeat('?,', count($userSections) - 1) . '?';
    $querySections .= " WHERE s.idsection IN ($placeholders)";
}
$querySections .= ' ORDER BY s."designationSection"';

$stmtSections = $pdo->prepare($querySections);
if ($isResponsableSection && !empty($userSections)) {
    $stmtSections->execute($userSections);
} else {
    $stmtSections->execute();
}
$sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

// Spécialisations
$querySpec = "SELECT * FROM specialisation ORDER BY designation";
$stmtSpec = $pdo->prepare($querySpec);
$stmtSpec->execute();
$specialisations = $stmtSpec->fetchAll(PDO::FETCH_ASSOC);

// Promotions (selon la section sélectionnée)
$promotions = [];
if ($filterSection) {
    $queryPromotions = "SELECT DISTINCT p.idpromotion, p.\"designationPromotion\", p.cycle
                        FROM promotion p
                        JOIN orientation o ON p.orientation_idorientation = o.idorientation
                        WHERE o.section_idsection = :section
                        AND p.annee_acad_idannee_acad = :annee
                        ORDER BY p.cycle, p.\"designationPromotion\"";
    $stmtPromotions = $pdo->prepare($queryPromotions);
    $stmtPromotions->bindParam(':section', $filterSection);
    $stmtPromotions->bindParam(':annee', $filterAnnee);
    $stmtPromotions->execute();
    $promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);
}

// Construction de la requête principale pour les sujets
$params = [':anneeId' => $filterAnnee];

$querySujets = "SELECT DISTINCT
                    s.idsujets,
                    s.intitule,
                    s.statut_validation as \"etatSujet\",
                    s.cycle,
                    s.\"idSpecialisation\",
                    s.annee_acad_idannee_acad,
                    s.\"idDirecteur\",
                    s.\"idEncadreur\",
                    s.etudiant_idetudiant,
                    sp.designation as specialisation,
                    e.noms as etudiant_nom,
                    e.matricule as etudiant_matricule,
                    p.\"designationPromotion\",
                    o.\"designationOrientation\",
                    sec.\"designationSection\",
                    aa.designation as annee_designation,
                    dir.noms as directeur_nom,
                    enc.noms as encadreur_nom,
                    CASE 
                        WHEN s.statut_validation = 'Validé' THEN 'success'
                        WHEN s.statut_validation = 'En attente' THEN 'warning'
                        WHEN s.statut_validation = 'Rejeté' THEN 'danger'
                        WHEN s.statut_validation = 'Modifié' THEN 'info'
                        ELSE 'secondary'
                    END as badge_class
                FROM sujets s
                LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
                LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                LEFT JOIN section sec ON o.section_idsection = sec.idsection
                LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                LEFT JOIN agent dir ON s.\"idDirecteur\" = dir.\"idAgent\"
                LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
                WHERE s.annee_acad_idannee_acad = :anneeId";

// Appliquer les filtres selon les droits
if ($userRole != 1) { // Si pas admin
    if ($isEnseignant) {
        // Enseignant : voir ses sujets + ceux de ses sections s'il est responsable
        $conditions = ['(s."idDirecteur" = :idEnseignant OR s."idEncadreur" = :idEnseignant)'];
        $params[':idEnseignant'] = $idEnseignant;
        
        if ($isResponsableSection && !empty($userSections)) {
            $sectionPlaceholders = [];
            foreach ($userSections as $i => $section) {
                $paramName = ":userSection{$i}";
                $sectionPlaceholders[] = $paramName;
                $params[$paramName] = $section;
            }
            $conditions[] = "sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
        }
        
        $querySujets .= " AND (" . implode(' OR ', $conditions) . ")";
    } elseif ($isResponsableSection && !empty($userSections)) {
        // Responsable de section : voir les sujets de ses sections
        $sectionPlaceholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":userSection{$i}";
            $sectionPlaceholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $querySujets .= " AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
    } else {
        // Utilisateur sans droits spéciaux : aucun sujet
        $querySujets .= " AND 1 = 0";
    }
}

// Appliquer les filtres de recherche
if (!empty($search)) {
    $querySujets .= " AND (s.intitule LIKE :search 
                          OR e.noms LIKE :search 
                          OR e.matricule LIKE :search
                          OR sp.designation LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filterSection)) {
    $querySujets .= " AND sec.idsection = :filterSection";
    $params[':filterSection'] = $filterSection;
}

if (!empty($filterPromotion)) {
    $querySujets .= " AND p.idpromotion = :filterPromotion";
    $params[':filterPromotion'] = $filterPromotion;
}

if (!empty($filterCycle)) {
    $querySujets .= " AND s.cycle = :filterCycle";
    $params[':filterCycle'] = $filterCycle;
}

if (!empty($filterEtat)) {
    $querySujets .= " AND s.statut_validation = :filterEtat";
    $params[':filterEtat'] = $filterEtat;
}

if (!empty($filterSpecialisation)) {
    $querySujets .= ' AND s."idSpecialisation" = :filterSpecialisation';
    $params[':filterSpecialisation'] = $filterSpecialisation;
}

$querySujets .= " ORDER BY s.idsujets DESC";

$stmtSujets = $pdo->prepare($querySujets);
foreach ($params as $key => $value) {
    $stmtSujets->bindValue($key, $value);
}
$stmtSujets->execute();
$sujets = $stmtSujets->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalSujets = count($sujets);
$sujetsValides = count(array_filter($sujets, function($s) { return $s['etatSujet'] == 'Validé'; }));
$sujetsEnAttente = count(array_filter($sujets, function($s) { return $s['etatSujet'] == 'En attente'; }));
$sujetsRejetes = count(array_filter($sujets, function($s) { return $s['etatSujet'] == 'Rejeté'; }));

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUJETS DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Sujets</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Informations générales -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Année académique : <?= htmlspecialchars($currentYear['designation']) ?></h5>
                        <?php if ($isResponsableSection): ?>
                            <p class="text-muted">Vous êtes responsable de <?= count($userSections) ?> section(s)</p>
                        <?php elseif ($isEnseignant): ?>
                            <p class="text-muted">Vue de vos sujets en tant qu'enseignant</p>
                        <?php elseif ($userRole == 1): ?>
                            <p class="text-muted">Vue globale de tous les sujets</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total sujets</h5>
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

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Validés</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $sujetsValides ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalSujets > 0 ? round(($sujetsValides / $totalSujets) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">En attente</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $sujetsEnAttente ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalSujets > 0 ? round(($sujetsEnAttente / $totalSujets) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Rejetés</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $sujetsRejetes ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalSujets > 0 ? round(($sujetsRejetes / $totalSujets) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filtres et recherche</h5>
                
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="view" value="recherche/sujets">
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                               class="form-control" placeholder="Intitulé, étudiant, matricule...">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="annee" class="form-label">Année académique</label>
                        <select name="annee" id="annee" class="form-select">
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['idannee_acad'] ?>" 
                                        <?= $filterAnnee == $year['idannee_acad'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="section" class="form-label">Section</label>
                        <select name="section" id="section" class="form-select" onchange="loadPromotions()">
                            <option value="">Toutes les sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['idsection'] ?>" 
                                        <?= $filterSection == $section['idsection'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['designationSection']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="promotion" class="form-label">Promotion</label>
                        <select name="promotion" id="promotion" class="form-select">
                            <option value="">Toutes les promotions</option>
                            <?php foreach ($promotions as $promotion): ?>
                                <option value="<?= $promotion['idpromotion'] ?>" 
                                        <?= $filterPromotion == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="cycle" class="form-label">Cycle</label>
                        <select name="cycle" id="cycle" class="form-select">
                            <option value="">Tous les cycles</option>
                            <option value="Premier" <?= $filterCycle == 'Premier' ? 'selected' : '' ?>>Premier</option>
                            <option value="Deuxieme" <?= $filterCycle == 'Deuxieme' ? 'selected' : '' ?>>Deuxième</option>
                            <option value="Troisieme" <?= $filterCycle == 'Troisieme' ? 'selected' : '' ?>>Troisième</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="specialisation" class="form-label">Spécialisation</label>
                        <select name="specialisation" id="specialisation" class="form-select">
                            <option value="">Toutes les spécialisations</option>
                            <?php foreach ($specialisations as $spec): ?>
                                <option value="<?= $spec['idSpecialisation'] ?>" 
                                        <?= $filterSpecialisation == $spec['idSpecialisation'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="etat" class="form-label">État</label>
                        <select name="etat" id="etat" class="form-select">
                            <option value="">Tous les états</option>
                            <option value="En attente" <?= $filterEtat == 'En attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="Validé" <?= $filterEtat == 'Validé' ? 'selected' : '' ?>>Validé</option>
                            <option value="Rejeté" <?= $filterEtat == 'Rejeté' ? 'selected' : '' ?>>Rejeté</option>
                            <option value="Modifié" <?= $filterEtat == 'Modifié' ? 'selected' : '' ?>>Modifié</option>
                        </select>
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <a href="index?view=recherche/sujets" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                            </a>
                            <?php if ($userRole == 1 || $isEnseignant): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createSujetModal">
                                    <i class="bi bi-plus-circle"></i> Nouveau sujet
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="exportToExcel()">
                                <i class="bi bi-file-excel"></i> Exporter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des sujets -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Liste des sujets de recherche
                    <span class="badge bg-primary"><?= $totalSujets ?> résultat(s)</span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="sujetsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Intitulé</th>
                                <th>Étudiant</th>
                                <th>Promotion</th>
                                <th>Section</th>
                                <th>Cycle</th>
                                <th>Spécialisation</th>
                                <th>Directeur</th>
                                <th>Encadreur</th>
                                <th>État</th>
                                <th>Date création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($sujets as $sujet): 
                            ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($sujet['intitule']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($sujet['etudiant_nom']): ?>
                                        <strong><?= htmlspecialchars($sujet['etudiant_nom']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($sujet['etudiant_matricule']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sujet['designationPromotion']): ?>
                                        <strong><?= htmlspecialchars($sujet['designationPromotion']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($sujet['designationOrientation']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sujet['designationSection'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= getCycleBadgeColor($sujet['cycle']) ?>">
                                        <?= htmlspecialchars($sujet['cycle']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sujet['specialisation'] ?? '-') ?></td>
                                <td>
                                    <?php if ($sujet['directeur_nom']): ?>
                                        <?= htmlspecialchars($sujet['directeur_nom']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sujet['encadreur_nom']): ?>
                                        <?= htmlspecialchars($sujet['encadreur_nom']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $sujet['badge_class'] ?>">
                                        <?= htmlspecialchars($sujet['etatSujet']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted">-</span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="index?view=recherche/sujet_details&id=<?= $sujet['idsujets'] ?>" 
                                           class="btn btn-sm btn-outline-info" title="Voir détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($userRole == 1 || $idEnseignant == $sujet['idDirecteur'] || $idEnseignant == $sujet['idEncadreur']): ?>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="openEditSujetModal(<?= $sujet['idsujets'] ?>, '<?= htmlspecialchars(addslashes($sujet['intitule'])) ?>', '<?= $sujet['cycle'] ?>', <?= $sujet['idSpecialisation'] ?>, <?= $sujet['annee_acad_idannee_acad'] ?>)"
                                                    title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDeleteSujet(<?= $sujet['idsujets'] ?>)"
                                                    title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un sujet -->
<?php if ($userRole == 1 || $isEnseignant): ?>
<div class="modal fade" id="createSujetModal" tabindex="-1" aria-labelledby="createSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sujetForm" method="POST" action="controller/create_sujet2.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="intitule" class="form-label">Intitulé du Sujet</label>
                            <textarea name="intitule" id="intitule" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">Veuillez entrer l'intitulé du sujet.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="cycle" class="form-label">Cycle</label>
                            <select name="cycle" id="cycle" class="form-control" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="idSpecialisation" class="form-label">Spécialisation</label>
                            <select name="idSpecialisation" id="idSpecialisation" class="form-control" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>"><?= $specialisation['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="annee_acad" class="form-label">Année Académique</label>
                            <select name="annee_acad" id="annee_acad" class="form-control" required>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>" 
                                            <?= $year['idannee_acad'] == $currentYear['idannee_acad'] ? 'selected' : '' ?>>
                                        <?= $year['designation'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSujetBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un sujet -->
<div class="modal fade" id="editSujetModal" tabindex="-1" aria-labelledby="editSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSujetForm" method="POST" action="controller/edit_sujet2.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editIdSujet" id="editIdSujet">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editIntitule" class="form-label">Intitulé du Sujet</label>
                            <textarea name="editIntitule" id="editIntitule" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">Veuillez entrer l'intitulé du sujet.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editCycle" class="form-label">Cycle</label>
                            <select name="editCycle" id="editCycle" class="form-control" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editIdSpecialisation" class="form-label">Spécialisation</label>
                            <select name="editIdSpecialisation" id="editIdSpecialisation" class="form-control" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>"><?= $specialisation['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editAnneeAcad" class="form-label">Année Académique</label>
                            <select name="editAnneeAcad" id="editAnneeAcad" class="form-control" required>
                                <option value="">Sélectionner une année académique</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSujetBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Fonctions utilitaires
function getCycleBadgeColor($cycle) {
    switch ($cycle) {
        case 'Premier': return 'primary';
        case 'Deuxieme': return 'success';
        case 'Troisieme': return 'warning';
        default: return 'secondary';
    }
}
?>

<script>
// Charger les promotions selon la section sélectionnée
function loadPromotions() {
    const sectionId = document.getElementById('section').value;
    const anneeId = document.getElementById('annee').value;
    const promotionSelect = document.getElementById('promotion');
    
    // Réinitialiser les promotions
    promotionSelect.innerHTML = '<option value="">Toutes les promotions</option>';
    
    if (sectionId && anneeId) {
        fetch(`controller/ajax_get_promotions.php?section=${sectionId}&annee=${anneeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.promotions.forEach(promotion => {
                        const option = document.createElement('option');
                        option.value = promotion.idpromotion;
                        option.textContent = promotion.designationPromotion;
                        if (promotion.idpromotion == '<?= $filterPromotion ?>') {
                            option.selected = true;
                        }
                        promotionSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Erreur:', error));
    }
}

// Exporter vers Excel
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'controller/export_sujets.php?' + params.toString();
}

// Validation des formulaires Bootstrap
(function() {
    'use strict';
    
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Fonction pour ouvrir le modal d'édition et pré-remplir les champs
function openEditSujetModal(id, intitule, cycle, idSpecialisation, anneeAcad) {
    document.getElementById('editIdSujet').value = id;
    document.getElementById('editIntitule').value = intitule;
    document.getElementById('editCycle').value = cycle;
    document.getElementById('editIdSpecialisation').value = idSpecialisation;
    document.getElementById('editAnneeAcad').value = anneeAcad;
    
    var editModal = new bootstrap.Modal(document.getElementById('editSujetModal'));
    editModal.show();
}

// Fonction pour confirmer la suppression d'un sujet
function confirmDeleteSujet(id) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action est irréversible!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/delete_sujet2.php?id=' + id;
        }
    });
}

// Charger les promotions au chargement de la page si une section est sélectionnée
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('section').value) {
        loadPromotions();
    }
});
</script>

<style>
.card-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.info-card.sales-card .card-icon {
    color: #4154f1;
    background: #f6f6fe;
}

.info-card.revenue-card .card-icon {
    color: #2eca6a;
    background: #e0f8e9;
}

.info-card.customers-card .card-icon {
    color: #ff771d;
    background: #ffecdf;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.btn-group .btn {
    margin-right: 2px;
}

.badge {
    font-size: 0.75em;
}
</style>

<?php include "./views/include/footer.php"; ?>