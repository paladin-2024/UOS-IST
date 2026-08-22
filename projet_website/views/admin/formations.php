<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'formations';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images et les ressources
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/formations/';
$resourcesPath = $uploadsPath . 'resources/formations/';

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
        // Action d'ajout de formation
        if ($_POST['action'] === 'add_formation' && isset($_POST['title'], $_POST['slug'], $_POST['content'])) {
            // Gérer l'upload de l'image à la une
            $featuredImage = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $featuredImage = uploadFile($_FILES['featured_image'], $imagesPath);
                // Rendre le chemin relatif
                $featuredImage = str_replace('./', '/', $featuredImage);
            } else if (!empty($_POST['featured_image_url'])) {
                $featuredImage = $_POST['featured_image_url'];
            }
            
            // Insérer la formation dans la base de données
            $stmt = $db->prepare("INSERT INTO formations (title, slug, short_description, content, featured_image, category_id, 
                                duration, level, credits, is_featured, is_published, published_at, created_by) 
                                VALUES (:title, :slug, :short_description, :content, :featured_image, :category_id, 
                                :duration, :level, :credits, :is_featured, :is_published, :published_at, :created_by)");
            
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $shortDescription = !empty($_POST['short_description']) ? $_POST['short_description'] : null;
            $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $duration = !empty($_POST['duration']) ? $_POST['duration'] : null;
            $level = $_POST['level'];
            $credits = !empty($_POST['credits']) ? $_POST['credits'] : null;
            $currentUserId = $_SESSION['user_id']; // Assumons que l'ID de l'utilisateur est stocké en session
            $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
            
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':short_description', $shortDescription);
            $stmt->bindParam(':content', $_POST['content']);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':category_id', $categoryId);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':level', $level);
            $stmt->bindParam(':credits', $credits);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->bindParam(':published_at', $publishedAt);
            $stmt->bindParam(':created_by', $currentUserId);
            $stmt->execute();
            
            $formationId = $db->lastInsertId();
            
            // Gestion des modules si présents
            if (isset($_POST['module_titles']) && is_array($_POST['module_titles'])) {
                foreach ($_POST['module_titles'] as $key => $title) {
                    if (empty($title)) continue;
                    
                    $moduleStmt = $db->prepare("INSERT INTO formation_modules (formation_id, title, description, credits, semester, order_index) 
                                             VALUES (:formation_id, :title, :description, :credits, :semester, :order_index)");
                    
                    $description = isset($_POST['module_descriptions'][$key]) ? $_POST['module_descriptions'][$key] : null;
                    $moduleCredits = isset($_POST['module_credits'][$key]) ? $_POST['module_credits'][$key] : null;
                    $semester = isset($_POST['module_semesters'][$key]) ? $_POST['module_semesters'][$key] : null;
                    $order = isset($_POST['module_orders'][$key]) ? $_POST['module_orders'][$key] : $key;
                    
                    $moduleStmt->bindParam(':formation_id', $formationId);
                    $moduleStmt->bindParam(':title', $title);
                    $moduleStmt->bindParam(':description', $description);
                    $moduleStmt->bindParam(':credits', $moduleCredits);
                    $moduleStmt->bindParam(':semester', $semester);
                    $moduleStmt->bindParam(':order_index', $order);
                    $moduleStmt->execute();
                }
            }
            
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
                        
                        
                        
                        // First insert into media table without gallery_id
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, uploaded_by) 
                        VALUES (:file_name, :file_path, :file_type, :file_size, :title, :uploaded_by)");
                        $mediaStmt->bindParam(':file_name', $name);
                        $mediaStmt->bindParam(':file_path', $resourcePath);
                        $mediaStmt->bindParam(':file_type', $file['type']);
                        $mediaStmt->bindParam(':file_size', $file['size']);
                        $mediaStmt->bindParam(':title', $name);
                        $mediaStmt->bindParam(':uploaded_by', $currentUserId);
                        $mediaStmt->execute();

                        // Get the media ID
                        $mediaId = $db->lastInsertId();

                        // Now create the relationship in formation_media table
                        $formationMediaStmt = $db->prepare("INSERT INTO formation_media (formation_id, media_id) VALUES (:formation_id, :media_id)");
                        $formationMediaStmt->bindParam(':formation_id', $formationId);
                        $formationMediaStmt->bindParam(':media_id', $mediaId);
                        $formationMediaStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La formation a été créée avec succès.";
        }
        
        // Action de mise à jour d'une formation
        else if ($_POST['action'] === 'update_formation' && isset($_POST['formation_id'], $_POST['title'], $_POST['slug'], $_POST['content'])) {
            // Récupérer l'image actuelle
            $currentImageStmt = $db->prepare("SELECT featured_image FROM formations WHERE id = :id");
            $currentImageStmt->bindParam(':id', $_POST['formation_id']);
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
            
            // Mettre à jour la formation
            $stmt = $db->prepare("UPDATE formations SET title = :title, slug = :slug, short_description = :short_description, 
                                content = :content, featured_image = :featured_image, category_id = :category_id, 
                                duration = :duration, level = :level, credits = :credits,
                                is_featured = :is_featured, is_published = :is_published, 
                                published_at = :published_at WHERE id = :id");
            
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $shortDescription = !empty($_POST['short_description']) ? $_POST['short_description'] : null;
            $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $duration = !empty($_POST['duration']) ? $_POST['duration'] : null;
            $level = $_POST['level'];
            $credits = !empty($_POST['credits']) ? $_POST['credits'] : null;
            
            // Mettre à jour published_at si la formation est publiée et n'avait pas de date précédente
            $publishedAtStmt = $db->prepare("SELECT published_at FROM formations WHERE id = :id");
            $publishedAtStmt->bindParam(':id', $_POST['formation_id']);
            $publishedAtStmt->execute();
            $currentPublishedAt = $publishedAtStmt->fetchColumn();
            
            $publishedAt = $isPublished ? ($currentPublishedAt ?: date('Y-m-d H:i:s')) : $currentPublishedAt;
            
            $stmt->bindParam(':id', $_POST['formation_id']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':short_description', $shortDescription);
            $stmt->bindParam(':content', $_POST['content']);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':category_id', $categoryId);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':level', $level);
            $stmt->bindParam(':credits', $credits);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':is_published', $isPublished);
            $stmt->bindParam(':published_at', $publishedAt);
            $stmt->execute();
            
            // Gérer les modules de formation
            // D'abord supprimer les modules existants
            $deleteModulesStmt = $db->prepare("DELETE FROM formation_modules WHERE formation_id = :formation_id");
            $deleteModulesStmt->bindParam(':formation_id', $_POST['formation_id']);
            $deleteModulesStmt->execute();
            
            // Puis ajouter les nouveaux modules
            if (isset($_POST['module_titles']) && is_array($_POST['module_titles'])) {
                foreach ($_POST['module_titles'] as $key => $title) {
                    if (empty($title)) continue;
                    
                    $moduleStmt = $db->prepare("INSERT INTO formation_modules (formation_id, title, description, credits, semester, order_index) 
                                             VALUES (:formation_id, :title, :description, :credits, :semester, :order_index)");
                    
                    $description = isset($_POST['module_descriptions'][$key]) ? $_POST['module_descriptions'][$key] : null;
                    $moduleCredits = isset($_POST['module_credits'][$key]) ? $_POST['module_credits'][$key] : null;
                    $semester = isset($_POST['module_semesters'][$key]) ? $_POST['module_semesters'][$key] : null;
                    $order = isset($_POST['module_orders'][$key]) ? $_POST['module_orders'][$key] : $key;
                    
                    $moduleStmt->bindParam(':formation_id', $_POST['formation_id']);
                    $moduleStmt->bindParam(':title', $title);
                    $moduleStmt->bindParam(':description', $description);
                    $moduleStmt->bindParam(':credits', $moduleCredits);
                    $moduleStmt->bindParam(':semester', $semester);
                    $moduleStmt->bindParam(':order_index', $order);
                    $moduleStmt->execute();
                }
            }
            
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
                        
                        // First insert into media table without gallery_id
                        $mediaStmt = $db->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, title, uploaded_by) 
                        VALUES (:file_name, :file_path, :file_type, :file_size, :title, :uploaded_by)");
                        $mediaStmt->bindParam(':file_name', $name);
                        $mediaStmt->bindParam(':file_path', $resourcePath);
                        $mediaStmt->bindParam(':file_type', $file['type']);
                        $mediaStmt->bindParam(':file_size', $file['size']);
                        $mediaStmt->bindParam(':title', $name);
                        $mediaStmt->bindParam(':uploaded_by', $currentUserId);
                        $mediaStmt->execute();

                        // Get the media ID
                        $mediaId = $db->lastInsertId();

                        // Now create the relationship in formation_media table
                        $formationMediaStmt = $db->prepare("INSERT INTO formation_media (formation_id, media_id) VALUES (:formation_id, :media_id)");
                        $formationMediaStmt->bindParam(':formation_id', $_POST['formation_id']);
                        $formationMediaStmt->bindParam(':media_id', $mediaId);
                        $formationMediaStmt->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "La formation a été mise à jour avec succès.";
        }
        
        // Action de suppression d'une formation
        else if ($_POST['action'] === 'delete_formation' && isset($_POST['formation_id'])) {
            // Récupérer l'image à supprimer
            $imageStmt = $db->prepare("SELECT featured_image FROM formations WHERE id = :id");
            $imageStmt->bindParam(':id', $_POST['formation_id']);
            $imageStmt->execute();
            $image = $imageStmt->fetchColumn();
            
            // Récupérer les ressources liées à la formation
            $resourcesStmt = $db->prepare("
                SELECT m.id, m.file_path 
                FROM media m
                JOIN formation_media fm ON m.id = fm.media_id
                WHERE fm.formation_id = :formation_id");
            $resourcesStmt->bindParam(':formation_id', $_POST['formation_id']);
            $resourcesStmt->execute();
            $resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Supprimer l'image si elle existe et n'est pas une URL externe
            if ($image && file_exists('.' . $image) && strpos($image, 'http') !== 0) {
                unlink('.' . $image);
            }
            
            // Supprimer les fichiers physiques des ressources
            foreach ($resources as $resource) {
                if (file_exists('.' . $resource['file_path'])) {
                    unlink('.' . $resource['file_path']);
                }
            }
            
            // Supprimer les modules de la formation
            $deleteModulesStmt = $db->prepare("DELETE FROM formation_modules WHERE formation_id = :formation_id");
            $deleteModulesStmt->bindParam(':formation_id', $_POST['formation_id']);
            $deleteModulesStmt->execute();
            
            // D'abord récupérer les IDs des médias liés à cette formation
            $mediaIdsStmt = $db->prepare("SELECT media_id FROM formation_media WHERE formation_id = :formation_id");
            $mediaIdsStmt->bindParam(':formation_id', $_POST['formation_id']);
            $mediaIdsStmt->execute();
            $mediaIds = $mediaIdsStmt->fetchAll(PDO::FETCH_COLUMN);

            // Supprimer les relations dans la table formation_media
            $deleteFormationMediaStmt = $db->prepare("DELETE FROM formation_media WHERE formation_id = :formation_id");
            $deleteFormationMediaStmt->bindParam(':formation_id', $_POST['formation_id']);
            $deleteFormationMediaStmt->execute();

            // Supprimer les médias orphelins
            if (!empty($mediaIds)) {
                $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
                $deleteMediaStmt = $db->prepare("DELETE FROM media WHERE id IN ($placeholders)");
                foreach ($mediaIds as $index => $id) {
                    $deleteMediaStmt->bindValue($index + 1, $id);
                }
                $deleteMediaStmt->execute();
            }
            
            // Supprimer la formation
            $stmt = $db->prepare("DELETE FROM formations WHERE id = :id");
            $stmt->bindParam(':id', $_POST['formation_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La formation a été supprimée avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: formations');
    exit;
}

// Récupérer toutes les formations
$formationsStmt = $db->query("SELECT f.*, u.full_name as author_name, c.name as category_name 
                            FROM formations f 
                            LEFT JOIN users u ON f.created_by = u.id 
                            LEFT JOIN categories c ON f.category_id = c.id
                            ORDER BY f.created_at DESC");
$formationsList = $formationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Pour chaque formation, récupérer les modules et ressources associés
foreach ($formationsList as &$formation) {
    // Récupérer les modules
    $modulesStmt = $db->prepare("
        SELECT * 
        FROM formation_modules 
        WHERE formation_id = :formation_id
        ORDER BY order_index");
    $modulesStmt->bindParam(':formation_id', $formation['id']);
    $modulesStmt->execute();
    $formation['modules'] = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les ressources
    $resourcesStmt = $db->prepare("
    SELECT m.* 
    FROM media m
    JOIN formation_media fm ON m.id = fm.media_id
    WHERE fm.formation_id = :formation_id
    ORDER BY m.id");
    $resourcesStmt->bindParam(':formation_id', $formation['id']);
    $resourcesStmt->execute();
    $formation['resources'] = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les catégories de type 'formation'
$categoriesStmt = $db->query("SELECT id, name FROM categories WHERE type = 'formation' OR type = 'general' ORDER BY name");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des formations -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-graduation-cap me-2"></i>Gestion des formations</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFormationModal">
        <i class="fas fa-plus me-2"></i>Ajouter une formation
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
                    Gérez les formations proposées par votre établissement. Vous pouvez créer, modifier et supprimer des formations, ainsi que gérer leurs modules et ressources pédagogiques.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des formations -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des formations</h5>
    </div>
    <div class="card-body">
        <?php if (empty($formationsList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucune formation n'a été créée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Titre</th>
                            <th>Niveau</th>
                            <th>Catégorie</th>
                            <th>Durée</th>
                            <th>Crédits</th>
                            <th>Statut</th>
                            <th>À la une</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formationsList as $formation): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($formation['featured_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($formation['featured_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-image"></i> Aucune image</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                </td>
                                <td>
                                    <?php 
                                    $niveaux = [
                                        'licence' => 'Licence', 
                                        'master' => 'Master', 
                                        'doctorat' => 'Doctorat', 
                                        'formation_continue' => 'Formation continue'
                                    ];
                                    echo isset($niveaux[$formation['level']]) ? $niveaux[$formation['level']] : $formation['level']; 
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($formation['category_name'] ?? 'Non catégorisé'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($formation['duration'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($formation['credits'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php if ($formation['is_published']): ?>
                                        <span class="badge bg-success">Publié</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Brouillon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($formation['is_featured']): ?>
                                        <span class="badge bg-warning text-dark">À la une</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../formation_details&slug=<?php echo htmlspecialchars($formation['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-formation-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editFormationModal"
                                                data-id="<?php echo $formation['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($formation['title']); ?>"
                                                data-slug="<?php echo htmlspecialchars($formation['slug']); ?>"
                                                data-short-description="<?php echo htmlspecialchars($formation['short_description'] ?? ''); ?>"
                                                data-content="<?php echo htmlspecialchars($formation['content']); ?>"
                                                data-featured-image="<?php echo htmlspecialchars($formation['featured_image'] ?? ''); ?>"
                                                data-category-id="<?php echo $formation['category_id'] ?? ''; ?>"
                                                data-duration="<?php echo htmlspecialchars($formation['duration'] ?? ''); ?>"
                                                data-level="<?php echo $formation['level']; ?>"
                                                data-credits="<?php echo $formation['credits'] ?? ''; ?>"
                                                data-is-featured="<?php echo $formation['is_featured']; ?>"
                                                data-is-published="<?php echo $formation['is_published']; ?>"
                                                data-modules='<?php echo htmlspecialchars(json_encode($formation['modules'])); ?>'
                                                data-resources='<?php echo htmlspecialchars(json_encode($formation['resources'])); ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-formation-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteFormationModal"
                                                data-id="<?php echo $formation['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($formation['title']); ?>">
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

<!-- Modal Ajouter une formation -->
<div class="modal fade" id="addFormationModal" tabindex="-1" aria-labelledby="addFormationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFormationModalLabel">Ajouter une nouvelle formation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="formation-title" class="form-label">Titre de la formation</label>
                                <input type="text" class="form-control" id="formation-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="formation-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="formation-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique de la formation dans l'URL (ex: "licence-informatique").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="formation-short-description" class="form-label">Description courte</label>
                                <textarea class="form-control" id="formation-short-description" name="short_description" rows="3"></textarea>
                                <div class="form-text">
                                    Un bref résumé de la formation qui sera affiché sur les pages de liste.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="formation-content" class="form-label">Contenu détaillé</label>
                                <textarea class="form-control" id="formation-content" name="content" rows="10"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">Informations générales</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="formation-category" class="form-label">Catégorie</label>
                                        <select class="form-select" id="formation-category" name="category_id">
                                            <option value="">-- Sélectionnez une catégorie --</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="formation-level" class="form-label">Niveau d'études</label>
                                        <select class="form-select" id="formation-level" name="level" required>
                                            <option value="licence">Licence</option>
                                            <option value="master">Master</option>
                                            <option value="doctorat">Doctorat</option>
                                            <option value="formation_continue">Formation continue</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="formation-duration" class="form-label">Durée</label>
                                        <input type="text" class="form-control" id="formation-duration" name="duration" placeholder="Ex: 3 ans, 4 semestres...">
                                    </div>
                                    <div class="mb-3">
                                        <label for="formation-credits" class="form-label">Crédits (ECTS)</label>
                                        <input type="number" class="form-control" id="formation-credits" name="credits" min="0">
                                    </div>
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
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="formation-featured" name="is_featured">
                                <label class="form-check-label" for="formation-featured">
                                    Mettre à la une
                                </label>
                                <div class="form-text">
                                    Les formations à la une sont mises en avant sur la page d'accueil.
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="formation-published" name="is_published" checked>
                                <label class="form-check-label" for="formation-published">
                                    Publier cette formation
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modules de formation -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Modules de formation</h5>
                            <button type="button" class="btn btn-sm btn-primary" id="add-module-btn">
                                <i class="fas fa-plus me-1"></i> Ajouter un module
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="modules-container">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Ajoutez des modules à votre formation pour détailler son contenu pédagogique.
                                </div>
                                <!-- Les modules seront ajoutés ici dynamiquement -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ressources attachées -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Ressources pédagogiques</h5>
                        </div>
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
                                Ajoutez des documents PDF, Word, Excel ou des images qui seront disponibles au téléchargement (programme détaillé, emplois du temps, etc.).
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_formation">Publier la formation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier une formation -->
<div class="modal fade" id="editFormationModal" tabindex="-1" aria-labelledby="editFormationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFormationModalLabel">Modifier la formation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-formation-id" name="formation_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-formation-title" class="form-label">Titre de la formation</label>
                                <input type="text" class="form-control" id="edit-formation-title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-formation-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-formation-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique de la formation dans l'URL.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-formation-short-description" class="form-label">Description courte</label>
                                <textarea class="form-control" id="edit-formation-short-description" name="short_description" rows="3"></textarea>
                                <div class="form-text">
                                    Un bref résumé de la formation qui sera affiché sur les pages de liste.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-formation-content" class="form-label">Contenu détaillé</label>
                                <textarea class="form-control" id="edit-formation-content" name="content" rows="10"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">Informations générales</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="edit-formation-category" class="form-label">Catégorie</label>
                                        <select class="form-select" id="edit-formation-category" name="category_id">
                                            <option value="">-- Sélectionnez une catégorie --</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit-formation-level" class="form-label">Niveau d'études</label>
                                        <select class="form-select" id="edit-formation-level" name="level" required>
                                            <option value="licence">Licence</option>
                                            <option value="master">Master</option>
                                            <option value="doctorat">Doctorat</option>
                                            <option value="formation_continue">Formation continue</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit-formation-duration" class="form-label">Durée</label>
                                        <input type="text" class="form-control" id="edit-formation-duration" name="duration" placeholder="Ex: 3 ans, 4 semestres...">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit-formation-credits" class="form-label">Crédits (ECTS)</label>
                                        <input type="number" class="form-control" id="edit-formation-credits" name="credits" min="0">
                                    </div>
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
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit-formation-featured" name="is_featured">
                                <label class="form-check-label" for="edit-formation-featured">
                                    Mettre à la une
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit-formation-published" name="is_published">
                                <label class="form-check-label" for="edit-formation-published">
                                    Publier cette formation
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modules de formation -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Modules de formation</h5>
                            <button type="button" class="btn btn-sm btn-primary" id="edit-add-module-btn">
                                <i class="fas fa-plus me-1"></i> Ajouter un module
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="edit-modules-container">
                                <!-- Les modules existants seront chargés ici dynamiquement -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ressources attachées -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Ressources actuelles</h5>
                        </div>
                        <div class="card-body">
                            <div id="current-resources-container">
                                <!-- Les ressources existantes seront affichées ici -->
                            </div>
                            <div class="form-text mt-2" id="no-resources-message" style="display: none;">
                                Aucune ressource attachée à cette formation.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Ajouter de nouvelles ressources</h5>
                        </div>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_formation">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer une formation -->
<div class="modal fade" id="deleteFormationModal" tabindex="-1" aria-labelledby="deleteFormationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteFormationModalLabel">Supprimer la formation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-formation-id" name="formation_id">
                    <p>Êtes-vous sûr de vouloir supprimer la formation <strong id="delete-formation-title"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement cette formation ainsi que tous ses modules et ressources associés.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_formation">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour initialiser l'éditeur de texte riche et gérer les formulaires -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des éditeurs CKEditor
    const editorConfig = {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'mediaEmbed', 'undo', 'redo'],
        language: 'fr',
        placeholder: 'Saisissez le contenu détaillé de votre formation ici...',
        height: '400px'
    };
    
    // Initialiser CKEditor pour le formulaire d'ajout
    let addEditor;
    ClassicEditor
        .create(document.querySelector('#formation-content'), editorConfig)
        .then(editor => {
            addEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    // Initialiser CKEditor pour le formulaire d'édition
    let editEditor;
    ClassicEditor
        .create(document.querySelector('#edit-formation-content'), editorConfig)
        .then(editor => {
            editEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Générer le slug à partir du titre pour le nouveau formulaire
    document.getElementById('formation-title').addEventListener('keyup', function() {
        const title = this.value;
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-') // Remplacer les espaces par des tirets
            .replace(/--+/g, '-'); // Éviter les tirets multiples
        
        document.getElementById('formation-slug').value = slug;
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
    
    // Gestion des modules
    const addModuleBtn = document.getElementById('add-module-btn');
    const modulesContainer = document.getElementById('modules-container');
    let moduleCount = 0;
    
    // Fonction pour ajouter un nouveau module
    function addModule(title = '', description = '', credits = '', semester = '', order = '') {
        const moduleId = 'module-' + moduleCount;
        const moduleHTML = `
            <div class="module-item card mb-3" id="${moduleId}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Module</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-module" data-module="${moduleId}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Titre du module</label>
                                <input type="text" class="form-control" name="module_titles[]" value="${title}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Crédits</label>
                                <input type="number" class="form-control" name="module_credits[]" value="${credits}" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Semestre</label>
                                <input type="text" class="form-control" name="module_semesters[]" value="${semester}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Ordre</label>
                                <input type="number" class="form-control" name="module_orders[]" value="${order || moduleCount}" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="module_descriptions[]" rows="3">${description}</textarea>
                    </div>
                </div>
            </div>
        `;
        
        // Si c'est le premier module, supprimer l'alerte d'info
        if (moduleCount === 0) {
            const alertInfo = modulesContainer.querySelector('.alert-info');
            if (alertInfo) alertInfo.remove();
        }
        
        // Ajouter le module au conteneur
        modulesContainer.insertAdjacentHTML('beforeend', moduleHTML);
        
        // Ajouter un écouteur pour le bouton de suppression
        const removeBtn = modulesContainer.querySelector(`#${moduleId} .remove-module`);
        removeBtn.addEventListener('click', function() {
            document.getElementById(moduleId).remove();
        });
        
        moduleCount++;
    }
    
    // Ajouter un premier module vide par défaut
    addModuleBtn.addEventListener('click', function() {
        addModule();
    });
    
    // Gestion des modules pour l'édition
    const editAddModuleBtn = document.getElementById('edit-add-module-btn');
    const editModulesContainer = document.getElementById('edit-modules-container');
    let editModuleCount = 0;
    
    // Fonction pour ajouter un module en mode édition
    function addEditModule(title = '', description = '', credits = '', semester = '', order = '') {
        const moduleId = 'edit-module-' + editModuleCount;
        const moduleHTML = `
            <div class="module-item card mb-3" id="${moduleId}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Module</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-module" data-module="${moduleId}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Titre du module</label>
                                <input type="text" class="form-control" name="module_titles[]" value="${title}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Crédits</label>
                                <input type="number" class="form-control" name="module_credits[]" value="${credits}" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Semestre</label>
                                <input type="text" class="form-control" name="module_semesters[]" value="${semester}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Ordre</label>
                                <input type="number" class="form-control" name="module_orders[]" value="${order || editModuleCount}" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="module_descriptions[]" rows="3">${description}</textarea>
                    </div>
                </div>
            </div>
        `;
        
        // Ajouter le module au conteneur
        editModulesContainer.insertAdjacentHTML('beforeend', moduleHTML);
        
        // Ajouter un écouteur pour le bouton de suppression
        const removeBtn = editModulesContainer.querySelector(`#${moduleId} .remove-module`);
        removeBtn.addEventListener('click', function() {
            document.getElementById(moduleId).remove();
        });
        
        editModuleCount++;
    }
    
    // Ajouter un module en mode édition
    editAddModuleBtn.addEventListener('click', function() {
        addEditModule();
    });
    
    // Fonction pour charger les modules existants lors de l'édition
    function loadExistingModules(modules) {
        // Vider le conteneur de modules
        editModulesContainer.innerHTML = '';
        editModuleCount = 0;
        
        if (modules && modules.length > 0) {
            modules.forEach(module => {
                addEditModule(
                    module.title || '',
                    module.description || '',
                    module.credits || '',
                    module.semester || '',
                    module.order_index || ''
                );
            });
        } else {
            // Ajouter un message si aucun module n'existe
            editModulesContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Aucun module n'a été ajouté à cette formation.
                </div>
            `;
        }
    }
    
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
                let iconColor = 'secondary';
                
                if (resource.file_type.includes('pdf')) {
                    fileIcon = 'fas fa-file-pdf';
                    iconColor = 'danger';
                } else if (resource.file_type.includes('word') || resource.file_type.includes('document')) {
                    fileIcon = 'fas fa-file-word';
                    iconColor = 'primary';
                } else if (resource.file_type.includes('excel') || resource.file_type.includes('spreadsheet')) {
                    fileIcon = 'fas fa-file-excel';
                    iconColor = 'success';
                } else if (resource.file_type.includes('image')) {
                    fileIcon = 'fas fa-file-image';
                    iconColor = 'info';
                } else if (resource.file_type.includes('zip') || resource.file_type.includes('archive')) {
                    fileIcon = 'fas fa-file-archive';
                    iconColor = 'warning';
                }
                
                resourceItem.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="me-2"><i class="${fileIcon} fa-lg text-${iconColor}"></i></div>
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
    
    // Gestion du modal d'édition de formation
    const editFormationModal = document.getElementById('editFormationModal');
    if (editFormationModal) {
        editFormationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données de la formation
            document.getElementById('edit-formation-id').value = button.getAttribute('data-id');
            document.getElementById('edit-formation-title').value = button.getAttribute('data-title');
            document.getElementById('edit-formation-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-formation-short-description').value = button.getAttribute('data-short-description') || '';
            
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
            
            // Mettre à jour l'aperçu de l'image
            const featuredImage = button.getAttribute('data-featured-image');
            document.getElementById('edit-featured-image-url').value = featuredImage || '';
            
            if (featuredImage) {
                displayEditImagePreview(featuredImage);
            } else {
                displayEditImagePreview(null);
            }
            
            // Mettre à jour les autres champs
            document.getElementById('edit-formation-category').value = button.getAttribute('data-category-id') || '';
            document.getElementById('edit-formation-level').value = button.getAttribute('data-level');
            document.getElementById('edit-formation-duration').value = button.getAttribute('data-duration') || '';
            document.getElementById('edit-formation-credits').value = button.getAttribute('data-credits') || '';
            document.getElementById('edit-formation-featured').checked = button.getAttribute('data-is-featured') === '1';
            document.getElementById('edit-formation-published').checked = button.getAttribute('data-is-published') === '1';
            
            // Charger les modules existants
            try {
                const modules = JSON.parse(button.getAttribute('data-modules') || '[]');
                loadExistingModules(modules);
            } catch (error) {
                console.error('Erreur lors du parsing des modules:', error);
                loadExistingModules([]);
            }
            
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
    
    // Gestion du modal de suppression de formation
    const deleteFormationModal = document.getElementById('deleteFormationModal');
    if (deleteFormationModal) {
        deleteFormationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const formationId = button.getAttribute('data-id');
            const formationTitle = button.getAttribute('data-title');
            
            const idField = this.querySelector('#delete-formation-id');
            const titleSpan = this.querySelector('#delete-formation-title');
            
            if (idField) idField.value = formationId;
            if (titleSpan) titleSpan.textContent = formationTitle;
        });
    }

    // Assurer que le contenu de l'éditeur est soumis avec le formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('button[name="action"]').value;
            
            if (action === 'add_formation' && addEditor) {
                const contentField = document.querySelector('#formation-content');
                contentField.value = addEditor.getData();
            } else if (action === 'update_formation' && editEditor) {
                const contentField = document.querySelector('#edit-formation-content');
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
    
    // Trier les modules par ordre lors de la soumission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            // Trier les modules par ordre pour le formulaire d'ajout
            const modulesItems = Array.from(document.querySelectorAll('#modules-container .module-item'));
            if (modulesItems.length > 0) {
                modulesItems.sort((a, b) => {
                    const orderA = parseInt(a.querySelector('input[name="module_orders[]"]').value) || 0;
                    const orderB = parseInt(b.querySelector('input[name="module_orders[]"]').value) || 0;
                    return orderA - orderB;
                });
                
                const modulesContainer = document.getElementById('modules-container');
                modulesItems.forEach(item => {
                    modulesContainer.appendChild(item);
                });
            }
            
            // Trier les modules par ordre pour le formulaire d'édition
            const editModulesItems = Array.from(document.querySelectorAll('#edit-modules-container .module-item'));
            if (editModulesItems.length > 0) {
                editModulesItems.sort((a, b) => {
                    const orderA = parseInt(a.querySelector('input[name="module_orders[]"]').value) || 0;
                    const orderB = parseInt(b.querySelector('input[name="module_orders[]"]').value) || 0;
                    return orderA - orderB;
                });
                
                const editModulesContainer = document.getElementById('edit-modules-container');
                editModulesItems.forEach(item => {
                    editModulesContainer.appendChild(item);
                });
            }
        });
    });
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>




