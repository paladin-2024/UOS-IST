<?php
include "./views/include/header.php";
$universite = new Universite();

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
$checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND column_name = 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    // La colonne existe, utiliser la requête avec est_active
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    // La colonne n'existe pas, prendre la dernière année
    $queryAnnee = 'SELECT * FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYearActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYearActive) {
    // Si aucune année trouvée, prendre la dernière année
    $queryAnnee = 'SELECT * FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYearActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les sections dont l'utilisateur est responsable
$query = 'SELECT section_idsection
          FROM responsable_section
          WHERE "idUser" = :userId
          AND annee_acad_idannee_acad = :anneeId';

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYearActive['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isResponsableSection = !empty($userSections);

// Vérifier si l'utilisateur a le droit d'accéder à cette page
$hasFullAccess = $_SESSION['idRole'] == 1; // Supposons que le rôle 1 est administrateur

if (!$isResponsableSection && !$hasFullAccess) {
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

$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;

// Récupérer toutes les années académiques
$allAcademicYears = $universite->getAllAcademicYears();

// Récupérer l'année académique sélectionnée ou utiliser l'année en cours (active) par défaut
$selectedYear = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

// Si aucune année n'est sélectionnée, utiliser l'année active en cours
if ($selectedYear == 0 && $currentYearActive) {
    $selectedYear = $currentYearActive['idannee_acad'];
} elseif ($selectedYear == 0 && !empty($allAcademicYears)) {
    // Sinon, utiliser la plus récente
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

// Si aucune année académique n'est trouvée, afficher un message d'erreur
if (!$currentYear && !empty($allAcademicYears)) {
    $currentYear = $allAcademicYears[0];
}

// Récupérer toutes les sections accessibles à l'utilisateur pour l'année sélectionnée
$sections = [];
if ($hasFullAccess) { // Si administrateur
    $sections = $universite->getSections('', $selectedYear);
} else {
    // Pour les responsables de section, récupérer uniquement leurs sections
    foreach ($userSections as $sectionId) {
        $sectionData = $universite->getSectionById($sectionId);
        if ($sectionData) {
            $sections[] = $sectionData;
        }
    }
}

// Récupérer toutes les UEs pour l'année sélectionnée
$ues = [];
if (!empty($sections) && $currentYear) {
    $sectionIds = array_column($sections, 'idsection');
    
    // Si c'est un administrateur et aucune section n'est sélectionnée, récupérer toutes les UEs
    if ($hasFullAccess && $selectedSection == 0) {
        $ues = $universite->getAllUEs($currentYear['idannee_acad'], $search);
    } 
    // Si une section spécifique est sélectionnée
    elseif ($selectedSection > 0 && in_array($selectedSection, $sectionIds)) {
        $ues = $universite->getAllUEsBySection($selectedSection, $currentYear['idannee_acad'], $search);
    } 
    // Pour les responsables de section, récupérer les UEs de toutes leurs sections
    elseif ($isResponsableSection && !$hasFullAccess && $selectedSection == 0) {
        // Récupérer les UEs de toutes les sections dont l'utilisateur est responsable
        $ues = [];
        foreach ($sectionIds as $sectionId) {
            $sectionUEs = $universite->getAllUEsBySection($sectionId, $currentYear['idannee_acad'], $search);
            $ues = array_merge($ues, $sectionUEs);
        }
        // Éliminer les doublons basés sur l'idUE
        $uniqueUEs = [];
        $seenIds = [];
        foreach ($ues as $ue) {
            if (!in_array($ue['idUE'], $seenIds)) {
                $uniqueUEs[] = $ue;
                $seenIds[] = $ue['idUE'];
            }
        }
        $ues = $uniqueUEs;
    }
    // Sinon, utiliser la première section accessible
    else {
        $ues = $universite->getAllUEsBySection($sectionIds[0], $currentYear['idannee_acad'], $search);
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES UNITÉS D'ENSEIGNEMENT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Unités d'Enseignement</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection && !$hasFullAccess): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Accès restreint :</strong> Vous visualisez uniquement les unités d'enseignement des sections dont vous êtes responsable.
            <?php 
            // Récupérer les noms des sections
            $sectionNames = [];
            if (!empty($userSections)) {
                $placeholders = implode(',', array_fill(0, count($userSections), '?'));
                $querySections = 'SELECT "designationSection" FROM section WHERE idsection IN (' . $placeholders . ')';
                $stmtSections = $pdo->prepare($querySections);
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
            <strong>Accès administrateur :</strong> Vous avez accès à toutes les sections et promotions.
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table des UEs -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des Unités d'Enseignement
                                    <?php if ($currentYear): ?>
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createUEModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter une UE
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importUEModal" class="btnPage">
                                            <i class="bi bi-upload"></i> Importer des UEs
                                        </a>
                                    </span>
                                    <?php endif; ?>
                                </h5>

                                <?php
                                // Récupérer la promotion sélectionnée
                                $selectedPromotion = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
                                
                                // Récupérer les promotions disponibles pour les filtres
                                $availablePromotions = [];
                                if ($currentYear && !empty($sections)) {
                                    foreach ($sections as $sect) {
                                        $promos = $universite->getPromotionsBySection($sect['idsection'], $currentYear['idannee_acad']);
                                        foreach ($promos as $promo) {
                                            $promo['sectionName'] = $sect['designationSection'];
                                            $availablePromotions[] = $promo;
                                        }
                                    }
                                }
                                ?>
                                <form method="GET" action="" class="mb-3" id="filterForm">
                                    <input type="hidden" name="view" value="enseignement/unites_enseignement">
                                    <div class="row g-2 align-items-end">
                                        <!-- Année académique -->
                                        <div class="col-md-2">
                                            <label for="annee_acad" class="form-label small mb-1">Année académique</label>
                                            <select name="annee_acad" id="annee_acad" class="form-select form-select-sm" onchange="this.form.submit()">
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
                                        <div class="col-md-2">
                                            <label for="section" class="form-label small mb-1">Section</label>
                                            <select name="section" id="section" class="form-select form-select-sm" onchange="updatePromotionFilter(); this.form.submit()">
                                                <option value="0"><?= $hasFullAccess ? 'Toutes sections' : 'Mes sections' ?></option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($section['designationSection']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Promotion -->
                                        <div class="col-md-3">
                                            <label for="promotion" class="form-label small mb-1">Promotion</label>
                                            <select name="promotion" id="promotion" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="0">Toutes les promotions</option>
                                                <?php foreach ($availablePromotions as $promo): ?>
                                                    <?php 
                                                    // Filtrer par section si une section est sélectionnée
                                                    if ($selectedSection > 0) {
                                                        // Récupérer l'idSection de la promotion
                                                        $queryPromoSection = "SELECT s.idsection FROM section s 
                                                                              INNER JOIN orientation o ON o.section_idsection = s.idsection 
                                                                              INNER JOIN promotion p ON p.orientation_idorientation = o.idorientation 
                                                                              WHERE p.idpromotion = :promoid";
                                                        $stmtPromoSection = $pdo->prepare($queryPromoSection);
                                                        $stmtPromoSection->bindParam(':promoid', $promo['idpromotion']);
                                                        $stmtPromoSection->execute();
                                                        $promoSection = $stmtPromoSection->fetch(PDO::FETCH_ASSOC);
                                                        if (!$promoSection || $promoSection['idsection'] != $selectedSection) {
                                                            continue;
                                                        }
                                                    }
                                                    ?>
                                                    <option value="<?= $promo['idpromotion'] ?>" <?= $selectedPromotion == $promo['idpromotion'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($promo['designationPromotion']) ?> (<?= htmlspecialchars($promo['cycle'] ?? '') ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Recherche -->
                                        <div class="col-md-3">
                                            <label for="search" class="form-label small mb-1">Recherche</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Code ou désignation...">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="col-md-2">
                                            <label class="form-label small mb-1">&nbsp;</label>
                                            <div class="d-flex gap-1">
                                                <a href="?view=enseignement/unites_enseignement&annee_acad=<?= $selectedYear ?>" class="btn btn-outline-secondary btn-sm" title="Réinitialiser les filtres">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </a>
                                                <?php if ($selectedPromotion > 0): ?>
                                                <button type="button" class="btn btn-success btn-sm" onclick="exportMaquette(<?= $selectedPromotion ?>)" title="Exporter la maquette">
                                                    <i class="bi bi-file-earmark-excel"></i> Maquette
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($selectedPromotion > 0): ?>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="alert alert-info py-2 mb-0 d-flex align-items-center justify-content-between">
                                                <span>
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <strong>Promotion sélectionnée :</strong> 
                                                    <?php 
                                                    foreach ($availablePromotions as $promo) {
                                                        if ($promo['idpromotion'] == $selectedPromotion) {
                                                            echo htmlspecialchars($promo['designationPromotion']);
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    - Cliquez sur "Maquette" pour exporter l'offre de formation en Excel.
                                                </span>
                                                <button type="button" class="btn btn-success btn-sm" onclick="exportMaquette(<?= $selectedPromotion ?>)">
                                                    <i class="bi bi-file-earmark-excel me-1"></i> Exporter Maquette Excel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>

                                <?php if (!$currentYear): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Veuillez sélectionner une année académique pour afficher les unités d'enseignement.
                                </div>
                                <?php else: ?>
                                <!-- Tableau des UEs -->
                                <table class="table table-striped table-bordered datatable">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Code</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Semestres/Promotions</th>
                                            <th scope="col">Section</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Récupérer les UE regroupées par code et désignation
                                        if ($isResponsableSection && !$hasFullAccess) {
                                            // Pour les responsables de section, filtrer par leurs sections
                                            $uesGroupees = [];
                                            if ($selectedSection > 0 && in_array($selectedSection, $userSections)) {
                                                // Si une section spécifique est sélectionnée
                                                $uesGroupees = $universite->getUEsGroupees($currentYear['idannee_acad'], $search, $selectedSection, $selectedPromotion);
                                            } else {
                                                // Récupérer les UEs de toutes les sections du responsable
                                                $allUEsGroupees = [];
                                                foreach ($userSections as $sectionId) {
                                                    $sectionUEs = $universite->getUEsGroupees($currentYear['idannee_acad'], $search, $sectionId, $selectedPromotion);
                                                    foreach ($sectionUEs as $ue) {
                                                        $key = $ue['codeUE'] . '_' . $ue['designationUE'];
                                                        if (!isset($allUEsGroupees[$key])) {
                                                            $allUEsGroupees[$key] = $ue;
                                                        }
                                                    }
                                                }
                                                $uesGroupees = array_values($allUEsGroupees);
                                            }
                                        } else {
                                            // Pour les administrateurs
                                            $uesGroupees = $universite->getUEsGroupees($currentYear['idannee_acad'], $search, $selectedSection, $selectedPromotion);
                                        }
                                        
                                        $i = 1;

                                        foreach ($uesGroupees as $ue) {
                                            // Récupérer toutes les instances de cette UE
                                            if ($isResponsableSection && !$hasFullAccess) {
                                                // Pour les responsables, filtrer les instances par leurs sections
                                                $allInstances = $universite->getUEsByCodeDesignation($ue['codeUE'], $ue['designationUE'], $currentYear['idannee_acad'], $search, 0, $selectedPromotion);
                                                $instancesUE = [];
                                                foreach ($allInstances as $instance) {
                                                    // Vérifier si l'instance appartient à une section du responsable
                                                    // Requête directe pour récupérer la section depuis la promotion
                                                    $querySection = "SELECT s.* 
                                                                    FROM section s
                                                                    INNER JOIN orientation o ON o.section_idsection = s.idsection
                                                                    INNER JOIN promotion p ON p.orientation_idorientation = o.idorientation
                                                                    WHERE p.idpromotion = :promotionId";
                                                    $stmtSection = $pdo->prepare($querySection);
                                                    $stmtSection->bindParam(':promotionId', $instance['promotion_idpromotion']);
                                                    $stmtSection->execute();
                                                    $instanceSection = $stmtSection->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    if ($instanceSection && in_array($instanceSection['idsection'], $userSections)) {
                                                        $instancesUE[] = $instance;
                                                    }
                                                }
                                            } else {
                                                $instancesUE = $universite->getUEsByCodeDesignation($ue['codeUE'], $ue['designationUE'], $currentYear['idannee_acad'], $search, $selectedSection, $selectedPromotion);
                                            }
                                            
                                            // Construire la liste des semestres/promotions
                                            $semestresHtml = '';
                                            $idsUE = [];
                                            
                                            foreach ($instancesUE as $instance) {
                                                $semestresHtml .= "<span class='badge bg-info me-1 mb-1'>S{$instance['numeroSemestre']} - {$instance['designationPromotion']}</span> ";
                                                $idsUE[] = $instance['idUE'];
                                            }
                                            
                                            // Ignorer les UE sans instances (après filtrage par promotion)
                                            if (empty($idsUE)) {
                                                continue;
                                            }
                                            
                                            // Convertir le tableau d'IDs en chaîne JSON pour les actions
                                            $idsUEJson = json_encode($idsUE);
                                            
                                            echo "
                                            <tr>
                                            <td>{$i}</td>
                                            <td><strong>{$ue['codeUE']}</strong></td>
                                            <td>{$ue['designationUE']}</td>
                                            <td>{$semestresHtml}</td>
                                            <td>";
                                            echo str_replace(',', '', htmlspecialchars($ue['designationSection']));
                                            echo "</td>
                                            <td>
                                            <button class='btn btn-sm btn-warning' onclick='editUEGroupe(\"{$ue['codeUE']}\", \"{$ue['designationUE']}\", {$idsUEJson})'>
                                                <i class='bi bi-pencil-square'></i> Modifier
                                            </button>
                                            <button class='btn btn-sm btn-danger' onclick='deleteUEGroupe(\"{$ue['codeUE']}\", \"{$ue['designationUE']}\", {$idsUEJson})'>
                                                <i class='bi bi-trash'></i> Supprimer
                                            </button>
                                            <button class='btn btn-sm btn-info' onclick='viewUEDetails(\"{$ue['codeUE']}\", \"{$ue['designationUE']}\", {$idsUEJson})'>
                                                <i class='bi bi-eye'></i> Détails
                                            </button>
                                            <button class='btn btn-sm btn-primary' onclick='viewECUEs({$idsUEJson})'>
                                                    <i class='bi bi-book'></i> ECUEs
                                                    </button>
                                                 </td>
                                             </tr>";
                                            $i++;
                                        }

                                        if (empty($uesGroupees) || $i === 1) {
                                            echo "<tr><td colspan='6' class='text-center'>Aucune unité d'enseignement trouvée pour les critères sélectionnés</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<!-- Ajouter ce modal pour les détails de l'UE -->
<div class="modal fade" id="ueDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de l'UE <span id="detailUECode"></span> - <span id="detailUEDesignation"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Semestre</th>
                                <th>Promotion</th>
                                <th>Section</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="detailUEBody">
                            <!-- Les détails seront chargés dynamiquement -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>


<!-- Ajouter ce modal pour modifier un groupe d'UE -->
<div class="modal fade" id="editUEGroupeModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Groupe d'UE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_ue_groupe.php" class="needs-validation" novalidate>
                    <input type="hidden" name="ue_ids" id="editUEIds">
                    <input type="hidden" name="annee_acad_id" value="<?= $currentYear ? $currentYear['idannee_acad'] : 0 ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="editUECode" class="form-label">Code UE <span class="text-danger">*</span></label>
                            <input type="text" name="codeUE" id="editUECode" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un code UE.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="editUEDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationUE" id="editUEDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                        <label for="editUEDescription" class="form-label">Description</label>
                            <textarea name="description" id="editUEDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editUEGroupeBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour ajouter une UE -->
<div class="modal fade" id="createUEModal" tabindex="-1" aria-labelledby="addUEModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUEModalLabel">Ajouter une Unité d'Enseignement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="ueForm" method="POST" action="controller/ue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create_multiple">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear ? $currentYear['idannee_acad'] : 0 ?>">
                    
                    <!-- Placer Code UE, Désignation et Description sur la même ligne dans le modal d'ajout -->
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="code" class="form-label">Code UE</label>
                            <input type="text" class="form-control" id="code" name="code" required>
                            <div class="invalid-feedback">Veuillez entrer un code.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" class="form-control" id="designation" name="designation" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" name="description" id="description" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Section des semestres dans le modal d'ajout d'UE -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Sélection des semestres</label>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Sélectionnez les semestres dans lesquels vous souhaitez ajouter cette UE.
                            </div>
                            
                            <div class="card">
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="semestresContainer" class="border rounded p-3">
                                        <?php 
                                        if ($currentYear) {
                                            // Afficher uniquement les sections accessibles
                                            $sectionsToDisplay = $sections;
                                            
                                            if (empty($sectionsToDisplay)) {
                                                echo "<div class='alert alert-warning'>Aucune section accessible</div>";
                                            } else {
                                                foreach ($sectionsToDisplay as $section) {
                                                    echo "<h6 class='mt-2'>{$section['designationSection']}</h6>";
                                                    $promotions = $universite->getPromotionsBySection($section['idsection'], $currentYear['idannee_acad']);
                                                    
                                                    if (!empty($promotions)) {
                                                        echo "<div class='ms-3 mb-3'>";
                                                        foreach ($promotions as $promotion) {
                                                            echo "<div class='mb-2'><strong>{$promotion['designationPromotion']} ({$promotion['cycle']})</strong></div>";
                                                            
                                                            // Récupérer les semestres pour cette promotion
                                                            $semestres = $universite->getSemestresByPromotion($promotion['idpromotion']);
                                                            
                                                            if (!empty($semestres)) {
                                                                echo "<div class='ms-3 mb-3 row'>";
                                                                foreach ($semestres as $semestre) {
                                                                    echo "
                                                                    <div class='form-check col-md-4 mb-2'>
                                                                        <input class='form-check-input' type='checkbox' name='semestres[]' value='{$semestre['idsemestre']}' id='semestre_{$semestre['idsemestre']}'>
                                                                        <label class='form-check-label' for='semestre_{$semestre['idsemestre']}'>
                                                                            Semestre {$semestre['numeroSemestre']}
                                                                        </label>
                                                                    </div>";
                                                                }
                                                                echo "</div>";
                                                            } else {
                                                                echo "<div class='ms-3 text-muted'>Aucun semestre disponible</div>";
                                                            }
                                                        }
                                                        echo "</div>";
                                                    } else {
                                                        echo "<div class='ms-3 text-muted'>Aucune promotion disponible</div>";
                                                    }
                                                }
                                            }
                                        } else {
                                            echo "<div class='alert alert-warning'>Veuillez d'abord sélectionner une année académique</div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="invalid-feedback">Veuillez sélectionner au moins un semestre.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addUEBtn" class="btn btn-primary" <?= !$currentYear ? 'disabled' : '' ?>>
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier une UE -->
<div class="modal fade" id="editUEModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Unité d'Enseignement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUEForm" method="POST" action="controller/ue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="idUE" id="edit_idUE">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear ? $currentYear['idannee_acad'] : 0 ?>">
                    
                    <!-- Remplacer uniquement la partie des champs Code UE et Désignation dans le modal de modification d'UE -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_code" class="form-label">Code UE</label>
                            <input type="text" name="code" id="edit_code" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un code.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="edit_designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="edit_designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>


                    <!-- Ajouter uniquement le champ Description dans le modal de modification d'UE -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_semestre" class="form-label">Semestre</label>
                            <select name="semestre" id="edit_semestre" class="form-select" required>
                                <?php
                                if ($currentYear) {
                                    if ($isResponsableSection && !$hasFullAccess) {
                                        // Pour les responsables, afficher uniquement les semestres de leurs sections
                                        foreach ($sections as $section) {
                                            $promotions = $universite->getPromotionsBySection($section['idsection'], $currentYear['idannee_acad']);
                                            foreach ($promotions as $promotion) {
                                                $semestres = $universite->getSemestresByPromotion($promotion['idpromotion']);
                                                foreach ($semestres as $sem) {
                                                    echo "<option value='{$sem['idsemestre']}'>S{$sem['numeroSemestre']} - {$promotion['designationPromotion']} ({$section['designationSection']})</option>";
                                                }
                                            }
                                        }
                                    } else {
                                        // Pour les administrateurs, afficher tous les semestres de l'année sélectionnée
                                        $allSemestres = $universite->getAllSemestres($currentYear['idannee_acad']);
                                        foreach ($allSemestres as $sem) {
                                            echo "<option value='{$sem['idsemestre']}'>S{$sem['numeroSemestre']} - {$sem['designationPromotion']} ({$sem['designationSection']})</option>";
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un semestre.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editUEBtn" class="btn btn-primary" <?= !$currentYear ? 'disabled' : '' ?>>
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour importer des UEs -->
<div class="modal fade" id="importUEModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des Unités d'Enseignement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" method="POST" action="controller/ue_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear ? $currentYear['idannee_acad'] : 0 ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="promotion_import" class="form-label">Promotion</label>
                            <select name="promotion_import" id="promotion_import" class="form-select" required onchange="loadSemestresImport(this.value)">
                                <option value="">Sélectionnez une promotion</option>
                                <?php 
                                if ($currentYear) {
                                    // Afficher uniquement les promotions des sections accessibles
                                    $sectionsToDisplay = $sections;
                                    
                                    if (!empty($sectionsToDisplay)) {
                                        foreach ($sectionsToDisplay as $section) {
                                            $promotions = $universite->getPromotionsBySection($section['idsection'], $currentYear['idannee_acad']);
                                            if (!empty($promotions)) {
                                                echo "<optgroup label='{$section['designationSection']}'>";
                                                foreach ($promotions as $promotion) {
                                                    echo "<option value='{$promotion['idpromotion']}'>{$promotion['designationPromotion']} ({$promotion['cycle']})</option>";
                                                }
                                                echo "</optgroup>";
                                            }
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="semestre_import" class="form-label">Semestre</label>
                            <select name="semestre_import" id="semestre_import" class="form-select" required>
                                <option value="">Sélectionnez d'abord une promotion</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un semestre.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichier_import" class="form-label">Fichier Excel/CSV</label>
                            <input type="file" name="fichier_import" id="fichier_import" class="form-control" required accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Le fichier doit contenir les colonnes suivantes: Code UE, Désignation, Description (optionnelle).
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="colonne_code" class="form-label">Colonne Code UE</label>
                            <input type="text" name="colonne_code" id="colonne_code" class="form-control" value="A" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="colonne_designation" class="form-label">Colonne Désignation</label>
                            <input type="text" name="colonne_designation" id="colonne_designation" class="form-control" value="B" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                    <div class="col-md-12">
                            <label for="colonne_description" class="form-label">Colonne Description (optionnelle)</label>
                            <input type="text" name="colonne_description" id="colonne_description" class="form-control" value="C">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skip_header" name="skip_header" value="1" checked>
                                <label class="form-check-label" for="skip_header">
                                    Ignorer la première ligne (en-têtes)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" <?= !$currentYear ? 'disabled' : '' ?>>
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
// Fonction pour charger les semestres en fonction de la promotion
function loadSemestres(promotionId) {
    if (!promotionId) {
        document.getElementById('semestre').innerHTML = '<option value="">Sélectionnez d\'abord une promotion</option>';
        return;
    }
    
    fetch(`controller/get_semestres.php?promotion=${promotionId}`)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">Sélectionnez un semestre</option>';
            data.forEach(semestre => {
                options += `<option value="${semestre.idsemestre}">${semestre.numeroSemestre}</option>`;
            });
            document.getElementById('semestre').innerHTML = options;
        })
        .catch(error => console.error('Erreur:', error));
}

// Fonction pour charger les semestres pour l'importation
function loadSemestresImport(promotionId) {
    if (!promotionId) {
        document.getElementById('semestre_import').innerHTML = '<option value="">Sélectionnez d\'abord une promotion</option>';
        return;
    }
    
    fetch(`controller/get_semestres.php?promotion=${promotionId}`)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">Sélectionnez un semestre</option>';
            data.forEach(semestre => {
                options += `<option value="${semestre.idsemestre}">${semestre.numeroSemestre}</option>`;
            });
            document.getElementById('semestre_import').innerHTML = options;
        })
        .catch(error => console.error('Erreur:', error));
}

// Fonction pour ouvrir le modal de modification d'une UE
function openEditUEModal(idUE, code, designation, semestreId) {
    document.getElementById('edit_idUE').value = idUE;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_designation').value = designation;
    document.getElementById('edit_semestre').value = semestreId;
    
    // Récupérer la description de l'UE par une requête AJAX
    fetch(`controller/get_ue_details.php?id=${idUE}`)
        .then(response => response.json())
        .then(data => {
            if (data.description) {
                document.getElementById('edit_description').value = data.description;
            }
        })
        .catch(error => console.error('Erreur:', error));
    
    new bootstrap.Modal(document.getElementById('editUEModal')).show();
}

function deleteUE(idUE) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera l'unité d'enseignement et ne peut pas être annulée!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Utiliser une requête AJAX pour plus de fiabilité
            fetch(`controller/ue_controller.php?action=delete&id=${idUE}`)
                .then(response => {
                    if (response.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'L\'unité d\'enseignement a été supprimée avec succès.'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la suppression.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la communication avec le serveur.'
                    });
                });
        }
    });
}



// Validation des formulaires Bootstrap
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()


// Validation du formulaire d'ajout d'UE
document.getElementById('ueForm').addEventListener('submit', function(event) {
    // Vérifier si au moins un semestre est sélectionné
    const semestresChecked = document.querySelectorAll('input[name="semestres[]"]:checked');
    if (semestresChecked.length === 0) {
        event.preventDefault();
        event.stopPropagation();
        
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Veuillez sélectionner au moins un semestre.'
        });
        
        return false;
    }
});

</script>

<script>
    // Fonction pour modifier un groupe d'UE
    function editUEGroupe(codeUE, designationUE, idsUE) {
        document.getElementById('editUECode').value = codeUE;
        document.getElementById('editUEDesignation').value = designationUE;
        document.getElementById('editUEIds').value = JSON.stringify(idsUE);
        
        // Récupérer la description (on prend celle de la première UE du groupe)
        if (idsUE.length > 0) {
            fetch(`controller/get_ue_details.php?id=${idsUE[0]}`)
                .then(response => response.json())
                .then(data => {
                    if (data.description) {
                        document.getElementById('editUEDescription').value = data.description;
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
        
        new bootstrap.Modal(document.getElementById('editUEGroupeModal')).show();
    }
    
    // Fonction pour confirmer la suppression d'un groupe d'UE
    function deleteUEGroupe(codeUE, designationUE, idsUE) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            html: `Cette action supprimera l'UE <strong>${codeUE} - ${designationUE}</strong> de tous les semestres associés.<br>Cette action est irréversible!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_ue_groupe.php?ids=${JSON.stringify(idsUE)}`;
            }
        });
    }
    
    // Fonction pour afficher les détails d'une UE
    function viewUEDetails(codeUE, designationUE, idsUE) {
        document.getElementById('detailUECode').textContent = codeUE;
        document.getElementById('detailUEDesignation').textContent = designationUE;
        
        // Charger les détails via AJAX
        fetch(`controller/get_ue_groupe_details.php?ids=${JSON.stringify(idsUE)}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                data.forEach(ue => {
                    html += `
                    <tr>
                        <td>${ue.idUE}</td>
                        <td>Semestre ${ue.numeroSemestre}</td>
                        <td>${ue.designationPromotion}</td>
                        <td>${ue.designationSection}</td>
                        <td>
                            <button class='btn btn-sm btn-warning' onclick='openEditUEModal(${ue.idUE}, "${ue.codeUE}", "${ue.designationUE}", ${ue.semestre_idsemestre})'>
                                <i class='bi bi-pencil-square'></i>
                            </button>
                            <button class='btn btn-sm btn-danger' onclick='deleteUE(${ue.idUE})'>
                                <i class='bi bi-trash'></i>
                            </button>
                            <button class='btn btn-sm btn-primary' onclick='window.location.href="?view=enseignement/ecues&ue=${ue.idUE}"'>
                                <i class='bi bi-book'></i> ECUEs
                            </button>
                        </td>
                    </tr>`;
                });
                document.getElementById('detailUEBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('detailUEBody').innerHTML = 
                    '<tr><td colspan="5" class="text-center text-danger">Erreur lors du chargement des détails</td></tr>';
            });
        
        new bootstrap.Modal(document.getElementById('ueDetailsModal')).show();
    }
    
    // Fonction pour rediriger vers la page des ECUEs avec plusieurs UE
    function viewECUEs(idsUE) {
        // Si une seule UE, rediriger directement
        if (idsUE.length === 1) {
            window.location.href = `?view=enseignement/ecues&ue=${idsUE[0]}`;
            return;
        }
        
        // Sinon, demander à l'utilisateur de choisir une UE spécifique
        Swal.fire({
            title: 'Choisir une UE',
            text: 'Veuillez sélectionner une UE spécifique pour voir ses ECUEs',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Voir les détails',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                viewUEDetails(document.getElementById('detailUECode').textContent, 
                              document.getElementById('detailUEDesignation').textContent, 
                              idsUE);
            }
        });
    }
</script>

<script>
// Fonction pour mettre à jour le filtre de promotion en fonction de la section
function updatePromotionFilter() {
    // La mise à jour se fait par le rechargement de la page via form.submit()
}

// Fonction pour exporter la maquette d'une promotion
function exportMaquette(promotionId) {
    if (!promotionId) {
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'Veuillez d\'abord sélectionner une promotion pour exporter la maquette.'
        });
        return;
    }
    
    // Afficher un indicateur de chargement
    Swal.fire({
        title: 'Export en cours...',
        text: 'Génération de la maquette Excel',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Déclencher le téléchargement
    window.location.href = `controller/export_maquette_ue.php?promotion=${promotionId}&annee_acad=<?= $selectedYear ?>`;
    
    // Fermer le loader après un délai
    setTimeout(() => {
        Swal.close();
    }, 2000);
}
</script>

<script src="assets/js/ue-manager.js"></script>


<?php include "./views/include/footer.php"; ?>
