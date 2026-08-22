<?php 
include "include/head.php";

// Récupération des données de l'article
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /actualites');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails de l'article
$stmt = $db->prepare("SELECT n.*, c.name as category_name, c.slug as category_slug, 
                      u.full_name as author_name, u.id as author_id
                   FROM news n 
                   LEFT JOIN categories c ON n.category_id = c.id 
                   LEFT JOIN users u ON n.created_by = u.id 
                   WHERE n.slug = :slug AND n.is_published = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$article = $stmt->fetch(PDO::FETCH_ASSOC);

// Si l'article n'existe pas ou n'est pas publié, rediriger
if (!$article) {
    header('Location: /actualites');
    exit;
}

// Récupérer les articles similaires
$stmt = $db->prepare("SELECT n.id, n.title, n.slug, n.excerpt, n.featured_image, n.published_at 
                   FROM news n 
                   WHERE n.category_id = :category_id AND n.id != :article_id AND n.is_published = 1 
                   ORDER BY n.published_at DESC LIMIT 4");
$stmt->bindParam(':category_id', $article['category_id']);
$stmt->bindParam(':article_id', $article['id']);
$stmt->execute();
$similarArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les articles populaires
$stmt = $db->prepare("SELECT n.id, n.title, n.slug, n.published_at 
                   FROM news n 
                   WHERE n.is_published = 1 
                   ORDER BY n.views DESC, n.published_at DESC LIMIT 5");
$stmt->execute();
$popularArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ressources attachées à l'article via la table news_media
$resources = [];
$stmt = $db->prepare("
    SELECT m.* 
    FROM media m 
    JOIN news_media nm ON m.id = nm.media_id 
    WHERE nm.news_id = :article_id
    ORDER BY nm.is_featured DESC, nm.order_index ASC");
$stmt->bindParam(':article_id', $article['id']);
$stmt->execute();
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Récupérer les catégories populaires
$stmt = $db->prepare("SELECT c.name, c.slug, COUNT(n.id) as article_count 
                   FROM categories c
                   JOIN news n ON c.id = n.category_id
                   WHERE n.is_published = 1
                   GROUP BY c.id
                   ORDER BY article_count DESC
                   LIMIT 8");
$stmt->execute();
$popularCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incrémenter le compteur de vues
$stmt = $db->prepare("UPDATE news SET views = views + 1 WHERE id = :id");
$stmt->bindParam(':id', $article['id']);
$stmt->execute();

// Mise en forme de la date
$publishedDate = new DateTime($article['published_at']);
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

// Calculer le temps de lecture estimé
$wordCount = str_word_count(strip_tags($article['content']));
$readingTime = max(1, ceil($wordCount / 200)); // 200 mots par minute en moyenne
?>

<!-- En-tête de l'article avec background et titre -->
<section class="article-hero bg-gradient-primary text-white py-5" style="position: relative; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="actualites" class="text-white opacity-75">Actualités</a></li>
                        <?php if ($article['category_name']): ?>
                        <li class="breadcrumb-item"><a href="actualites&category_slug=<?php echo htmlspecialchars($article['category_slug']); ?>" class="text-white opacity-75"><?php echo htmlspecialchars($article['category_name']); ?></a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars(substr($article['title'], 0, 30)) . (strlen($article['title']) > 30 ? '...' : ''); ?></li>
                    </ol>
                </nav>
                
                <div class="article-meta d-flex align-items-center mb-3">
                    <?php if ($article['category_name']): ?>
                    <a href="/actualites/categorie/<?php echo htmlspecialchars($article['category_slug']); ?>" class="badge bg-light text-primary me-2 px-3 py-2 rounded-pill">
                        <?php echo htmlspecialchars($article['category_name']); ?>
                    </a>
                    <?php endif; ?>
                    <span class="text-white opacity-75 me-3"><i class="far fa-calendar-alt me-1"></i> <?php echo $formattedDate; ?></span>
                    <?php if ($article['author_name']): ?>
                    <span class="text-white opacity-75 me-3"><i class="far fa-user me-1"></i> <?php echo htmlspecialchars($article['author_name']); ?></span>
                    <?php endif; ?>
                    <span class="text-white opacity-75 me-3"><i class="far fa-clock me-1"></i> <?php echo $readingTime; ?> min de lecture</span>
                    <span class="text-white opacity-75"><i class="far fa-eye me-1"></i> <?php echo number_format($article['views'] ?? 0); ?> vues</span>
                </div>
                
                <h1 class="article-title display-4 fw-bold mb-3"><?php echo htmlspecialchars($article['title']); ?></h1>
                
                <?php if ($article['excerpt']): ?>
                <div class="article-excerpt fw-light">
                    <p class="lead fs-4 mb-0"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="article-share mt-4">
                    <div class="d-flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-sm btn-light" aria-label="Partager sur Facebook">
                            <i class="fab fa-facebook-f"></i> Partager
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($article['title']); ?>" target="_blank" class="btn btn-sm btn-light" aria-label="Partager sur Twitter">
                            <i class="fab fa-twitter"></i> Tweeter
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($article['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-sm btn-light d-none d-sm-inline-flex" aria-label="Partager sur WhatsApp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($article['title']); ?>&body=<?php echo urlencode('Découvrez cet article intéressant : https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-sm btn-light" aria-label="Partager par email">
                            <i class="far fa-envelope"></i> Email
                        </a>
                        <button class="btn btn-sm btn-light" onclick="window.print()" aria-label="Imprimer l'article">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
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
<section class="article-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu article -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Image principale de l'article -->
                <?php if ($article['featured_image']): ?>
                <div class="article-featured-image mb-4 rounded shadow-sm overflow-hidden">
                    <img src=".<?php echo htmlspecialchars($article['featured_image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <!-- Table des matières -->
                <div class="article-toc card mb-4 d-lg-none">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Table des matières</h5>
                        <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#tableOfContents">
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
                
                <!-- Corps de l'article -->
                <div class="article-body mb-5 content-wrapper">
                    <?php echo $article['content']; ?>
                </div>
                
                <!-- Tags et catégories -->
                <div class="article-tags mb-5">
                    <h5 class="text-uppercase fs-6 text-muted mb-3">Catégories & Mots-clés</h5>
                    <?php if ($article['category_name']): ?>
                    <a href="/actualites/categorie/<?php echo htmlspecialchars($article['category_slug']); ?>" class="btn btn-sm btn-outline-primary me-2 mb-2 rounded-pill">
                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($article['category_name']); ?>
                    </a>
                    <?php endif; ?>
                    <!-- Exemple de tags statiques pour l'UI - À adapter selon votre modèle de données -->
                    <a href="#" class="btn btn-sm btn-outline-secondary me-2 mb-2 rounded-pill">ISTM</a>
                    <a href="#" class="btn btn-sm btn-outline-secondary me-2 mb-2 rounded-pill">Éducation</a>
                    <a href="#" class="btn btn-sm btn-outline-secondary me-2 mb-2 rounded-pill">Santé</a>
                </div>
                
                <!-- Auteur de l'article -->
                <div class="article-author mb-5">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                        <div class="d-flex align-items-center">
                                <div class="author-avatar me-3">
                                    <?php if (isset($article['author_id'])): ?>
                                    <img src="/assets/img/avatars/user<?php echo $article['author_id']; ?>.jpg" 
                                         onerror="this.src='/assets/img/avatars/default.jpg'" 
                                         alt="<?php echo htmlspecialchars($article['author_name']); ?>"
                                         class="rounded-circle" width="80" height="80">
                                    <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px; background-color: var(--primary-color); color: white;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="author-info">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($article['author_name'] ?? 'ISTM BENI'); ?></h5>
                                    <p class="text-muted mb-2">Publié le <?php echo $formattedDate; ?></p>
                                    <p class="mb-0 small"><?php echo htmlspecialchars("Auteur à l'ISTM BENI, spécialisé dans les domaines de la santé et de l'éducation médicale."); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ressources téléchargeables -->
                <?php if (!empty($resources)): ?>
                <div class="article-resources mb-5">
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-download text-primary me-2"></i>Ressources associées</h5>
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
                
                <!-- Commentaires (placeholder) -->
                <div class="article-comments mb-5">
                    <h4 class="mb-4">Discussions et commentaires</h4>
                    <div class="alert alert-info">
                        <p class="mb-0"><i class="fas fa-info-circle me-2"></i>Les commentaires sont temporairement désactivés pour cet article.</p>
                    </div>
                </div>
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
                    <!-- Carte de l'auteur compact -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title border-bottom pb-3">À propos de l'auteur</h5>
                            <div class="d-flex align-items-center">
                                <?php if (isset($article['author_id'])): ?>
                                <img src="/assets/img/avatars/user<?php echo $article['author_id']; ?>.jpg" 
                                     onerror="this.src='/assets/img/avatars/default.jpg'" 
                                     alt="<?php echo htmlspecialchars($article['author_name']); ?>"
                                     class="rounded-circle me-3" width="50" height="50">
                                <?php else: ?>
                                <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px; background-color: var(--primary-color); color: white;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($article['author_name'] ?? 'ISTM BENI'); ?></h6>
                                    <small class="text-muted">Membre de l'équipe ISTM</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table des matières desktop -->
                    <div class="card mb-4 shadow-sm d-none d-lg-block">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Table des matières</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush toc-list-desktop">
                                <!-- Généré dynamiquement par JavaScript -->
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Infos complémentaires sur l'article -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Infos sur l'article</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-calendar-alt text-primary me-2"></i> Date de publication</span>
                                    <span class="badge bg-light text-dark"><?php echo $formattedDate; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-clock text-primary me-2"></i> Temps de lecture</span>
                                    <span class="badge bg-light text-dark"><?php echo $readingTime; ?> min</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-eye text-primary me-2"></i> Nombre de vues</span>
                                    <span class="badge bg-light text-dark"><?php echo number_format($article['views'] ?? 0); ?></span>
                                </li>
                                <?php if ($article['category_name']): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-tag text-primary me-2"></i> Catégorie</span>
                                    <a href="/actualites/categorie/<?php echo htmlspecialchars($article['category_slug']); ?>" class="badge bg-primary"><?php echo htmlspecialchars($article['category_name']); ?></a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Articles populaires -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Articles populaires</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($popularArticles as $index => $popular): ?>
                                <li class="list-group-item px-3 py-3">
                                    <a href="/actualites/<?php echo htmlspecialchars($popular['slug']); ?>" class="d-flex text-decoration-none text-dark">
                                        <div class="article-number me-3 d-flex align-items-center justify-content-center" 
                                             style="min-width: 30px; height: 30px; background-color: <?php echo $index === 0 ? 'var(--primary-color)' : 'var(--light)'; ?>; color: <?php echo $index === 0 ? 'white' : 'var(--dark)'; ?>; border-radius: 50%;">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($popular['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php 
                                                $popularDate = new DateTime($popular['published_at']);
                                                echo $popularDate->format('d/m/Y'); 
                                                ?>
                                            </small>
                                        </div>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Catégories populaires -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Catégories</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($popularCategories as $category): ?>
                                <a href="/actualites/categorie/<?php echo htmlspecialchars($category['slug']); ?>" class="btn btn-sm btn-outline-secondary mb-2">
                                    <?php echo htmlspecialchars($category['name']); ?> 
                                    <span class="badge bg-secondary ms-1"><?php echo $category['article_count']; ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Newsletter -->
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Restez informé</h5>
                            <p class="card-text">Recevez nos dernières actualités directement dans votre boîte mail.</p>
                            <form action="/subscribe.php" method="post" class="mb-0">
                                <div class="input-group mb-2">
                                    <input type="email" class="form-control" placeholder="Votre adresse email" required>
                                    <button type="submit" class="btn btn-primary">S'abonner</button>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="newsletter-consent" required>
                                    <label class="form-check-label text-muted" for="newsletter-consent">
                                        J'accepte de recevoir la newsletter de l'ISTM BENI
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

<!-- Articles similaires en carousel -->
<?php if (!empty($similarArticles)): ?>
<section class="similar-articles py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Articles similaires</h2>
            <p class="section-text">Découvrez d'autres actualités dans la catégorie <strong><?php echo htmlspecialchars($article['category_name']); ?></strong></p>
        </div>
        
        <div class="row">
            <?php foreach ($similarArticles as $similar): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 shadow-sm border-0 article-card">
                    <div class="card-img-container position-relative" style="height: 180px; overflow: hidden;">
                        <?php if ($similar['featured_image']): ?>
                        <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($similar['title']); ?>" style="object-fit: cover; height: 100%; width: 100%;">
                        <?php else: ?>
                        <div class="bg-secondary d-flex align-items-center justify-content-center h-100">
                            <i class="fas fa-newspaper fa-3x text-white"></i>
                        </div>
                        <?php endif; ?>
                        <?php if ($article['category_name']): ?>
                        <span class="position-absolute top-0 start-0 m-2 badge bg-primary"><?php echo htmlspecialchars($article['category_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="card-date small text-muted mb-2">
                            <?php 
                            $similarDate = new DateTime($similar['published_at']);
                            echo $similarDate->format('d/m/Y'); 
                            ?>
                        </div>
                        <h5 class="card-title">
                            <a href="/actualites/<?php echo htmlspecialchars($similar['slug']); ?>" class="text-decoration-none text-dark stretched-link">
                            <?php echo htmlspecialchars($similar['title']); ?>
                            </a>
                        </h5>
                        <?php if ($similar['excerpt']): ?>
                        <p class="card-text text-muted small flex-grow-1">
                            <?php echo substr(htmlspecialchars($similar['excerpt']), 0, 100) . '...'; ?>
                        </p>
                        <?php endif; ?>
                        <div class="mt-auto">
                            <a href="/actualites/<?php echo htmlspecialchars($similar['slug']); ?>" class="btn btn-link p-0 text-primary">
                                Lire la suite <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="/actualites" class="btn btn-outline-primary px-4">
                Voir toutes les actualités <i class="fas fa-long-arrow-alt-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call-to-Action Section -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Vous souhaitez rester informé ?</h2>
                <p class="lead mb-4">Inscrivez-vous à notre newsletter pour recevoir les dernières nouvelles et mises à jour de l'ISTM BENI.</p>
                <form class="newsletter-form d-flex justify-content-center flex-wrap flex-sm-nowrap gap-2">
                    <input type="email" class="form-control form-control-lg" placeholder="Votre adresse email" aria-label="Votre adresse email">
                    <button class="btn btn-lg btn-light" type="submit">
                        S'abonner <i class="fas fa-paper-plane ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Back to list button -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center">
        <a href="actualites" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour aux actualités
        </a>
        <div class="sharing-buttons">
            <button class="btn btn-sm btn-outline-secondary me-2" id="copyLink" title="Copier le lien">
                <i class="fas fa-link"></i> Copier le lien
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Imprimer">
                <i class="fas fa-print"></i> Imprimer
            </button>
        </div>
    </div>
</div>

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
    const articleBody = document.querySelector('.article-body');
    const tocList = document.querySelector('.toc-list');
    const tocListDesktop = document.querySelector('.toc-list-desktop');
    
    if (articleBody && (tocList || tocListDesktop)) {
        const headings = articleBody.querySelectorAll('h2, h3, h4');
        
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
            document.querySelectorAll('.article-toc').forEach(toc => {
                toc.style.display = 'none';
            });
        }
    }
    
    // Amélioration des éléments de l'article
    if (articleBody) {
        // Ajouter des classes Bootstrap aux images
        articleBody.querySelectorAll('img').forEach(img => {
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
        articleBody.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-striped', 'table-bordered', 'my-4');
            
            // Wrapper pour rendre les tableaux responsifs
            const tableResponsive = document.createElement('div');
            tableResponsive.className = 'table-responsive';
            table.parentNode.insertBefore(tableResponsive, table);
            tableResponsive.appendChild(table);
        });
        
        // Amélioration des liens
        articleBody.querySelectorAll('a').forEach(link => {
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
        articleBody.querySelectorAll('ul, ol').forEach(list => {
            list.classList.add('my-3');
            
            list.querySelectorAll('li').forEach(item => {
                item.classList.add('mb-2');
            });
        });
        
        // Amélioration des citations
        articleBody.querySelectorAll('blockquote').forEach(quote => {
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
                    this.innerHTML = '<i class="fas fa-check"></i> Lien copié';
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-secondary');
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
    
    document.querySelectorAll('.card, .article-body > p, .article-body > h2, .article-body > h3, .article-body > ul, .article-body > ol, .article-body > blockquote').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
});
</script>

<!-- Styles spécifiques pour la page d'article -->
<style>
/* Aspect général de l'article */
.article-hero {
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

.article-body {
    font-family: 'Georgia', serif;
    font-size: 1.125rem;
    line-height: 1.8;
    color: #333;
}

.article-body h2 {
    font-family: 'Arial', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.article-body h3 {
    font-family: 'Arial', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #444;
}

.article-body h4 {
    font-family: 'Arial', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #555;
}

.article-body p {
    margin-bottom: 1.5rem;
}

.article-body ul, .article-body ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.article-body li {
    margin-bottom: 0.5rem;
}

.article-body blockquote {
    font-style: italic;
    position: relative;
    padding: 1.5rem 2rem;
    margin: 1.5rem 0;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 0.25rem;
}

.article-body blockquote::before {
    content: "\201C";
    font-family: Georgia, serif;
    font-size: 3rem;
    position: absolute;
    left: 0.5rem;
    top: -0.5rem;
    color: rgba(var(--primary-color-rgb), 0.2);
}

.article-body a {
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
    transition: all 0.2s ease;
}

.article-body a:hover {
    color: var(--secondary-color);
    border-color: var(--secondary-color);
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

/* Cartes articles */
.article-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.article-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
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
    .article-title {
        font-size: 2.2rem !important;
    }
    
    .article-body {
        font-size: 1rem;
    }
    
    .article-body h2 {
        font-size: 1.6rem;
    }
    
    .article-body h3 {
        font-size: 1.4rem;
    }
    
    .article-body h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 767.98px) {
    .article-title {
        font-size: 1.8rem !important;
    }
    
    .article-hero {
        padding: 2rem 0;
    }
    
    .article-meta {
        flex-wrap: wrap;
    }
    
    .article-meta > span {
        margin-bottom: 0.5rem;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .cta-section, .similar-articles, .article-comments, .sharing-buttons, .article-share {
        display: none !important;
    }
    
    .article-body {
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .article-body a {
        font-weight: bold;
        text-decoration: none;
        color: #000 !important;
        border: none;
    }
    
    .article-body a::after {
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
    
    .article-title {
        font-size: 24pt !important;
    }
    
    .article-body img {
        max-height: 300px;
    }
}
</style>

<?php 
include "include/footer.php";
?>



