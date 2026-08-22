<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'année académique actuelle (active)
$stmt = $connexion->query("SELECT * FROM annee_acad WHERE est_active = 1");
$anneeActuelle = $stmt->fetch(PDO::FETCH_ASSOC);

// Filtres (définir $anneeId tôt pour filtrer les promotions)
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : ($anneeActuelle ? $anneeActuelle['idannee_acad'] : 0);
$sectionId = isset($_GET['section']) ? intval($_GET['section']) : 0;

// Récupérer toutes les années académiques pour le sélecteur
$stmtAnnees = $connexion->query("SELECT * FROM annee_acad ORDER BY designation DESC");
$anneesAcademiques = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les sections (filtrées par année si sélectionnée)
$sqlSections = "
    SELECT DISTINCT sec.idsection, sec.\"designationSection\"
    FROM section sec
    JOIN orientation o ON sec.idsection = o.section_idsection
    JOIN promotion p ON o.idorientation = p.orientation_idorientation
    WHERE 1=1
";
$paramsSections = [];

if ($anneeId > 0) {
    $sqlSections .= " AND p.annee_acad_idannee_acad = ?";
    $paramsSections[] = $anneeId;
}

$sqlSections .= " ORDER BY sec.\"designationSection\"";

$stmtSections = $connexion->prepare($sqlSections);
$stmtSections->execute($paramsSections);
$sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les promotions avec leurs années académiques (filtrées par année et section si sélectionnées)
$sqlPromotions = "
    SELECT p.*, aa.designation as annee_academique
    FROM promotion p
    LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section sec ON o.section_idsection = sec.idsection
    WHERE 1=1
";
$paramsPromotions = [];

if ($anneeId > 0) {
    $sqlPromotions .= " AND p.annee_acad_idannee_acad = ?";
    $paramsPromotions[] = $anneeId;
}

if ($sectionId > 0) {
    $sqlPromotions .= " AND sec.idsection = ?";
    $paramsPromotions[] = $sectionId;
}

$sqlPromotions .= " ORDER BY p.\"designationPromotion\"";

$stmt = $connexion->prepare($sqlPromotions);
$stmt->execute($paramsPromotions);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtres
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// Construire la requête SQL en fonction des filtres
$sql = "
    SELECT e.*, p.\"designationPromotion\", aa.designation as annee_academique, e.\"dateEnregistrement\" as date_inscription
    FROM etudiant e
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
    WHERE 1=1 AND e.est_actif=1
";

$params = [];

if ($anneeId > 0) {
    $sql .= " AND e.annee_acad_idannee_acad = ?";
    $params[] = $anneeId;
}

if ($promotionId > 0) {
    $sql .= " AND e.promotion_idpromotion = ?";
    $params[] = $promotionId;
}

if (!empty($recherche)) {
    $sql .= " AND (e.noms LIKE ? OR e.matricule LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

$sql .= " ORDER BY e.noms";

$stmt = $connexion->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Liste des Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item active">Liste des Étudiants</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistiques rapides -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                    <i class="bi bi-people text-primary fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-muted mb-0">Total Étudiants</p>
                                <h4 class="mb-0"><?= count($etudiants) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-muted mb-0">Promotions</p>
                                <h4 class="mb-0"><?= count($promotions) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                    <i class="bi bi-calendar text-info fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-muted mb-0">Année académique</p>
                                <h6 class="mb-0"><?= htmlspecialchars($anneeActuelle['designation'] ?? 'Non définie') ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                    <i class="bi bi-collection text-warning fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-muted mb-0">Sections</p>
                                <h4 class="mb-0"><?= count($sections) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-funnel me-2"></i>Filtres
                            </h5>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                        
                        <form action="" method="GET" class="row g-3">
                            <input type="hidden" name="view" value="etudiants/liste_etudiants">

                            <div class="col-md-2">
                                <label for="annee" class="form-label">
                                    <i class="bi bi-calendar3 me-1"></i>Année académique
                                </label>
                                <select class="form-select" id="annee" name="annee" onchange="this.form.submit()">
                                    <option value="0">Toutes les années</option>
                                    <?php foreach ($anneesAcademiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $anneeId == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                            <?php if ($anneeActuelle && $annee['idannee_acad'] == $anneeActuelle['idannee_acad']): ?>
                                                (En cours)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="section" class="form-label">
                                    <i class="bi bi-grid me-1"></i>Section
                                </label>
                                <select class="form-select" id="section" name="section" onchange="this.form.submit()">
                                    <option value="0">Toutes les sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section['idsection'] ?>" <?= $sectionId == $section['idsection'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section['designationSection']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="promotion" class="form-label">
                                    <i class="bi bi-bookmark me-1"></i>Promotion
                                </label>
                                <select class="form-select" id="promotion" name="promotion" onchange="this.form.submit()">
                                    <option value="0">Toutes les promotions</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" <?= $promotionId == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="recherche" class="form-label">
                                    <i class="bi bi-search me-1"></i>Recherche
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-person-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="recherche" name="recherche" value="<?= htmlspecialchars($recherche) ?>" placeholder="Nom ou matricule...">
                                </div>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people me-2"></i>Liste des Étudiants
                                <span class="badge bg-primary ms-2"><?= count($etudiants) ?></span>
                            </h5>
                        </div>
                        
                        <?php if (empty($etudiants)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-info-circle fs-4 me-2"></i>
                                <div>
                                    <strong>Aucun étudiant trouvé</strong>
                                    <p class="mb-0">avec les critères spécifiés.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="etudiantsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="bi bi-hash me-1"></i>Matricule</th>
                                            <th><i class="bi bi-person me-1"></i>Nom complet</th>
                                            <th><i class="bi bi-bookmark me-1"></i>Promotion</th>
                                            <th><i class="bi bi-calendar3 me-1"></i>Année académique</th>
                                            <th><i class="bi bi-calendar-plus me-1"></i>Date d'inscription</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $etudiant): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($etudiant['matricule']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle bg-primary bg-opacity-10 text-primary me-2">
                                                            <?= strtoupper(substr($etudiant['noms'], 0, 1)) ?>
                                                        </div>
                                                        <?= htmlspecialchars($etudiant['noms']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= htmlspecialchars($etudiant['designationPromotion'] ?? 'Non définie') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($etudiant['annee_academique'] ?? 'Non définie') ?></td>
                                                <td>
                                                    <i class="bi bi-calendar3 text-muted me-1"></i>
                                                    <?php if (!empty($etudiant['date_inscription'])): ?>
                                                        <?= date('d/m/Y', strtotime($etudiant['date_inscription'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non renseignée</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="?view=enseignement/fiche_scolarite&id=<?= $etudiant['idetudiant'] ?>" 
                                                           class="btn btn-outline-primary" title="Fiche de scolarité">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="showStudentDetails(<?= $etudiant['idetudiant'] ?>)" title="Détails">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <a href="?view=etudiants/carte_etudiant&id=<?= $etudiant['idetudiant'] ?>" 
                                                           class="btn btn-outline-success" title="Carte étudiant">
                                                            <i class="bi bi-person-badge"></i>
                                                        </a>
                                                    </div>
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
        </div>
    </section>
</main>

<style>
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}
.table > tbody > tr > td {
    vertical-align: middle;
}
</style>


<!-- Modal pour afficher les détails d'un étudiant -->
<div class="modal fade" id="studentDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-circle me-2"></i>Détails de l'étudiant
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="studentDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-3 text-muted">Chargement des données...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Fermer
                </button>
                <a href="#" id="viewFullProfileBtn" class="btn btn-primary">
                    <i class="bi bi-file-earmark-text me-1"></i> Voir la fiche complète
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour réinitialiser les filtres
function resetFilters() {
    window.location.href = '?view=etudiants/liste_etudiants';
}

// Fonction pour afficher les détails d'un étudiant dans un modal
function showStudentDetails(idEtudiant) {
    const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
    const contentContainer = document.getElementById('studentDetailsContent');
    const viewFullProfileBtn = document.getElementById('viewFullProfileBtn');
    
    // Mettre à jour le lien vers la fiche complète
    viewFullProfileBtn.href = `?view=enseignement/fiche_scolarite&id=${idEtudiant}`;
    
    // Afficher le modal avec l'indicateur de chargement
    contentContainer.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des données...</p>
        </div>
    `;
    modal.show();
    
    // Charger les détails de l'étudiant via AJAX
    fetch(`controller/get_student_details.php?id=${idEtudiant}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                contentContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        ${data.error}
                    </div>
                `;
                return;
            }
            
            // Afficher les détails de l'étudiant
            contentContainer.innerHTML = `
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        ${data.photo ? 
                            `<img src="${data.photo}" alt="Photo de l'étudiant" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">` : 
                            `<div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                <i class="bi bi-person" style="font-size: 4rem;"></i>
                            </div>`
                        }
                        <h5 class="mt-2">${data.noms}</h5>
                        <p class="text-muted">${data.matricule}</p>
                    </div>
                    <div class="col-md-8">
                        <h6 class="border-bottom pb-2">Informations personnelles</h6>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Date de naissance:</div>
                            <div class="col-sm-8">${data.date_naissance || 'Non renseignée'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Lieu de naissance:</div>
                            <div class="col-sm-8">${data.lieu_naissance || 'Non renseigné'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Sexe:</div>
                            <div class="col-sm-8">${data.sexe || 'Non renseigné'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Adresse:</div>
                            <div class="col-sm-8">${data.adresse || 'Non renseignée'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Téléphone:</div>
                            <div class="col-sm-8">${data.telephone || 'Non renseigné'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Email:</div>
                            <div class="col-sm-8">${data.email || 'Non renseigné'}</div>
                        </div>
                        
                        <h6 class="border-bottom pb-2 mt-4">Informations académiques</h6>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Promotion actuelle:</div>
                            <div class="col-sm-8">${data.promotion || 'Non définie'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Date d'inscription:</div>
                            <div class="col-sm-8">${data.date_inscription || 'Non renseignée'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4 text-muted">Année académique:</div>
                            <div class="col-sm-8">${data.annee_academique || 'Non renseignée'}</div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title">Statistiques académiques</h6>
                                <p class="mb-1"><strong>Crédits obtenus:</strong> ${data.credits_obtenus || '0'}/${data.credits_total || '0'}</p>
                                <p class="mb-1"><strong>UE validées:</strong> ${data.ue_validees || '0'}/${data.ue_total || '0'}</p>
                                <p class="mb-0"><strong>Taux de réussite:</strong> ${data.taux_reussite || '0'}%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-info">
                            <div class="card-body">
                                <h6 class="card-title">Documents</h6>
                                <p class="mb-0"><strong>Nombre de documents:</strong> ${data.nombre_documents || '0'}</p>
                                <p class="mb-0"><small class="text-muted">Consultez la fiche complète pour voir les documents</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error('Erreur:', error);
            contentContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Une erreur est survenue lors du chargement des données.
                </div>
            `;
        });
}
</script>

<?php include "./views/include/footer.php"; ?>
