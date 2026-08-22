<?php
// Établir la connexion à la base de donnéess
require_once 'config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

// Récupérer les travaux récents (limiter à 3 travaux)
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
WHERE t.est_public = 1 AND t.statut = 'Validé'
GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
    t.orientation_id, t.specialisation_id, t.annee_academique_id, 
    t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
    t.date_depot, t.statut, t.est_public, 
    t.\"anneeThese\", t.\"universiteThese\", t.\"faculteThese\", t.\"specialisationThese\",
    o.\"designationOrientation\", s.designation, aa.designation, e.\"nomEnseignant\"
ORDER BY t.date_depot DESC
LIMIT 3";

$stmtTravaux = $db->prepare($queryTravaux);
$stmtTravaux->execute();
$travauxRecents = $stmtTravaux->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
// Total des travaux
$queryTotalTravaux = "SELECT COUNT(*) as total FROM travaux_scientifiques WHERE statut = 'Validé'";
$stmtTotalTravaux = $db->prepare($queryTotalTravaux);
$stmtTotalTravaux->execute();
$totalTravaux = $stmtTotalTravaux->fetch(PDO::FETCH_ASSOC)['total'];

// Total des chercheurs (auteurs)
$queryTotalAuteurs = "SELECT COUNT(DISTINCT nom_auteur) as total FROM travaux_scientifiques WHERE statut = 'Validé'";
$stmtTotalAuteurs = $db->prepare($queryTotalAuteurs);
$stmtTotalAuteurs->execute();
$totalAuteurs = $stmtTotalAuteurs->fetch(PDO::FETCH_ASSOC)['total'];

// Total des institutions (orientations)
$queryTotalInstitutions = "SELECT COUNT(*) as total FROM orientation";
$stmtTotalInstitutions = $db->prepare($queryTotalInstitutions);
$stmtTotalInstitutions->execute();
$totalInstitutions = $stmtTotalInstitutions->fetch(PDO::FETCH_ASSOC)['total'];

// Total des consultations
$queryTotalConsultations = "SELECT COUNT(*) as total FROM consultations";
$stmtTotalConsultations = $db->prepare($queryTotalConsultations);
$stmtTotalConsultations->execute();
$totalConsultations = $stmtTotalConsultations->fetch(PDO::FETCH_ASSOC)['total'];

$stats = [
    'total_travaux' => $totalTravaux,
    'total_chercheurs' => $totalAuteurs,
    'total_institutions' => $totalInstitutions,
    'total_consultations' => $totalConsultations
];

// Récupérer les orientations pour les catégories
$queryOrientations = "SELECT idorientation, \"designationOrientation\" FROM orientation GROUP BY \"designationOrientation\" ORDER BY \"designationOrientation\"";
$stmtOrientations = $db->prepare($queryOrientations);
$stmtOrientations->execute();
$orientations = $stmtOrientations->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<!-- Search Bar -->
<div class="search-bar">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up" data-aos-duration="800">
                <form>
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" placeholder="Rechercher par titre, auteur, mot-clé...">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-2"></i> Rechercher
                        </button>
                    </div>
                    <div class="mt-3 d-flex flex-wrap justify-content-center">
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkTheses">
                            <label class="form-check-label" for="checkTheses">Thèses</label>
                        </div>
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkMemoires">
                            <label class="form-check-label" for="checkMemoires">Mémoires</label>
                        </div>
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkArticles">
                            <label class="form-check-label" for="checkArticles">Articles</label>
                        </div>
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkProjets">
                            <label class="form-check-label" for="checkProjets">Projets tutorés</label>
                        </div>
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkLivres">
                            <label class="form-check-label" for="checkLivres">Livres</label>
                        </div>
                        <div class="form-check me-3 mb-2">
                            <input class="form-check-input" type="checkbox" id="checkCours">
                            <label class="form-check-label" for="checkCours">Cours</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Featured Section -->
<section class="featured-section">
    <div class="container">
        <h2 class="text-center mb-5" data-aos="fade-up">Travaux récemment ajoutés</h2>
        <div class="row gy-4">
            <?php if (!empty($travauxRecents)): ?>
                <?php foreach ($travauxRecents as $index => $travail): ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="featured-card h-100">
                            <!-- En-tête avec l'initiale et le type de document -->
                            <div class="featured-img-container text-center d-flex align-items-center justify-content-center 
                                <?php 
                                $bgClass = match($travail['type_document']) {
                                    'Thèse' => 'bg-primary',
                                    'Mémoire' => 'bg-success',
                                    'Rapport de stage' => 'bg-info',
                                    'Article scientifique' => 'bg-danger',
                                    'Projet tutoré' => 'bg-warning',
                                    'Livre' => 'bg-book',
                                    'Cours' => 'bg-course',
                                    default => 'bg-secondary'
                                };
                                echo $bgClass;
                                ?> text-white">
                                <div>
                                    <div class="display-4 mb-2">
                                        <?= strtoupper(mb_substr(iconv('UTF-8', 'ASCII//TRANSLIT', $travail['titre']), 0, 1)) ?>
                                    </div>
                                    <div class="small text-uppercase"><?= $travail['type_document'] ?></div>
                                </div>
                            </div>

                            <!-- Corps de la carte -->
                            <div class="card-body">
                                <!-- Type de document -->
                                <span class="badge <?= $bgClass ?> mb-2"><?= $travail['type_document'] ?></span>
                                
                                <h5 class="card-title" style="font-size: 1.1rem;">
                                    <?= htmlspecialchars($travail['titre']) ?>
                                </h5>
                                
                                <!-- Informations sur l'auteur -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user-circle text-muted me-2"></i>
                                    <span class="text-muted">
                                        <?= htmlspecialchars($travail['nom_auteur']) ?>
                                        <span class="badge bg-secondary ms-1"><?= $travail['type_auteur'] ?></span>
                                    </span>
                                </div>
                                
                                <!-- Orientation et date -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-graduation-cap text-muted me-2"></i>
                                    <span class="text-muted small">
                                        <?= htmlspecialchars($travail['designationOrientation']) ?>
                                    </span>
                                    <i class="fas fa-calendar-alt text-muted ms-3 me-2"></i>
                                    <span class="text-muted small">
                                        <?= date('d/m/Y', strtotime($travail['date_depot'])) ?>
                                    </span>
                                </div>
                                
                                <!-- Informations spécifiques aux thèses -->
                                <?php if ($travail['type_document'] === 'Thèse' && 
                                         (!empty($travail['universiteThese']) || 
                                          !empty($travail['faculteThese']) || 
                                          !empty($travail['anneeThese']) || 
                                          !empty($travail['specialisationThese']))): ?>
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
                                
                                <!-- Résumé -->
                                <p class="card-text small">
                                    <?= htmlspecialchars(substr($travail['resume'], 0, 150)) ?>...
                                </p>
                            </div>

                            <!-- Pied de la carte -->
                            <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                                <a href="voir_travail&id=<?= $travail['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> Consulter
                                </a>
                                <div class="text-muted small">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    <?= number_format($travail['nb_consultations'] ?? 0) ?> vues
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Aucun travail scientifique disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="recherche" class="btn btn-outline-primary px-4 py-2">
                <i class="fas fa-search me-2"></i>
                Voir plus de travaux récents
            </a>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories-section">
    <div class="container">
        <h2 class="text-center mb-5" data-aos="fade-up">Explorer par domaine de recherche</h2>
        <div class="row gy-4">
            <?php foreach ($orientations as $index => $orientation): ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                <div class="category-item">
                    <div class="category-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h4><?= htmlspecialchars($orientation['designationOrientation']) ?></h4>
                    <a href="domaine&orientation=<?= $orientation['idorientation'] ?>" class="btn btn-sm btn-primary">Explorer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
    
<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <h2 class="text-center mb-5" data-aos="fade-up">Statistiques de la plateforme</h2>
        <div class="row">
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stats['total_travaux'] ?>">0</div>
                    <div class="stat-label">Travaux scientifiques</div>
                </div>
            </div>
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stats['total_chercheurs'] ?>">0</div>
                    <div class="stat-label">Chercheurs</div>
                </div>
            </div>
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stats['total_institutions'] ?>">0</div>
                    <div class="stat-label">Orientations</div>
                </div>
            </div>
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stats['total_consultations'] ?>">0</div>
                    <div class="stat-label">Consultations</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mobile App Download Section -->
<section class="app-download-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <h2 class="mb-3">Téléchargez notre application mobile</h2>
                <p class="lead mb-4">Accédez à tous les travaux scientifiques depuis votre smartphone Android. Consultez, recherchez et partagez des documents où que vous soyez.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="../uploads/sciencehub.apk" class="btn btn-primary btn-lg d-inline-flex align-items-center">
                        <i class="fab fa-android me-2 fs-4"></i>
                        Télécharger l'APK
                    </a>
                    <div class="d-flex align-items-center ms-3">
                        <div class="qr-code bg-white p-2 rounded">
                            <img src="../uploads/qrcode.jpg" alt="QR Code" width="100" height="100" class="img-fluid">
                        </div>
                        <div class="ms-3">
                            <span class="d-block text-muted">Scanner pour télécharger</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="fas fa-info-circle me-1"></i> Version 1.0.0 | Taille: 15 MB | Android 6.0+
                </div>
            </div>
            <div class="col-lg-6 mt-4 mt-lg-0 text-center" data-aos="fade-left">
                <img src="../uploads/phone2-right.png" alt="Application Mobile" class="img-fluid" style="max-height: 400px;">
            </div>
        </div>
    </div>
</section>
    
<!-- Partners Section -->
<section class="container py-5">
    <h2 class="text-center mb-5" data-aos="fade-up">Nos partenaires académiques</h2>
    <div class="row align-items-center justify-content-center g-4">
        <div class="col-4 col-md-2 text-center" data-aos="fade-up" data-aos-delay="100">
            <img src="../uploads/esu.png" alt="Logo Université" class="img-fluid">
        </div>
        <div class="col-4 col-md-2 text-center" data-aos="fade-up" data-aos-delay="200">
            <img src="../uploads/unikin.png" alt="Logo École" class="img-fluid">
        </div>
        <div class="col-4 col-md-2 text-center" data-aos="fade-up" data-aos-delay="300">
            <img src="../uploads/inbtp.png" alt="Logo Institut" class="img-fluid">
        </div>
        <div class="col-4 col-md-2 text-center" data-aos="fade-up" data-aos-delay="400">
            <img src="../uploads/esu.png" alt="Logo Centre" class="img-fluid">
        </div>
        <div class="col-4 col-md-2 text-center" data-aos="fade-up" data-aos-delay="500">
            <img src="../uploads/unikin.png" alt="Logo Laboratoire" class="img-fluid">
        </div>
    </div>
</section>
    
<?php include 'footer.php'; ?>
