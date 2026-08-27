<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
$checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND table_schema = 'public' AND column_name = 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    // La colonne existe, utiliser la requête avec est_active
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    // La colonne n'existe pas, prendre la dernière année
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    // Si aucune année trouvée, prendre la dernière année
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

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$promotionFilter = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$ecueFilter = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$typeCoursFilter = isset($_GET['type_cours']) ? $_GET['type_cours'] : '';

// Fonction pour récupérer les promotions accessibles
function getPromotionsAccessibles($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT DISTINCT p.* 
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les ECUE d'une promotion
function getECUEsByPromotion($pdo, $promotionId) {
    $query = "SELECT DISTINCT e.* 
              FROM ecue e
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              WHERE s.promotion_idpromotion = :promotionId
              AND e.\"estVisible\" = 1
              ORDER BY e.\"designationECUE\"";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les suivis d'enseignement
function getSuivisEnseignement($pdo, $filters, $userSections = []) {
    $params = [];
    
    $query = "SELECT se.*, 
                     e.\"designationECUE\",
                     tu.\"nomUser\" as user_nom,
                     a.noms as enseignant_nom,
                     gr.designation as grade_enseignant,
                     p.\"designationPromotion\",
                     sec.\"designationSection\" as section
              FROM suivi_enseignements se
              JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
              LEFT JOIN t_users tu ON se.\"idUser\" = tu.\"idUser\"
              LEFT JOIN agent a ON se.enseignant_id = a.\"idAgent\"
              LEFT JOIN grade gr ON a.grade_id = gr.idgrade
              -- Jointure pour récupérer la promotion via l'ECUE
              LEFT JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              LEFT JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              LEFT JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE 1=1";
    
    // Filtrer par sections si l'utilisateur est responsable
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtrer par promotion
    if (!empty($filters['promotion'])) {
        $query .= " AND p.idpromotion = :promotion";
        $params[':promotion'] = $filters['promotion'];
    }
    
    // Filtrer par ECUE
    if (!empty($filters['ecue'])) {
        $query .= " AND se.\"idECUE\" = :ecue";
        $params[':ecue'] = $filters['ecue'];
    }
    
    // Filtrer par type de cours
    if (!empty($filters['type_cours'])) {
        $query .= " AND se.type_cours = :type_cours";
        $params[':type_cours'] = $filters['type_cours'];
    }
    
    // Filtrer par date
    if (!empty($filters['date_debut'])) {
        $query .= " AND se.date_cours >= :date_debut";
        $params[':date_debut'] = $filters['date_debut'];
    }
    
    if (!empty($filters['date_fin'])) {
        $query .= " AND se.date_cours <= :date_fin";
        $params[':date_fin'] = $filters['date_fin'];
    }
    
    // Recherche textuelle
    if (!empty($filters['search'])) {
        $query .= " AND (e.designationECUE LIKE :search 
                        OR p.designationPromotion LIKE :search 
                        OR et.noms LIKE :search 
                        OR a.noms LIKE :search 
                        OR se.commentaire LIKE :search)";
        $params[':search'] = "%{$filters['search']}%";
    }
    
    // Filtrer par année académique
    if (!empty($filters['annee_acad'])) {
        $query .= " AND se.annee_acad_idannee_acad = :annee_acad";
        $params[':annee_acad'] = $filters['annee_acad'];
    }
    
    $query .= " ORDER BY se.date_cours DESC, se.heure_debut DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG - Afficher le nombre de résultats
        error_log("getSuivisEnseignement - Nombre de résultats: " . count($result));
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur dans getSuivisEnseignement: " . $e->getMessage());
        error_log("Query: " . $query);
        error_log("Params: " . print_r($params, true));
        return [];
    }
}

// Récupérer les promotions accessibles
$promotions = [];
if ($isResponsableSection) {
    $promotions = getPromotionsAccessibles($pdo, $userSections, $currentYear['idannee_acad']);
} else {
    $promotions = getPromotionsAccessibles($pdo, [], $currentYear['idannee_acad']);
}

// Récupérer les ECUE si une promotion est sélectionnée
$ecues = [];
if ($promotionFilter > 0) {
    $ecues = getECUEsByPromotion($pdo, $promotionFilter);
}

// Préparer les filtres
$filters = [
    'search' => $search,
    'promotion' => $promotionFilter,
    'ecue' => $ecueFilter,
    'date_debut' => $dateDebut,
    'date_fin' => $dateFin,
    'type_cours' => $typeCoursFilter,
    'annee_acad' => $currentYear['idannee_acad']
];

// Récupérer les suivis
$suivis = [];
if ($isResponsableSection) {
    $suivis = getSuivisEnseignement($pdo, $filters, $userSections);
} else {
    $suivis = getSuivisEnseignement($pdo, $filters);
}

// DEBUG - Vérifier le contenu de la table
$debugQuery = "SELECT COUNT(*) as total FROM suivi_enseignements WHERE annee_acad_idannee_acad = :annee";
$debugStmt = $pdo->prepare($debugQuery);
$debugStmt->bindParam(':annee', $currentYear['idannee_acad']);
$debugStmt->execute();
$totalEnregistrements = $debugStmt->fetchColumn();

// Afficher un message de debug
if ($totalEnregistrements == 0) {
    echo "<!-- DEBUG: Aucun enregistrement dans suivi_enseignements pour l'année " . $currentYear['idannee_acad'] . " -->";
} else {
    echo "<!-- DEBUG: " . $totalEnregistrements . " enregistrements trouvés dans suivi_enseignements -->";
    echo "<!-- DEBUG: " . count($suivis) . " enregistrements après filtrage -->";
}

// Calculer les statistiques
$totalHeures = 0;
$heuresParType = ['CM' => 0, 'TD' => 0, 'TP' => 0, 'Evaluation' => 0];
$coursParPromotion = [];

foreach ($suivis as $suivi) {
    $duree = (strtotime($suivi['heure_fin']) - strtotime($suivi['heure_debut'])) / 3600;
    $totalHeures += $duree;
    
    if (isset($heuresParType[$suivi['type_cours']])) {
        $heuresParType[$suivi['type_cours']] += $duree;
    }
    
    if (!isset($coursParPromotion[$suivi['designationPromotion']])) {
        $coursParPromotion[$suivi['designationPromotion']] = 0;
    }
    $coursParPromotion[$suivi['designationPromotion']]++;
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUIVI DES ENSEIGNEMENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Suivi des enseignements</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Accès restreint :</strong> Vous visualisez uniquement les enseignements des sections dont vous êtes responsable.
            <?php 
            // Récupérer les noms des sections
            $sectionNames = [];
            if (!empty($userSections)) {
                $placeholders = implode(',', array_fill(0, count($userSections), '?'));
                $querySections = "SELECT \"designationSection\" FROM section WHERE idsection IN ($placeholders)";
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

        <!-- Statistiques -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total des heures</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($totalHeures, 1) ?> h</h6>
                                <span class="text-muted small pt-2 ps-1"><?= count($suivis) ?> séances</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Cours Magistraux</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($heuresParType['CM'], 1) ?> h</h6>
                                <span class="text-success small pt-1 fw-bold">
                                    <?= $totalHeures > 0 ? round(($heuresParType['CM'] / $totalHeures) * 100) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux Dirigés</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($heuresParType['TD'], 1) ?> h</h6>
                                <span class="text-info small pt-1 fw-bold">
                                    <?= $totalHeures > 0 ? round(($heuresParType['TD'] / $totalHeures) * 100) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux Pratiques</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($heuresParType['TP'], 1) ?> h</h6>
                                <span class="text-warning small pt-1 fw-bold">
                                    <?= $totalHeures > 0 ? round(($heuresParType['TP'] / $totalHeures) * 100) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filtrer les enseignements</h5>
                
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="view" value="enseignement/suivi_enseignements">
                    
                    <div class="col-md-3">
                        <label class="form-label">Promotion</label>
                        <select name="promotion" class="form-select" id="promotionFilter">
                            <option value="">Toutes les promotions</option>
                            <?php foreach ($promotions as $promo): ?>
                                <option value="<?= $promo['idpromotion'] ?>" <?= $promotionFilter == $promo['idpromotion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($promo['designationPromotion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Cours (ECUE)</label>
                        <select name="ecue" class="form-select" id="ecueFilter">
                            <option value="">Tous les cours</option>
                            <?php foreach ($ecues as $ecue): ?>
                                <option value="<?= $ecue['idECUE'] ?>" <?= $ecueFilter == $ecue['idECUE'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ecue['designationECUE']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Type de cours</label>
                        <select name="type_cours" class="form-select">
                            <option value="">Tous les types</option>
                            <option value="CM" <?= $typeCoursFilter == 'CM' ? 'selected' : '' ?>>CM</option>
                            <option value="TD" <?= $typeCoursFilter == 'TD' ? 'selected' : '' ?>>TD</option>
                            <option value="TP" <?= $typeCoursFilter == 'TP' ? 'selected' : '' ?>>TP</option>
                            <option value="Evaluation" <?= $typeCoursFilter == 'Evaluation' ? 'selected' : '' ?>>Évaluation</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date début</label>
                        <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date fin</label>
                        <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filtrer
                        </button>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="?view=enseignement/suivi_enseignements" class="btn btn-secondary w-100">
                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des suivis -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Liste des enseignements
                    <div class="float-end">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Exporter
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="printReport()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                    </div>
                </h5>

                <div class="table-responsive">
                    <?php if (empty($suivis)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun enseignement trouvé avec les critères spécifiés.
                            <?php if ($totalEnregistrements == 0 && $hasFullAccess): ?>
                                <hr>
                                <p>La table de suivi des enseignements est vide. Voulez-vous ajouter des données de test ?</p>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addTestData()">
                                    <i class="bi bi-plus-circle"></i> Ajouter des données de test
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered table-striped" id="suiviTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Cours (ECUE)</th>
                                    <th>Type</th>
                                    <th>Promotion</th>
                                    <th>Enseignant</th>
                                    <th>Enregistré par</th>
                                    <th>Salle</th>
                                    <th>Durée</th>
                                    <th>Matières enseignées</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 1;
                                foreach ($suivis as $suivi): 
                                    $duree = (strtotime($suivi['heure_fin']) - strtotime($suivi['heure_debut'])) / 3600;
                                    
                                    // Définir la classe pour le type de cours
                                    $typeClass = '';
                                    switch ($suivi['type_cours']) {
                                        case 'CM':
                                            $typeClass = 'badge bg-primary';
                                            break;
                                        case 'TD':
                                            $typeClass = 'badge bg-info';
                                            break;
                                        case 'TP':
                                            $typeClass = 'badge bg-warning';
                                            break;
                                        case 'Evaluation':
                                            $typeClass = 'badge bg-danger';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?= $index++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($suivi['date_cours'])) ?></td>
                                        <td><?= substr($suivi['heure_debut'], 0, 5) ?> - <?= substr($suivi['heure_fin'], 0, 5) ?></td>
                                        <td><?= htmlspecialchars($suivi['designationECUE']) ?></td>
                                        <td><span class="<?= $typeClass ?>"><?= $suivi['type_cours'] ?></span></td>
                                        <td><?= htmlspecialchars($suivi['designationPromotion']) ?></td>
                                        <td>
                                            <?php if ($suivi['enseignant_nom']): ?>
                                                <?= $suivi['grade_enseignant'] ? htmlspecialchars($suivi['grade_enseignant']) . ' ' : '' ?>
                                                <?= htmlspecialchars($suivi['enseignant_nom']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($suivi['user_nom']) ?>
                                        </td>
                                        <td><?= $suivi['salle'] ? htmlspecialchars($suivi['salle']) : '<span class="text-muted">-</span>' ?></td>
                                        <td><?= number_format($duree, 1) ?> h</td>
                                        <td>
                                            <?php if ($suivi['commentaire']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="showMatieres(<?= htmlspecialchars(json_encode($suivi['commentaire'])) ?>)">
                                                    <i class="bi bi-book"></i> Voir
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Graphique des heures par promotion -->
        <?php if (!empty($coursParPromotion)): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Répartition des cours par promotion</h5>
                <canvas id="promotionChart" style="max-height: 400px;"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour afficher les matières enseignées -->
<div class="modal fade" id="matieresModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-book me-2"></i>Matières enseignées</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Contenu du cours :</strong>
                </div>
                <div id="matieresContent" class="p-3 bg-light rounded"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Script pour le chargement des ECUE -->
<script src="assets/js/suivi_enseignements.js"></script>

<script>
// Variables PHP à passer au JavaScript
const selectedEcue = <?= json_encode($ecueFilter) ?>;

// Attendre que le script externe soit chargé
$(document).ready(function() {
    // Si une ECUE était pré-sélectionnée, la restaurer après le chargement
    if (selectedEcue && $('#ecueFilter option[value="' + selectedEcue + '"]').length > 0) {
        $('#ecueFilter').val(selectedEcue);
    }
});

// Afficher les matières enseignées
function showMatieres(matieres) {
    // Échapper les caractères HTML pour éviter les injections XSS
    const escapeHtml = (text) => {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    };
    
    // Échapper le contenu et remplacer les retours à la ligne par des <br>
    const content = escapeHtml(matieres).replace(/\n/g, '<br>');
    document.getElementById('matieresContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('matieresModal'));
    modal.show();
}

// Exporter vers Excel
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'controller/export_suivi_enseignements.php?' + params.toString();
}

// Imprimer le rapport
function printReport() {
    window.print();
}

// Fonction pour ajouter des données de test
function addTestData() {
    if (confirm('Voulez-vous vraiment ajouter des données de test dans la table de suivi des enseignements ?')) {
        fetch('controller/add_test_data_suivi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'add_test_data',
                annee_acad_id: <?= $currentYear['idannee_acad'] ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: data.message
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue'
                });
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'ajout des données'
            });
        });
    }
}

// Graphique des heures par promotion
<?php if (!empty($coursParPromotion)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('promotionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($coursParPromotion)) ?>,
            datasets: [{
                label: 'Nombre de séances',
                data: <?= json_encode(array_values($coursParPromotion)) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php include "./views/include/footer.php"; ?>