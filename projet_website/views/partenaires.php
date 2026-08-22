<?php 
include "include/head.php";

// Initialize database connection
$db = Connexion::getInstance()->getPDO();

// Get all active partners
$stmt = $db->prepare("SELECT * FROM partners WHERE is_active = 1 ORDER BY is_featured DESC, order_index ASC, name ASC");
$stmt->execute();
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group partners by type
$partnersByType = [
    'academique' => [],
    'hospitalier' => [],
    'recherche' => [],
    'financier' => [],
    'autre' => []
];

// Get featured partners
$featuredPartners = [];

foreach ($partners as $partner) {
    if ($partner['is_featured']) {
        $featuredPartners[] = $partner;
    }
    $partnersByType[$partner['partnership_type']][] = $partner;
}

// Translate partnership types
$typeTranslations = [
    'academique' => 'Partenaires académiques',
    'hospitalier' => 'Partenaires hospitaliers',
    'recherche' => 'Partenaires de recherche',
    'financier' => 'Partenaires financiers',
    'autre' => 'Autres partenaires'
];

// Type descriptions
$typeDescriptions = [
    'academique' => 'Universités et institutions académiques avec lesquelles nous collaborons pour des programmes d\'échange et de coopération.',
    'hospitalier' => 'Hôpitaux et centres de santé qui accueillent nos étudiants en stage et participent à leur formation pratique.',
    'recherche' => 'Organisations avec lesquelles nous menons des projets de recherche conjoints dans le domaine de la santé.',
    'financier' => 'Institutions qui soutiennent financièrement nos activités académiques et nos infrastructures.',
    'autre' => 'Diverses organisations qui contribuent au développement de notre institut.'
];

// Type icons
$typeIcons = [
    'academique' => 'fa-university',
    'hospitalier' => 'fa-hospital',
    'recherche' => 'fa-microscope',
    'financier' => 'fa-hand-holding-usd',
    'autre' => 'fa-handshake'
];
?>

<!-- Hero Section -->
<section class="page-hero py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center text-white">
                <h1 class="display-4 fw-bold mb-3">Nos Partenaires</h1>
                <p class="lead mb-4">Découvrez les institutions et organisations qui collaborent avec l'ISTM BENI pour offrir une formation de qualité et soutenir nos activités.</p>
            </div>
        </div>
    </div>
    <div class="wave-bottom">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 150">
            <path fill="#ffffff" fill-opacity="1" d="M0,96L60,106.7C120,117,240,139,360,138.7C480,139,600,117,720,101.3C840,85,960,75,1080,74.7C1200,75,1320,85,1380,90.7L1440,96L1440,150L1380,150C1320,150,1200,150,1080,150C960,150,840,150,720,150C600,150,480,150,360,150C240,150,120,150,60,150L0,150Z"></path>
        </svg>
    </div>
</section>

<!-- Featured Partners Section (if any) -->
<?php if (!empty($featuredPartners)): ?>
<section class="featured-partners py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="fw-bold">Partenaires principaux</h2>
                <p class="lead text-muted">Nos collaborateurs privilégiés qui contribuent significativement au développement de notre institut</p>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <?php foreach ($featuredPartners as $partner): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm partner-card">
                    <div class="card-body text-center p-4">
                        <div class="partner-logo mb-3">
                            <?php if ($partner['logo']): ?>
                            <img src=".<?php echo htmlspecialchars($partner['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($partner['name']); ?>" 
                                 class="img-fluid" style="max-height: 100px;">
                            <?php else: ?>
                            <div class="placeholder-logo d-flex align-items-center justify-content-center bg-light rounded" style="height: 100px;">
                                <i class="fas <?php echo $typeIcons[$partner['partnership_type']]; ?> fa-3x text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="card-title mb-2"><?php echo htmlspecialchars($partner['name']); ?></h4>
                        <span class="badge bg-primary mb-3"><?php echo $typeTranslations[$partner['partnership_type']]; ?></span>
                        
                        <?php if ($partner['description']): ?>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($partner['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($partner['website_url']): ?>
                        <a href="<?php echo htmlspecialchars($partner['website_url']); ?>" target="_blank" class="btn btn-outline-primary mt-3">
                            <i class="fas fa-external-link-alt me-2"></i> Visiter le site
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- All Partners by Type -->
<section class="all-partners py-5 bg-light">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="fw-bold">Nos partenaires par domaine</h2>
                <p class="lead text-muted">Explorez nos différents types de collaboration</p>
            </div>
        </div>
        
        <!-- Partners Tabs Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-pills nav-fill mb-4" id="partnersTab" role="tablist">
                    <?php $first = true; foreach ($partnersByType as $type => $typePartners): ?>
                    <?php if (!empty($typePartners)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $first ? 'active' : ''; ?>" 
                                id="<?php echo $type; ?>-tab" 
                                data-toggle="pill" 
                                data-target="#<?php echo $type; ?>" 
                                type="button" 
                                role="tab" 
                                aria-controls="<?php echo $type; ?>" 
                                aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                            <i class="fas <?php echo $typeIcons[$type]; ?> me-2"></i> <?php echo $typeTranslations[$type]; ?>
                        </button>
                    </li>
                    <?php $first = false; endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <!-- Partners Tabs Content -->
        <div class="tab-content" id="partnersTabContent">
            <?php $first = true; foreach ($partnersByType as $type => $typePartners): ?>
            <?php if (!empty($typePartners)): ?>
            <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                 id="<?php echo $type; ?>" 
                 role="tabpanel" 
                 aria-labelledby="<?php echo $type; ?>-tab">
                
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-white shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-wrapper me-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="fas <?php echo $typeIcons[$type]; ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $typeTranslations[$type]; ?></h3>
                                    </div>
                                </div>
                                <p class="mb-0"><?php echo $typeDescriptions[$type]; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <?php foreach ($typePartners as $partner): ?>
                    <?php if (!$partner['is_featured']): // Skip featured partners as they're already shown ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm partner-card">
                            <div class="card-body text-center p-3">
                                <div class="partner-logo mb-3">
                                    <?php if ($partner['logo']): ?>
                                    <img src=".<?php echo htmlspecialchars($partner['logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($partner['name']); ?>" 
                                         class="img-fluid" style="max-height: 80px;">
                                    <?php else: ?>
                                    <div class="placeholder-logo d-flex align-items-center justify-content-center bg-light rounded" style="height: 80px;">
                                        <i class="fas <?php echo $typeIcons[$type]; ?> fa-2x text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h5 class="card-title"><?php echo htmlspecialchars($partner['name']); ?></h5>
                                
                                <?php if ($partner['website_url']): ?>
                                <a href="<?php echo htmlspecialchars($partner['website_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-external-link-alt me-1"></i> Site web
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $first = false; endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Partnership Benefits -->
<section class="benefits-section py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">Les avantages du partenariat</h2>
                <p class="lead text-muted">Collaborer avec l'ISTM BENI offre de nombreux avantages aux institutions partenaires</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h4 class="card-title">Accès aux talents</h4>
                        <p class="card-text text-muted">Accédez à un vivier de professionnels de la santé qualifiés et formés selon les normes internationales.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-flask fa-2x"></i>
                        </div>
                        <h4 class="card-title">Innovation</h4>
                        <p class="card-text text-muted">Participez à des projets de recherche innovants dans le domaine de la santé et des sciences médicales.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-globe-africa fa-2x"></i>
                        </div>
                        <h4 class="card-title">Visibilité internationale</h4>
                        <p class="card-text text-muted">Gagnez en visibilité grâce à notre réseau international de partenaires académiques et hospitaliers.</p>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-sync-alt fa-2x"></i>
                        </div>
                        <h4 class="card-title">Échange d'expertises</h4>
                        <p class="card-text text-muted">Participez à des programmes d'échange de professeurs et d'experts pour enrichir mutuellement nos compétences.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-handshake fa-2x"></i>
                        </div>
                        <h4 class="card-title">Responsabilité sociale</h4>
                        <p class="card-text text-muted">Contribuez à l'amélioration du système de santé de la région à travers nos projets communs de développement.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="icon-wrapper rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-certificate fa-2x"></i>
                        </div>
                        <h4 class="card-title">Prestige</h4>
                        <p class="card-text text-muted">Associez votre image à une institution reconnue pour son excellence académique et la qualité de ses formations.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials from Partners (if any) -->
<section class="testimonials-section py-5 bg-light">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">Ce que disent nos partenaires</h2>
                <p class="lead text-muted">Découvrez les témoignages de nos partenaires sur notre collaboration</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <div class="testimonial-quote p-4 h-100 d-flex flex-column justify-content-center bg-primary text-white">
                                    <div class="quote-icon mb-3">
                                        <i class="fas fa-quote-left fa-3x opacity-25"></i>
                                    </div>
                                    <blockquote class="mb-0">
                                        <p class="mb-3">Notre partenariat avec l'ISTM BENI nous a permis d'accéder à des talents exceptionnels et de contribuer à l'amélioration du système de santé local. Une collaboration enrichissante pour nos deux institutions.</p>
                                        <footer class="blockquote-footer text-white-50">
                                            <cite title="Source Title">Dr. Marie Dubois, Directrice de l'Hôpital Général de Référence</cite>
                                        </footer>
                                    </blockquote>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="testimonial-quote p-4 h-100 d-flex flex-column justify-content-center">
                                    <div class="quote-icon mb-3 text-primary">
                                        <i class="fas fa-quote-left fa-3x opacity-25"></i>
                                    </div>
                                    <blockquote class="mb-0">
                                        <p class="mb-3">Les étudiants formés à l'ISTM BENI font preuve d'un niveau de compétence remarquable. Notre partenariat a permis de développer des programmes de stage pratique qui bénéficient autant aux étudiants qu'à notre institution.</p>
                                        <footer class="blockquote-footer text-muted">
                                            <cite title="Source Title">Prof. Jean-Paul Mulongo, Université de Kinshasa</cite>
                                        </footer>
                                    </blockquote>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="testimonial-quote p-4 h-100 d-flex flex-column justify-content-center bg-primary text-white">
                                    <div class="quote-icon mb-3">
                                        <i class="fas fa-quote-left fa-3x opacity-25"></i>
                                    </div>
                                    <blockquote class="mb-0">
                                        <p class="mb-3">Notre collaboration de recherche avec l'ISTM BENI a abouti à des publications significatives dans le domaine de la santé publique et des maladies tropicales. Un partenaire scientifique de premier ordre.</p>
                                        <footer class="blockquote-footer text-white-50">
                                            <cite title="Source Title">Dr. Sarah Ntumba, Directrice de Recherche, Institut National de Santé</cite>
                                        </footer>
                                    </blockquote>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Become a Partner CTA -->
<section class="become-partner-cta py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="row g-0">
                        <div class="col-md-7">
                            <div class="card-body p-5">
                                <h2 class="card-title fw-bold mb-3">Devenez notre partenaire</h2>
                                <p class="card-text lead">Vous souhaitez collaborer avec l'ISTM BENI et participer à la formation des professionnels de la santé de demain ?</p>
                                <p class="card-text">Nous sommes ouverts à de nouveaux partenariats académiques, hospitaliers, de recherche et financiers. Contactez-nous pour discuter des opportunités de collaboration.</p>
                                <a href="contact" class="btn btn-primary btn-lg mt-3">
                                    <i class="fas fa-handshake me-2"></i> Nous contacter
                                </a>
                            </div>
                        </div>
                        <div class="col-md-5 d-none d-md-block" style="background: url('/assets/img/partnership.jpg') center/cover; min-height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Partners Map Section (Optional) -->
<section class="partners-map-section py-5 bg-light">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">Notre réseau international</h2>
                <p class="lead text-muted">L'ISTM BENI collabore avec des partenaires à travers le monde</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="partners-map ratio ratio-21x9">
                            <!-- You can replace this with an actual interactive map if available -->
                            <img src="/assets/img/world-map.png" alt="Carte des partenaires" class="img-fluid" style="object-fit: cover;">
                            
                            <!-- Placeholder for map - replace with actual map implementation -->
                            <div class="map-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                <div class="text-center p-4 bg-white shadow rounded">
                                    <p class="mb-2"><i class="fas fa-map-marker-alt text-danger me-2"></i> Notre réseau compte des partenaires dans plus de 10 pays</p>
                                    <a href="#" class="btn btn-sm btn-primary">Voir le détail des pays</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Custom style for this page -->
<style>
/* Card hover effect */
.partner-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.partner-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Partner logo container */
.partner-logo {
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

/* Wave bottom */
.wave-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    overflow: hidden;
    line-height: 0;
}

/* Benefits hover effect */
.benefits-section .card {
    transition: all 0.3s ease;
}

.benefits-section .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.benefits-section .card:hover .icon-wrapper {
    transform: scale(1.1);
}

/* Icon wrapper transition */
.icon-wrapper {
    transition: all 0.3s ease;
}

/* Tab styling */
.nav-pills .nav-link {
    color: var(--bs-dark);
    background-color: #f8f9fa;
    margin: 0 0.2rem;
    border-radius: 0.5rem;
}

.nav-pills .nav-link.active {
    background-color: var(--primary-color);
    color: white;
}

@media (max-width: 767.98px) {
    .nav-pills .nav-link {
        margin-bottom: 0.5rem;
    }
}

/* Map overlay */
.map-overlay {
    background-color: rgba(255, 255, 255, 0.2);
}

/* Testimonial quotes */
.testimonial-quote {
    position: relative;
}

.testimonial-quote .quote-icon {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 0;
}

.testimonial-quote blockquote {
    position: relative;
    z-index: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    document.querySelectorAll('.partner-card, .benefits-section .card').forEach(card => {
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
