<?php 
include "include/head.php";

// Récupération des données du département
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /departments');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails du département
$stmt = $db->prepare("SELECT d.*, s.full_name as head_name, s.slug as head_slug, s.profile_image as head_image, 
                    s.position as head_position, s.email as head_email
                    FROM departments d 
                    LEFT JOIN staff s ON d.head_id = s.id 
                    WHERE d.slug = :slug AND d.is_active = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le département n'existe pas ou n'est pas actif, rediriger
if (!$department) {
    header('Location: /departments');
    exit;
}

// Récupérer les membres du personnel de ce département
$stmt = $db->prepare("SELECT id, full_name, slug, position, email, profile_image, expertise, is_featured
                   FROM staff 
                   WHERE department = :department_name AND is_active = 1
                   ORDER BY is_featured DESC, order_index ASC, full_name ASC");
$stmt->bindParam(':department_name', $department['name']);
$stmt->execute();
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les formations liées à ce département (si la relation existe)
$formations = [];
try {
    $stmt = $db->prepare("SELECT id, title, slug, short_description, featured_image, level 
                      FROM formations 
                      WHERE category_id IN (SELECT id FROM categories WHERE name = :department_name) 
                      AND is_published = 1 
                      ORDER BY is_featured DESC, title ASC");
    $stmt->bindParam(':department_name', $department['name']);
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer si la structure n'existe pas
}

// Récupérer les autres départements pour la sidebar
$stmt = $db->prepare("SELECT id, name, slug, featured_image 
                   FROM departments 
                   WHERE id != :dept_id AND is_active = 1 
                   ORDER BY name ASC");
$stmt->bindParam(':dept_id', $department['id']);
$stmt->execute();
$otherDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- En-tête du département avec background et titre -->
<section class="department-hero position-relative bg-primary text-white py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="/departments" class="text-white opacity-75">Départements</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($department['name']); ?></li>
                    </ol>
                </nav>
                
                <h2 class="department-title display-4 fw-bold mb-3"><?php echo htmlspecialchars($department['name']); ?></h2>
                
                <?php if (!empty($department['head_name'])): ?>
                <div class="department-meta d-flex flex-wrap align-items-center mb-3 gap-3">
                    <span class="text-white me-3">
                        <i class="fas fa-user-tie me-1"></i> Chef de département: 
                        <a href="staff_details&slug=<?php echo htmlspecialchars($department['head_slug']); ?>" class="text-white">
                            <?php echo htmlspecialchars($department['head_name']); ?>
                        </a>
                    </span>
                    
                    <?php if (!empty($department['head_position'])): ?>
                    <span class="text-white me-3">
                        <i class="fas fa-id-badge me-1"></i> <?php echo htmlspecialchars($department['head_position']); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($department['head_email'])): ?>
                    <span class="text-white me-3">
                        <i class="fas fa-envelope me-1"></i> 
                        <a href="mailto:<?php echo htmlspecialchars($department['head_email']); ?>" class="text-white">
                            <?php echo htmlspecialchars($department['head_email']); ?>
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
<section class="department-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu département -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Image principale du département -->
                <?php if ($department['featured_image']): ?>
                <div class="department-featured-image mb-4 rounded shadow-sm overflow-hidden">
                    <img src=".<?php echo htmlspecialchars($department['featured_image']); ?>" alt="<?php echo htmlspecialchars($department['name']); ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <!-- Présentation du département -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Présentation</h4>
                    </div>
                    <div class="card-body content-wrapper">
                        <?php 
                        if (!empty($department['description'])) {
                            echo $department['description'];
                        } else {
                            echo '<p class="text-muted">Aucune information disponible pour ce département.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Équipe du département -->
                <div class="department-team mb-5">
                    <h4 class="border-bottom pb-2 mb-4">Équipe du département</h4>
                    
                    <?php if (empty($staffMembers)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Aucun membre du personnel n'est actuellement assigné à ce département.
                    </div>
                    <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($staffMembers as $staff): ?>
                        <div class="col">
                            <div class="card h-100 border-0 shadow-sm staff-card">
                                <div class="row g-0">
                                    <div class="col-md-4">
                                        <?php if (!empty($staff['profile_image'])): ?>
                                        <img src=".<?php echo htmlspecialchars($staff['profile_image']); ?>" class="img-fluid rounded-start staff-img" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" style="height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                        <div class="staff-placeholder bg-light d-flex align-items-center justify-content-center h-100 rounded-start">
                                            <i class="fas fa-user fa-2x text-secondary"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body">
                                            <h5 class="card-title mb-1">
                                                <?php if ($staff['is_featured']): ?>
                                                <span class="badge bg-primary me-1">Staff</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($staff['full_name']); ?>
                                            </h5>
                                            <?php if (!empty($staff['position'])): ?>
                                            <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($staff['position']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($staff['expertise'])): ?>
                                            <p class="card-text small">
                                                <strong>Spécialités:</strong> <?php echo htmlspecialchars(substr(strip_tags($staff['expertise']), 0, 100)) . (strlen(strip_tags($staff['expertise'])) > 100 ? '...' : ''); ?>
                                            </p>
                                            <?php endif; ?>
                                            
                                            <a href="staff_details&slug=<?php echo htmlspecialchars($staff['slug']); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                Voir le profil <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Formations liées au département -->
                <?php if (!empty($formations)): ?>
                <div class="department-courses mb-5">
                    <h4 class="border-bottom pb-2 mb-4">Formations</h4>
                    
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($formations as $formation): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0 course-card">
                                <?php if (!empty($formation['featured_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($formation['featured_image']); ?>" class="card-img-top course-img" alt="<?php echo htmlspecialchars($formation['title']); ?>" style="height: 160px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title"><?php echo htmlspecialchars($formation['title']); ?></h5>
                                        <?php 
                                        $levelLabel = '';
                                        $levelClass = '';
                                        switch($formation['level']) {
                                            case 'licence':
                                                $levelLabel = 'Licence';
                                                $levelClass = 'bg-primary';
                                                break;
                                            case 'master':
                                                $levelLabel = 'Master';
                                                $levelClass = 'bg-success';
                                                break;
                                            case 'doctorat':
                                                $levelLabel = 'Doctorat';
                                                $levelClass = 'bg-info';
                                                break;
                                            case 'formation_continue':
                                                $levelLabel = 'Formation continue';
                                                $levelClass = 'bg-warning text-dark';
                                                break;
                                        }
                                        ?>
                                        <?php if ($levelLabel): ?>
                                        <span class="badge <?php echo $levelClass; ?>"><?php echo $levelLabel; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr(strip_tags($formation['short_description']), 0, 120)) . (strlen(strip_tags($formation['short_description'])) > 120 ? '...' : ''); ?>
                                    </p>
                                    
                                    <a href="/formation&slug=<?php echo htmlspecialchars($formation['slug']); ?>" class="btn btn-sm btn-outline-primary">
                                        Détails de la formation <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Contact du département -->
                <div class="department-contact mb-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Contactez le département</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                <h6 class="text-primary mb-3">Informations de contact</h6>
                                    <p>
                                        <?php if (!empty($department['head_name'])): ?>
                                        <strong>Chef de département:</strong> <?php echo htmlspecialchars($department['head_name']); ?><br>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($department['head_email'])): ?>
                                        <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($department['head_email']); ?>"><?php echo htmlspecialchars($department['head_email']); ?></a><br>
                                        <?php endif; ?>
                                        
                                        <strong>Téléphone:</strong> +243 xxx xxx xxx<br>
                                        <strong>Bureau:</strong> Bâtiment principal, <?php echo htmlspecialchars($department['name']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <a href="/contact" class="btn btn-outline-primary">
                                            <i class="fas fa-envelope me-2"></i>Formulaire de contact
                                        </a>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Horaires d'ouverture</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="ps-0"><strong>Lundi - Vendredi:</strong></td>
                                                <td>8h00 - 16h00</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0"><strong>Samedi:</strong></td>
                                                <td>8h00 - 12h00</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0"><strong>Dimanche:</strong></td>
                                                <td>Fermé</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
                    <!-- Carte du chef de département -->
                    <?php if (!empty($department['head_name'])): ?>
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Chef de département</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <?php if (!empty($department['head_image'])): ?>
                                    <img src=".<?php echo htmlspecialchars($department['head_image']); ?>" class="rounded-circle" alt="<?php echo htmlspecialchars($department['head_name']); ?>" width="80" height="80" style="object-fit: cover;">
                                    <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fas fa-user-tie fa-2x text-secondary"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($department['head_name']); ?></h5>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($department['head_position'] ?? 'Chef de département'); ?></p>
                                    <a href="staff_details&slug=<?php echo htmlspecialchars($department['head_slug']); ?>" class="btn btn-sm btn-outline-primary">
                                        Voir le profil <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statistiques du département -->
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Chiffres clés</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-primary mb-0"><?php echo count($staffMembers); ?></h3>
                                        <p class="text-muted mb-0">Enseignants</p>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-primary mb-0"><?php echo count($formations); ?></h3>
                                        <p class="text-muted mb-0">Formations</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-primary mb-0">150+</h3>
                                        <p class="text-muted mb-0">Étudiants</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-primary mb-0">85%</h3>
                                        <p class="text-muted mb-0">Taux de réussite</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Autres départements -->
                    <?php if (!empty($otherDepartments)): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Autres départements</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($otherDepartments as $otherDept): ?>
                                <li class="list-group-item">
                                    <a href="/department&slug=<?php echo htmlspecialchars($otherDept['slug']); ?>" class="text-decoration-none text-dark d-flex align-items-center">
                                        <?php if (!empty($otherDept['featured_image'])): ?>
                                        <div class="flex-shrink-0 me-3">
                                            <img src=".<?php echo htmlspecialchars($otherDept['featured_image']); ?>" class="rounded" alt="<?php echo htmlspecialchars($otherDept['name']); ?>" width="40" height="40" style="object-fit: cover;">
                                        </div>
                                        <?php else: ?>
                                        <div class="flex-shrink-0 me-3 bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-building text-secondary"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <?php echo htmlspecialchars($otherDept['name']); ?>
                                        </div>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="/departments" class="btn btn-sm btn-link text-decoration-none">Voir tous les départements</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Téléchargements -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Documents utiles</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="#" class="text-decoration-none text-dark d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <i class="fas fa-file-pdf text-danger fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            Programme académique <?php echo date('Y'); ?>
                                        </div>
                                        <i class="fas fa-download ms-2 text-muted"></i>
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#" class="text-decoration-none text-dark d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <i class="fas fa-file-alt text-primary fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            Règlement du département
                                        </div>
                                        <i class="fas fa-download ms-2 text-muted"></i>
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#" class="text-decoration-none text-dark d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <i class="fas fa-file-powerpoint text-warning fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            Présentation du département
                                        </div>
                                        <i class="fas fa-download ms-2 text-muted"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Événements liés au département -->
<section class="department-events py-5 bg-light">
    <div class="container">
        <h3 class="text-center mb-4">Événements à venir</h3>
        <p class="text-center text-muted mb-5">Découvrez les prochains événements organisés par le département <?php echo htmlspecialchars($department['name']); ?></p>
        
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- À remplacer par de vrais événements si disponibles -->
                <div class="text-center">
                    <p class="text-muted">Aucun événement planifié pour le moment.</p>
                    <a href="/evenements" class="btn btn-outline-primary mt-2">
                        Voir tous les événements de l'ISTM
                    </a>
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
                <h2 class="mb-4">Intéressé par les formations de ce département ?</h2>
                <p class="lead mb-4">Rejoignez l'ISTM BENI et bénéficiez d'une formation de qualité en sciences médicales dans le département <?php echo htmlspecialchars($department['name']); ?>.</p>
                <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                    <a href="pre-inscription" class="btn btn-lg btn-light">
                        Procédure d'inscription <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    <a href="contact" class="btn btn-lg btn-outline-light">
                        Nous contacter
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Back to all departments button -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center">
        <a href="/departments" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Tous les départements
        </a>
        <div class="sharing-buttons">
            <button class="btn btn-sm btn-outline-secondary me-2" id="copyLinkBtn" title="Copier le lien">
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
    
    // Bouton de copie de lien
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    
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
    
    // Amélioration des éléments de l'article
    const contentWrapper = document.querySelector('.content-wrapper');
    if (contentWrapper) {
        // Ajouter des classes Bootstrap aux images
        contentWrapper.querySelectorAll('img').forEach(img => {
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
        contentWrapper.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-striped', 'table-bordered', 'my-4');
            
            // Wrapper pour rendre les tableaux responsifs
            const tableResponsive = document.createElement('div');
            tableResponsive.className = 'table-responsive';
            table.parentNode.insertBefore(tableResponsive, table);
            tableResponsive.appendChild(table);
        });
        
        // Amélioration des liens
        contentWrapper.querySelectorAll('a').forEach(link => {
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
        contentWrapper.querySelectorAll('ul, ol').forEach(list => {
            list.classList.add('my-3');
            
            list.querySelectorAll('li').forEach(item => {
                item.classList.add('mb-2');
            });
        });
        
        // Amélioration des citations
        contentWrapper.querySelectorAll('blockquote').forEach(quote => {
            quote.classList.add('blockquote', 'border-start', 'border-primary', 'border-4', 'ps-4', 'py-2', 'my-4');
        });
    }
    
    // Animation des cartes staff et formations
    document.querySelectorAll('.staff-card, .course-card').forEach(card => {
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

<!-- Styles spécifiques pour la page de département -->
<style>
/* Aspect général du département */
.department-hero {
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

/* Cartes staff et formations */
.staff-card, .course-card {
    transition: all 0.3s ease;
}

.staff-img {
    height: 100%;
    object-fit: cover;
}

.staff-placeholder {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
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
    .department-title {
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
    .department-title {
        font-size: 1.8rem !important;
    }
    
    .department-hero {
        padding: 2rem 0;
    }
    
    .department-meta {
        flex-wrap: wrap;
    }
    
    .department-meta > span {
        margin-bottom: 0.5rem;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .cta-section, .department-events, .sharing-buttons {
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
    
    .department-title {
        font-size: 24pt !important;
    }
    
    .department-featured-image img {
        max-height: 300px;
    }
}
</style>

<?php 
include "include/footer.php";
?>
