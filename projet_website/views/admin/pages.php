<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'pages';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images et les ressources
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/pages/';
$resourcesPath = $uploadsPath . 'resources/pages/';

// Créer les répertoires d'upload s'ils n'existent pas
if (!file_exists($imagesPath)) {
    mkdir($imagesPath, 0777, true);
}
if (!file_exists($resourcesPath)) {
    mkdir($resourcesPath, 0777, true);
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
        // Action d'ajout de page
        if ($_POST['action'] === 'add_page' && isset($_POST['title'], $_POST['slug'], $_POST['content'])) {
            // Gérer l'upload de l'image à la une
            $featuredImage = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $featuredImage = uploadFile($_FILES['featured_image'], $imagesPath);
                // Rendre le chemin relatif
                $featuredImage = str_replace('./', '/', $featuredImage);
            } else if (!empty($_POST['featured_image_url'])) {
                $featuredImage = $_POST['featured_image_url'];
            }
            
            $stmt = $db->prepare("INSERT INTO pages (title, slug, content, meta_description, featured_image, template, is_published, created_by) 
                                VALUES (:title, :slug, :content, :meta_description, :featured_image, :template, :is_published, :created_by)");
            
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $metaDescription = !empty($_POST['meta_description']) ? $_POST['meta_description'] : null;
            $currentUserId = $_SESSION['user_id']; // Assumons que l'ID de l'utilisateur est stocké en session
            
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':content', $_POST['content']);
            $stmt->bindParam(':meta_description', $metaDescription);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':template', $_POST['template']);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->bindParam(':created_by', $currentUserId);
            $stmt->execute();
            
            $pageId = $db->lastInsertId();
            
            // Gérer l'upload des ressources
            if (isset($_FILES['resources'])) {
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'image/jpeg', 'image/png', 'image/gif'];
                
                foreach ($_FILES['resources']['name'] as $key => $name) {
                    if ($_FILES['resources']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['resources']['name'][$key],
                            'type' => $_FILES['resources']['type'][$key],
                            'tmp_name' => $_FILES['resources']['tmp_name'][$key],
                            'error' => $_FILES['resources']['error'][$key],
                            'size' => $_FILES['resources']['size'][$key]
                        ];
                        
                        $resourcePath = uploadFile($file, $resourcesPath, $allowedTypes);
                        $resourcePath = str_replace('./', '/', $resourcePath);
                        
                        // Insérer la ressource dans la table media
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, uploaded_by) VALUES (:file_name, :file_path, :file_type, :file_size, :title, :uploaded_by)");
                        
                        $mediaStmt->bindParam(':file_name', $name);
                        $mediaStmt->bindParam(':file_path', $resourcePath);
                        $mediaStmt->bindParam(':file_type', $file['type']);
                        $mediaStmt->bindParam(':file_size', $file['size']);
                        $mediaStmt->bindParam(':title', $name);
                        $mediaStmt->bindParam(':uploaded_by', $currentUserId);
                        $mediaStmt->execute();
                        
                        $mediaId = $db->lastInsertId();
                        
                        // Associer le média à la page via la table page_media
                        $linkStmt = $db->prepare("INSERT INTO page_media (page_id, media_id) VALUES (:page_id, :media_id)");
                        $linkStmt->bindParam(':page_id', $pageId);
                        $linkStmt->bindParam(':media_id', $mediaId);
                        $linkStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La page a été créée avec succès.";
        }
        
        // Action de mise à jour de page
        else if ($_POST['action'] === 'update_page' && isset($_POST['page_id'], $_POST['title'], $_POST['slug'], $_POST['content'])) {
            // Récupérer l'image actuelle
            $currentImageStmt = $db->prepare("SELECT featured_image FROM pages WHERE id = :id");
            $currentImageStmt->bindParam(':id', $_POST['page_id']);
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
            
            $stmt = $db->prepare("UPDATE pages SET title = :title, slug = :slug, content = :content, 
                                meta_description = :meta_description, featured_image = :featured_image, 
                                template = :template, is_published = :is_published WHERE id = :id");
            
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $metaDescription = !empty($_POST['meta_description']) ? $_POST['meta_description'] : null;
            
            $stmt->bindParam(':id', $_POST['page_id']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':content', $_POST['content']);
            $stmt->bindParam(':meta_description', $metaDescription);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':template', $_POST['template']);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->execute();
            
            // Gérer les nouvelles ressources ajoutées
            if (isset($_FILES['resources'])) {
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'image/jpeg', 'image/png', 'image/gif'];
                $currentUserId = $_SESSION['user_id'];
                
                foreach ($_FILES['resources']['name'] as $key => $name) {
                    if ($_FILES['resources']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['resources']['name'][$key],
                            'type' => $_FILES['resources']['type'][$key],
                            'tmp_name' => $_FILES['resources']['tmp_name'][$key],
                            'error' => $_FILES['resources']['error'][$key],
                            'size' => $_FILES['resources']['size'][$key]
                        ];
                        
                        $resourcePath = uploadFile($file, $resourcesPath, $allowedTypes);
                        $resourcePath = str_replace('./', '/', $resourcePath);
                        
                        // Insérer la ressource dans la table media
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, uploaded_by) VALUES (:file_name, :file_path, :file_type, :file_size, :title, :uploaded_by)");
                        
                        $mediaStmt->bindParam(':file_name', $name);
                        $mediaStmt->bindParam(':file_path', $resourcePath);
                        $mediaStmt->bindParam(':file_type', $file['type']);
                        $mediaStmt->bindParam(':file_size', $file['size']);
                        $mediaStmt->bindParam(':title', $name);
                        $mediaStmt->bindParam(':uploaded_by', $currentUserId);
                        $mediaStmt->execute();
                        
                        $mediaId = $db->lastInsertId();
                        
                        // Associer le média à la page via la table page_media
                        $linkStmt = $db->prepare("INSERT INTO page_media (page_id, media_id) VALUES (:page_id, :media_id)");
                        $linkStmt->bindParam(':page_id', $_POST['page_id']);
                        $linkStmt->bindParam(':media_id', $mediaId);
                        $linkStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La page a été mise à jour avec succès.";
        }
        
        // Action de suppression de page
        else if ($_POST['action'] === 'delete_page' && isset($_POST['page_id'])) {
            // Récupérer l'image à supprimer
            $imageStmt = $db->prepare("SELECT featured_image FROM pages WHERE id = :id");
            $imageStmt->bindParam(':id', $_POST['page_id']);
            $imageStmt->execute();
            $image = $imageStmt->fetchColumn();
            
            // Récupérer les ressources liées à la page pour les supprimer
            $resourcesStmt = $db->prepare("
                SELECT m.id, m.file_path 
                FROM media m 
                JOIN page_media pm ON m.id = pm.media_id 
                WHERE pm.page_id = :page_id");
            $resourcesStmt->bindParam(':page_id', $_POST['page_id']);
            $resourcesStmt->execute();
            $resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Supprimer l'image si elle existe
            if ($image && file_exists('.' . $image) && !strpos($image, 'http')) {
                unlink('.' . $image);
            }
            
            // Supprimer les fichiers de ressources
            foreach ($resources as $resource) {
                if (file_exists('.' . $resource['file_path'])) {
                    unlink('.' . $resource['file_path']);
                }
            }
            
            // Les relations page_media seront supprimées automatiquement grâce à la contrainte ON DELETE CASCADE
            $stmt = $db->prepare("DELETE FROM pages WHERE id = :id");
            $stmt->bindParam(':id', $_POST['page_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La page a été supprimée avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: pages');
    exit;
}

// Récupérer toutes les pages avec leurs ressources
$pagesStmt = $db->query("SELECT p.*, u.full_name as author_name 
                       FROM pages p 
                       LEFT JOIN users u ON p.created_by = u.id 
                       ORDER BY p.created_at DESC");
$pages = $pagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les modèles de page disponibles
// Récupérer les modèles de page disponibles
$templates = ['default', 'full-width', 'with-sidebar', 'contact', 'home', 'about'];

// Pour chaque page, récupérer les ressources associées
foreach ($pages as &$page) {
    $resourcesStmt = $db->prepare("
        SELECT m.* 
        FROM media m 
        JOIN page_media pm ON m.id = pm.media_id 
        WHERE pm.page_id = :page_id
        ORDER BY pm.order_index");
    $resourcesStmt->bindParam(':page_id', $page['id']);
    $resourcesStmt->execute();
    $page['resources'] = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des pages -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Gestion des pages</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">
        <i class="fas fa-plus me-2"></i>Créer une page
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
                    Gérez les pages de votre site. Vous pouvez créer, modifier et supprimer des pages statiques comme "À propos", "Contact", etc.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des pages -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des pages</h5>
    </div>
    <div class="card-body">
        <?php if (empty($pages)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucune page n'a été créée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Titre</th>
                            <th>Slug</th>
                            <th>Modèle</th>
                            <th>Auteur</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($page['featured_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($page['featured_image']); ?>" alt="<?php echo htmlspecialchars($page['title']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-image"></i> Aucune image</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($page['title']); ?>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($page['slug']); ?></code>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($page['template']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($page['author_name'] ?? 'Non attribué'); ?>
                                </td>
                                <td>
                                    <?php if ($page['is_published']): ?>
                                        <span class="badge bg-success">Publié</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Brouillon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($page['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../details_page&slug=<?php echo htmlspecialchars($page['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-page-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPageModal"
                                                data-id="<?php echo $page['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($page['title']); ?>"
                                                data-slug="<?php echo htmlspecialchars($page['slug']); ?>"
                                                data-content="<?php echo htmlspecialchars($page['content']); ?>"
                                                data-meta-description="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>"
                                                data-featured-image="<?php echo htmlspecialchars($page['featured_image'] ?? ''); ?>"
                                                data-template="<?php echo htmlspecialchars($page['template']); ?>"
                                                data-is-published="<?php echo $page['is_published']; ?>"
                                                data-resources='<?php echo htmlspecialchars(json_encode($page['resources'])); ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-page-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deletePageModal"
                                                data-id="<?php echo $page['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($page['title']); ?>">
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

<!-- Modal Ajouter une page -->
<div class="modal fade" id="addPageModal" tabindex="-1" aria-labelledby="addPageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPageModalLabel">Créer une nouvelle page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="page-title" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="page-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="page-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="page-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique de la page dans l'URL (ex: "a-propos" pour "www.votresite.com/a-propos").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="page-content" class="form-label">Contenu</label>
                                <textarea class="form-control" id="page-content" name="content" rows="10"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="page-template" class="form-label">Modèle de page</label>
                                <select class="form-select" id="page-template" name="template">
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template; ?>"><?php echo ucfirst(str_replace('-', ' ', $template)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="page-meta-description" class="form-label">Méta description</label>
                                <textarea class="form-control" id="page-meta-description" name="meta_description" rows="3"></textarea>
                                <div class="form-text">
                                    Description qui apparaît dans les résultats de recherche (max 160 caractères).
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Image à la une</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="featured-image-preview" src="/uploads/placeholder.jpg" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px; display: none;">
                                            <div id="no-image-preview" class="text-muted">
                                                <i class="fas fa-image fa-3x mb-2"></i>
                                                <p>Aucune image sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="featured-image-upload" name="featured_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="featured-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="featured-image-url" name="featured_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ressources attachées</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div id="resources-container">
                                            <div class="mb-2">
                                                <input type="file" class="form-control resource-upload" name="resources[]">
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-resource-btn">
                                            <i class="fas fa-plus me-1"></i> Ajouter une ressource
                                        </button>
                                        <div class="form-text mt-2">
                                            Ajoutez des documents PDF, Word, Excel ou des images qui seront disponibles au téléchargement.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="page-published" name="is_published" checked>
                                <label class="form-check-label" for="page-published">
                                    Publier cette page
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_page">Créer la page</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier une page -->
<div class="modal fade" id="editPageModal" tabindex="-1" aria-labelledby="editPageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPageModalLabel">Modifier la page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-page-id" name="page_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                            <label for="edit-page-title" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="edit-page-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-page-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-page-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique de la page dans l'URL (ex: "a-propos" pour "www.votresite.com/a-propos").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-page-content" class="form-label">Contenu</label>
                                <textarea class="form-control" id="edit-page-content" name="content" rows="10"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit-page-template" class="form-label">Modèle de page</label>
                                <select class="form-select" id="edit-page-template" name="template">
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template; ?>"><?php echo ucfirst(str_replace('-', ' ', $template)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit-page-meta-description" class="form-label">Méta description</label>
                                <textarea class="form-control" id="edit-page-meta-description" name="meta_description" rows="3"></textarea>
                                <div class="form-text">
                                    Description qui apparaît dans les résultats de recherche (max 160 caractères).
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Image à la une</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-featured-image-preview" src="" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            <div id="edit-no-image-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-image fa-3x mb-2"></i>
                                                <p>Aucune image sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="edit-featured-image-upload" name="featured_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit-featured-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="edit-featured-image-url" name="featured_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ressources actuelles</label>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div id="current-resources-container">
                                            <!-- Les ressources existantes seront affichées ici dynamiquement -->
                                        </div>
                                        <div class="form-text mt-2" id="no-resources-message" style="display: none;">
                                            Aucune ressource attachée à cette page.
                                        </div>
                                    </div>
                                </div>
                                <label class="form-label">Ajouter de nouvelles ressources</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div id="edit-resources-container">
                                            <div class="mb-2">
                                                <input type="file" class="form-control resource-upload" name="resources[]">
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="edit-add-resource-btn">
                                            <i class="fas fa-plus me-1"></i> Ajouter une ressource
                                        </button>
                                        <div class="form-text mt-2">
                                            Ajoutez des documents PDF, Word, Excel ou des images qui seront disponibles au téléchargement.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit-page-published" name="is_published">
                                <label class="form-check-label" for="edit-page-published">
                                    Publier cette page
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_page">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer une page -->
<div class="modal fade" id="deletePageModal" tabindex="-1" aria-labelledby="deletePageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePageModalLabel">Supprimer la page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-page-id" name="page_id">
                    <p>Êtes-vous sûr de vouloir supprimer la page <strong id="delete-page-title"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement cette page et toutes les ressources associées.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_page">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour initialiser l'éditeur de texte riche -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des éditeurs CKEditor
    const editorConfig = {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'undo', 'redo'],
        language: 'fr',
        placeholder: 'Saisissez le contenu de votre page ici...',
        height: '400px'
    };
    
    // Initialiser CKEditor pour le formulaire d'ajout
    let addEditor;
    ClassicEditor
        .create(document.querySelector('#page-content'), editorConfig)
        .then(editor => {
            addEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    // Initialiser CKEditor pour le formulaire d'édition
    let editEditor;
    ClassicEditor
        .create(document.querySelector('#edit-page-content'), editorConfig)
        .then(editor => {
            editEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Générer le slug à partir du titre pour le nouveau formulaire
    document.getElementById('page-title').addEventListener('keyup', function() {
        const title = this.value;
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-') // Remplacer les espaces par des tirets
            .replace(/--+/g, '-'); // Éviter les tirets multiples
        
        document.getElementById('page-slug').value = slug;
    });
    
    // Gestion de la prévisualisation de l'image à la une pour l'ajout
    const featuredImageUpload = document.getElementById('featured-image-upload');
    const featuredImageUrl = document.getElementById('featured-image-url');
    const featuredImagePreview = document.getElementById('featured-image-preview');
    const noImagePreview = document.getElementById('no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image
    function displayImagePreview(src) {
        if (src) {
            featuredImagePreview.src = src;
            featuredImagePreview.style.display = 'block';
            noImagePreview.style.display = 'none';
        } else {
            featuredImagePreview.style.display = 'none';
            noImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image
    featuredImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                featuredImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        } else {
            displayImagePreview(null);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image
    featuredImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            featuredImageUpload.value = '';
        } else {
            displayImagePreview(null);
        }
    });
    
    // Gestion de la prévisualisation de l'image à la une pour l'édition
    const editFeaturedImageUpload = document.getElementById('edit-featured-image-upload');
    const editFeaturedImageUrl = document.getElementById('edit-featured-image-url');
    const editFeaturedImagePreview = document.getElementById('edit-featured-image-preview');
    const editNoImagePreview = document.getElementById('edit-no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image en mode édition
    function displayEditImagePreview(src) {
        if (src) {
            editFeaturedImagePreview.src = src.startsWith('http') ? src : ".."+src;
            editFeaturedImagePreview.style.display = 'block';
            editNoImagePreview.style.display = 'none';
        } else {
            editFeaturedImagePreview.style.display = 'none';
            editNoImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image en mode édition
    editFeaturedImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayEditImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                editFeaturedImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image en mode édition
    editFeaturedImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayEditImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            editFeaturedImageUpload.value = '';
        }
    });
    
    // Ajouter des champs pour les ressources supplémentaires
    const addResourceBtn = document.getElementById('add-resource-btn');
    const resourcesContainer = document.getElementById('resources-container');
    
    addResourceBtn.addEventListener('click', function() {
        const newResourceField = document.createElement('div');
        newResourceField.className = 'mb-2 resource-field';
        newResourceField.innerHTML = `
            <div class="input-group">
                <input type="file" class="form-control resource-upload" name="resources[]">
                <button type="button" class="btn btn-outline-danger remove-resource">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        resourcesContainer.appendChild(newResourceField);
        
        // Ajouter un écouteur d'événement pour le bouton de suppression
        newResourceField.querySelector('.remove-resource').addEventListener('click', function() {
            newResourceField.remove();
        });
    });
    
    // Ajouter des champs pour les ressources supplémentaires en mode édition
    const editAddResourceBtn = document.getElementById('edit-add-resource-btn');
    const editResourcesContainer = document.getElementById('edit-resources-container');
    
    editAddResourceBtn.addEventListener('click', function() {
        const newResourceField = document.createElement('div');
        newResourceField.className = 'mb-2 resource-field';
        newResourceField.innerHTML = `
            <div class="input-group">
                <input type="file" class="form-control resource-upload" name="resources[]">
                                <button type="button" class="btn btn-outline-danger remove-resource">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        editResourcesContainer.appendChild(newResourceField);
        
        // Ajouter un écouteur d'événement pour le bouton de suppression
        newResourceField.querySelector('.remove-resource').addEventListener('click', function() {
            newResourceField.remove();
        });
    });
    
    // Fonction pour afficher les ressources existantes
    function displayExistingResources(resources) {
        const currentResourcesContainer = document.getElementById('current-resources-container');
        const noResourcesMessage = document.getElementById('no-resources-message');
        
        // Vider le conteneur
        currentResourcesContainer.innerHTML = '';
        
        if (resources && resources.length > 0) {
            noResourcesMessage.style.display = 'none';
            
            resources.forEach(resource => {
                const resourceItem = document.createElement('div');
                resourceItem.className = 'resource-item mb-2 p-2 border rounded';
                
                // Déterminer l'icône en fonction du type de fichier
                let fileIcon = 'fas fa-file';
                if (resource.file_type.includes('pdf')) {
                    fileIcon = 'fas fa-file-pdf';
                } else if (resource.file_type.includes('word')) {
                    fileIcon = 'fas fa-file-word';
                } else if (resource.file_type.includes('excel') || resource.file_type.includes('spreadsheet')) {
                    fileIcon = 'fas fa-file-excel';
                } else if (resource.file_type.includes('image')) {
                    fileIcon = 'fas fa-file-image';
                }
                
                resourceItem.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="me-2"><i class="${fileIcon} fa-lg"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">${resource.file_name}</div>
                            <div class="small text-muted">${formatFileSize(resource.file_size)}</div>
                        </div>
                        <div>
                            <a href="..${resource.file_path}" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Télécharger">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                `;
                
                currentResourcesContainer.appendChild(resourceItem);
            });
        } else {
            noResourcesMessage.style.display = 'block';
        }
    }
    
    // Fonction pour formater la taille du fichier
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Gestion du modal d'édition de page
    const editPageModal = document.getElementById('editPageModal');
    if (editPageModal) {
        editPageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données de la page
            document.getElementById('edit-page-id').value = button.getAttribute('data-id');
            document.getElementById('edit-page-title').value = button.getAttribute('data-title');
            document.getElementById('edit-page-slug').value = button.getAttribute('data-slug');
            
            // Pour CKEditor 5, on doit mettre à jour le contenu de l'éditeur
            const content = button.getAttribute('data-content');
            if (editEditor) {
                editEditor.setData(content);
            } else {
                // Si l'éditeur n'est pas encore initialisé, attendre un peu
                setTimeout(() => {
                    if (editEditor) {
                        editEditor.setData(content);
                    }
                }, 300);
            }
            
            document.getElementById('edit-page-meta-description').value = button.getAttribute('data-meta-description') || '';
            
            // Mettre à jour l'aperçu de l'image
            const featuredImage = button.getAttribute('data-featured-image');
            document.getElementById('edit-featured-image-url').value = featuredImage || '';
            
            if (featuredImage) {
                displayEditImagePreview(featuredImage);
            } else {
                displayEditImagePreview(null);
            }
            
            document.getElementById('edit-page-template').value = button.getAttribute('data-template');
            document.getElementById('edit-page-published').checked = button.getAttribute('data-is-published') === '1';
            
            // Afficher les ressources existantes
            try {
                const resources = JSON.parse(button.getAttribute('data-resources') || '[]');
                displayExistingResources(resources);
            } catch (error) {
                console.error('Erreur lors du parsing des ressources:', error);
                displayExistingResources([]);
            }
        });
    }
    
    // Gestion du modal de suppression de page
    const deletePageModal = document.getElementById('deletePageModal');
    if (deletePageModal) {
        deletePageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const pageId = button.getAttribute('data-id');
            const pageTitle = button.getAttribute('data-title');
            
            const idField = this.querySelector('#delete-page-id');
            const titleSpan = this.querySelector('#delete-page-title');
            
            if (idField) idField.value = pageId;
            if (titleSpan) titleSpan.textContent = pageTitle;
        });
    }

    // Assurer que le contenu de l'éditeur est soumis avec le formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('button[name="action"]').value;
            
            if (action === 'add_page' && addEditor) {
                const contentField = document.querySelector('#page-content');
                contentField.value = addEditor.getData();
            } else if (action === 'update_page' && editEditor) {
                const contentField = document.querySelector('#edit-page-content');
                contentField.value = editEditor.getData();
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
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>

