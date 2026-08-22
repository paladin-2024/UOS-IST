<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les filtres
$orientationId = isset($_GET['orientation']) ? intval($_GET['orientation']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construire la requête SQL de base
$query = "SELECT t.*, 
    o.\"designationOrientation\", 
    s.designation as specialisation,
    aa.designation as annee,
    e.\"nomEnseignant\" as directeur,
    COUNT(c.id) as nb_consultations
FROM travaux_scientifiques t
LEFT JOIN orientation o ON t.orientation_id = o.idorientation
LEFT JOIN specialisation s ON t.specialisation_id = s.\"idSpecialisation\"
LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
LEFT JOIN enseignant e ON t.directeur_id = e.idenseignant
LEFT JOIN consultations c ON t.id = c.travail_id
WHERE t.type_document = 'Thèse'
AND t.est_public = 1 
AND t.statut = 'Validé'";

$params = [];

// Ajouter les conditions de filtrage
if ($orientationId > 0) {
    $query .= " AND t.orientation_id = :orientation_id";
    $params[':orientation_id'] = $orientationId;
}

if ($anneeId > 0) {
    $query .= " AND t.annee_academique_id = :annee_id";
    $params[':annee_id'] = $anneeId;
}

if (!empty($search)) {
    $query .= " AND (t.titre LIKE :search 
               OR t.mots_cles LIKE :search 
               OR t.resume LIKE :search
               OR t.nom_auteur LIKE :search
               OR o.designationOrientation LIKE :search
               OR t.universiteThese LIKE :search
               OR t.faculteThese LIKE :search
               OR t.specialisationThese LIKE :search)";
    $params[':search'] = "%$search%";
}

// Ajouter le GROUP BY pour le comptage des consultations
$query .= " GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
            t.orientation_id, t.specialisation_id, t.annee_academique_id, 
            t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
            t.date_depot, t.statut, t.est_public, 
            t.\"anneeThese\", t.\"universiteThese\", t.\"faculteThese\", t.\"specialisationThese\",
            o.\"designationOrientation\", s.designation, aa.designation, e.\"nomEnseignant\"";

// Trier par date de dépôt décroissante (plus récent en premier)
$query .= " ORDER BY t.date_depot DESC";

// Exécuter la requête
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données pour les filtres
// Orientations
$queryOrientations = "SELECT * FROM orientation ORDER BY \"designationOrientation\"";
$stmtOrientations = $db->prepare($queryOrientations);
$stmtOrientations->execute();
$orientations = $stmtOrientations->fetchAll(PDO::FETCH_ASSOC);

// Années académiques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $db->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$stats = [
    'total_theses' => count($theses),
    'total_chercheurs' => count(array_unique(array_column($theses, 'nom_auteur'))),
    'total_consultations' => array_sum(array_column($theses, 'nb_consultations')),
    'total_orientations' => count(array_unique(array_column($theses, 'orientation_id')))
];

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Thèses de doctorat</li>
            </ol>
        </nav>
    </div>

    <!-- En-tête de la page -->
    <div class="bg-primary text-white py-4 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">Thèses de doctorat</h1>
                    <p class="lead mb-0">Découvrez les travaux de recherche doctorale</p>
                </div>
                <div class="col-md-4">
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_theses']) ?></div>
                                <small>Thèses</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_consultations']) ?></div>
                                <small>Consultations</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filtres de recherche -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" placeholder="Titre, auteur, mots-clés..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Orientation</label>
                        <select name="orientation" class="form-select">
                            <option value="">Toutes les orientations</option>
                            <?php foreach ($orientations as $orientation): ?>
                                <option value="<?= $orientation['idorientation'] ?>" <?= $orientationId === $orientation['idorientation'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($orientation['designationOrientation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Année académique</label>
                        <select name="annee" class="form-select">
                            <option value="">Toutes les années</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId === $year['idannee_acad'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des thèses -->
        <div class="row g-4">
            <?php if (!empty($theses)): ?>
                <?php foreach ($theses as $these): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-scroll me-2"></i>
                                Thèse de doctorat
                            </div>

                            <div class="card-body">
                                <!-- Titre -->
                                <h5 class="card-title mb-3">
                                    <a href="voir_travail?id=<?= $these['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($these['titre']) ?>
                                    </a>
                                </h5>

                                <!-- Auteur -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user-graduate text-muted me-2"></i>
                                    <span>
                                        Dr. <?= htmlspecialchars($these['nom_auteur']) ?>
                                    </span>
                                </div>

                                <!-- Directeur -->
                                <?php if (!empty($these['directeur'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-chalkboard-teacher text-muted me-2"></i>
                                    <span class="small">
                                        Dir. <?= htmlspecialchars($these['directeur']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Informations spécifiques aux thèses -->
                                <div class="these-details mt-2 mb-3">
                                    <?php if (!empty($these['universiteThese'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-university text-primary me-2"></i>
                                        <span class="small">
                                            <?= htmlspecialchars($these['universiteThese']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($these['faculteThese'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-building text-primary me-2"></i>
                                        <span class="small">
                                            <?= htmlspecialchars($these['faculteThese']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap">
                                        <?php if (!empty($these['anneeThese'])): ?>
                                        <div class="me-3 d-flex align-items-center mb-2">
                                            <i class="fas fa-calendar-check text-primary me-1"></i>
                                            <span class="small"><?= htmlspecialchars($these['anneeThese']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($these['specialisationThese'])): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-microscope text-primary me-1"></i>
                                            <span class="small"><?= htmlspecialchars($these['specialisationThese']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Orientation et année académique -->
                                <div class="small text-muted mb-3">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    <?= htmlspecialchars($these['designationOrientation']) ?>
                                    <br>
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?= $these['annee'] ?>
                                </div>

                                <!-- Résumé -->
                                <p class="card-text small">
                                    <?= htmlspecialchars(substr($these['resume'], 0, 100)) ?>...
                                </p>
                            </div>

                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <a href="voir_travail?id=<?= $these['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> Consulter
                                </a>
                                <div class="text-muted small">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    <?= number_format($these['nb_consultations']) ?> vues
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucune thèse ne correspond à vos critères de recherche.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
}

.card-title {
    font-size: 1.1rem;
    line-height: 1.4;
    height: 3.1em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
}

.bg-primary {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #0056b3 100%);
}
</style>

<?php include 'footer.php'; ?>
