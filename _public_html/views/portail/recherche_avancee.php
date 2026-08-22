<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer tous les filtres
$filters = [
    'titre' => isset($_GET['titre']) ? trim($_GET['titre']) : '',
    'auteur' => isset($_GET['auteur']) ? trim($_GET['auteur']) : '',
    'mots_cles' => isset($_GET['mots_cles']) ? trim($_GET['mots_cles']) : '',
    'type_document' => isset($_GET['type_document']) ? $_GET['type_document'] : '',
    'type_auteur' => isset($_GET['type_auteur']) ? $_GET['type_auteur'] : '',
    'orientation_id' => isset($_GET['orientation']) ? intval($_GET['orientation']) : 0,
    'specialisation_id' => isset($_GET['specialisation']) ? intval($_GET['specialisation']) : 0,
    'annee_academique_id' => isset($_GET['annee']) ? intval($_GET['annee']) : 0,
    'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
    'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
    'est_public' => 1,
    'statut' => 'Validé'
];

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

// Spécialisations
$specialisations = [];
if ($filters['orientation_id']) {
    $querySpecialisations = "SELECT * FROM specialisation WHERE idsection = :orientation_id ORDER BY designation";
    $stmtSpecialisations = $db->prepare($querySpecialisations);
    $stmtSpecialisations->bindParam(':orientation_id', $filters['orientation_id'], PDO::PARAM_INT);
    $stmtSpecialisations->execute();
    $specialisations = $stmtSpecialisations->fetchAll(PDO::FETCH_ASSOC);
}

// Effectuer la recherche si des filtres sont appliqués
$resultats = [];
$isSearching = false;

if (isset($_GET['rechercher'])) {
    $isSearching = true;
    
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
    WHERE t.est_public = 1 
    AND t.statut = 'Validé'";

    $params = [];

    // Ajouter les conditions de filtrage
    if (!empty($filters['titre'])) {
        $query .= " AND t.titre LIKE :titre";
        $params[':titre'] = '%' . $filters['titre'] . '%';
    }

    if (!empty($filters['auteur'])) {
        $query .= " AND t.nom_auteur LIKE :auteur";
        $params[':auteur'] = '%' . $filters['auteur'] . '%';
    }

    if (!empty($filters['mots_cles'])) {
        $query .= " AND t.mots_cles LIKE :mots_cles";
        $params[':mots_cles'] = '%' . $filters['mots_cles'] . '%';
    }

    if (!empty($filters['type_document'])) {
        $query .= " AND t.type_document = :type_document";
        $params[':type_document'] = $filters['type_document'];
    }

    if (!empty($filters['type_auteur'])) {
        $query .= " AND t.type_auteur = :type_auteur";
        $params[':type_auteur'] = $filters['type_auteur'];
    }

    if (!empty($filters['orientation_id'])) {
        $query .= " AND t.orientation_id = :orientation_id";
        $params[':orientation_id'] = $filters['orientation_id'];
    }

    if (!empty($filters['specialisation_id'])) {
        $query .= " AND t.specialisation_id = :specialisation_id";
        $params[':specialisation_id'] = $filters['specialisation_id'];
    }

    if (!empty($filters['annee_academique_id'])) {
        $query .= " AND t.annee_academique_id = :annee_academique_id";
        $params[':annee_academique_id'] = $filters['annee_academique_id'];
    }

    if (!empty($filters['date_debut'])) {
        $query .= " AND t.date_depot >= :date_debut";
        $params[':date_debut'] = $filters['date_debut'] . ' 00:00:00';
    }

    if (!empty($filters['date_fin'])) {
        $query .= " AND t.date_depot <= :date_fin";
        $params[':date_fin'] = $filters['date_fin'] . ' 23:59:59';
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
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter des informations supplémentaires pour chaque travail
    foreach ($resultats as &$travail) {
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
}

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Recherche avancée</li>
            </ol>
        </nav>
    </div>

    <div class="container">
        <div class="row">
            <!-- Formulaire de recherche -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-search me-2"></i>
                            Critères de recherche
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" id="searchForm">
                            <!-- Titre -->
                            <div class="mb-3">
                                <label class="form-label">Titre</label>
                                <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($filters['titre']) ?>" placeholder="Mots dans le titre...">
                            </div>

                            <!-- Auteur -->
                            <div class="mb-3">
                                <label class="form-label">Auteur</label>
                                <input type="text" name="auteur" class="form-control" value="<?= htmlspecialchars($filters['auteur']) ?>" placeholder="Nom de l'auteur...">
                            </div>

                            <!-- Mots-clés -->
                            <div class="mb-3">
                                <label class="form-label">Mots-clés</label>
                                <input type="text" name="mots_cles" class="form-control" value="<?= htmlspecialchars($filters['mots_cles']) ?>" placeholder="Mots-clés séparés par des virgules...">
                            </div>

                            <!-- Type de document -->
                            <div class="mb-3">
                                <label class="form-label">Type de document</label>
                                <select name="type_document" class="form-select">
                                    <option value="">Tous les types</option>
                                    <option value="Thèse" <?= $filters['type_document'] === 'Thèse' ? 'selected' : '' ?>>Thèse</option>
                                    <option value="Mémoire" <?= $filters['type_document'] === 'Mémoire' ? 'selected' : '' ?>>Mémoire</option>
                                    <option value="Mémoire Master Complémentaire" <?= $filters['type_document'] === 'Mémoire Master Complémentaire' ? 'selected' : '' ?>>Mémoire Master Complémentaire</option>
                                    <option value="Article scientifique" <?= $filters['type_document'] === 'Article scientifique' ? 'selected' : '' ?>>Article scientifique</option>
                                    <option value="Projet tutoré" <?= $filters['type_document'] === 'Projet tutoré' ? 'selected' : '' ?>>Projet tutoré</option>
                                    <option value="Rapport de stage" <?= $filters['type_document'] === 'Rapport de stage' ? 'selected' : '' ?>>Rapport de stage</option>
                                    <option value="Livre" <?= $filters['type_document'] === 'Livre' ? 'selected' : '' ?>>Livre</option>
                                    <option value="Cours" <?= $filters['type_document'] === 'Cours' ? 'selected' : '' ?>>Cours</option>
                                </select>
                            </div>

                            <!-- Type d'auteur -->
                            <div class="mb-3">
                                <label class="form-label">Type d'auteur</label>
                                <select name="type_auteur" class="form-select">
                                    <option value="">Tous les types</option>
                                    <option value="Etudiant" <?= $filters['type_auteur'] === 'Etudiant' ? 'selected' : '' ?>>Étudiant</option>
                                    <option value="Enseignant" <?= $filters['type_auteur'] === 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                                    <option value="Autre" <?= $filters['type_auteur'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                                </select>
                            </div>

                            <!-- Orientation -->
                            <div class="mb-3">
                                <label class="form-label">Orientation</label>
                                <select name="orientation" class="form-select" id="orientation">
                                    <option value="">Toutes les orientations</option>
                                    <?php foreach ($orientations as $orientation): ?>
                                        <option value="<?= $orientation['idorientation'] ?>" <?= $filters['orientation_id'] === $orientation['idorientation'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($orientation['designationOrientation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Spécialisation -->
                            <div class="mb-3">
                                <label class="form-label">Spécialisation</label>
                                <select name="specialisation" class="form-select" id="specialisation">
                                    <option value="">Toutes les spécialisations</option>
                                    <?php foreach ($specialisations as $spec): ?>
                                        <option value="<?= $spec['idSpecialisation'] ?>" <?= $filters['specialisation_id'] === $spec['idSpecialisation'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($spec['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Année académique -->
                            <div class="mb-3">
                                <label class="form-label">Année académique</label>
                                <select name="annee" class="form-select">
                                    <option value="">Toutes les années</option>
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $filters['annee_academique_id'] === $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Période de dépôt -->
                            <div class="mb-3">
                                <label class="form-label">Période de dépôt</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="date" name="date_debut" class="form-control" value="<?= $filters['date_debut'] ?>" placeholder="Date début">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" name="date_fin" class="form-control" value="<?= $filters['date_fin'] ?>" placeholder="Date fin">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="rechercher" value="1" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i> Rechercher
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-redo me-2"></i> Réinitialiser
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Résultats de recherche -->
            <div class="col-lg-8">
                <?php if ($isSearching): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h4 mb-0">
                            <i class="fas fa-list me-2"></i>
                            Résultats de la recherche
                            <span class="badge bg-primary"><?= count($resultats) ?></span>
                        </h2>
                        <?php if (count($resultats) > 0): ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportResults('pdf')">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="exportResults('excel')">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($resultats) > 0): ?>
                        <div class="row g-4">
                            <?php foreach ($resultats as $travail): ?>
                                <div class="col-md-6">
                                    <div class="card h-100 shadow-sm">
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

                                            <!-- Auteur -->
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-user text-muted me-2"></i>
                                                <span>
                                                    <?= htmlspecialchars($travail['nom_auteur']) ?>
                                                    <span class="badge bg-secondary ms-1"><?= $travail['type_auteur'] ?></span>
                                                </span>
                                            </div>

                                            <!-- Orientation et année -->
                                            <div class="small text-muted mb-3">
                                                <i class="fas fa-graduation-cap me-2"></i>
                                                <?= htmlspecialchars($travail['designationOrientation']) ?>
                                                <?php if (!empty($travail['specialisation'])): ?>
                                                    - <?= htmlspecialchars($travail['specialisation']) ?>
                                                <?php endif; ?>
                                                <br>
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                <?= $travail['annee'] ?> (<?= $travail['date_depot_formatee'] ?>)
                                            </div>

                                            <!-- Informations spécifiques aux thèses -->
                                            <?php if ($travail['type_document'] === 'Thèse' && 
                                                    (!empty($travail['universiteThese']) || 
                                                    !empty($travail['faculteThese']) || 
                                                    !empty($travail['anneeThese']))): ?>
                                            <div class="these-info mt-2 mb-3 p-2 bg-light rounded">
                                                <div class="small fw-bold mb-1">Informations sur la thèse :</div>
                                                <div class="row g-2">
                                                    <?php if (!empty($travail['universiteThese'])): ?>
                                                    <div class="col-12">
                                                        <i class="fas fa-university text-primary me-1"></i>
                                                        <span class="small"><?= htmlspecialchars($travail['universiteThese']) ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($travail['faculteThese'])): ?>
                                                    <div class="col-12">
                                                        <i class="fas fa-building text-primary me-1"></i>
                                                        <span class="small"><?= htmlspecialchars($travail['faculteThese']) ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="col-6">
                                                        <?php if (!empty($travail['anneeThese'])): ?>
                                                        <i class="fas fa-calendar-check text-primary me-1"></i>
                                                        <span class="small"><?= htmlspecialchars($travail['anneeThese']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="col-6">
                                                        <?php if (!empty($travail['specialisationThese'])): ?>
                                                        <i class="fas fa-microscope text-primary me-1"></i>
                                                        <span class="small"><?= htmlspecialchars($travail['specialisationThese']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

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
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun résultat ne correspond à vos critères de recherche.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h3 class="h4 mb-3">Recherche avancée</h3>
                            <p class="text-muted">
                                Utilisez les filtres à gauche pour effectuer une recherche précise dans notre bibliothèque numérique.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// Fonction pour réinitialiser le formulaire
function resetForm() {
    document.getElementById('searchForm').reset();
    // Rediriger vers la page sans paramètres
    window.location.href = 'recherche_avancee';
}

// Fonction pour charger les spécialisations en fonction de l'orientation
document.getElementById('orientation').addEventListener('change', function() {
    const orientationId = this.value;
    const specialisationSelect = document.getElementById('specialisation');
    
    // Réinitialiser les options
    specialisationSelect.innerHTML = '<option value="">Toutes les spécialisations</option>';
    
    if (orientationId) {
        // Charger les spécialisations via AJAX
        fetch(`controller/get_specialisations.php?orientation_id=${orientationId}`)
            .then(response => response.json())
            .then(data => {
                data.forEach(spec => {
                    const option = document.createElement('option');
                    option.value = spec.idSpecialisation;
                    option.textContent = spec.designation;
                    specialisationSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Erreur:', error));
    }
});

// Fonction pour exporter les résultats
function exportResults(format) {
    // Récupérer tous les paramètres actuels
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.append('export', format);
    
    // Rediriger vers la page d'export
    window.location.href = 'controller/export_resultats.php?' + urlParams.toString();
}
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
</style>

<?php include 'footer.php'; ?>

