<?php 
include "include/head.php";

// Récupération des données du membre du personnel
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /staff');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails du membre du personnel
$stmt = $db->prepare("SELECT * FROM staff WHERE slug = :slug AND is_active = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le membre n'existe pas ou n'est pas actif, rediriger
if (!$staff) {
    header('Location: /staff');
    exit;
}

// Décoder les liens sociaux
$socialLinks = [];
if (!empty($staff['social_links'])) {
    $socialLinks = json_decode($staff['social_links'], true);
}

// Récupérer les autres membres du personnel du même département
$otherStaff = [];
if (!empty($staff['department'])) {
    $stmt = $db->prepare("SELECT id, full_name, slug, position, profile_image 
                      FROM staff 
                      WHERE department = :department AND id != :staff_id AND is_active = 1 
                      ORDER BY is_featured DESC, order_index ASC, full_name ASC 
                      LIMIT 4");
    $stmt->bindParam(':department', $staff['department']);
    $stmt->bindParam(':staff_id', $staff['id']);
    $stmt->execute();
    $otherStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les informations du département
$department = null;
if (!empty($staff['department'])) {
    $stmt = $db->prepare("SELECT id, name, slug FROM departments WHERE name = :name AND is_active = 1");
    $stmt->bindParam(':name', $staff['department']);
    $stmt->execute();
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les projets de recherche associés (si disponible)
$researchProjects = [];
try {
    $stmt = $db->prepare("SELECT rp.id, rp.title, rp.slug, rp.short_description, rp.featured_image, rpm.role 
                       FROM research_projects rp 
                       JOIN research_project_members rpm ON rp.id = rpm.project_id 
                       WHERE rpm.staff_id = :staff_id AND rp.is_published = 1 
                       ORDER BY rp.start_date DESC");
    $stmt->bindParam(':staff_id', $staff['id']);
    $stmt->execute();
    $researchProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer si la table n'existe pas
}

// Récupérer les publications associées (si disponible)
$publications = [];
try {
    $stmt = $db->prepare("SELECT * FROM publications WHERE author_id = :staff_id ORDER BY publish_date DESC LIMIT 5");
    $stmt->bindParam(':staff_id', $staff['id']);
    $stmt->execute();
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer si la table n'existe pas
}

// Définir la couleur d'arrière-plan basée sur le département (pour la personnalisation)
$departmentColors = [
    'Médecine' => 'primary',
    'Pharmacie' => 'success',
    'Laboratoire' => 'info',
    'Santé Publique' => 'warning',
    'Administration' => 'secondary',
    'Nutrition' => 'danger'
];

$bgColor = isset($departmentColors[$staff['department']]) ? $departmentColors[$staff['department']] : 'primary';
?>

<!-- En-tête du profil avec background et titre -->
<section class="staff-hero position-relative bg-<?php echo $bgColor; ?> text-white py-5" style="background: linear-gradient(135deg, var(--<?php echo $bgColor; ?>-color) 0%, var(--<?php echo $bgColor; ?>-dark) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="/staff" class="text-white opacity-75">Personnel</a></li>
                        <?php if ($department): ?>
                        <li class="breadcrumb-item"><a href="/department&slug=<?php echo htmlspecialchars($department['slug']); ?>" class="text-white opacity-75"><?php echo htmlspecialchars($department['name']); ?></a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($staff['full_name']); ?></li>
                    </ol>
                </nav>
                
                <div class="row align-items-center">
                    <div class="col-md-3 col-lg-3 mb-4 mb-md-0">
                        <div class="staff-profile-image text-center">
                            <?php if (!empty($staff['profile_image'])): ?>
                            <img src=".<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" class="img-fluid rounded-circle border border-4 border-white shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-white text-<?php echo $bgColor; ?> d-flex align-items-center justify-content-center mx-auto border border-4 border-white shadow-sm" style="width: 150px; height: 150px;">
                                <i class="fas fa-user-tie fa-5x"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-9 col-lg-9">
                        <h2 class="staff-title display-4 fw-bold mb-2"><?php echo htmlspecialchars($staff['full_name']); ?></h2>
                        
                        <?php if (!empty($staff['position'])): ?>
                        <h4 class="mb-3 fw-light"><?php echo htmlspecialchars($staff['position']); ?></h4>
                        <?php endif; ?>
                        
                        <div class="staff-meta d-flex flex-wrap align-items-center mb-3 gap-3">
                            <?php if (!empty($staff['department'])): ?>
                            <span class="badge bg-white text-<?php echo $bgColor; ?> fs-6 px-3 py-2">
                                <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($staff['department']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($staff['email'])): ?>
                            <span class="text-white">
                                <i class="fas fa-envelope me-1"></i> 
                                <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-white text-decoration-none">
                                    <?php echo htmlspecialchars($staff['email']); ?>
                                </a>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($staff['phone'])): ?>
                            <span class="text-white">
                                <i class="fas fa-phone me-1"></i> 
                                <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>" class="text-white text-decoration-none">
                                    <?php echo htmlspecialchars($staff['phone']); ?>
                                </a>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($socialLinks)): ?>
                        <div class="social-links mt-3">
                            <?php if (isset($socialLinks['facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($socialLinks['facebook']); ?>" class="btn btn-light btn-sm me-2" target="_blank">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (isset($socialLinks['twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($socialLinks['twitter']); ?>" class="btn btn-light btn-sm me-2" target="_blank">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (isset($socialLinks['linkedin'])): ?>
                            <a href="<?php echo htmlspecialchars($socialLinks['linkedin']); ?>" class="btn btn-light btn-sm me-2" target="_blank">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (isset($socialLinks['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($socialLinks['instagram']); ?>" class="btn btn-light btn-sm me-2" target="_blank">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
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
<section class="staff-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu profil -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Biographie -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Biographie</h4>
                    </div>
                    <div class="card-body content-wrapper">
                        <?php 
                        if (!empty($staff['bio'])) {
                            echo $staff['bio'];
                        } else {
                            echo '<p class="text-muted">Aucune information biographique disponible.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Expertise et Spécialités -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Expertise et Spécialités</h4>
                    </div>
                    <div class="card-body content-wrapper">
                        <?php 
                        if (!empty($staff['expertise'])) {
                            echo $staff['expertise'];
                        } else {
                            echo '<p class="text-muted">Aucune information d\'expertise disponible.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Projets de recherche -->
                <?php if (!empty($researchProjects)): ?>
                <div class="staff-research mb-5">
                    <h4 class="border-bottom pb-2 mb-4">Projets de recherche</h4>
                    
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($researchProjects as $project): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0 project-card">
                                <?php if (!empty($project['featured_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($project['featured_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($project['title']); ?>" style="height: 160px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                    </div>
                                    
                                    <?php if (!empty($project['role'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-<?php echo $bgColor; ?>">
                                            <?php echo htmlspecialchars($project['role']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr(strip_tags($project['short_description']), 0, 120)) . (strlen(strip_tags($project['short_description'])) > 120 ? '...' : ''); ?>
                                    </p>
                                    <a href="/research_project&slug=<?php echo htmlspecialchars($project['slug']); ?>" class="btn btn-sm btn-outline-<?php echo $bgColor; ?>">
                                        Voir le projet <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Publications -->
                <?php if (!empty($publications)): ?>
                <div class="staff-publications mb-5">
                    <h4 class="border-bottom pb-2 mb-4">Publications</h4>
                    
                    <div class="list-group">
                        <?php foreach ($publications as $publication): ?>
                        <div class="list-group-item list-group-item-action border-0 shadow-sm mb-3">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <h5 class="mb-2"><?php echo htmlspecialchars($publication['title']); ?></h5>
                                <small class="text-muted"><?php echo (new DateTime($publication['publish_date']))->format('Y'); ?></small>
                            </div>
                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($publication['journal'] ?? $publication['publisher']); ?></p>
                            <?php if (!empty($publication['abstract'])): ?>
                            <p class="mb-2"><?php echo htmlspecialchars(substr(strip_tags($publication['abstract']), 0, 200)) . (strlen(strip_tags($publication['abstract'])) > 200 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($publication['url'])): ?>
                            <a href="<?php echo htmlspecialchars($publication['url']); ?>" class="btn btn-sm btn-outline-<?php echo $bgColor; ?>" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> Lire la publication
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Contact -->
                <div class="staff-contact mb-5">
                    <h4 class="border-bottom pb-2 mb-3">Contact</h4>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <h6 class="text-<?php echo $bgColor; ?> mb-3">Coordonnées</h6>
                                    <ul class="list-unstyled mb-0">
                                        <?php if (!empty($staff['email'])): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-envelope me-2 text-<?php echo $bgColor; ?>"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>"><?php echo htmlspecialchars($staff['email']); ?></a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($staff['phone'])): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-phone me-2 text-<?php echo $bgColor; ?>"></i>
                                            <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>"><?php echo htmlspecialchars($staff['phone']); ?></a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($staff['department'])): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-building me-2 text-<?php echo $bgColor; ?>"></i>
                                            <?php if ($department): ?>
                                            <a href="/department&slug=<?php echo htmlspecialchars($department['slug']); ?>"><?php echo htmlspecialchars($staff['department']); ?></a>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($staff['department']); ?>
                                            <?php endif; ?>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <li>
                                            <i class="fas fa-map-marker-alt me-2 text-<?php echo $bgColor; ?>"></i>
                                            ISTM BENI, Bâtiment principal
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-<?php echo $bgColor; ?> mb-3">Horaires de consultation</h6>
                                    <ul class="list-unstyled mb-3">
                                        <li class="mb-2"><i class="fas fa-clock me-2 text-<?php echo $bgColor; ?>"></i> Lundi - Vendredi: 9h00 - 15h00</li>
                                        <li><i class="fas fa-info-circle me-2 text-<?php echo $bgColor; ?>"></i> Sur rendez-vous uniquement</li>
                                    </ul>
                                    
                                    <a href="/contact" class="btn btn-outline-<?php echo $bgColor; ?>">
                                        <i class="fas fa-envelope me-1"></i> Prendre rendez-vous
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
                    <!-- Carte de visite numérique -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Carte de visite</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-4 bg-light text-center">
                                <div class="qr-code mb-3">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" alt="QR Code" class="img-fluid">
                                </div>
                                <p class="mb-3 small text-muted">Scannez ce code QR pour sauvegarder les coordonnées de <?php echo htmlspecialchars($staff['full_name']); ?></p>
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-outline-primary" id="shareVcardBtn">
                                        <i class="fas fa-address-card me-1"></i> Partager vCard
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" id="copyContactBtn">
                                        <i class="fas fa-copy me-1"></i> Copier
                                    </button>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><i class="fas fa-user me-2 text-<?php echo $bgColor; ?>"></i> Nom:</span>
                                    <span class="text-muted"><?php echo htmlspecialchars($staff['full_name']); ?></span>
                                </li>
                                <?php if (!empty($staff['position'])): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><i class="fas fa-briefcase me-2 text-<?php echo $bgColor; ?>"></i> Fonction:</span>
                                    <span class="text-muted"><?php echo htmlspecialchars($staff['position']); ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($staff['email'])): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><i class="fas fa-envelope me-2 text-<?php echo $bgColor; ?>"></i> Email:</span>
                                    <span class="text-muted"><?php echo htmlspecialchars($staff['email']); ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($staff['phone'])): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><i class="fas fa-phone me-2 text-<?php echo $bgColor; ?>"></i> Téléphone:</span>
                                    <span class="text-muted"><?php echo htmlspecialchars($staff['phone']); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Autres membres du département -->
                    <?php if (!empty($otherStaff)): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <?php if ($department): ?>
                                Autres membres du département <?php echo htmlspecialchars($department['name']); ?>
                                <?php else: ?>
                                Autres membres du personnel
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($otherStaff as $colleague): ?>
                                <li class="list-group-item">
                                    <a href="/staff_details&slug=<?php echo htmlspecialchars($colleague['slug']); ?>" class="text-decoration-none text-dark d-flex align-items-center">
                                        <?php if (!empty($colleague['profile_image'])): ?>
                                        <div class="flex-shrink-0 me-3">
                                            <img src=".<?php echo htmlspecialchars($colleague['profile_image']); ?>" class="rounded-circle" alt="<?php echo htmlspecialchars($colleague['full_name']); ?>" width="40" height="40" style="object-fit: cover;">
                                        </div>
                                        <?php else: ?>
                                        <div class="flex-shrink-0 me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-secondary"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($colleague['full_name']); ?></h6>
                                            <?php if (!empty($colleague['position'])): ?>
                                            <span class="text-muted small"><?php echo htmlspecialchars($colleague['position']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if ($department): ?>
                        <div class="card-footer bg-white text-center">
                            <a href="/department&slug=<?php echo htmlspecialchars($department['slug']); ?>" class="btn btn-sm btn-link text-decoration-none">
                                Voir tous les membres de ce département
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Partager le profil -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Partager ce profil</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="fab fa-facebook me-2"></i> Partager sur Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Découvrez le profil de ' . $staff['full_name'] . ' à l\'ISTM BENI'); ?>" target="_blank" class="btn btn-outline-info">
                                    <i class="fab fa-twitter me-2"></i> Partager sur Twitter
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode('Découvrez le profil de ' . $staff['full_name'] . ' à l\'ISTM BENI: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-success">
                                    <i class="fab fa-whatsapp me-2"></i> Partager sur WhatsApp
                                </a>
                                <a href="mailto:?subject=<?php echo urlencode('Profil de ' . $staff['full_name'] . ' à l\'ISTM BENI'); ?>&body=<?php echo urlencode('Découvrez le profil de ' . $staff['full_name'] . ' sur le site de l\'ISTM BENI: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-envelope me-2"></i> Partager par email
                                </a>
                                <button class="btn btn-outline-dark" id="copyLinkBtn">
                                    <i class="fas fa-link me-2"></i> Copier le lien
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Back to staff button -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center">
        <a href="staff" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour à la liste du personnel
        </a>
        <div class="sharing-buttons">
            <button class="btn btn-sm btn-outline-secondary me-2" id="copyLinkBtn2" title="Copier le lien">
                <i class="fas fa-link"></i> Copier le lien
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Imprimer">
                <i class="fas fa-print"></i> Imprimer
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    document.querySelectorAll('.card, .content-wrapper > p, .content-wrapper > h2, .content-wrapper > h3, .content-wrapper > ul, .content-wrapper > ol, .content-wrapper > blockquote').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
    
    // Boutons de copie de lien
    function setupCopyButton(btnId) {
        const copyBtn = document.getElementById(btnId);
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const currentUrl = window.location.href;
                navigator.clipboard.writeText(currentUrl)
                    .then(() => {
                        // Changer temporairement le texte du bouton
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i> Lien copié';
                        this.classList.remove('btn-outline-secondary', 'btn-outline-dark');
                        this.classList.add('btn-success');
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.classList.remove('btn-success');
                            this.classList.add(btnId === 'copyLinkBtn' ? 'btn-outline-dark' : 'btn-outline-secondary');
                        }, 2000);
                    })
                    .catch(err => {
                        console.error('Erreur lors de la copie du lien:', err);
                    });
            });
        }
    }
    
    setupCopyButton('copyLinkBtn');
    setupCopyButton('copyLinkBtn2');
    
    // Partage de vCard
    const shareVcardBtn = document.getElementById('shareVcardBtn');
    if (shareVcardBtn) {
        shareVcardBtn.addEventListener('click', function() {
            // Créer un vCard
            const staffName = <?php echo json_encode($staff['full_name']); ?>;
            const staffEmail = <?php echo json_encode($staff['email'] ?? ''); ?>;
            const staffPhone = <?php echo json_encode($staff['phone'] ?? ''); ?>;
            const staffPosition = <?php echo json_encode($staff['position'] ?? ''); ?>;
            const staffDepartment = <?php echo json_encode($staff['department'] ?? ''); ?>;
            
            let vcard = `BEGIN:VCARD
VERSION:3.0
FN:${staffName}
ORG:ISTM BENI`;
            
            if (staffPosition) vcard += `\nTITLE:${staffPosition}`;
            if (staffDepartment) vcard += `\nX-DEPARTMENT:${staffDepartment}`;
            if (staffEmail) vcard += `\nEMAIL:${staffEmail}`;
            if (staffPhone) vcard += `\nTEL:${staffPhone}`;
            
            vcard += `\nURL:${window.location.href}
END:VCARD`;
            
            // Créer un Blob et un lien pour télécharger le vCard
            const blob = new Blob([vcard], { type: 'text/vcard' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${staffName.replace(/\s+/g, '_')}.vcf`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
    
    // Copier les informations de contact
    const copyContactBtn = document.getElementById('copyContactBtn');
    if (copyContactBtn) {
        copyContactBtn.addEventListener('click', function() {
            const staffName = <?php echo json_encode($staff['full_name']); ?>;
            const staffEmail = <?php echo json_encode($staff['email'] ?? ''); ?>;
            const staffPhone = <?php echo json_encode($staff['phone'] ?? ''); ?>;
            const staffPosition = <?php echo json_encode($staff['position'] ?? ''); ?>;
            const staffDepartment = <?php echo json_encode($staff['department'] ?? ''); ?>;
            
            let contactText = `Nom: ${staffName}\n`;
            if (staffPosition) contactText += `Fonction: ${staffPosition}\n`;
            if (staffDepartment) contactText += `Département: ${staffDepartment}\n`;
            if (staffEmail) contactText += `Email: ${staffEmail}\n`;
            if (staffPhone) contactText += `Téléphone: ${staffPhone}\n`;
            contactText += `Profil: ${window.location.href}`;
            
            navigator.clipboard.writeText(contactText)
                .then(() => {
                    // Changer temporairement le texte du bouton
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i> Copié';
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-primary');
                    }, 2000);
                })
                .catch(err => {
                    console.error('Erreur lors de la copie des informations:', err);
                });
        });
    }
    
    // Amélioration des éléments du contenu
    const contentWrappers = document.querySelectorAll('.content-wrapper');
    contentWrappers.forEach(wrapper => {
        if (!wrapper) return;
        
        // Ajouter des classes Bootstrap aux images
        wrapper.querySelectorAll('img').forEach(img => {
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
        wrapper.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-striped', 'table-bordered', 'my-4');
            
            // Wrapper pour rendre les tableaux responsifs
            const tableResponsive = document.createElement('div');
            tableResponsive.className = 'table-responsive';
            table.parentNode.insertBefore(tableResponsive, table);
            tableResponsive.appendChild(table);
        });
        
        // Amélioration des liens
        wrapper.querySelectorAll('a').forEach(link => {
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
        wrapper.querySelectorAll('ul, ol').forEach(list => {
            list.classList.add('my-3');
            
            list.querySelectorAll('li').forEach(item => {
                item.classList.add('mb-2');
            });
        });
        
        // Amélioration des citations
        wrapper.querySelectorAll('blockquote').forEach(quote => {
            quote.classList.add('blockquote', 'border-start', 'border-primary', 'border-4', 'ps-4', 'py-2', 'my-4');
        });
    });
    
    // Animation des cartes projet
    document.querySelectorAll('.project-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('shadow');
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('shadow');
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<!-- Styles spécifiques pour la page de profil staff -->
<style>
/* Aspect général du profil */
.staff-hero {
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

.content-wrapper {
    font-family: 'Arial', sans-serif;
    font-size: 1.125rem;
    line-height: 1.8;
    color: #333;
}

.content-wrapper h2 {
    font-family: 'Arial', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.content-wrapper h3 {
    font-family: 'Arial', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #444;
}

.content-wrapper h4 {
    font-family: 'Arial', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #555;
}

.content-wrapper p {
    margin-bottom: 1.5rem;
}

.content-wrapper ul, .content-wrapper ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.content-wrapper li {
    margin-bottom: 0.5rem;
}

.content-wrapper blockquote {
    font-style: italic;
    position: relative;
    padding: 1.5rem 2rem;
    margin: 1.5rem 0;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 0.25rem;
}

.content-wrapper blockquote::before {
    content: "\201C";
    font-family: Georgia, serif;
    font-size: 3rem;
    position: absolute;
    left: 0.5rem;
    top: -0.5rem;
    color: rgba(var(--primary-color-rgb), 0.2);
}

.content-wrapper a {
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
    transition: all 0.2s ease;
}

.content-wrapper a:hover {
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

/* Cartes de projet */
.project-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* QR Code */
.qr-code img {
    border: 1px solid #eee;
    padding: 5px;
    background: white;
}

/* Publications */
.list-group-item-action {
    transition: background-color 0.3s;
}

.list-group-item-action:hover {
    background-color: rgba(var(--primary-color-rgb), 0.05);
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

/* Media queries */
@media (max-width: 991.98px) {
    .staff-title {
        font-size: 2.2rem !important;
    }
    
    .content-wrapper {
        font-size: 1rem;
    }
    
    .content-wrapper h2 {
        font-size: 1.6rem;
    }
    
    .content-wrapper h3 {
        font-size: 1.4rem;
    }
    
    .content-wrapper h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 767.98px) {
    .staff-title {
        font-size: 1.8rem !important;
    }
    
    .staff-hero {
        padding: 2rem 0;
    }
    
    .staff-meta {
        flex-wrap: wrap;
    }
    
    .staff-meta > span {
        margin-bottom: 0.5rem;
    }
    
    .staff-profile-image {
        margin-bottom: 1.5rem;
    }
    
    .staff-profile-image img,
    .staff-profile-image .rounded-circle {
        width: 120px !important;
        height: 120px !important;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .sharing-buttons, .staff-hero .social-links, .wave-bottom {
        display: none !important;
    }
    
    .content-wrapper {
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .content-wrapper a {
        font-weight: bold;
        text-decoration: none;
        color: #000 !important;
        border: none;
    }
    
    .content-wrapper a::after {
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
    
    .staff-title {
        font-size: 24pt !important;
    }
    
    .staff-hero {
        background: none !important;
        color: #000 !important;
        padding: 0 !important;
    }
    
    .staff-hero h2, .staff-hero h4, .staff-hero .text-white {
        color: #000 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .badge {
        border: 1px solid #ddd;
        color: #000 !important;
        background: none !important;
    }
}
</style>

<?php 
include "include/footer.php";
?>
