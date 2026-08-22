<?php 
include "include/head.php";

// Récupération des données de la formation
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails de la formation
$stmt = $db->prepare("SELECT f.*, u.full_name as author_name, u.id as author_id, c.name as category_name
                   FROM formations f 
                   LEFT JOIN users u ON f.created_by = u.id 
                   LEFT JOIN categories c ON f.category_id = c.id
                   WHERE f.slug = :slug AND f.is_published = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$formation = $stmt->fetch(PDO::FETCH_ASSOC);

// Si la formation n'existe pas ou n'est pas publiée, rediriger
if (!$formation) {
    header('Location: /');
    exit;
}

// Récupérer les modules de la formation
$modulesStmt = $db->prepare("SELECT * FROM formation_modules 
                           WHERE formation_id = :formation_id 
                           ORDER BY order_index ASC, id ASC");
$modulesStmt->bindParam(':formation_id', $formation['id']);
$modulesStmt->execute();
$modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les formations populaires (dans la même catégorie si possible)
$relatedStmt = $db->prepare("SELECT f.id, f.title, f.slug, f.level, f.featured_image 
                           FROM formations f 
                           WHERE f.is_published = 1 AND f.id != :formation_id 
                           AND (f.category_id = :category_id OR f.level = :level)
                           ORDER BY f.is_featured DESC, f.published_at DESC LIMIT 3");
$relatedStmt->bindParam(':formation_id', $formation['id']);
$relatedStmt->bindParam(':category_id', $formation['category_id']);
$relatedStmt->bindParam(':level', $formation['level']);
$relatedStmt->execute();
$relatedFormations = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ressources attachées à la formation via formation_media
$resourcesStmt = $db->prepare("SELECT m.* 
                             FROM media m 
                             JOIN formation_media fm ON m.id = fm.media_id 
                             WHERE fm.formation_id = :formation_id 
                             ORDER BY fm.order_index, m.title");
$resourcesStmt->bindParam(':formation_id', $formation['id']);
$resourcesStmt->execute();
$resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);

// Mise en forme de la date
if ($formation['published_at']) {
    $publishedDate = new DateTime($formation['published_at']);
    $formattedDate = $publishedDate->format('d F Y');
    // Traduire le mois en français
    $months = [
        'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
        'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
        'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
        'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
    ];
    foreach ($months as $en => $fr) {
        $formattedDate = str_replace($en, $fr, $formattedDate);
    }
} else {
    $formattedDate = "Non publiée";
}

// Niveau de formation traduit en français
$niveaux = [
    'licence' => 'Licence', 
    'master' => 'Master', 
    'doctorat' => 'Doctorat', 
    'formation_continue' => 'Formation continue'
];
$niveauFormatted = isset($niveaux[$formation['level']]) ? $niveaux[$formation['level']] : $formation['level'];

// Calculer le temps de lecture estimé
$wordCount = str_word_count(strip_tags($formation['content']));
$readingTime = max(1, ceil($wordCount / 200)); // 200 mots par minute en moyenne
?>

<!-- En-tête de la page avec background et titre -->
<section class="formation-hero py-5" style="position: relative; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto text-center text-white">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb justify-content-center bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="/formations" class="text-white opacity-75">Formations</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($formation['title']); ?></li>
                    </ol>
                </nav>
                
                <div class="badge bg-warning text-dark mb-3 py-2 px-3"><?php echo htmlspecialchars($niveauFormatted); ?></div>
                
                <h1 class="formation-title display-4 fw-bold mb-3"><?php echo htmlspecialchars($formation['title']); ?></h1>
                
                <?php if (!empty($formation['short_description'])): ?>
                <div class="formation-excerpt fw-light">
                    <p class="lead fs-4 mb-0"><?php echo htmlspecialchars($formation['short_description']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="formation-meta d-flex flex-wrap justify-content-center align-items-center mt-4">
                    <?php if (!empty($formation['duration'])): ?>
                    <span class="text-white opacity-75 me-3 mb-2"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($formation['duration']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($formation['credits'])): ?>
                    <span class="text-white opacity-75 me-3 mb-2"><i class="fas fa-award me-1"></i> <?php echo htmlspecialchars($formation['credits']); ?> crédits</span>
                    <?php endif; ?>
                    <?php if (!empty($formation['category_name'])): ?>
                    <span class="text-white opacity-75 me-3 mb-2"><i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($formation['category_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($formation['published_at']): ?>
                    <span class="text-white opacity-75 mb-2"><i class="far fa-calendar-alt me-1"></i> Mise à jour: <?php echo $formattedDate; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wave-bottom">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 150">
            <path fill="#ffffff" fill-opacity="1" d="M0,96L60,106.7C120,117,240,139,360,138.7C480,139,600,117,720,101.3C840,85,960,75,1080,74.7C1200,75,1320,85,1380,90.7L1440,96L1440,150L1380,150C1320,150,1200,150,1080,150C960,150,840,150,720,150C600,150,480,150,360,150C240,150,120,150,60,150L0,150Z"></path>
        </svg>
    </div>
</section>

<!-- Contenu principal avec sidebar -->
<section class="formation-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu formation -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Image principale de la formation -->
                <?php if ($formation['featured_image']): ?>
                <div class="formation-featured-image mb-5 rounded shadow-sm overflow-hidden">
                    <img src=".<?php echo htmlspecialchars($formation['featured_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <!-- Table des matières -->
                <div class="formation-toc card mb-4 d-lg-none">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Table des matières</h5>
                        <button class="btn btn-sm btn-link" type="button" data-toggle="collapse" data-target="#tableOfContents">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="tableOfContents" class="collapse">
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush toc-list">
                                <!-- Généré dynamiquement par JavaScript -->
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Introduction rapide -->
                <div class="formation-highlights card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="text-center p-3">
                                    <div class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                    </div>
                                    <h5 class="mb-1">Niveau</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($niveauFormatted); ?></p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="text-center p-3">
                                    <div class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-clock fa-2x text-primary"></i>
                                    </div>
                                    <h5 class="mb-1">Durée</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($formation['duration'] ?: 'Non spécifiée'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3">
                                    <div class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-award fa-2x text-primary"></i>
                                    </div>
                                    <h5 class="mb-1">Crédits</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($formation['credits'] ?: 'Non spécifiés'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modules de formation -->
                <?php if (!empty($modules)): ?>
                <div class="formation-modules mb-5">
                    <h3 class="fw-bold mb-4 border-bottom pb-2">Programme de la formation</h3>
                    <div class="accordion" id="formationModules">
                        <?php foreach ($modules as $index => $module): ?>
                        <div class="accordion-item mb-3 border rounded shadow-sm">
                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-toggle="collapse" data-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                    <span class="fw-bold"><?php echo htmlspecialchars($module['title']); ?></span>
                                    <?php if (!empty($module['credits'])): ?>
                                    <span class="badge bg-primary ms-auto"><?php echo htmlspecialchars($module['credits']); ?> crédits</span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-parent="#formationModules">
                                <div class="accordion-body">
                                    <?php if (!empty($module['description'])): ?>
                                    <div class="mb-3"><?php echo htmlspecialchars($module['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center text-muted small">
                                        <?php if (!empty($module['semester'])): ?>
                                        <div class="me-3">
                                            <i class="fas fa-calendar-alt me-1"></i> Semestre: <?php echo htmlspecialchars($module['semester']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Corps de la formation -->
                <div class="formation-body mb-5 content-wrapper">
                    <h3 class="fw-bold mb-4 border-bottom pb-2">Détails de la formation</h3>
                    <?php echo $formation['content']; ?>
                </div>
                
                <!-- Ressources téléchargeables -->
                <?php if (!empty($resources)): ?>
                <div class="formation-resources mb-5">
                    <h3 class="fw-bold mb-4 border-bottom pb-2">Documents pédagogiques</h3>
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-download text-primary me-2"></i>Ressources à télécharger</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($resources as $resource): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="resource-info d-flex align-items-center">
                                        <div class="resource-icon me-3">
                                            <i class="fas <?php echo getFileIconClass($resource['file_type']); ?> fa-2x text-<?php echo getFileColorClass($resource['file_type']); ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($resource['title'] ?? $resource['file_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo formatFileSize($resource['file_size']); ?> • 
                                                <?php echo strtoupper(pathinfo($resource['file_name'], PATHINFO_EXTENSION)); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <a href=".<?php echo htmlspecialchars($resource['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                        <i class="fas fa-download me-1"></i> Télécharger
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Responsable de la formation (si disponible) -->
                <?php if ($formation['author_name']): ?>
                <div class="formation-author mb-5">
                    <h3 class="fw-bold mb-4 border-bottom pb-2">Responsable de la formation</h3>
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="author-avatar me-3">
                                    <?php if (isset($formation['author_id'])): ?>
                                    <img src="/assets/img/avatars/user<?php echo $formation['author_id']; ?>.jpg" 
                                         onerror="this.src='/assets/img/avatars/default.jpg'" 
                                         alt="<?php echo htmlspecialchars($formation['author_name']); ?>"
                                         class="rounded-circle" width="80" height="80">
                                    <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px; background-color: var(--primary-color); color: white;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="author-info">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($formation['author_name']); ?></h5>
                                    <p class="text-muted mb-2">Responsable de la formation</p>
                                    <p class="mb-0 small">Pour toute question concernant cette formation, vous pouvez contacter le responsable.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Partager la formation -->
                <div class="formation-share mb-5">
                    <h5 class="text-uppercase fs-6 text-muted mb-3">Partager cette formation</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary btn-sm" aria-label="Partager sur Facebook">
                            <i class="fab fa-facebook-f me-2"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($formation['title']); ?>" target="_blank" class="btn btn-outline-info btn-sm" aria-label="Partager sur Twitter">
                            <i class="fab fa-twitter me-2"></i> Twitter
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($formation['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-success btn-sm" aria-label="Partager sur WhatsApp">
                            <i class="fab fa-whatsapp me-2"></i> WhatsApp
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($formation['title']); ?>&body=<?php echo urlencode('Découvrez cette formation intéressante : https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-secondary btn-sm" aria-label="Partager par email">
                            <i class="far fa-envelope me-2"></i> Email
                        </a>
                        <button class="btn btn-outline-dark btn-sm" id="copyLink" title="Copier le lien">
                            <i class="fas fa-link me-2"></i> Copier le lien
                        </button>
                        <button class="btn btn-outline-dark btn-sm" onclick="window.print()" aria-label="Imprimer la page">
                            <i class="fas fa-print me-2"></i> Imprimer
                        </button>
                    </div>
                </div>
                
                <!-- Formations connexes -->
                <?php if (!empty($relatedFormations)): ?>
                <div class="related-formations mb-5">
                    <h3 class="fw-bold mb-4 border-bottom pb-2">Formations similaires</h3>
                    <div class="row">
                        <?php foreach ($relatedFormations as $relatedFormation): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <?php if (!empty($relatedFormation['featured_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($relatedFormation['featured_image']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($relatedFormation['title']); ?>"
                                     style="height: 160px; object-fit: cover;">
                                <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                                    <i class="fas fa-graduation-cap fa-3x text-muted"></i>
                                </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($relatedFormation['title']); ?></h5>
                                    <p class="card-text small mb-0">
                                        <span class="badge bg-secondary"><?php echo isset($niveaux[$relatedFormation['level']]) ? $niveaux[$relatedFormation['level']] : $relatedFormation['level']; ?></span>
                                    </p>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <a href="/formation&slug=<?php echo htmlspecialchars($relatedFormation['slug']); ?>" class="btn btn-outline-primary btn-sm stretched-link">
                                        En savoir plus
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
                    <!-- Appel à l'action -->
                    <div class="card mb-4 shadow-sm border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Intéressé(e) par cette formation ?</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Inscrivez-vous dès maintenant</strong> ou demandez plus d'informations sur cette formation.</p>
                            <div class="d-grid gap-2">
                                <a href="/inscription&formation=<?php echo htmlspecialchars($formation['id']); ?>" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i> S'inscrire
                                </a>
                                <a href="/contact&subject=Information sur la formation: <?php echo htmlspecialchars($formation['title']); ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-info-circle me-2"></i> Plus d'informations
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table des matières desktop -->
                    <div class="card mb-4 shadow-sm d-none d-lg-block">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Sur cette page</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush toc-list-desktop">
                                <!-- Généré dynamiquement par JavaScript -->
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Informations sur la formation -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Informations</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-graduation-cap text-primary me-2"></i> Niveau</span>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($niveauFormatted); ?></span>
                                </li>
                                <?php if (!empty($formation['duration'])): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-clock text-primary me-2"></i> Durée</span>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($formation['duration']); ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($formation['credits'])): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-award text-primary me-2"></i> Crédits</span>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($formation['credits']); ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($formation['category_name'])): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-tag text-primary me-2"></i> Catégorie</span>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($formation['category_name']); ?></span>
                                </li>
                                <?php endif; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-calendar-alt text-primary me-2"></i> Dernière mise à jour</span>
                                    <span class="badge bg-light text-dark"><?php echo $formattedDate; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-file-alt text-primary me-2"></i> Temps de lecture</span>
                                    <span class="badge bg-light text-dark"><?php echo $readingTime; ?> min</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Contact/Support -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Besoin d'aide ?</h5>
                        </div>
                        <div class="card-body">
                        <p>Vous avez des questions concernant cette formation ou vous souhaitez plus d'informations sur les modalités d'inscription ?</p>
                            <a href="/contact" class="btn btn-primary w-100">
                                <i class="fas fa-envelope me-2"></i> Contactez-nous
                            </a>
                        </div>
                    </div>
                    
                    <!-- Téléchargement de brochure si disponible -->
                    <?php 
                    $brochureFile = null;
                    foreach ($resources as $resource) {
                        if (strpos(strtolower($resource['file_name']), 'brochure') !== false || 
                            strpos(strtolower($resource['title']), 'brochure') !== false) {
                            $brochureFile = $resource;
                            break;
                        }
                    }
                    ?>
                    <?php if ($brochureFile): ?>
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-file-pdf fa-3x text-danger"></i>
                            </div>
                            <h5 class="card-title">Brochure de la formation</h5>
                            <p class="card-text small">Téléchargez la brochure complète de la formation pour plus de détails.</p>
                            <a href=".<?php echo htmlspecialchars($brochureFile['file_path']); ?>" class="btn btn-outline-danger" download>
                                <i class="fas fa-download me-2"></i> Télécharger (<?php echo formatFileSize($brochureFile['file_size']); ?>)
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Newsletter -->
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Restez informé</h5>
                            <p class="card-text">Recevez les dernières informations sur nos formations directement par email.</p>
                            <form action="subscribe" method="post" class="mb-0">
                                <div class="input-group mb-2">
                                    <input type="email" class="form-control" placeholder="Votre adresse email" required>
                                    <button type="submit" class="btn btn-primary">S'abonner</button>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="newsletter-consent" required>
                                    <label class="form-check-label text-muted" for="newsletter-consent">
                                        J'accepte de recevoir la newsletter
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ sur la formation -->
<section class="formation-faq py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold">Questions fréquentes</h2>
                <p class="lead text-muted">Retrouvez les réponses aux questions les plus courantes sur cette formation</p>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="accordion shadow-sm" id="faqAccordion">
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faqHeading1">
                            <button class="accordion-button" type="button" data-toggle="collapse" data-target="#faqCollapse1" aria-expanded="true" aria-controls="faqCollapse1">
                                Quelles sont les conditions d'admission ?
                            </button>
                        </h2>
                        <div id="faqCollapse1" class="accordion-collapse collapse show" aria-labelledby="faqHeading1" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Les conditions d'admission varient selon le niveau d'études. Pour cette formation de <?php echo htmlspecialchars($niveauFormatted); ?>, les candidats doivent généralement avoir validé leur diplôme précédent avec des résultats satisfaisants. Un dossier de candidature complet sera examiné par une commission pédagogique.</p>
                                <p>Pour plus d'informations spécifiques, veuillez consulter la section <a href="/admission">Admission</a> ou nous contacter directement.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faqHeading2">
                            <button class="accordion-button collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                Quand débutent les inscriptions ?
                            </button>
                        </h2>
                        <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faqHeading2" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Les inscriptions débutent généralement en avril et se terminent en septembre, selon les places disponibles. Nous vous recommandons de soumettre votre candidature le plus tôt possible, car certaines formations sont très demandées.</p>
                                <p>Les dates exactes sont disponibles sur la page <a href="/calendrier-academique">Calendrier académique</a>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faqHeading3">
                            <button class="accordion-button collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                Existe-t-il des possibilités de bourses d'études ?
                            </button>
                        </h2>
                        <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faqHeading3" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Oui, plusieurs types de bourses sont disponibles pour les étudiants, notamment des bourses d'excellence académique, des bourses sur critères sociaux et des aides financières pour les situations particulières.</p>
                                <p>Consultez la page <a href="/bourses">Bourses et financements</a> pour connaître toutes les options disponibles et les procédures de demande.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faqHeading4">
                            <button class="accordion-button collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                Quels sont les débouchés après cette formation ?
                            </button>
                        </h2>
                        <div id="faqCollapse4" class="accordion-collapse collapse" aria-labelledby="faqHeading4" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Cette formation de <?php echo htmlspecialchars($niveauFormatted); ?> offre de nombreux débouchés professionnels dans divers secteurs de la santé et des sciences médicales. Nos diplômés travaillent dans des hôpitaux, des cliniques, des centres de recherche, des ONG et des organismes internationaux.</p>
                                <p>Pour des informations détaillées sur les carrières possibles, consultez notre page <a href="/debouches-professionnels">Débouchés professionnels</a>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call-to-Action Section -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Prêt à vous lancer dans cette formation ?</h2>
                <p class="lead mb-4">Rejoignez l'ISTM BENI et bénéficiez d'une formation de qualité reconnue dans le domaine médical.</p>
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    <a href="" class="btn btn-lg btn-light px-4">
                        <i class="fas fa-user-plus me-2"></i> S'inscrire
                    </a>
                    <a href="contact" class="btn btn-lg btn-outline-light px-4">
                        <i class="fas fa-envelope me-2"></i> Nous contacter
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Fonction pour déterminer l'icône en fonction du type de fichier
function getFileIconClass($fileType) {
    if (strpos($fileType, 'pdf') !== false) {
        return 'fa-file-pdf';
    } elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false || strpos($fileType, 'docx') !== false) {
        return 'fa-file-word';
    } elseif (strpos($fileType, 'excel') !== false || strpos($fileType, 'sheet') !== false || strpos($fileType, 'xlsx') !== false) {
        return 'fa-file-excel';
    } elseif (strpos($fileType, 'image') !== false) {
        return 'fa-file-image';
    } elseif (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false || strpos($fileType, 'gzip') !== false) {
        return 'fa-file-archive';
    } elseif (strpos($fileType, 'audio') !== false || strpos($fileType, 'mp3') !== false) {
        return 'fa-file-audio';
    } elseif (strpos($fileType, 'video') !== false || strpos($fileType, 'mp4') !== false) {
        return 'fa-file-video';
    } elseif (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false || strpos($fileType, 'pptx') !== false) {
        return 'fa-file-powerpoint';
    } else {
        return 'fa-file-alt';
    }
}

// Fonction pour déterminer la couleur en fonction du type de fichier
function getFileColorClass($fileType) {
    if (strpos($fileType, 'pdf') !== false) {
        return 'danger';
    } elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false || strpos($fileType, 'docx') !== false) {
        return 'primary';
    } elseif (strpos($fileType, 'excel') !== false || strpos($fileType, 'sheet') !== false || strpos($fileType, 'xlsx') !== false) {
        return 'success';
    } elseif (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false || strpos($fileType, 'pptx') !== false) {
        return 'warning';
    } else {
        return 'secondary';
    }
}

// Fonction pour formater la taille des fichiers
function formatFileSize($size) {
    $units = array('o', 'Ko', 'Mo', 'Go', 'To');
    $size = max($size, 0);
    $pow = floor(($size ? log($size) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $size /= pow(1024, $pow);
    return round($size, 1) . ' ' . $units[$pow];
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Génération de la table des matières
    const pageBody = document.querySelector('.formation-body');
    const tocList = document.querySelector('.toc-list');
    const tocListDesktop = document.querySelector('.toc-list-desktop');
    
    if (pageBody && (tocList || tocListDesktop)) {
        const headings = pageBody.querySelectorAll('h2, h3, h4');
        
        if (headings.length > 0) {
            headings.forEach((heading, index) => {
                // Ajouter des IDs aux titres pour la navigation
                const headingText = heading.textContent.trim();
                const headingId = 'heading-' + index;
                heading.id = headingId;
                
                // Créer les éléments de la table des matières
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item border-0 p-2 ps-3';
                
                if (heading.tagName === 'H3') {
                    listItem.classList.add('ps-4');
                } else if (heading.tagName === 'H4') {
                    listItem.classList.add('ps-5');
                }
                
                const link = document.createElement('a');
                link.href = '#' + headingId;
                link.className = 'text-decoration-none d-block toc-link';
                link.textContent = headingText;
                
                if (heading.tagName === 'H2') {
                    link.classList.add('fw-bold');
                }
                
                listItem.appendChild(link);
                
                // Ajouter à la TOC mobile
                if (tocList) {
                    tocList.appendChild(listItem.cloneNode(true));
                }
                
                // Ajouter à la TOC desktop
                if (tocListDesktop) {
                    tocListDesktop.appendChild(listItem);
                }
            });
            
            // Activation des liens de la table des matières
            document.querySelectorAll('.toc-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                        
                        // Fermer la table des matières mobile
                        const tocCollapse = bootstrap.Collapse.getInstance(document.getElementById('tableOfContents'));
                        if (tocCollapse) {
                            tocCollapse.hide();
                        }
                    }
                });
            });
        } else {
            // S'il n'y a pas de titres, masquer la table des matières
            document.querySelectorAll('.formation-toc').forEach(toc => {
                toc.style.display = 'none';
            });
        }
    }
    
    // Amélioration des éléments de la page
    if (pageBody) {
        // Ajouter des classes Bootstrap aux images
        pageBody.querySelectorAll('img').forEach(img => {
            img.classList.add('img-fluid', 'rounded', 'my-4', 'shadow-sm');
            
            // Créer une figure pour envelopper l'image si elle a un alt text
            if (img.alt && !img.parentElement.matches('figure')) {
                const figure = document.createElement('figure');
                figure.className = 'text-center';
                
                const figcaption = document.createElement('figcaption');
                figcaption.className = 'figure-caption text-center mt-2';
                figcaption.textContent = img.alt;
                
                img.parentNode.insertBefore(figure, img);
                figure.appendChild(img);
                figure.appendChild(figcaption);
            }
        });
        
        // Ajouter des classes Bootstrap aux tableaux
        pageBody.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-striped', 'table-bordered', 'my-4');
            
            // Wrapper pour rendre les tableaux responsifs
            const tableResponsive = document.createElement('div');
            tableResponsive.className = 'table-responsive';
            table.parentNode.insertBefore(tableResponsive, table);
            tableResponsive.appendChild(table);
        });
        
        // Amélioration des liens
        pageBody.querySelectorAll('a').forEach(link => {
            link.classList.add('text-decoration-none');
            
            // Ajouter une icône aux liens externes
            if (link.hostname !== window.location.hostname) {
                link.classList.add('external-link');
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
                
                const icon = document.createElement('i');
                icon.className = 'fas fa-external-link-alt ms-1 small';
                link.appendChild(icon);
            }
        });
        
        // Amélioration des listes
        pageBody.querySelectorAll('ul, ol').forEach(list => {
            list.classList.add('my-3');
            
            list.querySelectorAll('li').forEach(item => {
                item.classList.add('mb-2');
            });
        });
        
        // Amélioration des citations
        pageBody.querySelectorAll('blockquote').forEach(quote => {
            quote.classList.add('blockquote', 'border-start', 'border-primary', 'border-4', 'ps-4', 'py-2', 'my-4');
        });
    }
    
    // Bouton de copie de lien
    const copyLinkBtn = document.getElementById('copyLink');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            const currentUrl = window.location.href;
            navigator.clipboard.writeText(currentUrl)
                .then(() => {
                    // Changer temporairement le texte du bouton
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-2"></i> Lien copié';
                    this.classList.remove('btn-outline-dark');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-dark');
                    }, 2000);
                })
                .catch(err => {
                    console.error('Erreur lors de la copie du lien:', err);
                });
        });
    }
    
    // Animation au défilement
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });
    
    document.querySelectorAll('.card, .formation-body > p, .formation-body > h2, .formation-body > h3, .formation-body > ul, .formation-body > ol, .formation-body > blockquote').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
});
</script>

<!-- Styles spécifiques pour la page formation -->
<style>
/* Aspect général de la page */
.formation-hero {
    position: relative;
    overflow: hidden;
}

.wave-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    overflow: hidden;
    line-height: 0;
}

.formation-body {
    font-family: 'Arial', sans-serif;
    font-size: 1.125rem;
    line-height: 1.8;
    color: #333;
}

.formation-body h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.formation-body h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #444;
}

.formation-body h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #555;
}

.formation-body p {
    margin-bottom: 1.5rem;
}

.formation-body ul, .formation-body ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.formation-body li {
    margin-bottom: 0.5rem;
}

.formation-body blockquote {
    font-style: italic;
    position: relative;
    padding: 1.5rem 2rem;
    margin: 1.5rem 0;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 0.25rem;
}

.formation-body blockquote::before {
    content: "\201C";
    font-family: Georgia, serif;
    font-size: 3rem;
    position: absolute;
    left: 0.5rem;
    top: -0.5rem;
    color: rgba(var(--primary-color-rgb), 0.2);
}

.formation-body a {
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
    transition: all 0.2s ease;
}

.formation-body a:hover {
    color: var(--secondary-color);
    border-color: var(--secondary-color);
}

/* Modules de formation */
.accordion-button:not(.collapsed) {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
}

.accordion-button:focus {
    border-color: rgba(var(--primary-color-rgb), 0.5);
    box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
}

/* Animation au défilement */
.fade-in-element {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}

.fade-in-element.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Table des matières */
.toc-link {
    color: #333;
    transition: all 0.2s ease;
}

.toc-link:hover {
    color: var(--primary-color);
    transform: translateX(5px);
}

/* Sidebar */
.sidebar {
    z-index: 1;
}

@media (min-width: 992px) {
    .sidebar {
        position: sticky;
        top: 2rem;
    }
}

/* Ressources */
.resource-icon {
    width: 40px;
    display: flex;
    justify-content: center;
}

/* Media queries */
@media (max-width: 991.98px) {
    .formation-title {
        font-size: 2.2rem !important;
    }
    
    .formation-body {
        font-size: 1rem;
    }
    
    .formation-body h2 {
        font-size: 1.6rem;
    }
    
    .formation-body h3 {
        font-size: 1.4rem;
    }
    
    .formation-body h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 767.98px) {
    .formation-title {
        font-size: 1.8rem !important;
    }
    
    .formation-hero {
        padding: 2rem 0;
    }
    
    .formation-meta {
        flex-wrap: wrap;
    }
    
    .formation-meta > span {
        margin-bottom: 0.5rem;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .cta-section, .formation-share, .sharing-buttons, .formation-faq {
        display: none !important;
    }
    
    .formation-body {
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .formation-body a {
        font-weight: bold;
        text-decoration: none;
        color: #000 !important;
        border: none;
    }
    
    .formation-body a::after {
        content: " (" attr(href) ")";
        font-size: 90%;
        font-weight: normal;
    }
    
    .container {
        max-width: 100%;
    }
    
    .col-lg-8 {
        width: 100%;
        max-width: 100%;
        flex: 0 0 100%;
    }
    
    .formation-title {
        font-size: 24pt !important;
    }
    
    .formation-body img {
        max-height: 300px;
    }
    
    .accordion-button::after {
        display: none;
    }
    
    .accordion-collapse {
        display: block !important;
    }
}
</style>

<?php 
include "include/footer.php";
?>


