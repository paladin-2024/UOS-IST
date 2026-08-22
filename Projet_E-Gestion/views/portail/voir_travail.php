<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

$travailId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier si le travail existe et est public
$queryTravail = "SELECT t.*,
    o.designationOrientation,
    s.designation as specialisation,
    aa.designation as annee,
    e.nomEnseignant as directeur
FROM travaux_scientifiques t
LEFT JOIN orientation o ON t.orientation_id = o.idorientation
LEFT JOIN specialisation s ON t.specialisation_id = s.idSpecialisation
LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
LEFT JOIN enseignant e ON t.directeur_id = e.idenseignant
WHERE t.id = :id";

$stmtTravail = $db->prepare($queryTravail);
$stmtTravail->bindParam(':id', $travailId, PDO::PARAM_INT);
$stmtTravail->execute();
$travail = $stmtTravail->fetch(PDO::FETCH_ASSOC);

if ($travail && $travail['est_public'] == 1 && $travail['statut'] == 'Validé') {
    // Enregistrer la consultation
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Modifier la requête pour n'inclure que les colonnes existantes
    $queryAddConsultation = "INSERT INTO consultations (travail_id, ip_address) 
                            VALUES (:travail_id, :ip_address)";
    $stmtAddConsultation = $db->prepare($queryAddConsultation);
    $stmtAddConsultation->bindParam(':travail_id', $travailId, PDO::PARAM_INT);
    $stmtAddConsultation->bindParam(':ip_address', $ip, PDO::PARAM_STR);
    $stmtAddConsultation->execute();
} else {
    // Rediriger vers la page d'accueil si le travail n'existe pas ou n'est pas public
    header('Location: index');
    exit;
}

// Récupérer les statistiques de consultation
$queryStats = "SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT ip_address) as unique_visitors,
    MIN(date_consultation) as first_view,
    MAX(date_consultation) as last_view
FROM consultations 
WHERE travail_id = :travail_id";

$stmtStats = $db->prepare($queryStats);
$stmtStats->bindParam(':travail_id', $travailId, PDO::PARAM_INT);
$stmtStats->execute();
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Récupérer les travaux similaires
$queryTravaux = "SELECT t.*, 
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
WHERE t.orientation_id = :orientation_id
AND t.id != :exclude_id
AND t.est_public = 1
AND t.statut = 'Validé'
GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
    t.orientation_id, t.specialisation_id, t.annee_academique_id, 
    t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
    t.date_depot, t.statut, t.est_public, 
    t.anneeThese, t.universiteThese, t.faculteThese, t.specialisationThese,
    o.designationOrientation, s.designation, aa.designation, e.nomEnseignant
ORDER BY t.date_depot DESC
LIMIT 3";

$stmtTravaux = $db->prepare($queryTravaux);
$stmtTravaux->bindParam(':orientation_id', $travail['orientation_id'], PDO::PARAM_INT);
$stmtTravaux->bindParam(':exclude_id', $travailId, PDO::PARAM_INT);
$stmtTravaux->execute();
$travauxSimilaires = $stmtTravaux->fetchAll(PDO::FETCH_ASSOC);

include 'header2.php';
?>


<main class="container py-5">
    <!-- Fil d'Ariane -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index">Accueil</a></li>
            <li class="breadcrumb-item"><a href="recherche">Travaux scientifiques</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($travail['titre']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Contenu principal -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <!-- Type de document et date -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary"><?= $travail['type_document'] ?></span>
                        <small class="text-muted">
                            Publié le <?= date('d/m/Y', strtotime($travail['date_depot'])) ?>
                        </small>
                    </div>

                    <!-- Titre -->
                    <h1 class="h2 mb-4"><?= htmlspecialchars($travail['titre']) ?></h1>

                    <!-- Informations sur l'auteur -->
                    <div class="d-flex align-items-center mb-4">
                        <div class="border rounded-circle p-2 me-3">
                            <i class="fas fa-user-circle fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($travail['nom_auteur']) ?></h6>
                            <p class="text-muted mb-0">
                                <span class="badge bg-secondary"><?= $travail['type_auteur'] ?></span>
                                <?= htmlspecialchars($travail['designationOrientation']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Résumé -->
                    <div class="mb-4">
                        <h5>Résumé</h5>
                        <p class="text-justify"><?= nl2br(htmlspecialchars($travail['resume'])) ?></p>
                    </div>

                    <!-- Mots-clés -->
                    <div class="mb-4">
                        <h5>Mots-clés</h5>
                        <div>
                            <?php foreach (explode(',', $travail['mots_cles']) as $motCle): ?>
                                <span class="badge bg-light text-dark me-2"><?= trim(htmlspecialchars($motCle)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bouton de téléchargement -->
                    <div class="text-center">
                        <a href="../<?= htmlspecialchars($travail['fichier_path']) ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-download me-2"></i>
                            Télécharger le document
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barre latérale -->
        <div class="col-lg-4">
            <!-- Statistiques -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Statistiques</h5>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h3 mb-0"><?= number_format($stats['total']) ?></div>
                            <small class="text-muted">Consultations</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h3 mb-0"><?= number_format($stats['unique_visitors']) ?></div>
                            <small class="text-muted">Visiteurs uniques</small>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <p class="mb-1">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Première consultation: <?= date('d/m/Y', strtotime($stats['first_view'])) ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Dernière consultation: <?= date('d/m/Y', strtotime($stats['last_view'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Travaux similaires -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Travaux similaires</h5>
                    <?php if (!empty($travauxSimilaires)): ?>
                        <?php foreach ($travauxSimilaires as $travailSimilaire): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="voir_travail?id=<?= $travailSimilaire['id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($travailSimilaire['titre']) ?>
                                        </a>
                                    </h6>
                                    <p class="card-text small text-muted mb-0">
                                        Par <?= htmlspecialchars($travailSimilaire['nom_auteur']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aucun travail similaire trouvé.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.text-justify {
    text-align: justify;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
}

.badge {
    padding: 0.5em 1em;
}
</style>

<?php include 'footer.php'; ?>
