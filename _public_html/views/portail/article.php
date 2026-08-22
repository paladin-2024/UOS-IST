<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les filtres
$orientationId = isset($_GET['orientation']) ? intval($_GET['orientation']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construire la requête SQL de base
$query = "SELECT t.*, 
    o.designationOrientation, 
    s.designation as specialisation,
    aa.designation as annee,
    e.nomEnseignant as directeur,
    COUNT(c.id) as nb_consultations
FROM travaux_scientifiques t
LEFT JOIN orientation o ON t.orientation_id = o.idorientation
LEFT JOIN specialisation s ON t.specialisation_id = s.idSpecialisation
LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
LEFT JOIN enseignant e ON t.directeur_id = e.idenseignant
LEFT JOIN consultations c ON t.id = c.travail_id
WHERE t.type_document = 'Article scientifique'
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
               OR o.designationOrientation LIKE :search)";
    $params[':search'] = "%$search%";
}

// Ajouter le GROUP BY pour le comptage des consultations
$query .= " GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
            t.orientation_id, t.specialisation_id, t.annee_academique_id, 
            t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
            t.date_depot, t.statut, t.est_public, 
            t.anneeThese, t.universiteThese, t.faculteThese, t.specialisationThese,
            o.designationOrientation, s.designation, aa.designation, e.nomEnseignant";

// Trier par date de dépôt décroissante (plus récent en premier)
$query .= " ORDER BY t.date_depot DESC";

// Exécuter la requête
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données pour les filtres
// Orientations
$queryOrientations = "SELECT * FROM orientation ORDER BY designationOrientation";
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
    'total_articles' => count($articles),
    'total_auteurs' => count(array_unique(array_column($articles, 'nom_auteur'))),
    'total_consultations' => array_sum(array_column($articles, 'nb_consultations')),
    'total_orientations' => count(array_unique(array_column($articles, 'orientation_id')))
];

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Articles scientifiques</li>
            </ol>
        </nav>
    </div>

    <!-- En-tête de la page -->
    <div class="bg-danger text-white py-4 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">Articles scientifiques</h1>
                    <p class="lead mb-0">Publications et contributions scientifiques</p>
                </div>
                <div class="col-md-4">
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_articles']) ?></div>
                                <small>Articles</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_auteurs']) ?></div>
                                <small>Auteurs</small>
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
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-search me-2"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des articles -->
        <div class="row g-4">
            <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-newspaper me-2"></i>
                                Article scientifique
                            </div>

                            <div class="card-body">
                                <!-- Titre -->
                                <h5 class="card-title mb-3">
                                    <a href="voir_travail?id=<?= $article['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($article['titre']) ?>
                                    </a>
                                </h5>

                                <!-- Auteur(s) -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-users text-muted me-2"></i>
                                    <span>
                                        <?= htmlspecialchars($article['nom_auteur']) ?>
                                    </span>
                                </div>

                                <!-- Spécialisation -->
                                <?php if (!empty($article['specialisation'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-microscope text-muted me-2"></i>
                                    <span class="small">
                                        <?= htmlspecialchars($article['specialisation']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <!-- Orientation et année -->
                                <div class="small text-muted mb-3">
                                    <i class="fas fa-university me-2"></i>
                                    <?= htmlspecialchars($article['designationOrientation']) ?>
                                    <br>
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?= $article['annee'] ?>
                                </div>

                                <!-- Résumé -->
                                <p class="card-text small">
                                    <?= htmlspecialchars(substr($article['resume'], 0, 100)) ?>...
                                </p>

                                <!-- Mots-clés -->
                                <?php if (!empty($article['mots_cles'])): ?>
                                <div class="mt-2">
                                    <?php foreach (explode(',', $article['mots_cles']) as $motCle): ?>
                                        <span class="badge bg-light text-dark me-1">
                                            <?= trim(htmlspecialchars($motCle)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <a href="voir_travail?id=<?= $article['id'] ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-eye me-1"></i> Consulter
                                </a>
                                <div class="text-muted small">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    <?= number_format($article['nb_consultations']) ?> vues
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun article ne correspond à vos critères de recherche.
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

.bg-danger {
    background: linear-gradient(135deg, var(--bs-danger) 0%, #dc3545 100%);
}
</style>

<?php include 'footer.php'; ?>
