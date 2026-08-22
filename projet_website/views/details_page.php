<?php 
include "include/head.php";

// Récupération des données de la page
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails de la page
$stmt = $db->prepare("SELECT p.*, u.full_name as author_name, u.id as author_id
                   FROM pages p 
                   LEFT JOIN users u ON p.created_by = u.id 
                   WHERE p.slug = :slug AND p.is_published = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$page = $stmt->fetch(PDO::FETCH_ASSOC);

// Si la page n'existe pas ou n'est pas publiée, rediriger
if (!$page) {
    header('Location: /');
    exit;
}

// Récupérer les pages populaires
$stmt = $db->prepare("SELECT p.id, p.title, p.slug, p.published_at 
                   FROM pages p 
                   WHERE p.is_published = 1 AND p.id != :page_id 
                   ORDER BY p.published_at DESC LIMIT 5");
$stmt->bindParam(':page_id', $page['id']);
$stmt->execute();
$popularPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ressources attachées à la page
$resources = [];
$stmt = $db->prepare("SELECT m.* 
                     FROM media m 
                     JOIN page_media pm ON m.id = pm.media_id 
                     WHERE pm.page_id = :page_id 
                     ORDER BY pm.order_index, m.title");
$stmt->bindParam(':page_id', $page['id']);
$stmt->execute();
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mise en forme de la date
if ($page['published_at']) {
    $publishedDate = new DateTime($page['published_at']);
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

// Calculer le temps de lecture estimé
$wordCount = str_word_count(strip_tags($page['content']));
$readingTime = max(1, ceil($wordCount / 200)); // 200 mots par minute en moyenne
?>

<!-- En-tête de la page avec background et titre -->
<section class="page-hero py-5" style="position: relative; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto text-center text-white">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb justify-content-center bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($page['title']); ?></li>
                    </ol>
                </nav>
                
                <h1 class="page-title display-4 fw-bold mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
                
                <?php if (!empty($page['meta_description'])): ?>
                <div class="page-excerpt fw-light">
                    <p class="lead fs-4 mb-0"><?php echo htmlspecialchars($page['meta_description']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="page-meta d-flex justify-content-center align-items-center mt-4">
                    <?php if ($page['published_at']): ?>
                    <span class="text-white opacity-75 me-3"><i class="far fa-calendar-alt me-1"></i> <?php echo $formattedDate; ?></span>
                    <?php endif; ?>
                    <?php if ($page['author_name']): ?>
                    <span class="text-white opacity-75 me-3"><i class="far fa-user me-1"></i> <?php echo htmlspecialchars($page['author_name']); ?></span>
                    <?php endif; ?>
                    <span class="text-white opacity-75"><i class="far fa-clock me-1"></i> <?php echo $readingTime; ?> min de lecture</span>
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
<section class="page-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu page -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Image principale de la page -->
                <?php if ($page['featured_image']): ?>
                <div class="page-featured-image mb-5 rounded shadow-sm overflow-hidden">
                    <img src=".<?php echo htmlspecialchars($page['featured_image']); ?>" alt="<?php echo htmlspecialchars($page['title']); ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <!-- Table des matières -->
                <div class="page-toc card mb-4 d-lg-none">
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
                
                <!-- Corps de la page -->
                <div class="page-body mb-5 content-wrapper">
                    <?php echo $page['content']; ?>
                </div>
                
                <!-- Ressources téléchargeables -->
                <?php if (!empty($resources)): ?>
                <div class="page-resources mb-5">
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-download text-primary me-2"></i>Documents associés</h5>
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
                
                <!-- Auteur de la page (si disponible) -->
                <?php if ($page['author_name']): ?>
                <div class="page-author mb-5">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="author-avatar me-3">
                                    <?php if (isset($page['author_id'])): ?>
                                    <img src="/assets/img/avatars/user<?php echo $page['author_id']; ?>.jpg" 
                                         onerror="this.src='/assets/img/avatars/default.jpg'" 
                                         alt="<?php echo htmlspecialchars($page['author_name']); ?>"
                                         class="rounded-circle" width="80" height="80">
                                    <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px; background-color: var(--primary-color); color: white;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="author-info">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($page['author_name']); ?></h5>
                                    <p class="text-muted mb-2">Dernière mise à jour le <?php echo $formattedDate; ?></p>
                                    <p class="mb-0 small">Membre de l'équipe ISTM BENI</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Partager la page -->
                <div class="page-share mb-5">
                    <h5 class="text-uppercase fs-6 text-muted mb-3">Partager cette page</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary btn-sm" aria-label="Partager sur Facebook">
                            <i class="fab fa-facebook-f me-2"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($page['title']); ?>" target="_blank" class="btn btn-outline-info btn-sm" aria-label="Partager sur Twitter">
                            <i class="fab fa-twitter me-2"></i> Twitter
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($page['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-success btn-sm" aria-label="Partager sur WhatsApp">
                            <i class="fab fa-whatsapp me-2"></i> WhatsApp
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($page['title']); ?>&body=<?php echo urlencode('Découvrez cette page intéressante : https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-secondary btn-sm" aria-label="Partager par email">
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
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
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
                    
                    <!-- Informations sur la page -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Informations</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-calendar-alt text-primary me-2"></i> Dernière mise à jour</span>
                                    <span class="badge bg-light text-dark"><?php echo $formattedDate; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="far fa-clock text-primary me-2"></i> Temps de lecture</span>
                                    <span class="badge bg-light text-dark"><?php echo $readingTime; ?> min</span>
                                </li>
                                <?php if ($page['template'] && $page['template'] != 'default'): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><i class="fas fa-file-alt text-primary me-2"></i> Type de page</span>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($page['template'])); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Contact/Support -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Besoin d'aide ?</h5>
                        </div>
                        <div class="card-body">
                            <p>Vous avez des questions concernant cette page ou vous souhaitez plus d'informations ?</p>
                            <a href="/contact" class="btn btn-primary w-100">
                                <i class="fas fa-envelope me-2"></i> Contactez-nous
                            </a>
                        </div>
                    </div>
                    
                    <!-- Pages connexes -->
                    <?php if (!empty($popularPages)): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Pages connexes</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($popularPages as $popularPage): ?>
                                <li class="list-group-item">
                                    <a href="/pages/<?php echo htmlspecialchars($popularPage['slug']); ?>" class="text-decoration-none text-dark d-block">
                                        <i class="fas fa-file-alt text-primary me-2"></i>
                                        <?php echo htmlspecialchars($popularPage['title']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Newsletter -->
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Restez informé</h5>
                            <p class="card-text">Recevez les dernières informations de l'ISTM BENI directement dans votre boîte mail.</p>
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

<!-- Call-to-Action Section -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Des questions sur nos programmes ?</h2>
                <p class="lead mb-4">Contactez-nous pour obtenir plus d'informations sur les formations et programmes proposés par l'ISTM BENI.</p>
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    <a href="contact" class="btn btn-lg btn-light px-4">
                        <i class="fas fa-envelope me-2"></i> Nous contacter
                    </a>
                    <a href="details_page&slug=formations" class="btn btn-lg btn-outline-light px-4">
                        <i class="fas fa-graduation-cap me-2"></i> Voir nos formations
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
    const pageBody = document.querySelector('.page-body');
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
            document.querySelectorAll('.page-toc').forEach(toc => {
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
    
    document.querySelectorAll('.card, .page-body > p, .page-body > h2, .page-body > h3, .page-body > ul, .page-body > ol, .page-body > blockquote').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
});
</script>

<!-- Styles spécifiques pour la page -->
<style>
/* Aspect général de la page */
.page-hero {
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

.page-body {
    font-family: 'Arial', sans-serif;
    font-size: 1.125rem;
    line-height: 1.8;
    color: #333;
}

.page-body h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.page-body h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #444;
}

.page-body h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #555;
}

.page-body p {
    margin-bottom: 1.5rem;
}

.page-body ul, .page-body ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.page-body li {
    margin-bottom: 0.5rem;
}

.page-body blockquote {
    font-style: italic;
    position: relative;
    padding: 1.5rem 2rem;
    margin: 1.5rem 0;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 0.25rem;
}

.page-body blockquote::before {
    content: "\201C";
    font-family: Georgia, serif;
    font-size: 3rem;
    position: absolute;
    left: 0.5rem;
    top: -0.5rem;
    color: rgba(var(--primary-color-rgb), 0.2);
}

.page-body a {
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
    transition: all 0.2s ease;
}

.page-body a:hover {
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
    .page-title {
        font-size: 2.2rem !important;
    }
    
    .page-body {
        font-size: 1rem;
    }
    
    .page-body h2 {
        font-size: 1.6rem;
    }
    
    .page-body h3 {
        font-size: 1.4rem;
    }
    
    .page-body h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 767.98px) {
    .page-title {
        font-size: 1.8rem !important;
    }
    
    .page-hero {
        padding: 2rem 0;
    }
    
    .page-meta {
        flex-wrap: wrap;
    }
    
    .page-meta > span {
        margin-bottom: 0.5rem;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .cta-section, .page-share, .sharing-buttons {
        display: none !important;
    }
    
    .page-body {
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .page-body a {
        font-weight: bold;
        text-decoration: none;
        color: #000 !important;
        border: none;
    }
    
    .page-body a::after {
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
    
    .page-title {
        font-size: 24pt !important;
    }
    
    .page-body img {
        max-height: 300px;
    }
}
</style>

<?php 
include "include/footer.php";
?>

