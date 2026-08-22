<?php 
include "include/head.php";

// Récupération des données du personnel
$db = Connexion::getInstance()->getPDO();

// Paramètres de filtre et tri
$departmentFilter = isset($_GET['department']) ? $_GET['department'] : null;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : null;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name'; // Par défaut, tri par nom

// Construction de la requête de base
$query = "SELECT s.*, d.slug as department_slug 
          FROM staff s 
          LEFT JOIN departments d ON s.department = d.name
          WHERE s.is_active = 1";
$params = [];

// Ajout des filtres à la requête
if ($departmentFilter) {
    $query .= " AND s.department = :department";
    $params[':department'] = $departmentFilter;
}

if ($searchQuery) {
    $query .= " AND (s.full_name LIKE :search OR s.position LIKE :search OR s.department LIKE :search OR s.bio LIKE :search OR s.expertise LIKE :search)";
    $params[':search'] = "%{$searchQuery}%";
}

// Tri
switch ($sortBy) {
    case 'position':
        $query .= " ORDER BY s.position, s.full_name";
        break;
    case 'department':
        $query .= " ORDER BY s.department, s.full_name";
        break;
    case 'featured':
        $query .= " ORDER BY s.is_featured DESC, s.order_index, s.full_name";
        break;
    default: // name
        $query .= " ORDER BY s.full_name";
        break;
}

// Exécution de la requête
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des départements pour le filtre
$deptStmt = $db->query("SELECT DISTINCT name, slug FROM departments WHERE is_active = 1 ORDER BY name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Regrouper le personnel par département si nécessaire
$staffByDepartment = [];
$featuredStaff = [];

foreach ($allStaff as $staff) {
    if ($staff['is_featured']) {
        $featuredStaff[] = $staff;
    }
    
    if (!empty($staff['department'])) {
        if (!isset($staffByDepartment[$staff['department']])) {
            $staffByDepartment[$staff['department']] = [
                'name' => $staff['department'],
                'slug' => $staff['department_slug'] ?? '',
                'staff' => []
            ];
        }
        $staffByDepartment[$staff['department']]['staff'][] = $staff;
    } else {
        if (!isset($staffByDepartment['Autre'])) {
            $staffByDepartment['Autre'] = [
                'name' => 'Autre',
                'slug' => '',
                'staff' => []
            ];
        }
        $staffByDepartment['Autre']['staff'][] = $staff;
    }
}

// Trier les départements par nom
ksort($staffByDepartment);

// Déplacer "Autre" à la fin si présent
if (isset($staffByDepartment['Autre'])) {
    $autre = $staffByDepartment['Autre'];
    unset($staffByDepartment['Autre']);
    $staffByDepartment['Autre'] = $autre;
}
?>

<!-- En-tête de la page avec background et titre -->
<section class="page-header bg-primary text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold">Notre équipe</h1>
                <p class="lead">Découvrez les membres du personnel de l'ISTM BENI</p>
                
                
            </div>
        </div>
    </div>
    <div class="wave-bottom">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 150">
            <path fill="#ffffff" fill-opacity="1" d="M0,96L60,106.7C120,117,240,139,360,138.7C480,139,600,117,720,101.3C840,85,960,75,1080,74.7C1200,75,1320,85,1380,90.7L1440,96L1440,150L1380,150C1320,150,1200,150,1080,150C960,150,840,150,720,150C600,150,480,150,360,150C240,150,120,150,60,150L0,150Z"></path>
        </svg>
    </div>
</section>

<!-- Contenu principal -->
<section class="staff-content py-5">
    <div class="container">
        <!-- Introduction -->
        <div class="row mb-5">
            <div class="col-lg-10 mx-auto text-center">
                <h2 class="mb-4">Une équipe dévouée à votre succès académique</h2>
                <p class="lead">
                    L'ISTM BENI compte une équipe de professionnels qualifiés et expérimentés, dédiés à l'excellence académique 
                    et au développement des compétences de nos étudiants dans le domaine des sciences médicales et de la santé.
                </p>
            </div>
        </div>
        
        <!-- Filtres et recherche -->
        <div class="row mb-5">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <form action="" method="get" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Rechercher</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Nom, fonction, expertise..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="department" class="form-label">Département</label>
                                <select class="form-select" id="department" name="department" onchange="this.form.submit()">
                                    <option value="">Tous les départements</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>" <?php echo ($departmentFilter === $dept['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Trier par</label>
                                <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                                    <option value="name" <?php echo ($sortBy === 'name') ? 'selected' : ''; ?>>Nom</option>
                                    <option value="position" <?php echo ($sortBy === 'position') ? 'selected' : ''; ?>>Fonction</option>
                                    <option value="department" <?php echo ($sortBy === 'department') ? 'selected' : ''; ?>>Département</option>
                                    <option value="featured" <?php echo ($sortBy === 'featured') ? 'selected' : ''; ?>>Membres à la une</option>
                                </select>
                            </div>
                            <?php if (!empty($searchQuery) || !empty($departmentFilter) || $sortBy !== 'name'): ?>
                            <div class="col-12 mt-3">
                                <a href="/staff" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Réinitialiser les filtres
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Résultats de recherche si filtres actifs -->
        <?php if (!empty($searchQuery) || !empty($departmentFilter)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="alert alert-info">
                    <p class="mb-0">
                        <strong>Résultats de recherche:</strong> 
                        <?php echo count($allStaff); ?> membre(s) trouvé(s)
                        <?php if (!empty($searchQuery)): ?>
                            pour "<?php echo htmlspecialchars($searchQuery); ?>"
                        <?php endif; ?>
                        <?php if (!empty($departmentFilter)): ?>
                            dans le département "<?php echo htmlspecialchars($departmentFilter); ?>"
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Personnel à la une -->
        <?php if (!empty($featuredStaff) && empty($searchQuery) && empty($departmentFilter)): ?>
        <div class="staff-featured mb-5">
            <h3 class="border-bottom pb-2 mb-4 text-center">Personnel à la une</h3>
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
                <?php foreach ($featuredStaff as $staff): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 card-hover">
                        <div class="position-relative">
                            <?php if (!empty($staff['profile_image'])): ?>
                            <img src=".<?php echo htmlspecialchars($staff['profile_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" style="height: 240px; object-fit: cover;">
                            <?php else: ?>
                            <div class="bg-light text-center py-5" style="height: 240px;">
                                <i class="fas fa-user-tie fa-5x text-secondary"></i>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($staff['department'])): ?>
                            <span class="position-absolute top-0 end-0 badge bg-primary m-2">
                                <?php echo htmlspecialchars($staff['department']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                            <?php if (!empty($staff['position'])): ?>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($staff['position']); ?></p>
                            <?php endif; ?>
                            
                            <div class="staff-contact mb-3">
                                <?php if (!empty($staff['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-decoration-none me-2" title="Email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($staff['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>" class="text-decoration-none me-2" title="Téléphone">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php 
                                if (!empty($staff['social_links'])) {
                                    $socialLinks = json_decode($staff['social_links'], true);
                                    if (!empty($socialLinks)) {
                                        foreach ($socialLinks as $platform => $url) {
                                            $icon = '';
                                            switch ($platform) {
                                                case 'facebook': $icon = 'fab fa-facebook'; break;
                                                case 'twitter': $icon = 'fab fa-twitter'; break;
                                                case 'linkedin': $icon = 'fab fa-linkedin'; break;
                                                case 'instagram': $icon = 'fab fa-instagram'; break;
                                            }
                                            if ($icon) {
                                                echo '<a href="' . htmlspecialchars($url) . '" class="text-decoration-none me-2" title="' . ucfirst($platform) . '" target="_blank">';
                                                echo '<i class="' . $icon . '"></i>';
                                                echo '</a>';
                                            }
                                        }
                                    }
                                }
                                ?>
                            </div>
                            
                            <a href="staff_details&slug=<?php echo htmlspecialchars($staff['slug']); ?>" class="btn btn-primary px-4">
                                Voir le profil
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
                <!-- Affichage par département -->
                <?php if (empty($searchQuery) && empty($departmentFilter)): ?>
            <?php foreach ($staffByDepartment as $deptName => $deptData): ?>
            <div class="staff-department mb-5">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="border-bottom pb-2 mb-0">
                        <?php if (!empty($deptData['slug'])): ?>
                        <a href="department&slug=<?php echo htmlspecialchars($deptData['slug']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($deptName); ?>
                            <i class="fas fa-external-link-alt ms-2 small"></i>
                        </a>
                        <?php else: ?>
                        <?php echo htmlspecialchars($deptName); ?>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($deptData['slug'])): ?>
                    <a href="department&slug=<?php echo htmlspecialchars($deptData['slug']); ?>" class="btn btn-sm btn-outline-primary">
                        Voir le département
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($deptData['staff'] as $staff): ?>
                    <!-- N'afficher que les non-featured dans cette section -->
                    <?php if (!$staff['is_featured']): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 card-hover">
                            <div class="text-center pt-4">
                                <?php if (!empty($staff['profile_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($staff['profile_image']); ?>" class="rounded-circle" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" style="width: 100px; height: 100px; object-fit: cover;">
                                <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                    <i class="fas fa-user-tie fa-3x text-secondary"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body text-center">
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                                <?php if (!empty($staff['position'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($staff['position']); ?></p>
                                <?php endif; ?>
                                
                                <div class="staff-contact mb-3">
                                    <?php if (!empty($staff['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-decoration-none me-2" title="Email">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staff['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>" class="text-decoration-none me-2" title="Téléphone">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="/staff_details&slug=<?php echo htmlspecialchars($staff['slug']); ?>" class="btn btn-sm btn-outline-primary">
                                    Voir le profil
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        
        <?php else: ?>
        <!-- Affichage des résultats de recherche ou filtrage en mode grille -->
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
            <?php if (empty($allStaff)): ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Aucun membre du personnel ne correspond à votre recherche.
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($allStaff as $staff): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 card-hover">
                        <div class="text-center pt-4">
                            <?php if (!empty($staff['profile_image'])): ?>
                            <img src=".<?php echo htmlspecialchars($staff['profile_image']); ?>" class="rounded-circle" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                <i class="fas fa-user-tie fa-3x text-secondary"></i>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($staff['department'])): ?>
                            <span class="badge bg-primary mt-2">
                                <?php echo htmlspecialchars($staff['department']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                            <?php if (!empty($staff['position'])): ?>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($staff['position']); ?></p>
                            <?php endif; ?>
                            
                            <div class="staff-contact mb-3">
                                <?php if (!empty($staff['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-decoration-none me-2" title="Email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($staff['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>" class="text-decoration-none me-2" title="Téléphone">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <a href="/staff_details&slug=<?php echo htmlspecialchars($staff['slug']); ?>" class="btn btn-sm btn-outline-primary">
                                Voir le profil
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Pas de résultats si la liste est vide -->
        <?php if (empty($allStaff)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Aucun membre du personnel n'est disponible actuellement.
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pagination si nécessaire -->
        <?php if (count($allStaff) > 20): ?>
        <div class="row mt-5">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Précédent</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
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

<!-- Call-to-Action Section: Rejoindre l'équipe -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Rejoignez notre équipe</h2>
                <p class="lead mb-4">Vous êtes passionné par l'enseignement et la recherche en sciences médicales ? L'ISTM BENI recrute régulièrement des professionnels qualifiés pour rejoindre notre corps enseignant et administratif.</p>
                
            </div>
        </div>
    </div>
</section>

<!-- Styles spécifiques pour la page staff -->
<style>
/* Aspect général de la page */
.page-header {
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

/* Cartes du personnel */
.card-hover {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Staff contact icons */
.staff-contact a {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
    transition: all 0.2s ease;
}

.staff-contact a:hover {
    background-color: var(--primary-color);
    color: white;
}

/* Media queries */
@media (max-width: 991.98px) {
    .card-img-top {
        height: 200px !important;
    }
}

@media (max-width: 767.98px) {
    .page-header h1 {
        font-size: 2.2rem !important;
    }
    
    .card-img-top {
        height: 180px !important;
    }
}
</style>

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
    
    document.querySelectorAll('.card, .staff-department').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
    
    // Recherche instantanée (optionnel)
    const searchInput = document.getElementById('search');
    let typingTimer;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            if (this.value) {
                typingTimer = setTimeout(() => {
                    document.querySelector('form').submit();
                }, 1000); // Délai de 1 seconde après la dernière frappe
            }
        });
    }
});
</script>

<?php 
include "include/footer.php";
?>
