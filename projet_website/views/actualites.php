<?php 
// Initialiser le contrôleur
require_once 'controllers/NewsController.php';
$newsController = new NewsController();

// Récupérer les paramètres de pagination et de filtrage
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Assurer que la page est au moins 1
$perPage = 9; // Nombre d'actualités par page

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Récupérer les données
$news = $newsController->getAllNews($page, $perPage, $category_id);
$totalNews = $newsController->countNews($category_id);
$totalPages = ceil($totalNews / $perPage);
$categories = $newsController->getNewsCategories();

// Inclure l'entête
include "include/head.php";
?>

<!-- Page Header -->
<section class="page-header" style="background-image: url('uploads/back.jpg'); background-size: cover; position: relative;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6);"></div>
    <div class="container" style="position: relative; z-index: 2; padding: 80px 0; text-align: center;">
        <h1 style="color: white; font-size: 42px; margin-bottom: 15px;">Actualités</h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 0 auto;">
            Restez informé des dernières nouvelles et événements de l'ISTM BENI. Découvrez nos recherches, nos collaborations et nos projets.
        </p>
    </div>
</section>

<!-- Filtres et catégories -->
<div class="container" style="padding: 30px 0; border-bottom: 1px solid #eee;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <h3 style="margin-bottom: 15px;">Filtrer par catégorie</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="actualites" class="category-filter <?php echo !$category_id ? 'active' : ''; ?>" style="display: inline-block; padding: 8px 15px; border-radius: 30px; background-color: <?php echo !$category_id ? 'var(--primary-color)' : '#f1f1f1'; ?>; color: <?php echo !$category_id ? 'white' : '#333'; ?>; text-decoration: none; font-weight: 500;">
                    Toutes les actualités
                </a>
                
                <?php if (is_array($categories) && !empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <?php if (isset($category['id']) && isset($category['name'])): ?>
                            <a href="actualites?category=<?php echo htmlspecialchars($category['id']); ?>" class="category-filter <?php echo $category_id == $category['id'] ? 'active' : ''; ?>" style="display: inline-block; padding: 8px 15px; border-radius: 30px; background-color: <?php echo $category_id == $category['id'] ? 'var(--primary-color)' : '#f1f1f1'; ?>; color: <?php echo $category_id == $category['id'] ? 'white' : '#333'; ?>; text-decoration: none; font-weight: 500;">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucune catégorie disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div>
            <p>Affichage de <?php echo count($news); ?> sur <?php echo $totalNews; ?> actualités</p>
        </div>
    </div>
</div>


<!-- Liste des actualités -->
<section class="section news-listing">
    <div class="container">
        <?php if (empty($news)): ?>
            <div style="text-align: center; padding: 50px 0;">
                <h3>Aucune actualité trouvée</h3>
                <p>Il n'y a actuellement aucune actualité dans cette catégorie.</p>
                <a href="actualites" class="btn btn-primary">Voir toutes les actualités</a>
            </div>
        <?php else: ?>
            <div class="card-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px;">
                <?php foreach ($news as $item): ?>
                    <div class="card fade-in-element" style="border-radius: 10px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <div style="height: 200px; overflow: hidden;">
                            <img src=".<?php echo !empty($item['featured_image']) ? htmlspecialchars($item['featured_image']) : 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                        </div>
                        <span class="card-badge" style="position: absolute; top: 15px; left: 15px; background-color: var(--primary-color); color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: 500;"><?php echo htmlspecialchars($item['category_name'] ?? 'Actualité'); ?></span>
                        <div class="card-content" style="padding: 20px;">
                            <div class="card-date" style="color: #777; margin-bottom: 10px; font-size: 14px;"><i class="far fa-calendar-alt"></i> <?php echo date('d F Y', strtotime($item['published_at'])); ?></div>
                            <h3 class="card-title" style="font-size: 20px; margin-bottom: 10px;"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="card-description" style="color: #666; margin-bottom: 15px;"><?php echo htmlspecialchars($item['excerpt']); ?></p>
                            <a href="details_article&slug=<?php echo htmlspecialchars($item['slug']); ?>" class="card-link" style="color: var(--primary-color); font-weight: 500; display: inline-flex; align-items: center;">Lire la suite <i class="fas fa-arrow-right" style="margin-left: 5px;"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: center; margin-top: 50px;">
                    <?php 
                    $queryParams = ['category' => $category_id];
                    $queryString = $category_id ? '&category=' . $category_id : '';
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1) . $queryString; ?>" class="pagination-item" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin: 0 5px; border-radius: 50%; background-color: #f1f1f1; color: #333; text-decoration: none;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Afficher 5 pages maximum
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, max($page + 2, 5));
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <a href="?page=<?php echo $i . $queryString; ?>" class="pagination-item <?php echo $i == $page ? 'active' : ''; ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin: 0 5px; border-radius: 50%; background-color: <?php echo $i == $page ? 'var(--primary-color)' : '#f1f1f1'; ?>; color: <?php echo $i == $page ? 'white' : '#333'; ?>; text-decoration: none; font-weight: <?php echo $i == $page ? '600' : '400'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1) . $queryString; ?>" class="pagination-item" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin: 0 5px; border-radius: 50%; background-color: #f1f1f1; color: #333; text-decoration: none;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Abonnement à la newsletter -->
<section class="section newsletter-section" style="background-color: var(--primary-color); color: white; padding: 60px 0;">
    <div class="container" style="text-align: center;">
        <h2 style="color: white; margin-bottom: 20px;">Restez informé</h2>
        <p style="max-width: 600px; margin: 0 auto 30px; color: rgba(255,255,255,0.8);">Abonnez-vous à notre newsletter pour recevoir les dernières actualités et informations sur l'ISTM BENI directement dans votre boîte de réception.</p>
        
        <div style="max-width: 500px; margin: 0 auto;">
                <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info">
                <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>

            <form action="controller/newsletter.php" method="post" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <input type="email" name="email" placeholder="Votre adresse email" required style="flex: 1; min-width: 200px; padding: 12px 15px; border-radius: 5px; border: none; outline: none;">
                <button type="submit" class="btn" style="background-color: var(--secondary-color); color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">S'abonner</button>
            </form>
            <p style="margin-top: 15px; font-size: 14px; color: rgba(255,255,255,0.7);">En vous abonnant, vous acceptez notre politique de confidentialité. Vous pouvez vous désabonner à tout moment.</p>
        </div>
    </div>
</section>

<!-- Actualités connexes -->
<section class="section related-posts" style="background-color: #f8f9fa; padding: 80px 0;">
    <div class="container">
        <h2 class="section-title" style="text-align: center; margin-bottom: 50px;">Articles populaires</h2>
        
        <div style="display: flex; flex-wrap: wrap; gap: 30px; justify-content: center;">
            <?php 
            // Récupérer quelques actualités mises en avant
            $featuredNews = $newsController->getAllNews(1, 3, null, true);
            
            foreach ($featuredNews as $featured): 
            ?>
                <div style="flex: 1; min-width: 300px; max-width: 350px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05);">
                    <img src=".<?php echo !empty($featured['featured_image']) ? htmlspecialchars($featured['featured_image']) : 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>" alt="<?php echo htmlspecialchars($featured['title']); ?>" style="width: 100%; height: 180px; object-fit: cover;">
                    <div style="padding: 20px;">
                        <span style="display: inline-block; padding: 5px 10px; background-color: rgba(0,51,102,0.1); color: var(--primary-color); border-radius: 5px; font-size: 12px; margin-bottom: 10px;"><?php echo htmlspecialchars($featured['category_name'] ?? 'Actualité'); ?></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;"><?php echo htmlspecialchars($featured['title']); ?></h3>
                        <a href="actualite/<?php echo htmlspecialchars($featured['slug']); ?>" style="color: var(--primary-color); font-weight: 500; display: inline-flex; align-items: center;">Lire l'article <i class="fas fa-arrow-right" style="margin-left: 5px;"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php 
include "include/footer.php";
?>
