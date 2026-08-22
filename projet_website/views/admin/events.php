<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'events';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/events/';

// Créer le répertoire d'upload s'il n'existe pas
if (!file_exists($imagesPath)) {
    mkdir($imagesPath, 0777, true);
}

// Fonction pour uploader un fichier
function uploadFile($file, $targetPath, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) {
    // Vérifier si le fichier a été correctement uploadé
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors de l'upload du fichier: " . $file['error']);
    }
    
    // Vérifier le type de fichier
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé. Types acceptés: " . implode(', ', $allowedTypes));
    }
    
    // Générer un nom de fichier unique
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '.' . $fileExtension;
    $targetFilePath = $targetPath . $fileName;
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        throw new Exception("Échec du déplacement du fichier uploadé");
    }
    
    return $targetFilePath;
}

// Traitement des actions
if (isset($_POST['action'])) {
    try {
        // Action d'ajout d'un événement
        if ($_POST['action'] === 'add_event' && isset($_POST['title'], $_POST['slug'])) {
            // Gérer l'upload de l'image
            $featuredImage = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $featuredImage = uploadFile($_FILES['featured_image'], $imagesPath);
                // Rendre le chemin relatif
                $featuredImage = str_replace('./', '/', $featuredImage);
            } else if (!empty($_POST['featured_image_url'])) {
                $featuredImage = $_POST['featured_image_url'];
            }
            
            // Formater les dates
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $startTime = !empty($_POST['start_time']) ? $_POST['start_time'] : '00:00';
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $endTime = !empty($_POST['end_time']) ? $_POST['end_time'] : '00:00';
            
            $startDateTime = $startDate ? date('Y-m-d H:i:s', strtotime("$startDate $startTime")) : null;
            $endDateTime = $endDate ? date('Y-m-d H:i:s', strtotime("$endDate $endTime")) : null;
            
            // Insérer l'événement dans la base de données
            $stmt = $db->prepare("INSERT INTO events (title, slug, description, content, featured_image, 
                                location, start_date, end_date, is_featured, is_published, created_by) 
                                VALUES (:title, :slug, :description, :content, :featured_image, 
                                :location, :start_date, :end_date, :is_featured, :is_published, :created_by)");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $content = !empty($_POST['content']) ? $_POST['content'] : null;
            $location = !empty($_POST['location']) ? $_POST['location'] : null;
            $createdBy = $_SESSION['user_id'] ?? null;
            
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':start_date', $startDateTime);
            $stmt->bindParam(':end_date', $endDateTime);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->bindParam(':created_by', $createdBy);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'événement a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un événement
        else if ($_POST['action'] === 'update_event' && isset($_POST['event_id'], $_POST['title'], $_POST['slug'])) {
            // Récupérer l'image actuelle
            $currentImageStmt = $db->prepare("SELECT featured_image FROM events WHERE id = :id");
            $currentImageStmt->bindParam(':id', $_POST['event_id']);
            $currentImageStmt->execute();
            $currentImage = $currentImageStmt->fetchColumn();
            
            // Gérer l'upload de la nouvelle image
            $featuredImage = $currentImage;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $featuredImage = uploadFile($_FILES['featured_image'], $imagesPath);
                $featuredImage = str_replace('./', '/', $featuredImage);
                
                // Supprimer l'ancienne image si elle existe
                if ($currentImage && file_exists('.' . $currentImage) && !strpos($currentImage, 'http')) {
                    unlink('.' . $currentImage);
                }
            } else if (!empty($_POST['featured_image_url'])) {
                $featuredImage = $_POST['featured_image_url'];
            }
            
            // Formater les dates
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $startTime = !empty($_POST['start_time']) ? $_POST['start_time'] : '00:00';
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $endTime = !empty($_POST['end_time']) ? $_POST['end_time'] : '00:00';
            
            $startDateTime = $startDate ? date('Y-m-d H:i:s', strtotime("$startDate $startTime")) : null;
            $endDateTime = $endDate ? date('Y-m-d H:i:s', strtotime("$endDate $endTime")) : null;
            
            // Mettre à jour l'événement
            $stmt = $db->prepare("UPDATE events SET title = :title, slug = :slug, description = :description, 
                                content = :content, featured_image = :featured_image, location = :location, 
                                start_date = :start_date, end_date = :end_date, is_featured = :is_featured, 
                                is_published = :is_published WHERE id = :id");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $content = !empty($_POST['content']) ? $_POST['content'] : null;
            $location = !empty($_POST['location']) ? $_POST['location'] : null;
            
            $stmt->bindParam(':id', $_POST['event_id']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':start_date', $startDateTime);
            $stmt->bindParam(':end_date', $endDateTime);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'événement a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un événement
        else if ($_POST['action'] === 'delete_event' && isset($_POST['event_id'])) {
            // Récupérer l'image à supprimer
            $imageStmt = $db->prepare("SELECT featured_image FROM events WHERE id = :id");
            $imageStmt->bindParam(':id', $_POST['event_id']);
            $imageStmt->execute();
            $image = $imageStmt->fetchColumn();
            
            // Supprimer l'image si elle existe et n'est pas une URL externe
            if ($image && file_exists('.' . $image) && strpos($image, 'http') !== 0) {
                unlink('.' . $image);
            }
            
            // Supprimer l'événement
            $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
            $stmt->bindParam(':id', $_POST['event_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'événement a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: events');
    exit;
}

// Récupérer tous les événements
$eventsStmt = $db->query("SELECT * FROM events ORDER BY start_date DESC");
$eventsList = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des événements -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>Gestion des événements</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="fas fa-plus me-2"></i>Ajouter un événement
    </button>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <p class="text-muted">
                    Gérez les événements de votre établissement tels que les conférences, séminaires, journées portes ouvertes, cérémonies, etc.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des événements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des événements</h5>
    </div>
    <div class="card-body">
        <?php if (empty($eventsList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun événement n'a été ajouté.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Lieu</th>
                            <th>Statut</th>
                            <th>À la une</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventsList as $event): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($event['featured_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($event['featured_image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="img-thumbnail" style="max-width: 70px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-calendar-day fa-2x"></i></div>
                                        <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </td>
                                <td>
                                    <?php 
                                        $startDate = new DateTime($event['start_date']);
                                        echo $startDate->format('d/m/Y H:i');
                                        
                                        if (!empty($event['end_date'])) {
                                            $endDate = new DateTime($event['end_date']);
                                            echo ' au ' . $endDate->format('d/m/Y H:i');
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($event['location'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php if ($event['is_published']): ?>
                                        <span class="badge bg-success">Publié</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Brouillon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($event['is_featured']): ?>
                                        <span class="badge bg-warning text-dark">À la une</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../event_details&slug=<?php echo htmlspecialchars($event['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-event-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editEventModal"
                                                data-id="<?php echo $event['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($event['title']); ?>"
                                                data-slug="<?php echo htmlspecialchars($event['slug']); ?>"
                                                data-description="<?php echo htmlspecialchars($event['description'] ?? ''); ?>"
                                                data-content="<?php echo htmlspecialchars($event['content'] ?? ''); ?>"
                                                data-location="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                                data-start-date="<?php echo $event['start_date'] ? date('Y-m-d', strtotime($event['start_date'])) : ''; ?>"
                                                data-start-time="<?php echo $event['start_date'] ? date('H:i', strtotime($event['start_date'])) : ''; ?>"
                                                data-end-date="<?php echo $event['end_date'] ? date('Y-m-d', strtotime($event['end_date'])) : ''; ?>"
                                                data-end-time="<?php echo $event['end_date'] ? date('H:i', strtotime($event['end_date'])) : ''; ?>"
                                                data-featured-image="<?php echo htmlspecialchars($event['featured_image'] ?? ''); ?>"
                                                data-is-featured="<?php echo $event['is_featured']; ?>"
                                                data-is-published="<?php echo $event['is_published']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-event-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteEventModal"
                                                data-id="<?php echo $event['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($event['title']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter un événement -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Ajouter un nouvel événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="event-title" class="form-label">Titre de l'événement</label>
                                <input type="text" class="form-control" id="event-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="event-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="event-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL (ex: "conference-annuelle-2023").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="event-description" class="form-label">Description courte</label>
                                <textarea class="form-control" id="event-description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="event-content" class="form-label">Contenu détaillé</label>
                                <textarea class="form-control" id="event-content" name="content" rows="10"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="event-location" class="form-label">Lieu de l'événement</label>
                                <input type="text" class="form-control" id="event-location" name="location">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="event-start-date" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="event-start-date" name="start_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="event-start-time" class="form-label">Heure de début</label>
                                    <input type="time" class="form-control" id="event-start-time" name="start_time" value="09:00">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="event-end-date" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="event-end-date" name="end_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="event-end-time" class="form-label">Heure de fin</label>
                                    <input type="time" class="form-control" id="event-end-time" name="end_time" value="18:00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">Image de l'événement</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="event-image-preview" src="/uploads/placeholder.jpg" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px; display: none;">
                                            <div id="no-image-preview" class="text-muted">
                                                <i class="fas fa-calendar-day fa-5x mb-2"></i>
                                                <p>Aucune image sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="event-image-upload" name="featured_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="event-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="event-image-url" name="featured_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="event-featured" name="is_featured">
                                        <label class="form-check-label" for="event-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="event-published" name="is_published" checked>
                                        <label class="form-check-label" for="event-published">
                                            Publier l'événement
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_event">Ajouter l'événement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un événement -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Modifier l'événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-event-id" name="event_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-event-title" class="form-label">Titre de l'événement</label>
                                <input type="text" class="form-control" id="edit-event-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-event-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-event-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-event-description" class="form-label">Description courte</label>
                                <textarea class="form-control" id="edit-event-description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit-event-content" class="form-label">Contenu détaillé</label>
                                <textarea class="form-control" id="edit-event-content" name="content" rows="10"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit-event-location" class="form-label">Lieu de l'événement</label>
                                <input type="text" class="form-control" id="edit-event-location" name="location">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit-event-start-date" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="edit-event-start-date" name="start_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-event-start-time" class="form-label">Heure de début</label>
                                    <input type="time" class="form-control" id="edit-event-start-time" name="start_time">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit-event-end-date" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="edit-event-end-date" name="end_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-event-end-time" class="form-label">Heure de fin</label>
                                    <input type="time" class="form-control" id="edit-event-end-time" name="end_time">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                            <label class="form-label">Image de l'événement</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-event-image-preview" src="" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            <div id="edit-no-image-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-calendar-day fa-5x mb-2"></i>
                                                <p>Aucune image sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="edit-event-image-upload" name="featured_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit-event-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="edit-event-image-url" name="featured_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-event-featured" name="is_featured">
                                        <label class="form-check-label" for="edit-event-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-event-published" name="is_published">
                                        <label class="form-check-label" for="edit-event-published">
                                            Publier l'événement
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_event">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un événement -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteEventModalLabel">Supprimer un événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-event-id" name="event_id">
                    <p>Êtes-vous sûr de vouloir supprimer l'événement <strong id="delete-event-title"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement cet événement et toutes ses informations associées.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_event">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des éditeurs CKEditor
    const editorConfig = {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'undo', 'redo'],
        language: 'fr',
    };
    
    // Initialiser CKEditor pour le formulaire d'ajout
    let descriptionEditor, contentEditor;
    ClassicEditor
        .create(document.querySelector('#event-description'), editorConfig)
        .then(editor => {
            descriptionEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    ClassicEditor
        .create(document.querySelector('#event-content'), editorConfig)
        .then(editor => {
            contentEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Initialiser CKEditor pour le formulaire d'édition
    let editDescriptionEditor, editContentEditor;
    ClassicEditor
        .create(document.querySelector('#edit-event-description'), editorConfig)
        .then(editor => {
            editDescriptionEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    ClassicEditor
        .create(document.querySelector('#edit-event-content'), editorConfig)
        .then(editor => {
            editContentEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Générer le slug à partir du titre pour le nouveau formulaire
    document.getElementById('event-title').addEventListener('keyup', function() {
        const title = this.value;
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-')     // Remplacer les espaces par des tirets
            .replace(/--+/g, '-');    // Éviter les tirets multiples
        
        document.getElementById('event-slug').value = slug;
    });
    
    // Gestion de la prévisualisation de l'image pour l'ajout
    const eventImageUpload = document.getElementById('event-image-upload');
    const eventImageUrl = document.getElementById('event-image-url');
    const eventImagePreview = document.getElementById('event-image-preview');
    const noImagePreview = document.getElementById('no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image
    function displayImagePreview(src) {
        if (src) {
            eventImagePreview.src = src;
            eventImagePreview.style.display = 'block';
            noImagePreview.style.display = 'none';
        } else {
            eventImagePreview.style.display = 'none';
            noImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image
    eventImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                eventImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        } else {
            displayImagePreview(null);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image
    eventImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            eventImageUpload.value = '';
        } else {
            displayImagePreview(null);
        }
    });
    
    // Gestion de la prévisualisation de l'image pour l'édition
    const editEventImageUpload = document.getElementById('edit-event-image-upload');
    const editEventImageUrl = document.getElementById('edit-event-image-url');
    const editEventImagePreview = document.getElementById('edit-event-image-preview');
    const editNoImagePreview = document.getElementById('edit-no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image en mode édition
    function displayEditImagePreview(src) {
        if (src) {
            editEventImagePreview.src = src.startsWith('http') ? src : ".."+src;
            editEventImagePreview.style.display = 'block';
            editNoImagePreview.style.display = 'none';
        } else {
            editEventImagePreview.style.display = 'none';
            editNoImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image en mode édition
    editEventImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayEditImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                editEventImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image en mode édition
    editEventImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayEditImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            editEventImageUpload.value = '';
        }
    });
    
    // Validation de la date de fin (postérieure à la date de début)
    function validateEndDate() {
        const startDate = document.getElementById('event-start-date').value;
        const startTime = document.getElementById('event-start-time').value;
        const endDate = document.getElementById('event-end-date').value;
        const endTime = document.getElementById('event-end-time').value;
        
        if (startDate && endDate) {
            const start = new Date(`${startDate}T${startTime}`);
            const end = new Date(`${endDate}T${endTime}`);
            
            if (end < start) {
                alert('La date de fin doit être postérieure à la date de début!');
                document.getElementById('event-end-date').value = '';
                document.getElementById('event-end-time').value = '';
            }
        }
    }
    
    // Validation de la date de fin (mode édition)
    function validateEditEndDate() {
        const startDate = document.getElementById('edit-event-start-date').value;
        const startTime = document.getElementById('edit-event-start-time').value;
        const endDate = document.getElementById('edit-event-end-date').value;
        const endTime = document.getElementById('edit-event-end-time').value;
        
        if (startDate && endDate) {
            const start = new Date(`${startDate}T${startTime}`);
            const end = new Date(`${endDate}T${endTime}`);
            
            if (end < start) {
                alert('La date de fin doit être postérieure à la date de début!');
                document.getElementById('edit-event-end-date').value = '';
                document.getElementById('edit-event-end-time').value = '';
            }
        }
    }
    
    // Ajouter des écouteurs pour la validation de date
    document.getElementById('event-end-date').addEventListener('change', validateEndDate);
    document.getElementById('event-end-time').addEventListener('change', validateEndDate);
    document.getElementById('edit-event-end-date').addEventListener('change', validateEditEndDate);
    document.getElementById('edit-event-end-time').addEventListener('change', validateEditEndDate);
    
    // Gestion du modal d'édition
    const editEventModal = document.getElementById('editEventModal');
    if (editEventModal) {
        editEventModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données de l'événement
            document.getElementById('edit-event-id').value = button.getAttribute('data-id');
            document.getElementById('edit-event-title').value = button.getAttribute('data-title');
            document.getElementById('edit-event-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-event-location').value = button.getAttribute('data-location');
            document.getElementById('edit-event-start-date').value = button.getAttribute('data-start-date');
            document.getElementById('edit-event-start-time').value = button.getAttribute('data-start-time');
            document.getElementById('edit-event-end-date').value = button.getAttribute('data-end-date');
            document.getElementById('edit-event-end-time').value = button.getAttribute('data-end-time');
            
            // Pour CKEditor, on doit mettre à jour le contenu des éditeurs
            const description = button.getAttribute('data-description');
            const content = button.getAttribute('data-content');
            
            if (editDescriptionEditor) {
                editDescriptionEditor.setData(description || '');
            } else {
                setTimeout(() => {
                    if (editDescriptionEditor) editDescriptionEditor.setData(description || '');
                }, 300);
            }
            
            if (editContentEditor) {
                editContentEditor.setData(content || '');
            } else {
                setTimeout(() => {
                    if (editContentEditor) editContentEditor.setData(content || '');
                }, 300);
            }
            
            // Mettre à jour l'aperçu de l'image
            const featuredImage = button.getAttribute('data-featured-image');
            document.getElementById('edit-event-image-url').value = featuredImage || '';
            
            if (featuredImage) {
                displayEditImagePreview(featuredImage);
            } else {
                displayEditImagePreview(null);
            }
            
                        // Mettre à jour les checkboxes
                        document.getElementById('edit-event-featured').checked = button.getAttribute('data-is-featured') === '1';
            document.getElementById('edit-event-published').checked = button.getAttribute('data-is-published') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deleteEventModal = document.getElementById('deleteEventModal');
    if (deleteEventModal) {
        deleteEventModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const eventId = button.getAttribute('data-id');
            const eventTitle = button.getAttribute('data-title');
            
            const idField = this.querySelector('#delete-event-id');
            const titleSpan = this.querySelector('#delete-event-title');
            
            if (idField) idField.value = eventId;
            if (titleSpan) titleSpan.textContent = eventTitle;
        });
    }
    
    // Assurer que le contenu de l'éditeur est soumis avec le formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('button[name="action"]').value;
            
            if (action === 'add_event' && descriptionEditor && contentEditor) {
                const descriptionField = document.querySelector('#event-description');
                const contentField = document.querySelector('#event-content');
                
                descriptionField.value = descriptionEditor.getData();
                contentField.value = contentEditor.getData();
            } else if (action === 'update_event' && editDescriptionEditor && editContentEditor) {
                const descriptionField = document.querySelector('#edit-event-description');
                const contentField = document.querySelector('#edit-event-content');
                
                descriptionField.value = editDescriptionEditor.getData();
                contentField.value = editContentEditor.getData();
            }
        });
    });
    
    // Vérifier la taille des fichiers avant l'envoi
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 Mo
    
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            let fileInputs = this.querySelectorAll('input[type="file"]');
            let hasLargeFile = false;
            
            fileInputs.forEach(input => {
                if (input.files.length > 0) {
                    for (let i = 0; i < input.files.length; i++) {
                        if (input.files[i].size > MAX_FILE_SIZE) {
                            hasLargeFile = true;
                            alert(`Le fichier "${input.files[i].name}" est trop volumineux. La taille maximale autorisée est de 2 Mo.`);
                            break;
                        }
                    }
                }
            });
            
            if (hasLargeFile) {
                e.preventDefault();
            }
        });
    });
    
    // Fonction pour mettre à jour la date de fin automatiquement
    document.getElementById('event-start-date').addEventListener('change', function() {
        const endDateField = document.getElementById('event-end-date');
        if (!endDateField.value) {
            endDateField.value = this.value;
        }
    });
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>

