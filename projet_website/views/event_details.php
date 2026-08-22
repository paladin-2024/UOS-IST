<?php 
include "include/head.php";

// Récupération des données de l'événement
$db = Connexion::getInstance()->getPDO();

// Vérifier si un slug est fourni dans l'URL
if (!isset($_GET['slug'])) {
    header('Location: /evenements');
    exit;
}

$slug = $_GET['slug'];

// Récupérer les détails de l'événement
$stmt = $db->prepare("SELECT e.*, u.full_name as creator_name, u.id as creator_id
                   FROM events e 
                   LEFT JOIN users u ON e.created_by = u.id 
                   WHERE e.slug = :slug AND e.is_published = 1");
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

// Si l'événement n'existe pas ou n'est pas publié, rediriger
if (!$event) {
    header('Location: /evenements');
    exit;
}

// Récupérer les événements similaires (prochains événements)
$today = date('Y-m-d H:i:s');
$stmt = $db->prepare("SELECT id, title, slug, description, featured_image, location, start_date, end_date  
                   FROM events 
                   WHERE id != :event_id AND is_published = 1 AND start_date >= :today
                   ORDER BY start_date ASC LIMIT 4");
$stmt->bindParam(':event_id', $event['id']);
$stmt->bindParam(':today', $today);
$stmt->execute();
$upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les événements populaires (événements passés)
$stmt = $db->prepare("SELECT id, title, slug, start_date, location
                   FROM events 
                   WHERE is_published = 1 AND start_date < :today
                   ORDER BY start_date DESC LIMIT 5");
$stmt->bindParam(':today', $today);
$stmt->execute();
$pastEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ressources attachées à l'événement via la table event_media si elle existe
$resources = [];
try {
    $stmt = $db->prepare("
        SELECT m.* 
        FROM media m 
        JOIN event_media em ON m.id = em.media_id 
        WHERE em.event_id = :event_id
        ORDER BY em.is_featured DESC, em.order_index ASC");
    $stmt->bindParam(':event_id', $event['id']);
    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la table n'existe pas, on continue sans erreur
}

// Formater les dates
$startDate = new DateTime($event['start_date']);
$formattedStartDate = $startDate->format('d F Y à H:i');

// Formater la date de fin si elle existe
$formattedEndDate = '';
if (!empty($event['end_date'])) {
    $endDate = new DateTime($event['end_date']);
    
    // Si même jour, afficher juste l'heure
    if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
        $formattedEndDate = ' à ' . $endDate->format('H:i');
    } else {
        $formattedEndDate = ' au ' . $endDate->format('d F Y à H:i');
    }
}

// Traduire les mois en français
$months = [
    'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
    'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
    'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
    'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
];
foreach ($months as $en => $fr) {
    $formattedStartDate = str_replace($en, $fr, $formattedStartDate);
    $formattedEndDate = str_replace($en, $fr, $formattedEndDate);
}

// Calculer si l'événement est passé, en cours ou à venir
$now = new DateTime();
$eventStatus = 'upcoming'; // Par défaut: à venir
if ($startDate <= $now) {
    if (!empty($event['end_date'])) {
        $endDate = new DateTime($event['end_date']);
        if ($endDate < $now) {
            $eventStatus = 'past'; // Passé
        } else {
            $eventStatus = 'ongoing'; // En cours
        }
    } else {
        if ($startDate->format('Y-m-d') < $now->format('Y-m-d')) {
            $eventStatus = 'past'; // Passé si juste la date de début et c'est passé
        } else {
            $eventStatus = 'ongoing'; // Considéré en cours si c'est aujourd'hui
        }
    }
}

// Format pour ajouter à l'agenda (iCal, Google Calendar)
$googleStartDate = $startDate->format('Ymd\THis');
$googleEndDate = !empty($event['end_date']) ? (new DateTime($event['end_date']))->format('Ymd\THis') : $startDate->modify('+1 hour')->format('Ymd\THis');
$googleCalendarUrl = "https://calendar.google.com/calendar/render?action=TEMPLATE&text=" . urlencode($event['title']) . "&dates=" . $googleStartDate . "/" . $googleEndDate . "&details=" . urlencode(strip_tags($event['description'])) . "&location=" . urlencode($event['location'] ?? 'ISTM BENI');
?>

<!-- En-tête de l'événement avec background et titre -->
<section class="event-hero position-relative <?php echo $eventStatus === 'past' ? 'bg-secondary' : ($eventStatus === 'ongoing' ? 'bg-success' : 'bg-primary'); ?> text-white py-5" style="background: linear-gradient(135deg, var(--<?php echo $eventStatus === 'past' ? 'secondary' : ($eventStatus === 'ongoing' ? 'success' : 'primary'); ?>-color) 0%, var(--<?php echo $eventStatus === 'past' ? 'secondary' : ($eventStatus === 'ongoing' ? 'success' : 'primary'); ?>-dark) 100%);">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb bg-transparent p-0 m-0">
                        <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="/evenements" class="text-white opacity-75">Événements</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars(substr($event['title'], 0, 30)) . (strlen($event['title']) > 30 ? '...' : ''); ?></li>
                    </ol>
                </nav>
                
                <div class="event-status mb-3">
                    <?php if ($eventStatus === 'past'): ?>
                    <span class="badge bg-light text-secondary fs-6 px-3 py-2">
                        <i class="fas fa-history me-1"></i> Événement terminé
                    </span>
                    <?php elseif ($eventStatus === 'ongoing'): ?>
                    <span class="badge bg-light text-success fs-6 px-3 py-2">
                        <i class="fas fa-hourglass-half me-1"></i> Événement en cours
                    </span>
                    <?php else: ?>
                    <span class="badge bg-light text-primary fs-6 px-3 py-2">
                        <i class="fas fa-calendar-alt me-1"></i> Événement à venir
                    </span>
                    <?php endif; ?>
                </div>
                
                <h2 class="event-title display-4 fw-bold mb-3"><?php echo htmlspecialchars($event['title']); ?></h2>
                
                <div class="event-meta d-flex flex-wrap align-items-center mb-3 gap-3">
                    <span class="text-white me-3"><i class="far fa-calendar-alt me-1"></i> <?php echo $formattedStartDate . $formattedEndDate; ?></span>
                    <?php if (!empty($event['location'])): ?>
                    <span class="text-white me-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                    <?php endif; ?>
                    <?php if ($event['creator_name']): ?>
                    <span class="text-white me-3"><i class="far fa-user me-1"></i> Organisé par: <?php echo htmlspecialchars($event['creator_name']); ?></span>
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
<section class="event-content py-5">
    <div class="container">
        <div class="row">
            <!-- Colonne principale - Contenu événement -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <!-- Image principale de l'événement -->
                <?php if ($event['featured_image']): ?>
                <div class="event-featured-image mb-4 rounded shadow-sm overflow-hidden">
                    <img src=".<?php echo htmlspecialchars($event['featured_image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <!-- Alert si l'événement est passé -->
                <?php if ($eventStatus === 'past'): ?>
                <div class="alert alert-secondary mb-4">
                    <i class="fas fa-info-circle me-2"></i> Cet événement est terminé. Retrouvez nos prochains événements dans la <a href="/evenements" class="alert-link">section événements</a>.
                </div>
                <?php endif; ?>
                
                <!-- Alert si l'événement est en cours -->
                <?php if ($eventStatus === 'ongoing'): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i> Cet événement est en cours actuellement. Venez nous rejoindre à <strong><?php echo htmlspecialchars($event['location']); ?></strong>.
                </div>
                <?php endif; ?>
                                <!-- Corps de l'événement -->
                                <div class="event-body mb-5 content-wrapper">
                    <?php echo $event['content']; ?>
                </div>
                
                <!-- Informations pratiques -->
                <div class="event-practical-info mb-5">
                    <h4 class="border-bottom pb-2 mb-3">Informations pratiques</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="far fa-calendar-alt text-primary me-2"></i>Date et heure</h5>
                                    <p class="card-text">
                                        <strong>Début:</strong> <?php echo $formattedStartDate; ?><br>
                                        <?php if (!empty($event['end_date'])): ?>
                                        <strong>Fin:</strong> <?php echo (new DateTime($event['end_date']))->format('d F Y à H:i'); ?><br>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($eventStatus !== 'past'): ?>
                                    <a href="<?php echo $googleCalendarUrl; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="far fa-calendar-plus me-1"></i> Ajouter à mon agenda
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-map-marker-alt text-primary me-2"></i>Lieu</h5>
                                    <p class="card-text"><?php echo htmlspecialchars($event['location'] ?? 'ISTM BENI'); ?></p>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($event['location'] ?? 'ISTM BENI, RDC'); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-directions me-1"></i> Obtenir l'itinéraire
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ressources téléchargeables -->
                <?php if (!empty($resources)): ?>
                <div class="event-resources mb-5">
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
                
                <!-- Organisateur / Contact -->
                <div class="event-organizer mb-5">
                    <h4 class="border-bottom pb-2 mb-3">Contact</h4>
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="organizer-avatar me-3">
                                    <?php if (isset($event['creator_id'])): ?>
                                    <img src="/assets/img/avatars/user<?php echo $event['creator_id']; ?>.jpg" 
                                         onerror="this.src='/assets/img/avatars/default.jpg'" 
                                         alt="<?php echo htmlspecialchars($event['creator_name']); ?>"
                                         class="rounded-circle" width="80" height="80">
                                    <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px; background-color: var(--primary-color); color: white;">
                                        <i class="fas fa-user-tie fa-2x"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="organizer-info">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($event['creator_name'] ?? 'ISTM BENI'); ?></h5>
                                    <p class="text-muted mb-2">Organisateur</p>
                                    <p class="mb-0">Pour toute question concernant cet événement, veuillez contacter le service des études au <a href="tel:+243123456789">+243 123 456 789</a> ou par email à <a href="mailto:contact@istmbeni.ac.cd">contact@istmbeni.ac.cd</a>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Colonne Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar position-sticky" style="top: 2rem;">
                    <!-- Carte événement -->
                    <div class="card mb-4 shadow-sm border-0 event-card-highlight">
                        <div class="card-body">
                            <h5 class="card-title border-bottom pb-3"><?php echo $eventStatus === 'past' ? 'Événement terminé' : ($eventStatus === 'ongoing' ? 'Événement en cours' : 'Participez à cet événement'); ?></h5>
                            
                            <div class="event-countdown mb-3">
                                <?php if ($eventStatus === 'upcoming'): ?>
                                <div class="text-center mb-3">
                                    <div class="countdown-timer" data-target="<?php echo strtotime($event['start_date']) * 1000; ?>">
                                        <div class="d-flex justify-content-between">
                                            <div class="countdown-item">
                                                <div class="countdown-value days">00</div>
                                                <div class="countdown-label">Jours</div>
                                            </div>
                                            <div class="countdown-item">
                                                <div class="countdown-value hours">00</div>
                                                <div class="countdown-label">Heures</div>
                                            </div>
                                            <div class="countdown-item">
                                                <div class="countdown-value minutes">00</div>
                                                <div class="countdown-label">Minutes</div>
                                            </div>
                                            <div class="countdown-item">
                                                <div class="countdown-value seconds">00</div>
                                                <div class="countdown-label">Secondes</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($eventStatus === 'past'): ?>
                                <div class="alert alert-secondary">
                                    <i class="fas fa-info-circle me-2"></i> Cet événement s'est terminé le <?php echo (new DateTime($event['end_date'] ?? $event['start_date']))->format('d/m/Y'); ?>.
                                </div>
                                <?php elseif ($eventStatus === 'ongoing'): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i> Cet événement est en cours actuellement.
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-calendar-day me-2"></i> Cet événement aura lieu dans <?php echo $startDate->diff(new DateTime())->days; ?> jours.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($eventStatus !== 'past'): ?>
                            <div class="text-center mb-3">
                                <a href="#" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i> S'inscrire à cet événement
                                </a>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo $googleCalendarUrl; ?>" target="_blank" class="btn btn-outline-secondary">
                                    <i class="far fa-calendar-plus me-1"></i> Ajouter à mon agenda
                                </a>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($event['location'] ?? 'ISTM BENI, RDC'); ?>" target="_blank" class="btn btn-outline-secondary">
                                    <i class="fas fa-map-marker-alt me-1"></i> Voir le lieu
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Partager l'événement -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Partager cet événement</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="fab fa-facebook me-2"></i> Partager sur Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($event['title']); ?>" target="_blank" class="btn btn-outline-info">
                                    <i class="fab fa-twitter me-2"></i> Partager sur Twitter
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode($event['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-success">
                                    <i class="fab fa-whatsapp me-2"></i> Partager sur WhatsApp
                                </a>
                                <a href="mailto:?subject=<?php echo urlencode($event['title']); ?>&body=<?php echo urlencode('Découvrez cet événement : https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-envelope me-2"></i> Partager par email
                                </a>
                                <button class="btn btn-outline-dark" id="copyLink">
                                    <i class="fas fa-link me-2"></i> Copier le lien
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Prochains événements -->
                    <?php if (!empty($upcomingEvents)): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Prochains événements</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($upcomingEvents as $upcoming): ?>
                                <li class="list-group-item px-3 py-3">
                                    <a href="/event_details&slug=<?php echo htmlspecialchars($upcoming['slug']); ?>" class="text-decoration-none text-dark">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($upcoming['featured_image'])): ?>
                                            <div class="flex-shrink-0 me-3">
                                                <img src=".<?php echo htmlspecialchars($upcoming['featured_image']); ?>" class="rounded" alt="<?php echo htmlspecialchars($upcoming['title']); ?>" width="50" height="50" style="object-fit: cover;">
                                            </div>
                                            <?php else: ?>
                                            <div class="flex-shrink-0 me-3 bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="fas fa-calendar-alt text-secondary"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 text-truncate"><?php echo htmlspecialchars($upcoming['title']); ?></h6>
                                                <div class="small text-muted">
                                                <i class="far fa-calendar-alt me-1"></i> <?php echo (new DateTime($upcoming['start_date']))->format('d/m/Y'); ?>
                                                    <?php if (!empty($upcoming['location'])): ?>
                                                    <br><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($upcoming['location']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="/evenements" class="btn btn-sm btn-link text-decoration-none">Voir tous les événements</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Newsletter -->
                    <div class="card mb-4 shadow-sm bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Restez informé</h5>
                            <p class="card-text">Recevez nos prochains événements directement dans votre boîte mail.</p>
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

<!-- Événements similaires -->
<?php if (!empty($upcomingEvents)): ?>
<section class="similar-events py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Autres événements à venir</h2>
            <p class="section-text">Découvrez nos prochains événements et activités à l'ISTM BENI</p>
        </div>
        
        <div class="row">
            <?php foreach ($upcomingEvents as $upcoming): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 shadow-sm border-0 event-card">
                    <div class="card-img-container position-relative" style="height: 180px; overflow: hidden;">
                        <?php if (!empty($upcoming['featured_image'])): ?>
                        <img src=".<?php echo htmlspecialchars($upcoming['featured_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($upcoming['title']); ?>" style="object-fit: cover; height: 100%; width: 100%;">
                        <?php else: ?>
                        <div class="bg-secondary d-flex align-items-center justify-content-center h-100">
                            <i class="fas fa-calendar-day fa-3x text-white"></i>
                        </div>
                        <?php endif; ?>
                        <div class="position-absolute top-0 start-0 m-2 event-date bg-white rounded shadow-sm p-2 text-center">
                            <div class="event-day fs-4 fw-bold text-primary">
                                <?php echo (new DateTime($upcoming['start_date']))->format('d'); ?>
                            </div>
                            <div class="event-month small text-uppercase">
                                <?php echo (new DateTime($upcoming['start_date']))->format('M'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="/event_details&slug=<?php echo htmlspecialchars($upcoming['slug']); ?>" class="text-decoration-none text-dark stretched-link">
                            <?php echo htmlspecialchars($upcoming['title']); ?>
                            </a>
                        </h5>
                        <?php if (!empty($upcoming['location'])): ?>
                        <p class="card-text text-muted small">
                            <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($upcoming['location']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($upcoming['description'])): ?>
                        <p class="card-text text-muted small flex-grow-1">
                            <?php echo substr(strip_tags($upcoming['description']), 0, 100) . '...'; ?>
                        </p>
                        <?php endif; ?>
                        <div class="mt-auto">
                            <div class="event-time small text-muted mb-2">
                                <i class="far fa-clock me-1"></i> <?php echo (new DateTime($upcoming['start_date']))->format('H:i'); ?>
                                <?php if (!empty($upcoming['end_date'])): ?> - <?php echo (new DateTime($upcoming['end_date']))->format('H:i'); ?><?php endif; ?>
                            </div>
                            <a href="/event_details&slug=<?php echo htmlspecialchars($upcoming['slug']); ?>" class="btn btn-link p-0 text-primary">
                                Plus d'informations <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="/evenements" class="btn btn-outline-primary px-4">
                Voir tous les événements <i class="fas fa-long-arrow-alt-right ms-2"></i>
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
                <h2 class="mb-4">Vous souhaitez organiser un événement à l'ISTM BENI ?</h2>
                <p class="lead mb-4">Nous disposons d'infrastructures adaptées pour vos conférences, séminaires et activités académiques.</p>
                <a href="contact" class="btn btn-lg btn-light">
                    Contactez-nous <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Back to list button -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center">
        <a href="evenements" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour aux événements
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
    
    document.querySelectorAll('.card, .event-body > p, .event-body > h2, .event-body > h3, .event-body > ul, .event-body > ol, .event-body > blockquote').forEach(element => {
        element.classList.add('fade-in-element');
        observer.observe(element);
    });
    
    // Bouton de copie de lien
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    const copyLink = document.getElementById('copyLink');
    
    function handleCopyLink(btn) {
        if (!btn) return;
        
        btn.addEventListener('click', function() {
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
                        this.classList.add(this.id === 'copyLink' ? 'btn-outline-dark' : 'btn-outline-secondary');
                    }, 2000);
                })
                .catch(err => {
                    console.error('Erreur lors de la copie du lien:', err);
                });
        });
    }
    
    handleCopyLink(copyLinkBtn);
    handleCopyLink(copyLink);
    
    // Compte à rebours
    const countdownElement = document.querySelector('.countdown-timer');
    if (countdownElement) {
        const targetTimestamp = parseInt(countdownElement.getAttribute('data-target'));
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetTimestamp - now;
            
            if (distance <= 0) {
                document.querySelector('.countdown-value.days').textContent = '00';
                document.querySelector('.countdown-value.hours').textContent = '00';
                document.querySelector('.countdown-value.minutes').textContent = '00';
                document.querySelector('.countdown-value.seconds').textContent = '00';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.querySelector('.countdown-value.days').textContent = String(days).padStart(2, '0');
            document.querySelector('.countdown-value.hours').textContent = String(hours).padStart(2, '0');
            document.querySelector('.countdown-value.minutes').textContent = String(minutes).padStart(2, '0');
            document.querySelector('.countdown-value.seconds').textContent = String(seconds).padStart(2, '0');
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
    // Amélioration des éléments de l'article
    const eventBody = document.querySelector('.event-body');
    if (eventBody) {
        // Ajouter des classes Bootstrap aux images
        eventBody.querySelectorAll('img').forEach(img => {
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
        eventBody.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-striped', 'table-bordered', 'my-4');
            
            // Wrapper pour rendre les tableaux responsifs
            const tableResponsive = document.createElement('div');
            tableResponsive.className = 'table-responsive';
            table.parentNode.insertBefore(tableResponsive, table);
            tableResponsive.appendChild(table);
        });
        
        // Amélioration des liens
        eventBody.querySelectorAll('a').forEach(link => {
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
        eventBody.querySelectorAll('ul, ol').forEach(list => {
            list.classList.add('my-3');
            
            list.querySelectorAll('li').forEach(item => {
                item.classList.add('mb-2');
            });
        });
        
        // Amélioration des citations
        eventBody.querySelectorAll('blockquote').forEach(quote => {
            quote.classList.add('blockquote', 'border-start', 'border-primary', 'border-4', 'ps-4', 'py-2', 'my-4');
        });
    }
});
</script>

<!-- Styles spécifiques pour la page d'événement -->
<style>
/* Aspect général de l'événement */
.event-hero {
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

.event-body {
    font-family: 'Arial', sans-serif;
    font-size: 1.125rem;
    line-height: 1.8;
    color: #333;
}

.event-body h2 {
    font-family: 'Arial', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.event-body h3 {
    font-family: 'Arial', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #444;
}

.event-body h4 {
    font-family: 'Arial', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #555;
}

.event-body p {
    margin-bottom: 1.5rem;
}

.event-body ul, .event-body ol {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.event-body li {
    margin-bottom: 0.5rem;
}

.event-body blockquote {
    font-style: italic;
    position: relative;
    padding: 1.5rem 2rem;
    margin: 1.5rem 0;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 0.25rem;
}

.event-body blockquote::before {
    content: "\201C";
    font-family: Georgia, serif;
    font-size: 3rem;
    position: absolute;
    left: 0.5rem;
    top: -0.5rem;
    color: rgba(var(--primary-color-rgb), 0.2);
}

.event-body a {
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
    transition: all 0.2s ease;
}

.event-body a:hover {
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

/* Cartes événements */
.event-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.event-card-highlight {
    border-left: 4px solid var(--primary-color) !important;
    background-color: rgba(var(--primary-color-rgb), 0.02);
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

/* Compte à rebours */
.countdown-timer {
    padding: 15px;
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-radius: 10px;
    margin-bottom: 15px;
}

.countdown-item {
    text-align: center;
    padding: 0 10px;
}

.countdown-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 5px;
}

.countdown-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
}

/* Date événement */
.event-date {
    min-width: 60px;
}

.event-day {
    line-height: 1;
}

.event-month {
    text-transform: uppercase;
    color: var(--primary-color);
    font-weight: 600;
}

/* Media queries */
@media (max-width: 991.98px) {
    .event-title {
        font-size: 2.2rem !important;
    }
    
    .event-body {
        font-size: 1rem;
    }
    
    .event-body h2 {
        font-size: 1.6rem;
    }
    
    .event-body h3 {
        font-size: 1.4rem;
    }
    
    .event-body h4 {
        font-size: 1.2rem;
    }
}

@media (max-width: 767.98px) {
    .event-title {
        font-size: 1.8rem !important;
    }
    
    .event-hero {
        padding: 2rem 0;
    }
    
    .event-meta {
        flex-wrap: wrap;
    }
    
    .event-meta > span {
        margin-bottom: 0.5rem;
    }
    
    .countdown-value {
        font-size: 1.5rem;
    }
    
    .countdown-label {
        font-size: 0.7rem;
    }
    
    .countdown-item {
        padding: 0 5px;
    }
}

/* Mode impression */
@media print {
    header, footer, .sidebar, .cta-section, .similar-events, .sharing-buttons, .event-actions {
        display: none !important;
    }
    
    .event-body {
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .event-body a {
        font-weight: bold;
        text-decoration: none;
        color: #000 !important;
        border: none;
    }
    
    .event-body a::after {
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
    
    .event-title {
        font-size: 24pt !important;
    }
    
    .event-body img {
        max-height: 300px;
    }
}
</style>

<?php 
include "include/footer.php";
?>
