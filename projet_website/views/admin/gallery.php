<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'gallery';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/gallery/';

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
        // Action d'ajout d'une galerie
        if ($_POST['action'] === 'add_gallery' && isset($_POST['title'], $_POST['slug'])) {
            // Insérer la galerie dans la base de données
            $stmt = $db->prepare("INSERT INTO galleries (title, slug, description, is_published, created_by) 
                                VALUES (:title, :slug, :description, :is_published, :created_by)");
            
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $createdBy = $_SESSION['id'] ?? null;
            
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->bindParam(':created_by', $createdBy, PDO::PARAM_INT);
            $stmt->execute();
            
            // Récupérer l'ID de la galerie créée
            $galleryId = $db->lastInsertId();
            
            // Traiter les images uploadées
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                    if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['gallery_images']['name'][$i],
                            'type' => $_FILES['gallery_images']['type'][$i],
                            'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                            'error' => $_FILES['gallery_images']['error'][$i],
                            'size' => $_FILES['gallery_images']['size'][$i],
                        ];
                        
                        $imagePath = uploadFile($file, $imagesPath);
                        $relativePath = str_replace('./', '/', $imagePath);
                        
                        // Enregistrer l'image dans la table media
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, gallery_id, uploaded_by) 
                                                VALUES (:file_name, :file_path, :file_type, :file_size, :title, :gallery_id, :uploaded_by)");
                        
                        $fileSize = $file['size'];
                        $fileType = $file['type'];
                        $fileName = pathinfo($file['name'], PATHINFO_FILENAME);
                        
                        $mediaStmt->bindParam(':file_name', $fileName);
                        $mediaStmt->bindParam(':file_path', $relativePath);
                        $mediaStmt->bindParam(':file_type', $fileType);
                        $mediaStmt->bindParam(':file_size', $fileSize);
                        $mediaStmt->bindParam(':title', $fileName);
                        $mediaStmt->bindParam(':gallery_id', $galleryId);
                        $mediaStmt->bindParam(':uploaded_by', $createdBy);
                        $mediaStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La galerie a été ajoutée avec succès.";
        }
        
        // Action de mise à jour d'une galerie
        else if ($_POST['action'] === 'update_gallery' && isset($_POST['gallery_id'], $_POST['title'], $_POST['slug'])) {
            // Mettre à jour la galerie
            $stmt = $db->prepare("UPDATE galleries SET title = :title, slug = :slug, description = :description, 
                                is_published = :is_published, updated_at = NOW() 
                                WHERE id = :id");
            
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            
            $stmt->bindParam(':id', $_POST['gallery_id']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->execute();
            
            // Traiter les nouvelles images uploadées
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                $galleryId = $_POST['gallery_id'];
                $createdBy = $_SESSION['user_id'] ?? null;
                
                for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                    if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['gallery_images']['name'][$i],
                            'type' => $_FILES['gallery_images']['type'][$i],
                            'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                            'error' => $_FILES['gallery_images']['error'][$i],
                            'size' => $_FILES['gallery_images']['size'][$i],
                        ];
                        
                        $imagePath = uploadFile($file, $imagesPath);
                        $relativePath = str_replace('./', '/', $imagePath);
                        
                        // Enregistrer l'image dans la table media
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, gallery_id, uploaded_by) 
                                                VALUES (:file_name, :file_path, :file_type, :file_size, :title, :gallery_id, :uploaded_by)");
                        
                        $fileSize = $file['size'];
                        $fileType = $file['type'];
                        $fileName = pathinfo($file['name'], PATHINFO_FILENAME);
                        
                        $mediaStmt->bindParam(':file_name', $fileName);
                        $mediaStmt->bindParam(':file_path', $relativePath);
                        $mediaStmt->bindParam(':file_type', $fileType);
                        $mediaStmt->bindParam(':file_size', $fileSize);
                        $mediaStmt->bindParam(':title', $fileName);
                        $mediaStmt->bindParam(':gallery_id', $galleryId);
                        $mediaStmt->bindParam(':uploaded_by', $createdBy);
                        $mediaStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La galerie a été mise à jour avec succès.";
        }
        
        // Action de suppression d'une galerie
        else if ($_POST['action'] === 'delete_gallery' && isset($_POST['gallery_id'])) {
            // Récupérer toutes les images associées à la galerie
            $mediaStmt = $db->prepare("SELECT file_path FROM media WHERE gallery_id = :gallery_id");
            $mediaStmt->bindParam(':gallery_id', $_POST['gallery_id']);
            $mediaStmt->execute();
            $medias = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Supprimer les fichiers physiques
            foreach ($medias as $media) {
                $filePath = '.' . $media['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Supprimer les entrées de média dans la base de données
            $deleteMediaStmt = $db->prepare("DELETE FROM media WHERE gallery_id = :gallery_id");
            $deleteMediaStmt->bindParam(':gallery_id', $_POST['gallery_id']);
            $deleteMediaStmt->execute();
            
            // Supprimer la galerie
            $stmt = $db->prepare("DELETE FROM galleries WHERE id = :id");
            $stmt->bindParam(':id', $_POST['gallery_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La galerie a été supprimée avec succès.";
        }
        
        // Action de suppression d'une image
        else if ($_POST['action'] === 'delete_media' && isset($_POST['media_id'])) {
            // Récupérer les informations de l'image
            $mediaStmt = $db->prepare("SELECT file_path FROM media WHERE id = :id");
            $mediaStmt->bindParam(':id', $_POST['media_id']);
            $mediaStmt->execute();
            $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
            
            // Supprimer le fichier physique
            if ($media && file_exists('.' . $media['file_path'])) {
                unlink('.' . $media['file_path']);
            }
            
            // Supprimer l'entrée dans la base de données
            $stmt = $db->prepare("DELETE FROM media WHERE id = :id");
            $stmt->bindParam(':id', $_POST['media_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'image a été supprimée avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: gallery');
    exit;
}

// Récupérer toutes les galeries
$galleriesStmt = $db->query("SELECT g.*, u.full_name as creator_name, 
                            (SELECT COUNT(*) FROM media m WHERE m.gallery_id = g.id) as image_count 
                            FROM galleries g 
                            LEFT JOIN users u ON g.created_by = u.id 
                            ORDER BY g.created_at DESC");
$galleriesList = $galleriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des galeries -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-images me-2"></i>Gestion des galeries</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
        <i class="fas fa-plus me-2"></i>Ajouter une galerie
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
                    Gérez les galeries d'images de votre site. Vous pouvez ajouter, modifier et supprimer des galeries ainsi que les images qu'elles contiennent.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des galeries -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des galeries</h5>
    </div>
    <div class="card-body">
        <?php if (empty($galleriesList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucune galerie n'a été ajoutée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Description</th>
                            <th>Images</th>
                            <th>Statut</th>
                            <th>Créé par</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($galleriesList as $gallery): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($gallery['title']); ?>
                                </td>
                                <td>
                                    <?php 
                                    // Tronquer la description si elle est trop longue
                                    $description = $gallery['description'] ?? '';
                                    echo strlen($description) > 100 ? htmlspecialchars(substr($description, 0, 100)) . '...' : htmlspecialchars($description); 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $gallery['image_count']; ?> images</span>
                                </td>
                                <td>
                                    <?php if ($gallery['is_published']): ?>
                                        <span class="badge bg-success">Publiée</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non publiée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($gallery['creator_name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($gallery['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../gallery&slug=<?php echo htmlspecialchars($gallery['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-gallery-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editGalleryModal"
                                                data-id="<?php echo $gallery['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($gallery['title']); ?>"
                                                data-slug="<?php echo htmlspecialchars($gallery['slug']); ?>"
                                                data-description="<?php echo htmlspecialchars($gallery['description'] ?? ''); ?>"
                                                data-is-published="<?php echo $gallery['is_published']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success view-images-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewImagesModal"
                                                data-id="<?php echo $gallery['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($gallery['title']); ?>">
                                            <i class="fas fa-images"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-gallery-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteGalleryModal"
                                                data-id="<?php echo $gallery['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($gallery['title']); ?>"
                                                data-image-count="<?php echo $gallery['image_count']; ?>">
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

<!-- Modal Ajouter une galerie -->
<div class="modal fade" id="addGalleryModal" tabindex="-1" aria-labelledby="addGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGalleryModalLabel">Ajouter une nouvelle galerie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="gallery-title" class="form-label">Titre de la galerie</label>
                        <input type="text" class="form-control" id="gallery-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="gallery-slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="gallery-slug" name="slug" required>
                        <div class="form-text">
                            L'identifiant unique dans l'URL (ex: "evenement-rentree-2023").
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="gallery-description" class="form-label">Description</label>
                        <textarea class="form-control" id="gallery-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="gallery-images" class="form-label">Images</label>
                        <input type="file" class="form-control" id="gallery-images" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">
                            Vous pouvez sélectionner plusieurs images à la fois. Formats acceptés: JPG, PNG, GIF, WEBP.
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="gallery-published" name="is_published" checked>
                            <label class="form-check-label" for="gallery-published">
                                Publier la galerie
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_gallery">Ajouter la galerie</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier une galerie -->
<div class="modal fade" id="editGalleryModal" tabindex="-1" aria-labelledby="editGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGalleryModalLabel">Modifier la galerie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-gallery-id" name="gallery_id">
                    <div class="mb-3">
                        <label for="edit-gallery-title" class="form-label">Titre de la galerie</label>
                        <input type="text" class="form-control" id="edit-gallery-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-gallery-slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="edit-gallery-slug" name="slug" required>
                        <div class="form-text">
                            L'identifiant unique dans l'URL.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-gallery-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-gallery-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-gallery-images" class="form-label">Ajouter de nouvelles images</label>
                        <input type="file" class="form-control" id="edit-gallery-images" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">
                            Vous pouvez sélectionner plusieurs images à la fois. Les nouvelles images seront ajoutées aux images existantes.
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit-gallery-published" name="is_published">
                            <label class="form-check-label" for="edit-gallery-published">
                                Publier la galerie
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_gallery">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Voir les images -->
<div class="modal fade" id="viewImagesModal" tabindex="-1" aria-labelledby="viewImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewImagesModalLabel">Images de la galerie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div id="gallery-images-container" class="row g-3">
                    <!-- Les images seront chargées dynamiquement ici -->
                    <div class="text-center w-100 py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Supprimer une galerie -->
<div class="modal fade" id="deleteGalleryModal" tabindex="-1" aria-labelledby="deleteGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGalleryModalLabel">Supprimer une galerie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-gallery-id" name="gallery_id">
                    <p>Êtes-vous sûr de vouloir supprimer la galerie <strong id="delete-gallery-title"></strong> ?</p>
                    <div id="delete-gallery-warning" class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="delete-gallery-message">Cette action est irréversible et supprimera définitivement cette galerie et toutes ses images.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_gallery">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer une image -->
<div class="modal fade" id="deleteMediaModal" tabindex="-1" aria-labelledby="deleteMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMediaModalLabel">Supprimer une image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-media-id" name="media_id">
                    <p>Êtes-vous sûr de vouloir supprimer cette image ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement cette image.</span>
                    </div>
                    <div class="text-center">
                        <img id="delete-media-preview" src="" alt="Image à supprimer" class="img-fluid img-thumbnail" style="max-height: 200px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_media">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Générer le slug à partir du titre de la galerie pour le nouveau formulaire
    document.getElementById('gallery-title').addEventListener('keyup', function() {
        const title = this.value;
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-')     // Remplacer les espaces par des tirets
            .replace(/--+/g, '-');    // Éviter les tirets multiples
        
        document.getElementById('gallery-slug').value = slug;
    });
    
    // Gestion du modal d'édition
    const editGalleryModal = document.getElementById('editGalleryModal');
    if (editGalleryModal) {
        editGalleryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données de la galerie
            document.getElementById('edit-gallery-id').value = button.getAttribute('data-id');
            document.getElementById('edit-gallery-title').value = button.getAttribute('data-title');
            document.getElementById('edit-gallery-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-gallery-description').value = button.getAttribute('data-description');
            document.getElementById('edit-gallery-published').checked = button.getAttribute('data-is-published') === '1';
        });
    }
    
    // Gestion du modal de visualisation des images
    const viewImagesModal = document.getElementById('viewImagesModal');
    if (viewImagesModal) {
        viewImagesModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const galleryId = button.getAttribute('data-id');
            const galleryTitle = button.getAttribute('data-title');
            
            // Mettre à jour le titre du modal
            const modalTitle = this.querySelector('.modal-title');
            if (modalTitle) modalTitle.textContent = `Images de la galerie: ${galleryTitle}`;
            
            // Récupérer les images de la galerie via AJAX
            const imagesContainer = document.getElementById('gallery-images-container');
            imagesContainer.innerHTML = `
                <div class="text-center w-100 py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            `;
            
            // Simuler une requête AJAX pour récupérer les images
            // Dans un environnement réel, vous utiliseriez fetch() ou XMLHttpRequest
            // pour récupérer les données depuis le serveur
            setTimeout(() => {
                fetch(`../controller/get_gallery_images.php?gallery_id=${galleryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        imagesContainer.innerHTML = `
                            <div class="col-12 text-center">
                                <div class="alert alert-info">
                                    Aucune image dans cette galerie.
                                </div>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = '';
                    data.forEach(media => {
                        html += `
                            <div class="col-md-4 col-lg-3">
                                <div class="card h-100">
                                    <img src="..${media.file_path}" class="card-img-top" alt="${media.title || 'Image'}">
                                    <div class="card-body">
                                        <h6 class="card-title">${media.title || 'Sans titre'}</h6>
                                        <p class="card-text text-muted small">
                                            ${formatFileSize(media.file_size)} - ${media.file_type}
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-media-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteMediaModal"
                                                data-id="${media.id}"
                                                data-image-path="${media.file_path}">
                                            <i class="fas fa-trash-alt"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    imagesContainer.innerHTML = html;
                    
                    // Initialiser les boutons de suppression d'image
                    document.querySelectorAll('.delete-media-btn').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            const mediaId = this.getAttribute('data-id');
                            const imagePath = this.getAttribute('data-image-path');
                            
                            document.getElementById('delete-media-id').value = mediaId;
                            document.getElementById('delete-media-preview').src = '..' + imagePath;
                            
                            // Fermer le modal des images quand on ouvre celui de suppression
                            const currentModal = bootstrap.Modal.getInstance(viewImagesModal);
                            currentModal.hide();
                        });
                    });
                })
                .catch(error => {
                    imagesContainer.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-danger">
                                Erreur lors du chargement des images: ${error.message}
                            </div>
                        </div>
                    `;
                });
            }, 500);
        });
        
        // Lorsqu'on ferme le modal de suppression d'image, réouvrir celui des images
        const deleteMediaModal = document.getElementById('deleteMediaModal');
        if (deleteMediaModal) {
            deleteMediaModal.addEventListener('hidden.bs.modal', function() {
                const galleryModal = new bootstrap.Modal(viewImagesModal);
                galleryModal.show();
            });
        }
    }
    
    // Gestion du modal de suppression de galerie
    const deleteGalleryModal = document.getElementById('deleteGalleryModal');
    if (deleteGalleryModal) {
        deleteGalleryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const galleryId = button.getAttribute('data-id');
            const galleryTitle = button.getAttribute('data-title');
            const imageCount = button.getAttribute('data-image-count');
            
            const idField = this.querySelector('#delete-gallery-id');
            const titleSpan = this.querySelector('#delete-gallery-title');
            const messageSpan = this.querySelector('#delete-gallery-message');
            
            if (idField) idField.value = galleryId;
            if (titleSpan) titleSpan.textContent = galleryTitle;
            
            if (messageSpan) {
                if (imageCount > 0) {
                    messageSpan.textContent = `Cette action est irréversible et supprimera définitivement cette galerie et toutes ses images (${imageCount} images).`;
                } else {
                    messageSpan.textContent = 'Cette action est irréversible et supprimera définitivement cette galerie.';
                }
            }
        });
    }
    
    // Fonction pour formater la taille des fichiers
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Vérifier la taille des fichiers avant l'envoi
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 Mo par image
    const MAX_TOTAL_SIZE = 50 * 1024 * 1024; // 50 Mo au total
    
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            let fileInputs = this.querySelectorAll('input[type="file"]');
            let hasLargeFile = false;
            let totalSize = 0;
            
            fileInputs.forEach(input => {
                if (input.files.length > 0) {
                    for (let i = 0; i < input.files.length; i++) {
                        const fileSize = input.files[i].size;
                        totalSize += fileSize;
                        
                        if (fileSize > MAX_FILE_SIZE) {
                            hasLargeFile = true;
                            alert(`Le fichier "${input.files[i].name}" est trop volumineux. La taille maximale autorisée est de 5 Mo par image.`);
                            break;
                        }
                    }
                    
                    if (totalSize > MAX_TOTAL_SIZE) {
                        hasLargeFile = true;
                        alert(`La taille totale des fichiers (${(totalSize / 1048576).toFixed(1)} Mo) dépasse la limite autorisée de 50 Mo.`);
                    }
                }
            });
            
            if (hasLargeFile) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php

// Inclure le footer
include_once 'views/admin/include/footer.php';
?>

