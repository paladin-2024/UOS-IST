<?php 
include "include/head.php";

// Récupération des événements
$db = Connexion::getInstance()->getPDO();

// Paramètres de filtre et tri
$timeFilter = isset($_GET['period']) ? $_GET['period'] : 'upcoming'; // upcoming, past, all
$searchQuery = isset($_GET['search']) ? $_GET['search'] : null;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date'; // Par défaut, tri par date

// Construction de la requête de base
$query = "SELECT * FROM events WHERE is_published = 1";
$params = [];

// Ajout des filtres à la requête
if ($timeFilter == 'upcoming') {
    $query .= " AND start_date >= NOW()";
} elseif ($timeFilter == 'past') {
    $query .= " AND end_date < NOW()";
}

if ($searchQuery) {
    $query .= " AND (title LIKE :search OR description LIKE :search OR location LIKE :search OR content LIKE :search)";
    $params[':search'] = "%{$searchQuery}%";
}

// Tri
switch ($sortBy) {
    case 'title':
        $query .= " ORDER BY title";
        break;
    case 'location':
        $query .= " ORDER BY location, start_date";
        break;
    case 'featured':
        $query .= " ORDER BY is_featured DESC, start_date";
        break;
    default: // date
        $query .= " ORDER BY start_date";
        break;
}

// Exécution de la requête
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les événements à la une
$featuredEventsStmt = $db->query("SELECT * FROM events WHERE is_published = 1 AND is_featured = 1 ORDER BY start_date LIMIT 3");
$featuredEvents = $featuredEventsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- En-tête de la page avec background et titre -->
<section class="page-header bg-primary text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold">Événements</h1>
                <p class="lead">Découvrez les événements et activités de l'ISTM BENI</p>
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
<section class="events-content py-5">
    <div class="container">
        <!-- Introduction -->
        <div class="row mb-5">
            <div class="col-lg-10 mx-auto text-center">
                <h2 class="mb-4">Calendrier des événements de l'ISTM BENI</h2>
                <p class="lead">
                    Retrouvez toutes les informations sur nos conférences, séminaires, 
                    colloques, journées portes ouvertes et autres événements organisés par notre institution.
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
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Titre, lieu, description..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="period" class="form-label">Période</label>
                                <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                                    <option value="upcoming" <?php echo ($timeFilter === 'upcoming') ? 'selected' : ''; ?>>Événements à venir</option>
                                    <option value="past" <?php echo ($timeFilter === 'past') ? 'selected' : ''; ?>>Événements passés</option>
                                    <option value="all" <?php echo ($timeFilter === 'all') ? 'selected' : ''; ?>>Tous les événements</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Trier par</label>
                                <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                                    <option value="date" <?php echo ($sortBy === 'date') ? 'selected' : ''; ?>>Date</option>
                                    <option value="title" <?php echo ($sortBy === 'title') ? 'selected' : ''; ?>>Titre</option>
                                    <option value="location" <?php echo ($sortBy === 'location') ? 'selected' : ''; ?>>Lieu</option>
                                    <option value="featured" <?php echo ($sortBy === 'featured') ? 'selected' : ''; ?>>Événements à la une</option>
                                </select>
                            </div>
                            <?php if (!empty($searchQuery) || $timeFilter !== 'upcoming' || $sortBy !== 'date'): ?>
                            <div class="col-12 mt-3">
                                <a href="/evenements" class="btn btn-outline-secondary">
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
        <?php if (!empty($searchQuery) || $timeFilter !== 'upcoming'): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="alert alert-info">
                    <p class="mb-0">
                        <strong>Résultats de recherche:</strong> 
                        <?php echo count($allEvents); ?> événement(s) trouvé(s)
                        <?php if (!empty($searchQuery)): ?>
                            pour "<?php echo htmlspecialchars($searchQuery); ?>"
                        <?php endif; ?>
                        <?php if ($timeFilter === 'past'): ?>
                            parmi les événements passés
                        <?php elseif ($timeFilter === 'all'): ?>
                            parmi tous les événements
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Événements à la une -->
        <?php if (!empty($featuredEvents) && empty($searchQuery) && $timeFilter === 'upcoming'): ?>
        <div class="events-featured mb-5">
            <h3 class="border-bottom pb-2 mb-4 text-center">Événements à la une</h3>
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
                <?php foreach ($featuredEvents as $event): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 card-hover">
                        <div class="position-relative">
                            <?php if (!empty($event['featured_image'])): ?>
                            <img src=".<?php echo htmlspecialchars($event['featured_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>" style="height: 220px; object-fit: cover;">
                            <?php else: ?>
                            <div class="bg-light text-center py-5" style="height: 220px;">
                                <i class="fas fa-calendar-alt fa-5x text-secondary"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="event-date position-absolute top-0 start-0 bg-primary text-white text-center m-3 py-2 px-3 rounded">
                                <span class="d-block fw-bold"><?php echo date('d', strtotime($event['start_date'])); ?></span>
                                <span><?php echo date('M', strtotime($event['start_date'])); ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            
                            <div class="event-details mb-3">
                                <p class="mb-2">
                                    <i class="fas fa-clock me-2 text-primary"></i>
                                    <?php echo date('H:i', strtotime($event['start_date'])); ?> - 
                                    <?php echo !empty($event['end_date']) ? date('H:i', strtotime($event['end_date'])) : 'à déterminer'; ?>
                                </p>
                                
                                <?php if (!empty($event['location'])): ?>
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($event['description'])): ?>
                            <p class="card-text"><?php echo substr(htmlspecialchars($event['description']), 0, 100); ?>...</p>
                            <?php endif; ?>
                            
                            <a href="/evenement_details&slug=<?php echo htmlspecialchars($event['slug']); ?>" class="btn btn-primary mt-2">
                                En savoir plus
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Liste de tous les événements -->
        <div class="events-list mb-5">
            <h3 class="border-bottom pb-2 mb-4">
                <?php 
                if ($timeFilter === 'upcoming') echo 'Événements à venir';
                elseif ($timeFilter === 'past') echo 'Événements passés';
                else echo 'Tous les événements';
                ?>
            </h3>
            
            <?php if (empty($allEvents)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Aucun événement n'est disponible pour la période sélectionnée.
            </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php foreach ($allEvents as $event): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 card-hover">
                            <div class="row g-0">
                                <div class="col-md-4 position-relative">
                                    <?php if (!empty($event['featured_image'])): ?>
                                    <img src=".<?php echo htmlspecialchars($event['featured_image']); ?>" class="img-fluid rounded-start h-100" alt="<?php echo htmlspecialchars($event['title']); ?>" style="object-fit: cover;">
                                    <?php else: ?>
                                    <div class="bg-light text-center py-5 h-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-calendar-alt fa-3x text-secondary"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="event-date position-absolute top-0 start-0 bg-primary text-white text-center m-2 py-1 px-2 rounded">
                                        <span class="d-block fw-bold"><?php echo date('d', strtotime($event['start_date'])); ?></span>
                                        <span><?php echo date('M', strtotime($event['start_date'])); ?></span>
                                        </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        
                                        <div class="event-details mb-3">
                                            <p class="mb-1">
                                                <i class="fas fa-clock me-2 text-primary"></i>
                                                <?php echo date('d M Y, H:i', strtotime($event['start_date'])); ?>
                                                <?php if (!empty($event['end_date'])): ?>
                                                - <?php echo date('H:i', strtotime($event['end_date'])); ?>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <?php if (!empty($event['location'])): ?>
                                            <p class="mb-1">
                                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($event['location']); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($event['description'])): ?>
                                        <p class="card-text"><?php echo substr($event['description'], 0, 120); ?>...</p>
                                        <?php endif; ?>
                                        
                                        <a href="event_details&slug=<?php echo htmlspecialchars($event['slug']); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                            En savoir plus
                                        </a>
                                        
                                        <?php if ($event['is_featured']): ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <i class="fas fa-star me-1"></i> À la une
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination si nécessaire -->
        <?php if (count($allEvents) > 20): ?>
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

<!-- Call-to-Action Section: Proposer un événement -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Vous organisez un événement ?</h2>
                <p class="lead mb-4">Vous êtes professeur, étudiant ou partenaire de l'ISTM BENI et vous souhaitez proposer un événement ou une activité ? N'hésitez pas à nous contacter pour nous faire part de votre projet.</p>
                <a href="contact" class="btn btn-light btn-lg px-4">
                    <i class="fas fa-paper-plane me-2"></i> Nous contacter
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Calendrier intégré (optionnel) -->
<section class="calendar-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center mb-5">
                <h2 class="mb-4">Calendrier des événements</h2>
                <p class="lead">Consultez notre calendrier pour voir tous les événements à venir et planifier votre participation.</p>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <!-- Intégration d'un calendrier FullCalendar ou Google Calendar ici -->
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div id="calendar" class="calendar-placeholder bg-white p-4" style="height: 500px;">
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center">
                                    <i class="fas fa-calendar-alt fa-5x text-secondary mb-3"></i>
                                    <h5>Calendrier des événements</h5>
                                    <p class="text-muted">Le calendrier interactif sera bientôt disponible.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Styles spécifiques pour la page événements -->
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

/* Cartes des événements */
.card-hover {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Styles pour les dates des événements */
.event-date {
    border-radius: 5px;
    font-size: 0.9rem;
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
    
    .row.g-0 .col-md-4 {
        height: 200px;
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
    
    document.querySelectorAll('.card, .events-featured, .events-list').forEach(element => {
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
