<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les paramètres de recherche et de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeDocument = isset($_GET['type_document']) ? $_GET['type_document'] : '';
$typeAuteur = isset($_GET['type_auteur']) ? $_GET['type_auteur'] : '';
$orientationId = isset($_GET['orientation_id']) ? intval($_GET['orientation_id']) : 0;
$anneeId = isset($_GET['annee_academique_id']) ? intval($_GET['annee_academique_id']) : 0;

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
WHERE t.est_public = 1 AND t.statut = 'Validé'";

$params = [];

// Ajouter les conditions de filtrage
if (!empty($typeDocument)) {
    $query .= " AND t.type_document = :type_document";
    $params[':type_document'] = $typeDocument;
}

if (!empty($typeAuteur)) {
    $query .= " AND t.type_auteur = :type_auteur";
    $params[':type_auteur'] = $typeAuteur;
}

if ($orientationId > 0) {
    $query .= " AND t.orientation_id = :orientation_id";
    $params[':orientation_id'] = $orientationId;
}

if ($anneeId > 0) {
    $query .= " AND t.annee_academique_id = :annee_academique_id";
    $params[':annee_academique_id'] = $anneeId;
}

if (!empty($search)) {
    $query .= " AND (t.titre LIKE :search 
               OR t.mots_cles LIKE :search 
               OR t.resume LIKE :search
               OR t.nom_auteur LIKE :search
               OR o.designationOrientation LIKE :search";
    
    // Ajouter la recherche dans les champs spécifiques aux thèses
    $query .= " OR t.universiteThese LIKE :search
               OR t.faculteThese LIKE :search
               OR t.specialisationThese LIKE :search";
    
    $query .= ")";
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
$travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajouter des informations supplémentaires pour chaque travail
foreach ($travaux as &$travail) {
    // Formater la date de dépôt
    $travail['date_depot_formatee'] = date('d/m/Y', strtotime($travail['date_depot']));

    // Ajouter une classe CSS pour le type de document
    $travail['type_class'] = match($travail['type_document']) {
        'Thèse' => 'bg-primary',
        'Mémoire' => 'bg-success',
        'Rapport de stage' => 'bg-info',
        'Article scientifique' => 'bg-danger',
        'Projet tutoré' => 'bg-warning',
        'Livre' => 'bg-secondary',
        'Cours' => 'bg-dark',
        default => 'bg-secondary'
    };
    
    // Ajouter un indicateur pour savoir si c'est une thèse
    $travail['est_these'] = ($travail['type_document'] === 'Thèse');
}

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

include 'header2.php';
?>

<main class="container py-5">
    <!-- Fil d'Ariane -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index">Accueil</a></li>
            <li class="breadcrumb-item active">Recherche de travaux scientifiques</li>
        </ol>
    </nav>

    <!-- Barre de recherche et filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <!-- Barre de recherche -->
                <div class="col-md-12 mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher par titre, auteur, mots-clés..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="col-md-3">
                    <label class="form-label">Type de document</label>
                    <select name="type_document" class="form-select">
                        <option value="">Tous les types</option>
                        <option value="Mémoire" <?= $typeDocument === 'Mémoire' ? 'selected' : '' ?>>Mémoire</option>
                        <option value="Thèse" <?= $typeDocument === 'Thèse' ? 'selected' : '' ?>>Thèse</option>
                        <option value="Rapport de stage" <?= $typeDocument === 'Rapport de stage' ? 'selected' : '' ?>>Rapport de stage</option>
                        <option value="Article scientifique" <?= $typeDocument === 'Article scientifique' ? 'selected' : '' ?>>Article scientifique</option>
                        <option value="Projet tutoré" <?= $typeDocument === 'Projet tutoré' ? 'selected' : '' ?>>Projet tutoré</option>
                        <option value="Livre" <?= $typeDocument === 'Livre' ? 'selected' : '' ?>>Livre</option>
                        <option value="Cours" <?= $typeDocument === 'Cours' ? 'selected' : '' ?>>Cours</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Type d'auteur</label>
                    <select name="type_auteur" class="form-select">
                        <option value="">Tous les auteurs</option>
                        <option value="Etudiant" <?= $typeAuteur === 'Etudiant' ? 'selected' : '' ?>>Étudiant</option>
                        <option value="Enseignant" <?= $typeAuteur === 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                        <option value="Autre" <?= $typeAuteur === 'Autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Orientation</label>
                    <select name="orientation_id" class="form-select">
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
                    <select name="annee_academique_id" class="form-select">
                        <option value="">Toutes les années</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId === $year['idannee_acad'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Résultats de recherche -->
    <div class="row">
        <?php if (!empty($travaux)): ?>
            <?php foreach ($travaux as $travail): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <!-- En-tête avec type de document -->
                        <div class="card-header <?= $travail['type_class'] ?> text-white">
                            <i class="fas fa-file-alt me-2"></i>
                            <?= $travail['type_document'] ?>
                        </div>

                        <div class="card-body">
                            <!-- Titre -->
                            <h5 class="card-title mb-3">
                                <a href="voir_travail?id=<?= $travail['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($travail['titre']) ?>
                                </a>
                            </h5>

                            <!-- Informations sur l'auteur -->
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-user-circle text-muted me-2"></i>
                                <span>
                                    <?= htmlspecialchars($travail['nom_auteur']) ?>
                                    <span class="badge bg-secondary ms-1"><?= $travail['type_auteur'] ?></span>
                                </span>
                            </div>

                            <!-- Orientation et date -->
                            <div class="small text-muted mb-3">
                                <i class="fas fa-graduation-cap me-2"></i>
                                <?= htmlspecialchars($travail['designationOrientation']) ?>
                                <br>
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?= $travail['date_depot_formatee'] ?>
                            </div>

                            <!-- Résumé court -->
                            <p class="card-text small">
                                <?= htmlspecialchars(substr($travail['resume'], 0, 100)) ?>...
                            </p>
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                            <a href="voir_travail?id=<?= $travail['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> Consulter
                            </a>
                            <div class="text-muted small">
                                <i class="fas fa-chart-bar me-1"></i>
                                <?= number_format($travail['nb_consultations']) ?> vues
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucun travail scientifique ne correspond à vos critères de recherche.
                </div>
            </div>
        <?php endif; ?>
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
</style>

<?php include 'footer.php'; ?>
