<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();
$queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    // Si aucune année active, prendre la dernière année
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les sections dont l'utilisateur est responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE \"idUser\" = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isResponsableSection = !empty($userSections);

// Vérifier les droits d'accès
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

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

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : $currentYear['idannee_acad'];

// Fonction pour récupérer les promotions avec leurs chefs
function getPromotionsWithChefs($pdo, $search, $sections = [], $anneeId = null) {
$params = [];

$query = "SELECT p.*,
s.\"designationSection\" as section,
o.\"designationOrientation\" as orientation,
cp.id_chef,
e.noms as chef_nom,
e.matricule as chef_matricule,
e.idetudiant as chef_id,
a.designation as annee
FROM promotion p
LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
LEFT JOIN section s ON o.section_idsection = s.idsection
LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion AND cp.annee_acad_idannee_acad = :anneeId AND cp.est_actif = 1
LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant
LEFT JOIN annee_acad a ON :anneeId = a.idannee_acad
WHERE EXISTS (
                SELECT 1 FROM etudiant etu
                WHERE etu.promotion_idpromotion = p.idpromotion
                AND etu.annee_acad_idannee_acad = :anneeId
                AND etu.est_actif = 1
            )";
    
    $params[':anneeId'] = $anneeId;
    
    // Filtrer par sections si spécifié
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
    
    // Filtrage par recherche textuelle
    if (!empty($search)) {
        $query .= " AND (p.\"designationPromotion\" LIKE :search 
                        OR o.\"designationOrientation\" LIKE :search 
                        OR e.noms LIKE :search 
                        OR e.matricule LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"";
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans getPromotionsWithChefs: " . $e->getMessage());
        return [];
    }
}

// Récupérer les promotions en fonction des droits de l'utilisateur
if ($isResponsableSection) {
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            $promotions = getPromotionsWithChefs($pdo, $search, [$sectionFilter], $anneeId);
        } else {
            $promotions = [];
        }
    } else {
        $promotions = getPromotionsWithChefs($pdo, $search, $userSections, $anneeId);
    }
} else {
    if ($hasFullAccess) {
        if ($sectionFilter > 0) {
            $promotions = getPromotionsWithChefs($pdo, $search, [$sectionFilter], $anneeId);
        } else {
            $promotions = getPromotionsWithChefs($pdo, $search, [], $anneeId);
        }
    } else {
        $promotions = [];
    }
}

// Récupérer les données nécessaires pour les filtres
// Années académiques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Sections disponibles selon les droits
if ($isResponsableSection) {
    $sections = [];
    if (!empty($userSections)) {
        $sectionPlaceholders = implode(',', array_fill(0, count($userSections), '?'));
        $querySection = "SELECT * FROM section WHERE idsection IN ($sectionPlaceholders) ORDER BY \"designationSection\"";
        $stmtSection = $pdo->prepare($querySection);
        foreach ($userSections as $i => $section) {
            $stmtSection->bindValue($i+1, $section);
        }
        $stmtSection->execute();
        $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $querySection = "SELECT * FROM section ORDER BY \"designationSection\"";
    $stmtSection = $pdo->prepare($querySection);
    $stmtSection->execute();
    $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les étudiants d'une promotion
function getEtudiantsPromotion($pdo, $promotionId, $anneeId) {
    $query = "SELECT e.idetudiant, e.noms, e.matricule 
              FROM etudiant e
              WHERE e.promotion_idpromotion = :promotionId 
              AND e.annee_acad_idannee_acad = :anneeId
              AND e.est_actif = 1
              ORDER BY e.noms";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->bindParam(':anneeId', $anneeId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!-- Début du HTML -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>CONFIGURATION DES CHEFS DE PROMOTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="#">Configuration</a></li>
                <li class="breadcrumb-item active">Chefs de Promotion</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Messages de succès et d'erreur -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php
                switch ($_GET['success']) {
                    case 'chef_assigned':
                        echo 'Chef de promotion assigné avec succès.';
                        break;
                    case 'chef_removed':
                        echo 'Chef de promotion retiré avec succès.';
                        break;
                    default:
                        echo 'Opération effectuée avec succès.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php
                switch ($_GET['error']) {
                    case 'not_connected':
                        echo 'Vous devez être connecté pour accéder à cette page.';
                        break;
                    case 'invalid_request':
                        echo 'Requête invalide.';
                        break;
                    case 'invalid_promotion':
                        echo 'Promotion invalide.';
                        break;
                    case 'invalid_year':
                        echo 'Année académique invalide.';
                        break;
                    case 'promotion_not_found':
                        echo 'Promotion non trouvée.';
                        break;
                    case 'access_denied':
                        echo 'Accès refusé. Vous n\'avez pas les droits pour cette promotion.';
                        break;
                    case 'invalid_student':
                        echo 'Étudiant invalide.';
                        break;
                    case 'student_not_in_promotion':
                        echo 'L\'étudiant sélectionné n\'est pas inscrit dans cette promotion.';
                        break;
                    case 'student_already_chef':
                        $promotion = isset($_GET['promotion']) ? htmlspecialchars($_GET['promotion']) : 'une autre promotion';
                        echo "Cet étudiant est déjà chef de {$promotion}.";
                        break;
                    case 'no_chef_to_remove':
                        echo 'Aucun chef à retirer pour cette promotion.';
                        break;
                    case 'database_error':
                        echo 'Erreur de base de données. Veuillez réessayer.';
                        if (isset($_GET['message'])) {
                            echo '<br><small>' . htmlspecialchars($_GET['message']) . '</small>';
                        }
                        break;
                    case 'general_error':
                        echo 'Une erreur s\'est produite. Veuillez réessayer.';
                        if (isset($_GET['message'])) {
                            echo '<br><small>' . htmlspecialchars($_GET['message']) . '</small>';
                        }
                        break;
                    default:
                        echo 'Une erreur s\'est produite.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Vous pouvez uniquement configurer les chefs de promotion pour les sections dont vous êtes responsable.
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
        <?php endif; ?>

        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        <form method="GET" action="" class="row g-3" id="filterForm">
                            <input type="hidden" name="view" value="configuration/chef_promotion">
                            
                            <div class="col-md-3">
                                <label for="anneeFilter" class="form-label">Année académique</label>
                                <select name="annee" class="form-select" id="anneeFilter">
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId == $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sectionFilter" class="form-label">Section</label>
                                <select name="section" class="form-select" id="sectionFilter">
                                    <option value="">Toutes les sections</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?= $sec['idsection'] ?>" <?= $sectionFilter == $sec['idsection'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sec['designationSection']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="searchInput" class="form-label">Recherche instantanée</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une promotion, spécialisation ou chef..." id="instantSearchInput">
                                    <button type="button" class="btn btn-outline-secondary" id="clearSearch" title="Effacer la recherche">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>" id="hiddenSearchInput">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Promotions</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= count($promotions) ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Avec Chef</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div class="ps-3">
                                <?php 
                                $withChef = array_filter($promotions, function($p) { return !empty($p['chef_nom']); });
                                ?>
                                <h6><?= count($withChef) ?></h6>
                                <span class="text-success small pt-1 fw-bold">
                                    <?= count($promotions) > 0 ? round((count($withChef) / count($promotions)) * 100) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Sans Chef</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-x"></i>
                            </div>
                            <div class="ps-3">
                                <?php 
                                $withoutChef = array_filter($promotions, function($p) { return empty($p['chef_nom']); });
                                ?>
                                <h6><?= count($withoutChef) ?></h6>
                                <span class="text-warning small pt-1 fw-bold">
                                    <?= count($promotions) > 0 ? round((count($withoutChef) / count($promotions)) * 100) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des promotions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                Gestion des Chefs de Promotion
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($currentYear['designation'] ?? '') ?></span>
                            </h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-info fs-6" id="resultCount">
                                    <?= count($promotions) ?> promotion(s) trouvée(s)
                                </span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <?php if (empty($promotions)): ?>
                                <div class="alert alert-info" id="noResultsAlert">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucune promotion trouvée avec les critères spécifiés.
                                </div>
                            <?php else: ?>
                                <table class="table table-hover table-bordered" id="promotionsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th style="width: 200px;">Promotion</th>
                                            <th style="width: 250px;">Orientation</th>
                                            <th style="width: 150px;">Section</th>
                                            <th style="width: 200px;">Chef Actuel</th>
                                            <th style="width: 100px;">Statut</th>
                                            <th style="width: 250px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="promotionsTableBody">
                                        <?php foreach ($promotions as $index => $promotion): ?>
                                            <tr class="promotion-row" data-search-text="<?= strtolower(htmlspecialchars($promotion['designationPromotion'] . ' ' . $promotion['orientation'] . ' ' . $promotion['section'] . ' ' . ($promotion['chef_nom'] ?? '') . ' ' . ($promotion['chef_matricule'] ?? ''))) ?>">
                                                <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-bold text-primary"><?= htmlspecialchars($promotion['designationPromotion']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-dark"><?= htmlspecialchars($promotion['orientation']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($promotion['section']) ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($promotion['chef_nom'])): ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-success rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                <i class="bi bi-person-fill text-white"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold text-success"><?= htmlspecialchars($promotion['chef_nom']) ?></div>
                                                                <?php if (!empty($promotion['chef_matricule'])): ?>
                                                                    <small class="text-muted"><?= htmlspecialchars($promotion['chef_matricule']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-light border rounded-circle d-flex align-items-center justify-content-center me-2">
                                                                <i class="bi bi-person text-muted"></i>
                                                            </div>
                                                            <span class="text-muted fst-italic">Non assigné</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($promotion['chef_nom'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Assigné
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>Non assigné
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="assignChef(<?= $promotion['idpromotion'] ?>, '<?= htmlspecialchars(addslashes($promotion['designationPromotion']), ENT_QUOTES) ?>', <?= $anneeId ?>)" title="<?= !empty($promotion['chef_nom']) ? 'Modifier le chef' : 'Assigner un chef' ?>">
                                                            <i class="bi bi-person-plus"></i>
                                                            <?= !empty($promotion['chef_nom']) ? 'Modifier' : 'Assigner' ?>
                                                        </button>
                                                        
                                                        <button class="btn btn-sm btn-outline-info" onclick="showHistory(<?= $promotion['idpromotion'] ?>, '<?= htmlspecialchars(addslashes($promotion['designationPromotion']), ENT_QUOTES) ?>', <?= $anneeId ?>)" title="Voir l'historique">
                                                            <i class="bi bi-clock-history"></i>
                                                            Historique
                                                        </button>
                                                        
                                                        <?php if (!empty($promotion['chef_nom'])): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="removeChef(<?= $promotion['idpromotion'] ?>, '<?= htmlspecialchars(addslashes($promotion['designationPromotion']), ENT_QUOTES) ?>', <?= $anneeId ?>)" title="Retirer le chef">
                                                            <i class="bi bi-person-dash"></i>
                                                            Retirer
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Message quand aucun résultat après recherche -->
                                <div class="alert alert-warning d-none" id="noSearchResults">
                                    <i class="bi bi-search me-2"></i>
                                    Aucune promotion ne correspond à votre recherche.
                                    <button type="button" class="btn btn-sm btn-outline-warning ms-2" onclick="clearInstantSearch()">
                                        <i class="bi bi-x-lg"></i> Effacer la recherche
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour assigner/modifier un chef de promotion -->
<div class="modal fade" id="assignChefModal" tabindex="-1" role="dialog" aria-labelledby="assignChefModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignChefModalLabel">Assigner un Chef de Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignChefForm" method="POST" action="controller/manage_chef_promotion.php">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="promotion_id" id="assign_promotion_id">
                <input type="hidden" name="annee_id" id="assign_annee_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Promotion:</label>
                        <p class="form-control-plaintext" id="assign_promotion_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="etudiant_select" class="form-label">Sélectionner l'étudiant <span class="text-danger">*</span></label>
                        <select class="form-select" id="etudiant_select" name="etudiant_id" required>
                            <option value="">Chargement des étudiants...</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assign_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="assign_comment" name="commentaire" rows="3" placeholder="Ajouter un commentaire..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour retirer un chef de promotion -->
<div class="modal fade" id="removeChefModal" tabindex="-1" role="dialog" aria-labelledby="removeChefModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Retirer le Chef de Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="removeChefForm" method="POST" action="controller/manage_chef_promotion.php">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="promotion_id" id="remove_promotion_id">
                <input type="hidden" name="annee_id" id="remove_annee_id">
                
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir retirer le chef de la promotion : <strong id="remove_promotion_name"></strong> ?</p>
                    
                    <div class="mb-3">
                        <label for="remove_comment" class="form-label">Motif (optionnel)</label>
                        <textarea class="form-control" id="remove_comment" name="commentaire" rows="3" placeholder="Expliquez la raison du retrait..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-person-dash"></i> Retirer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour l'historique des chefs de promotion -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="historyModalLabel">
                    <i class="bi bi-clock-history me-2"></i>
                    Historique des Chefs de Promotion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="text-primary">Promotion: <span id="history_promotion_name" class="fw-bold"></span></h6>
                </div>
                
                <!-- Loading spinner -->
                <div id="historyLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2 text-muted">Chargement de l'historique...</p>
                </div>
                
                <!-- Contenu de l'historique -->
                <div id="historyContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Chef</th>
                                    <th>Période</th>
                                    <th>Statut</th>
                                    <th>Assigné par</th>
                                    <th>Retiré par</th>
                                    <th>Commentaire</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- Contenu dynamique -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Message si aucun historique -->
                <div id="noHistoryMessage" class="alert alert-info d-none">
                    <i class="bi bi-info-circle me-2"></i>
                    Aucun historique trouvé pour cette promotion.
                </div>
                
                <!-- Message d'erreur -->
                <div id="historyError" class="alert alert-danger d-none">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="historyErrorMessage">Erreur lors du chargement de l'historique.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.promotion-row {
    transition: all 0.3s ease;
}

.promotion-row.fade-out {
    opacity: 0.3;
    transform: scale(0.98);
}

.search-highlight {
    background-color: yellow;
    padding: 1px 3px;
    border-radius: 3px;
}

#resultCount {
    transition: all 0.3s ease;
}

.card-icon {
    width: 60px;
    height: 60px;
}

.info-card .card-icon i {
    font-size: 24px;
}

.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.badge {
    font-size: 0.75rem;
    padding: 0.5em 0.75em;
}

.btn-outline-primary:hover,
.btn-outline-info:hover,
.btn-outline-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.modal-xl .modal-dialog {
    max-width: 1200px;
}

.timeline-item {
    border-left: 3px solid #dee2e6;
    padding-left: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 0.5rem;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #6c757d;
}

.timeline-item.active::before {
    background-color: #198754;
}

.timeline-item.inactive::before {
    background-color: #dc3545;
}
</style>

<script>
// Variables globales pour la recherche
let searchTimeout;
let allPromotions = [];

// Fonction pour assigner un chef de promotion
function assignChef(promotionId, promotionName, anneeId) {
    document.getElementById('assign_promotion_id').value = promotionId;
    document.getElementById('assign_promotion_name').textContent = promotionName;
    document.getElementById('assign_annee_id').value = anneeId;
    
    // Réinitialiser le formulaire
    document.getElementById('assignChefForm').reset();
    document.getElementById('assign_promotion_id').value = promotionId;
    document.getElementById('assign_annee_id').value = anneeId;
    
    // Charger les étudiants de cette promotion
    loadEtudiants(promotionId, anneeId);
    
    const assignModal = new bootstrap.Modal(document.getElementById('assignChefModal'));
    assignModal.show();
}

// Fonction pour retirer un chef de promotion
function removeChef(promotionId, promotionName, anneeId) {
    document.getElementById('remove_promotion_id').value = promotionId;
    document.getElementById('remove_promotion_name').textContent = promotionName;
    document.getElementById('remove_annee_id').value = anneeId;
    
    const removeModal = new bootstrap.Modal(document.getElementById('removeChefModal'));
    removeModal.show();
}

// Fonction pour charger les étudiants d'une promotion
function loadEtudiants(promotionId, anneeId) {
    const select = document.getElementById('etudiant_select');
    const submitBtn = document.querySelector('#assignChefForm button[type="submit"]');
    
    select.innerHTML = '<option value="">Chargement...</option>';
    select.disabled = true;
    if (submitBtn) submitBtn.disabled = true;
    
    fetch(`controller/get_etudiants_promotion.php?promotion_id=${promotionId}&annee_id=${anneeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            select.innerHTML = '<option value="">Sélectionner un étudiant</option>';
            
            if (data.etudiants && data.etudiants.length > 0) {
                data.etudiants.forEach(etudiant => {
                    const option = document.createElement('option');
                    option.value = etudiant.idetudiant;
                    option.textContent = `${etudiant.noms} (${etudiant.matricule})`;
                    select.appendChild(option);
                });
                
                if (data.etudiants.length === 0) {
                    select.innerHTML = '<option value="">Aucun étudiant trouvé dans cette promotion</option>';
                }
            } else {
                select.innerHTML = '<option value="">Aucun étudiant trouvé dans cette promotion</option>';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des étudiants:', error);
            select.innerHTML = '<option value="">Erreur lors du chargement</option>';
            
            // Afficher une notification d'erreur
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger la liste des étudiants: ' + error.message
                });
            } else {
                alert('Erreur lors du chargement des étudiants: ' + error.message);
            }
        })
        .finally(() => {
            select.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
        });
}

// Validation du formulaire d'assignation
function validateAssignForm() {
    const etudiantSelect = document.getElementById('etudiant_select');
    
    if (!etudiantSelect.value) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Veuillez sélectionner un étudiant.'
            });
        } else {
            alert('Veuillez sélectionner un étudiant.');
        }
        return false;
    }
    
    return true;
}

// Confirmation avant suppression
function confirmRemoveChef() {
    const promotionName = document.getElementById('remove_promotion_name').textContent;
    
    if (typeof Swal !== 'undefined') {
        return Swal.fire({
            title: 'Confirmer la suppression',
            text: `Êtes-vous sûr de vouloir retirer le chef de la promotion "${promotionName}" ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, retirer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            return result.isConfirmed;
        });
    } else {
        return confirm(`Êtes-vous sûr de vouloir retirer le chef de la promotion "${promotionName}" ?`);
    }
}

// Soumettre automatiquement le formulaire lorsque l'année ou la section change
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des filtres (utilisant Select2)
    const anneeFilter = document.getElementById('anneeFilter');
    const sectionFilter = document.getElementById('sectionFilter');

    if (anneeFilter) {
        // Utiliser l'événement Select2 pour l'année académique
        $(anneeFilter).on('select2:select', function() {
            document.getElementById('filterForm').submit();
        });
    }

    if (sectionFilter) {
        // Utiliser l'événement Select2 pour la section
        $(sectionFilter).on('select2:select', function() {
            document.getElementById('filterForm').submit();
        });
    }
    
    // Validation du formulaire d'assignation
    const assignForm = document.getElementById('assignChefForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function(e) {
            if (!validateAssignForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Validation du formulaire de suppression
    const removeForm = document.getElementById('removeChefForm');
    if (removeForm) {
        removeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (typeof Swal !== 'undefined') {
                confirmRemoveChef().then((confirmed) => {
                    if (confirmed) {
                        removeForm.submit();
                    }
                });
            } else {
                if (confirmRemoveChef()) {
                    removeForm.submit();
                }
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert && alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>