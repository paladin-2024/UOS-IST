<?php 
include "include/head.php";

// Initialize database connection
$db = Connexion::getInstance()->getPDO();

// Get filters from URL if any
$category = isset($_GET['category']) ? intval($_GET['category']) : null;
$level = isset($_GET['level']) ? $_GET['level'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Prepare base query
$query = "SELECT f.*, c.name as category_name, u.full_name as author_name 
          FROM formations f 
          LEFT JOIN categories c ON f.category_id = c.id 
          LEFT JOIN users u ON f.created_by = u.id 
          WHERE f.is_published = 1";

// Add filters to query if provided
$params = [];
if ($category) {
    $query .= " AND f.category_id = :category";
    $params[':category'] = $category;
}
if ($level) {
    $query .= " AND f.level = :level";
    $params[':level'] = $level;
}
if ($search) {
    $query .= " AND (f.title LIKE :search OR f.short_description LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add ordering
$query .= " ORDER BY f.is_featured DESC, f.published_at DESC";

// Prepare and execute the query
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter
$categoryStmt = $db->prepare("SELECT id, name FROM categories WHERE type = 'formation' ORDER BY name ASC");
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Niveaux de formation
$niveaux = [
    'licence' => 'Licence', 
    'master' => 'Master', 
    'doctorat' => 'Doctorat', 
    'formation_continue' => 'Formation continue'
];
?>

<!-- Hero Section -->
<section class="page-hero py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center text-white">
                <h1 class="display-4 fw-bold mb-3">Nos Formations</h1>
                <p class="lead mb-4">Découvrez l'ensemble des formations proposées par l'Institut Supérieur des Techniques Médicales de BENI</p>
                
                <!-- Search Form -->
                <div class="mt-4">
                    <form action="" method="get" class="d-flex justify-content-center">
                        <div class="input-group input-group-lg w-75">
                            <input type="text" name="search" class="form-control" placeholder="Rechercher une formation..." 
                                   value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button class="btn btn-light px-4" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
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

<!-- Formations Section -->
<section class="formations-list py-5">
    <div class="container">
        <!-- Filter Options -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <form action="" method="get" id="filterForm">
                            <div class="row g-3 align-items-center">
                                <!-- Category Filter -->
                                <div class="col-md-4">
                                    <label for="category" class="form-label mb-1">Catégorie</label>
                                    <select name="category" id="category" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                        <option value="">Toutes les catégories</option>
                                        <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Level Filter -->
                                <div class="col-md-4">
                                    <label for="level" class="form-label mb-1">Niveau</label>
                                    <select name="level" id="level" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                        <option value="">Tous les niveaux</option>
                                        <?php foreach($niveaux as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($level == $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Reset Filters -->
                                <div class="col-md-4 d-flex align-items-end">
                                    <?php if($category || $level || $search): ?>
                                    <a href="formations" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-sync-alt me-2"></i> Réinitialiser les filtres
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted d-block w-100 text-center">
                                        <?php echo count($formations); ?> formation(s) disponible(s)
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Keep search term if present -->
                            <?php if($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Featured Formations (if any) -->
        <?php 
        $featuredFormations = array_filter($formations, function($f) { return $f['is_featured'] == 1; });
        if (!empty($featuredFormations) && empty($search) && empty($category) && empty($level)):
        ?>
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="fw-bold mb-4 border-bottom pb-2">Formations à la une</h2>
                <div class="row">
                    <?php foreach($featuredFormations as $formation): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <?php if($formation['featured_image']): ?>
                            <div class="card-img-top position-relative overflow-hidden" style="height: 200px;">
                                <img src=".<?php echo htmlspecialchars($formation['featured_image']); ?>" 
                                     class="img-fluid w-100 h-100" 
                                     alt="<?php echo htmlspecialchars($formation['title']); ?>"
                                     style="object-fit: cover;">
                                <div class="badge position-absolute top-0 end-0 m-2 bg-warning text-dark">
                                    <?php echo htmlspecialchars($niveaux[$formation['level']] ?? $formation['level']); ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="card-img-top position-relative overflow-hidden bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="fas fa-graduation-cap fa-3x text-muted"></i>
                                <div class="badge position-absolute top-0 end-0 m-2 bg-warning text-dark">
                                    <?php echo htmlspecialchars($niveaux[$formation['level']] ?? $formation['level']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($formation['title']); ?></h5>
                                <?php if($formation['category_name']): ?>
                                <span class="badge bg-light text-primary mb-2"><?php echo htmlspecialchars($formation['category_name']); ?></span>
                                <?php endif; ?>
                                
                                <?php if($formation['short_description']): ?>
                                <p class="card-text text-muted"><?php echo mb_substr(htmlspecialchars($formation['short_description']), 0, 120); ?>...</p>
                                <?php endif; ?>
                                
                                <div class="mt-auto pt-3 d-flex justify-content-between align-items-center">
                                    <?php if($formation['duration']): ?>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($formation['duration']); ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if($formation['credits']): ?>
                                    <small class="text-muted"><i class="fas fa-award me-1"></i> <?php echo htmlspecialchars($formation['credits']); ?> crédits</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-white border-top-0">
                                <a href="/formation&slug=<?php echo htmlspecialchars($formation['slug']); ?>" class="btn btn-primary w-100">
                                    En savoir plus <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- All Formations -->
        <div class="row">
            <div class="col-12">
                <?php if(empty($search) && empty($category) && empty($level)): ?>
                <h2 class="fw-bold mb-4 border-bottom pb-2">Toutes nos formations</h2>
                <?php elseif(!empty($search)): ?>
                <h2 class="fw-bold mb-4 border-bottom pb-2">Résultats de recherche pour "<?php echo htmlspecialchars($search); ?>"</h2>
                <?php elseif(!empty($category)): ?>
                <?php 
                $currentCategory = "";
                foreach($categories as $cat) {
                    if($cat['id'] == $category) {
                        $currentCategory = $cat['name'];
                        break;
                    }
                }
                ?>
                <h2 class="fw-bold mb-4 border-bottom pb-2">Formations en <?php echo htmlspecialchars($currentCategory); ?></h2>
                <?php elseif(!empty($level)): ?>
                <h2 class="fw-bold mb-4 border-bottom pb-2">Formations de niveau <?php echo htmlspecialchars($niveaux[$level]); ?></h2>
                <?php endif; ?>
                
                <?php if(empty($formations)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Aucune formation ne correspond à votre recherche. Veuillez modifier vos critères ou consulter toutes nos formations.
                </div>
                <?php else: ?>
                
                <!-- Grid View (default) -->
                <div class="row" id="grid-view">
                    <?php 
                    // If we displayed featured formations, filter them out
                    $displayedFormations = (!empty($featuredFormations) && empty($search) && empty($category) && empty($level)) 
                        ? array_filter($formations, function($f) { return $f['is_featured'] != 1; })
                        : $formations;
                    
                    foreach($displayedFormations as $formation): 
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm hover-card">
                            <?php if($formation['featured_image']): ?>
                            <div class="card-img-top position-relative overflow-hidden" style="height: 200px;">
                                <img src=".<?php echo htmlspecialchars($formation['featured_image']); ?>" 
                                     class="img-fluid w-100 h-100" 
                                     alt="<?php echo htmlspecialchars($formation['title']); ?>"
                                     style="object-fit: cover;">
                                <div class="badge position-absolute top-0 end-0 m-2 bg-warning text-dark">
                                    <?php echo htmlspecialchars($niveaux[$formation['level']] ?? $formation['level']); ?>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="card-img-top position-relative overflow-hidden bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="fas fa-graduation-cap fa-3x text-muted"></i>
                                <div class="badge position-absolute top-0 end-0 m-2 bg-warning text-dark">
                                    <?php echo htmlspecialchars($niveaux[$formation['level']] ?? $formation['level']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($formation['title']); ?></h5>
                                <?php if($formation['category_name']): ?>
                                <span class="badge bg-light text-primary mb-2"><?php echo htmlspecialchars($formation['category_name']); ?></span>
                                <?php endif; ?>
                                
                                <?php if($formation['short_description']): ?>
                                <p class="card-text text-muted"><?php echo mb_substr(htmlspecialchars($formation['short_description']), 0, 120); ?>...</p>
                                <?php endif; ?>
                                
                                <div class="mt-auto pt-3 d-flex justify-content-between align-items-center">
                                    <?php if($formation['duration']): ?>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($formation['duration']); ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if($formation['credits']): ?>
                                    <small class="text-muted"><i class="fas fa-award me-1"></i> <?php echo htmlspecialchars($formation['credits']); ?> crédits</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-white border-top-0">
                                <a href="/formation&slug=<?php echo htmlspecialchars($formation['slug']); ?>" class="btn btn-primary w-100">
                                    En savoir plus <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination (if needed) -->
        <?php if(count($formations) > 12): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Pagination des formations">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Précédent</a>
                        </li>
                        <li class="page-item active" aria-current="page">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">3</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">Suivant</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Formation Categories Section -->
<section class="formation-categories py-5 bg-light">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="fw-bold">Explorer par catégorie</h2>
                <p class="lead text-muted">Découvrez nos formations par domaine d'étude</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php foreach($categories as $index => $cat): ?>
            <div class="col-md-6 col-lg-3">
                <a href="/formations?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                    <div class="card hover-card h-100 text-center shadow-sm">
                        <div class="card-body py-4">
                            <div class="icon-wrapper rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                style="width: 80px; height: 80px; background-color: rgba(var(--primary-color-rgb), 0.1);">
                                <?php
                                // Using a fixed set of icons based on index to ensure variety
                                $icons = ['fa-heartbeat', 'fa-microscope', 'fa-pills', 'fa-stethoscope', 
                                          'fa-brain', 'fa-hospital', 'fa-procedures', 'fa-flask'];
                                $iconIndex = $index % count($icons);
                                ?>
                                <i class="fas <?php echo $icons[$iconIndex]; ?> fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($cat['name']); ?></h5>
                            <?php
                            // Count formations in this category
                            $countStmt = $db->prepare("SELECT COUNT(*) FROM formations WHERE category_id = :category_id AND is_published = 1");
                            $countStmt->bindParam(':category_id', $cat['id']);
                            $countStmt->execute();
                            $count = $countStmt->fetchColumn();
                            ?>
                            <p class="card-text small text-muted"><?php echo $count; ?> formation(s) disponible(s)</p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Formation Process Section -->
<section class="process-section py-5">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">Comment s'inscrire à nos formations ?</h2>
                <p class="lead text-muted">L'inscription à l'ISTM BENI se déroule en quelques étapes simples</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="process-icon rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white"
                                     style="width: 80px; height: 80px;">
                                    <span class="h3 mb-0 fw-bold">1</span>
                                </div>
                                <h5 class="card-title">Choisir</h5>
                                <p class="card-text small text-muted">Explorez nos formations et trouvez celle qui correspond à votre projet professionnel</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="process-icon rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white"
                                     style="width: 80px; height: 80px;">
                                    <span class="h3 mb-0 fw-bold">2</span>
                                </div>
                                <h5 class="card-title">Postuler</h5>
                                <p class="card-text small text-muted">Remplissez le formulaire de candidature en ligne ou à notre bureau d'inscription</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="process-icon rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white"
                                     style="width: 80px; height: 80px;">
                                    <span class="h3 mb-0 fw-bold">3</span>
                                </div>
                                <h5 class="card-title">Admission</h5>
                                <p class="card-text small text-muted">Votre dossier est examiné par notre comité d'admission qui vous contactera</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="process-icon rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white"
                                     style="width: 80px; height: 80px;">
                                    <span class="h3 mb-0 fw-bold">4</span>
                                </div>
                                <h5 class="card-title">S'inscrire</h5>
                                <p class="card-text small text-muted">Finalisez votre inscription en payant les frais de scolarité et rejoignez nos étudiants</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <a href="contact" class="btn btn-lg btn-primary px-4 py-2">
                        <i class="fas fa-file-alt me-2"></i> Processus d'admission complet
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call-to-Action -->
<section class="cta-section py-5 bg-primary text-white">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="mb-4">Vous avez des questions sur nos formations ?</h2>
                <p class="lead mb-4">Notre équipe pédagogique est disponible pour vous aider à choisir la formation qui vous correspond</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="contact" class="btn btn-lg btn-light px-4 me-sm-3">Nous contacter</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Custom style for this page -->
<style>
/* Card hover effect */
.hover-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Process section */
.process-icon {
    transition: all 0.3s ease;
}

.card:hover .process-icon {
    transform: scale(1.1);
}

/* Wave animation */
.wave-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    overflow: hidden;
    line-height: 0;
}

/* Override list style for filter elements */
.filter-list .form-check {
    margin-bottom: 0.5rem;
}

/* Animation for category cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.formation-categories .card {
    animation: fadeInUp 0.6s ease backwards;
}

.formation-categories .card:nth-child(1) {
    animation-delay: 0.1s;
}

.formation-categories .card:nth-child(2) {
    animation-delay: 0.2s;
}

.formation-categories .card:nth-child(3) {
    animation-delay: 0.3s;
}

.formation-categories .card:nth-child(4) {
    animation-delay: 0.4s;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .page-hero .lead {
        font-size: 1rem;
    }
    
    .page-hero .input-group {
        width: 100% !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter form auto-submit
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const selectElements = filterForm.querySelectorAll('select');
        selectElements.forEach(select => {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    }
    
    // Animate cards on scroll
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
    
    document.querySelectorAll('.hover-card').forEach(card => {
        card.classList.add('opacity-0');
        observer.observe(card);
    });
    
    // Add opacity class for animation
    document.querySelectorAll('.opacity-0').forEach((el, index) => {
        setTimeout(() => {
            el.classList.remove('opacity-0');
            el.classList.add('animate__animated', 'animate__fadeIn');
        }, 100 * index);
    });
});
</script>

<?php include "include/footer.php"; ?>
