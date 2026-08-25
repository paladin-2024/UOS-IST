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
$filterStatut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filterAnnee = isset($_GET['annee']) ? $_GET['annee'] : $currentYear['idannee_acad'];
$filterEnOrdre = isset($_GET['en_ordre']) ? $_GET['en_ordre'] : '';

$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole'];

// Vérifier les responsabilités de l'utilisateur
$userSections = [];
$isResponsableSection = false;

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
$querySections .= " ORDER BY s.\"designationSection\"";

$stmtSections = $pdo->prepare($querySections);
if ($isResponsableSection && !empty($userSections)) {
    $stmtSections->execute($userSections);
} else {
    $stmtSections->execute();
}
$sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

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

// Construction de la requête principale pour les étudiants
$params = [':anneeId' => $filterAnnee];

$queryEtudiants = "SELECT DISTINCT
                    e.idetudiant,
                    e.matricule,
                    e.noms,
                    e.sexe,
                    e.\"dateNaissance\",
                    e.\"lieuNaissance\",
                    e.telephone,
                    e.adressemail as email,
                    1 as est_actif,
                    e.\"dateEnregistrement\" as \"dateCreation\",
                    p.idpromotion,
                    p.\"designationPromotion\",
                    p.cycle,
                    o.\"designationOrientation\",
                    sec.idsection,
                    sec.\"designationSection\",
                    aa.designation as annee_designation,
                    eo.idetudiant as est_en_ordre,
                    suj.idsujets as a_sujet,
                    suj.intitule as sujet_intitule,
                    suj.\"etatSujet\" as sujet_etat,
                    'Actif' as statut_etudiant,
                    'success' as badge_statut
                FROM etudiant e
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                JOIN section sec ON o.section_idsection = sec.idsection
                JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                LEFT JOIN etudiant_en_ordre eo ON e.idetudiant = eo.idetudiant 
                    AND eo.annee_acad_idannee_acad = e.annee_acad_idannee_acad
                LEFT JOIN sujets suj ON e.idetudiant = suj.etudiant_idetudiant 
                    AND suj.annee_acad_idannee_acad = e.annee_acad_idannee_acad
                WHERE e.annee_acad_idannee_acad = :anneeId";

// Appliquer les filtres selon les droits
if ($userRole != 1 && $isResponsableSection && !empty($userSections)) {
    // Responsable de section : voir les étudiants de ses sections
    $sectionPlaceholders = [];
    foreach ($userSections as $i => $section) {
        $paramName = ":userSection{$i}";
        $sectionPlaceholders[] = $paramName;
        $params[$paramName] = $section;
    }
    $queryEtudiants .= " AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
} elseif ($userRole != 1 && !$isResponsableSection) {
    // Utilisateur sans droits spéciaux : aucun étudiant
    $queryEtudiants .= " AND 1 = 0";
}

// Appliquer les filtres de recherche
if (!empty($search)) {
    $queryEtudiants .= " AND (e.noms LIKE :search 
                          OR e.matricule LIKE :search
                          OR e.adressemail LIKE :search
                          OR e.telephone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filterSection)) {
    $queryEtudiants .= " AND sec.idsection = :filterSection";
    $params[':filterSection'] = $filterSection;
}

if (!empty($filterPromotion)) {
    $queryEtudiants .= " AND p.idpromotion = :filterPromotion";
    $params[':filterPromotion'] = $filterPromotion;
}

if (!empty($filterCycle)) {
    $queryEtudiants .= " AND p.cycle = :filterCycle";
    $params[':filterCycle'] = $filterCycle;
}

// Pas de filtre par statut car tous les étudiants sont considérés comme actifs

if (!empty($filterEnOrdre)) {
    if ($filterEnOrdre == 'oui') {
        $queryEtudiants .= " AND eo.idetudiant IS NOT NULL";
    } else {
        $queryEtudiants .= " AND eo.idetudiant IS NULL";
    }
}

$queryEtudiants .= " ORDER BY sec.\"designationSection\", p.\"designationPromotion\", e.noms";

$stmtEtudiants = $pdo->prepare($queryEtudiants);
foreach ($params as $key => $value) {
    $stmtEtudiants->bindValue($key, $value);
}
$stmtEtudiants->execute();
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalEtudiants = count($etudiants);
$etudiantsActifs = $totalEtudiants; // Tous les étudiants sont considérés comme actifs
$etudiantsInactifs = 0;
$etudiantsEnOrdre = count(array_filter($etudiants, function($e) { return $e['est_en_ordre'] !== null; }));
$etudiantsAvecSujet = count(array_filter($etudiants, function($e) { return $e['a_sujet'] !== null; }));

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES ÉTUDIANTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Étudiants</li>
                <li class="breadcrumb-item active">Liste</li>
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
                        <?php elseif ($userRole == 1): ?>
                            <p class="text-muted">Vue globale de tous les étudiants</p>
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
                        <h5 class="card-title">Total étudiants</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $totalEtudiants ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Actifs</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $etudiantsActifs ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalEtudiants > 0 ? round(($etudiantsActifs / $totalEtudiants) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">En ordre</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $etudiantsEnOrdre ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalEtudiants > 0 ? round(($etudiantsEnOrdre / $totalEtudiants) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Avec sujet</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $etudiantsAvecSujet ?></h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalEtudiants > 0 ? round(($etudiantsAvecSujet / $totalEtudiants) * 100, 1) : 0 ?>%
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
                    <input type="hidden" name="view" value="etudiant/liste_etudiants">
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                               class="form-control" placeholder="Nom, prénom, matricule, email...">
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
                        <label for="statut" class="form-label">Statut</label>
                        <select name="statut" id="statut" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="Actif" <?= $filterStatut == 'Actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="Inactif" <?= $filterStatut == 'Inactif' ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="en_ordre" class="form-label">En ordre</label>
                        <select name="en_ordre" id="en_ordre" class="form-select">
                            <option value="">Tous</option>
                            <option value="oui" <?= $filterEnOrdre == 'oui' ? 'selected' : '' ?>>Oui</option>
                            <option value="non" <?= $filterEnOrdre == 'non' ? 'selected' : '' ?>>Non</option>
                        </select>
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <a href="index?view=etudiant/liste_etudiants" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                            </a>
                            <button type="button" class="btn btn-info" onclick="exportToExcel()">
                                <i class="bi bi-file-excel"></i> Exporter
                            </button>
                            <button type="button" class="btn btn-success" onclick="printList()">
                                <i class="bi bi-printer"></i> Imprimer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des étudiants -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Liste des étudiants
                    <span class="badge bg-primary"><?= $totalEtudiants ?> résultat(s)</span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="etudiantsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Nom complet</th>
                                <th>Sexe</th>
                                <th>Contact</th>
                                <th>Promotion</th>
                                <th>Section</th>
                                <th>Cycle</th>
                                <th>Statut</th>
                                <th>En ordre</th>
                                <th>Sujet recherche</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($etudiants as $etudiant): 
                            ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($etudiant['matricule']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($etudiant['noms']) ?></strong>
                                    <?php if ($etudiant['dateNaissance']): ?>
                                        <br><small class="text-muted">
                                            Né(e) le <?= date('d/m/Y', strtotime($etudiant['dateNaissance'])) ?>
                                            <?php if ($etudiant['lieuNaissance']): ?>
                                                à <?= htmlspecialchars($etudiant['lieuNaissance']) ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $etudiant['sexe'] == 'M' ? 'primary' : 'pink' ?>">
                                        <?= $etudiant['sexe'] == 'M' ? 'Masculin' : 'Féminin' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($etudiant['telephone']): ?>
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($etudiant['telephone']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($etudiant['email']): ?>
                                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($etudiant['email']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($etudiant['designationPromotion']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($etudiant['designationOrientation']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($etudiant['designationSection']) ?></td>
                                <td>
                                    <span class="badge bg-<?= getCycleBadgeColor($etudiant['cycle']) ?>">
                                        <?= htmlspecialchars($etudiant['cycle']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $etudiant['badge_statut'] ?>">
                                        <?= $etudiant['statut_etudiant'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($etudiant['est_en_ordre']): ?>
                                        <i class="bi bi-check-circle-fill text-success" title="En ordre"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger" title="Pas en ordre"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($etudiant['a_sujet']): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <div>
                                                <small class="text-muted"><?= htmlspecialchars(substr($etudiant['sujet_intitule'], 0, 50)) ?>...</small>
                                                <br><span class="badge bg-<?= getSujetBadgeColor($etudiant['sujet_etat']) ?>">
                                                    <?= htmlspecialchars($etudiant['sujet_etat']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-warning" title="Pas de sujet"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="index?view=etudiant/profil&id=<?= $etudiant['idetudiant'] ?>" 
                                           class="btn btn-sm btn-outline-info" title="Voir profil">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index?view=etudiant/dossier_academique&id=<?= $etudiant['idetudiant'] ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Dossier académique">
                                            <i class="bi bi-folder"></i>
                                        </a>
                                        <?php if ($etudiant['a_sujet']): ?>
                                            <a href="index?view=recherche/sujet_details&id=<?= $etudiant['a_sujet'] ?>" 
                                               class="btn btn-sm btn-outline-success" title="Voir sujet">
                                                <i class="bi bi-journal-text"></i>
                                            </a>
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

function getSujetBadgeColor($etat) {
    switch ($etat) {
        case 'Validé': return 'success';
        case 'En attente': return 'warning';
        case 'Rejeté': return 'danger';
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
    window.location.href = 'controller/export_etudiants.php?' + params.toString();
}

// Imprimer la liste
function printList() {
    window.print();
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

.bg-pink {
    background-color: #e91e63 !important;
}

@media print {
    .btn, .breadcrumb, .pagetitle nav, .card:first-child {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 12px;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}
</style>

<?php include "./views/include/footer.php"; ?>