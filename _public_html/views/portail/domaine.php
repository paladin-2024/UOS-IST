<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer l'ID de l'orientation
$orientationId = isset($_GET['orientation']) ? intval($_GET['orientation']) : 0;

// Récupérer les informations de l'orientation
$query = "SELECT * FROM orientation WHERE idorientation = :orientationId";
$stmt = $db->prepare($query);
$stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
$stmt->execute();
$orientation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orientation) {
    header('Location: index');
    exit;
}

// Récupérer le type de document demandé (si spécifié)
$typeDocument = isset($_GET['type']) ? $_GET['type'] : '';

// Construire la requête SQL de base pour les travaux
$queryTravaux = "SELECT t.*, 
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
WHERE t.orientation_id = :orientationId
AND t.est_public = 1 
AND t.statut = 'Validé'";

$params = [':orientationId' => $orientationId];

// Filtrer par type de document si spécifié
if (!empty($typeDocument)) {
    switch ($typeDocument) {
        case 'these':
            $queryTravaux .= " AND t.type_document = 'Thèse'";
            break;
        case 'memoire':
            $queryTravaux .= " AND (t.type_document = 'Mémoire' OR t.type_document = 'Mémoire Master Complémentaire')";
            break;
        case 'rapport':
            $queryTravaux .= " AND t.type_document = 'Rapport de stage'";
            break;
        case 'article':
            $queryTravaux .= " AND t.type_document = 'Article scientifique'";
            break;
        case 'projet':
            $queryTravaux .= " AND t.type_document = 'Projet tutoré'";
            break;
        case 'livre':
            $queryTravaux .= " AND t.type_document = 'Livre'";
            break;
        case 'cours':
            $queryTravaux .= " AND t.type_document = 'Cours'";
            break;
    }
}

// Ajouter le GROUP BY pour le comptage des consultations
$queryTravaux .= " GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
            t.orientation_id, t.specialisation_id, t.annee_academique_id, 
            t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
            t.date_depot, t.statut, t.est_public, 
            t.\"anneeThese\", t.\"universiteThese\", t.\"faculteThese\", t.\"specialisationThese\",
            o.\"designationOrientation\", s.designation, aa.designation, e.\"nomEnseignant\"";

// Trier par date de dépôt décroissante (plus récent en premier)
$queryTravaux .= " ORDER BY t.date_depot DESC";

// Exécuter la requête
$stmtTravaux = $db->prepare($queryTravaux);
foreach ($params as $key => $value) {
    $stmtTravaux->bindValue($key, $value);
}
$stmtTravaux->execute();
$travaux = $stmtTravaux->fetchAll(PDO::FETCH_ASSOC);

// Ajouter des informations supplémentaires pour chaque travail
foreach ($travaux as &$travail) {
    // Formater la date de dépôt
    $travail['date_depot_formatee'] = date('d/m/Y', strtotime($travail['date_depot']));

    // Ajouter une classe CSS pour le type de document
    switch ($travail['type_document']) {
        case 'Thèse':
            $travail['type_class'] = 'bg-primary';
            break;
        case 'Mémoire':
        case 'Mémoire Master Complémentaire':
            $travail['type_class'] = 'bg-success';
            break;
        case 'Article scientifique':
            $travail['type_class'] = 'bg-danger';
            break;
        case 'Projet tutoré':
            $travail['type_class'] = 'bg-warning';
            break;
        case 'Rapport de stage':
            $travail['type_class'] = 'bg-info';
            break;
        case 'Livre':
            $travail['type_class'] = 'bg-dark';
            break;
        case 'Cours':
            $travail['type_class'] = 'bg-secondary';
            break;
        default:
            $travail['type_class'] = 'bg-secondary';
    }
}

// Récupérer les statistiques de l'orientation
$stats = [
    'total_travaux' => count($travaux),
    'total_theses' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Thèse')),
    'total_memoires' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Mémoire' || $t['type_document'] === 'Mémoire Master Complémentaire')),
    'total_memoires_m2' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Mémoire')),
    'total_memoires_mc' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Mémoire Master Complémentaire')),
    'total_articles' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Article scientifique')),
    'total_projets' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Projet tutoré')),
    'total_rapports' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Rapport de stage')),
    'total_livres' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Livre')),
    'total_cours' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Cours'))
];

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Domaines de recherche</li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($orientation['designationOrientation']) ?></li>
            </ol>
        </nav>
    </div>

    <!-- En-tête de l'orientation -->
    <div class="bg-primary text-white py-4 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2"><?= htmlspecialchars($orientation['designationOrientation']) ?></h1>
                    <p class="lead mb-0">Explorez les travaux scientifiques de cette orientation</p>
                </div>
                <div class="col-md-4">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="h3 mb-0"><?= number_format($stats['total_travaux']) ?></div>
                            <small>Travaux</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 mb-0"><?= number_format(array_sum(array_column($travaux, 'nb_consultations'))) ?></div>
                            <small>Consultations</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filtres et statistiques -->
        <div class="row mb-4">
            <div class="col-md-8">
                <!-- Types de documents -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="?orientation=<?= $orientationId ?>" class="btn btn-outline-primary">
                        Tous (<?= $stats['total_travaux'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=these" class="btn btn-outline-primary">
                        Thèses (<?= $stats['total_theses'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=memoire" class="btn btn-outline-primary">
                        Mémoires (<?= $stats['total_memoires'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=rapport" class="btn btn-outline-primary">
                        Rapports (<?= $stats['total_rapports'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=article" class="btn btn-outline-primary">
                        Articles (<?= $stats['total_articles'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=projet" class="btn btn-outline-primary">
                        Projets tutorés (<?= $stats['total_projets'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=livre" class="btn btn-outline-primary">
                        Livres (<?= $stats['total_livres'] ?>)
                    </a>
                    <a href="?orientation=<?= $orientationId ?>&type=cours" class="btn btn-outline-primary">
                        Cours (<?= $stats['total_cours'] ?>)
                    </a>
                </div>
                
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Rechercher dans cette orientation..." id="searchInput">
                    <button class="btn btn-primary" id="searchButton">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Liste des travaux -->
        <div class="row g-4">
            <?php if (!empty($travaux)): ?>
                <?php foreach ($travaux as $travail): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <!-- En-tête avec type de document -->
                            <div class="card-header <?= $travail['type_class'] ?> text-white">
                                <i class="fas fa-file-alt me-2"></i>
                                <?php 
                                // Afficher le type de document approprié
                                if ($travail['type_document'] === 'Mémoire') {
                                    echo 'Mémoire M2';
                                } elseif ($travail['type_document'] === 'Mémoire Master Complémentaire') {
                                    echo 'Mémoire Master Complémentaire';
                                } else {
                                    echo $travail['type_document'];
                                }
                                ?>
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

                                <?php if ($travail['type_document'] === 'Thèse'): ?>
                                    <!-- Informations spécifiques aux thèses -->
                                    <?php if (!empty($travail['universiteThese']) || 
                                             !empty($travail['faculteThese']) || 
                                             !empty($travail['anneeThese']) || 
                                             !empty($travail['specialisationThese'])): ?>
                                    <div class="these-info mb-3">
                                        <div class="row g-2">
                                            <?php if (!empty($travail['universiteThese'])): ?>
                                            <div class="col-12 d-flex align-items-center mb-1">
                                                <i class="fas fa-university text-primary me-2"></i>
                                                <span class="small"><?= htmlspecialchars($travail['universiteThese']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($travail['faculteThese'])): ?>
                                            <div class="col-12 d-flex align-items-center mb-1">
                                                <i class="fas fa-building text-primary me-2"></i>
                                                <span class="small"><?= htmlspecialchars($travail['faculteThese']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex flex-wrap">
                                                <?php if (!empty($travail['anneeThese'])): ?>
                                                <div class="me-3 d-flex align-items-center mb-1">
                                                    <i class="fas fa-calendar-check text-primary me-1"></i>
                                                    <span class="small"><?= htmlspecialchars($travail['anneeThese']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($travail['specialisationThese'])): ?>
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="fas fa-microscope text-primary me-1"></i>
                                                    <span class="small"><?= htmlspecialchars($travail['specialisationThese']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Spécialisation pour les autres types de documents -->
                                    <div class="small text-muted mb-3">
                                        <i class="fas fa-bookmark me-2"></i>
                                        <?= htmlspecialchars($travail['specialisation']) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Date de dépôt -->
                                <div class="small text-muted mb-3">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?= $travail['date_depot_formatee'] ?>
                                </div>

                                <!-- Résumé court -->
                                <p class="card-text small">
                                <?= htmlspecialchars(substr($travail['resume'], 0, 150)) ?>...
                                </p>

                                <!-- Mots-clés -->
                                <?php if (!empty($travail['mots_cles'])): ?>
                                <div class="mt-2">
                                    <?php foreach (explode(',', $travail['mots_cles']) as $motCle): ?>
                                        <span class="badge bg-light text-dark me-1">
                                            <?= trim(htmlspecialchars($motCle)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
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
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun travail scientifique disponible pour cette orientation<?= !empty($typeDocument) ? ' et ce type de document' : '' ?>.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Recherche dans l'orientation
document.getElementById('searchButton').addEventListener('click', function() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    if (searchTerm) {
        window.location.href = 'recherche_avancee?orientation=<?= $orientationId ?>&titre=' + encodeURIComponent(searchTerm) + '&rechercher=1';
    }
});

// Permettre la recherche avec la touche Entrée
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchButton').click();
    }
});
</script>

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
    background: linear-gradient(135deg, var(--bs-primary) 0%, #0d6efd 100%);
}
</style>

<?php include 'footer.php'; ?>
