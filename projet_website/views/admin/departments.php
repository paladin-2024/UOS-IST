<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'departments';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/departments/';

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
        // Action d'ajout d'un département
        if ($_POST['action'] === 'add_department' && isset($_POST['name'], $_POST['slug'])) {
            // Gérer l'upload de l'image
            $featuredImage = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $featuredImage = uploadFile($_FILES['featured_image'], $imagesPath);
                // Rendre le chemin relatif
                $featuredImage = str_replace('./', '/', $featuredImage);
            } else if (!empty($_POST['featured_image_url'])) {
                $featuredImage = $_POST['featured_image_url'];
            }
            
            // Insérer le département dans la base de données
            $stmt = $db->prepare("INSERT INTO departments (name, slug, description, head_id, featured_image, is_active) 
                                VALUES (:name, :slug, :description, :head_id, :featured_image, :is_active)");
            
            $headId = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':head_id', $headId, PDO::PARAM_INT);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le département a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un département
        else if ($_POST['action'] === 'update_department' && isset($_POST['department_id'], $_POST['name'], $_POST['slug'])) {
            // Récupérer l'image actuelle
            $currentImageStmt = $db->prepare("SELECT featured_image FROM departments WHERE id = :id");
            $currentImageStmt->bindParam(':id', $_POST['department_id']);
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
            
            // Mettre à jour le département
            $stmt = $db->prepare("UPDATE departments SET name = :name, slug = :slug, description = :description, 
                                head_id = :head_id, featured_image = :featured_image, is_active = :is_active 
                                WHERE id = :id");
            
            $headId = !empty($_POST['head_id']) ? $_POST['head_id'] : null;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->bindParam(':id', $_POST['department_id']);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':head_id', $headId, PDO::PARAM_INT);
            $stmt->bindParam(':featured_image', $featuredImage);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le département a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un département
        else if ($_POST['action'] === 'delete_department' && isset($_POST['department_id'])) {
            // Vérifier si des membres du personnel sont associés à ce département
            $checkStaffStmt = $db->prepare("SELECT COUNT(*) FROM staff WHERE department = (SELECT name FROM departments WHERE id = :department_id)");
            $checkStaffStmt->bindParam(':department_id', $_POST['department_id']);
            $checkStaffStmt->execute();
            
            if ($checkStaffStmt->fetchColumn() > 0) {
                throw new Exception("Des membres du personnel sont associés à ce département. Veuillez d'abord changer leur département avant de supprimer celui-ci.");
            }
            
            // Récupérer l'image à supprimer
            $imageStmt = $db->prepare("SELECT featured_image FROM departments WHERE id = :id");
            $imageStmt->bindParam(':id', $_POST['department_id']);
            $imageStmt->execute();
            $image = $imageStmt->fetchColumn();
            
            // Supprimer l'image si elle existe et n'est pas une URL externe
            if ($image && file_exists('.' . $image) && strpos($image, 'http') !== 0) {
                unlink('.' . $image);
            }
            
            // Supprimer le département
            $stmt = $db->prepare("DELETE FROM departments WHERE id = :id");
            $stmt->bindParam(':id', $_POST['department_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le département a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: departments');
    exit;
}

// Récupérer tous les départements
$departmentsStmt = $db->query("SELECT d.*, s.full_name as head_name FROM departments d LEFT JOIN staff s ON d.head_id = s.id ORDER BY d.name");
$departmentsList = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le personnel pour le dropdown des chefs de département
$staffStmt = $db->query("SELECT id, full_name FROM staff WHERE is_active = 1 ORDER BY full_name");
$staffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des départements -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-building me-2"></i>Gestion des départements</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
        <i class="fas fa-plus me-2"></i>Ajouter un département
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
                    Gérez les départements de votre établissement. Vous pouvez ajouter, modifier et supprimer des départements, ainsi que désigner leurs responsables.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des départements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des départements</h5>
    </div>
    <div class="card-body">
        <?php if (empty($departmentsList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun département n'a été ajouté.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Responsable</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departmentsList as $department): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($department['featured_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($department['featured_image']); ?>" alt="<?php echo htmlspecialchars($department['name']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-building fa-2x"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($department['name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($department['head_name'] ?? 'Non assigné'); ?>
                                </td>
                                <td>
                                    <?php 
                                    // Tronquer la description si elle est trop longue
                                    $description = $department['description'] ?? '';
                                    echo strlen($description) > 100 ? htmlspecialchars(substr($description, 0, 100)) . '...' : htmlspecialchars($description); 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($department['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../department&slug=<?php echo htmlspecialchars($department['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-department-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editDepartmentModal"
                                                data-id="<?php echo $department['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($department['name']); ?>"
                                                data-slug="<?php echo htmlspecialchars($department['slug']); ?>"
                                                data-description="<?php echo htmlspecialchars($department['description'] ?? ''); ?>"
                                                data-head-id="<?php echo $department['head_id'] ?? ''; ?>"
                                                data-featured-image="<?php echo htmlspecialchars($department['featured_image'] ?? ''); ?>"
                                                data-is-active="<?php echo $department['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-department-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteDepartmentModal"
                                                data-id="<?php echo $department['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($department['name']); ?>">
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

<!-- Modal Ajouter un département -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">Ajouter un nouveau département</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="department-name" class="form-label">Nom du département</label>
                                <input type="text" class="form-control" id="department-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="department-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="department-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL (ex: "informatique-medicale").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="department-head" class="form-label">Responsable du département</label>
                                <select class="form-select" id="department-head" name="head_id">
                                    <option value="">-- Sélectionnez un responsable --</option>
                                    <?php foreach ($staffList as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="department-description" class="form-label">Description</label>
                                <textarea class="form-control" id="department-description" name="description" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Image du département</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="featured-image-preview" src="/uploads/placeholder.jpg" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px; display: none;">
                                            <div id="no-image-preview" class="text-muted">
                                                <i class="fas fa-building fa-5x mb-2"></i>
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
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="department-active" name="is_active" checked>
                                        <label class="form-check-label" for="department-active">
                                            Actif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_department">Ajouter le département</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un département -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDepartmentModalLabel">Modifier le département</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-department-id" name="department_id">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-department-name" class="form-label">Nom du département</label>
                                <input type="text" class="form-control" id="edit-department-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-department-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-department-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-department-head" class="form-label">Responsable du département</label>
                                <select class="form-select" id="edit-department-head" name="head_id">
                                    <option value="">-- Sélectionnez un responsable --</option>
                                    <?php foreach ($staffList as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit-department-description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit-department-description" name="description" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Image du département</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-featured-image-preview" src="" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            <div id="edit-no-image-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-building fa-5x mb-2"></i>
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
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-department-active" name="is_active">
                                        <label class="form-check-label" for="edit-department-active">
                                            Actif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_department">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un département -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteDepartmentModalLabel">Supprimer un département</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-department-id" name="department_id">
                    <p>Êtes-vous sûr de vouloir supprimer le département <strong id="delete-department-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce département.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_department">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration de CKEditor
    const editorConfig = {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo'],
        language: 'fr',
    };
    
    // Initialiser CKEditor pour le formulaire d'ajout
    let descriptionEditor;
    ClassicEditor
        .create(document.querySelector('#department-description'), editorConfig)
        .then(editor => {
            descriptionEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Initialiser CKEditor pour le formulaire d'édition
    let editDescriptionEditor;
    ClassicEditor
        .create(document.querySelector('#edit-department-description'), editorConfig)
        .then(editor => {
            editDescriptionEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Générer le slug à partir du nom du département pour le nouveau formulaire
    document.getElementById('department-name').addEventListener('keyup', function() {
        const name = this.value;
        const slug = name.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-')     // Remplacer les espaces par des tirets
            .replace(/--+/g, '-');    // Éviter les tirets multiples
        
        document.getElementById('department-slug').value = slug;
    });
    
    // Gestion de la prévisualisation de l'image pour l'ajout
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
    
    // Gestion de la prévisualisation de l'image pour l'édition
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
    
    // Gestion du modal d'édition
    const editDepartmentModal = document.getElementById('editDepartmentModal');
    if (editDepartmentModal) {
        editDepartmentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données du département
            document.getElementById('edit-department-id').value = button.getAttribute('data-id');
            document.getElementById('edit-department-name').value = button.getAttribute('data-name');
            document.getElementById('edit-department-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-department-head').value = button.getAttribute('data-head-id');
            
            // Pour CKEditor, on doit mettre à jour le contenu de l'éditeur
            const description = button.getAttribute('data-description');
            
            if (editDescriptionEditor) {
                editDescriptionEditor.setData(description || '');
            } else {
                setTimeout(() => {
                    if (editDescriptionEditor) editDescriptionEditor.setData(description || '');
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
            
            // Mettre à jour le checkbox
            document.getElementById('edit-department-active').checked = button.getAttribute('data-is-active') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deleteDepartmentModal = document.getElementById('deleteDepartmentModal');
    if (deleteDepartmentModal) {
        deleteDepartmentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const departmentId = button.getAttribute('data-id');
            const departmentName = button.getAttribute('data-name');
            
            const idField = this.querySelector('#delete-department-id');
            const nameSpan = this.querySelector('#delete-department-name');
            
            if (idField) idField.value = departmentId;
            if (nameSpan) nameSpan.textContent = departmentName;
        });
    }

    // Assurer que le contenu de l'éditeur est soumis avec le formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('button[name="action"]').value;
            
            if (action === 'add_department' && descriptionEditor) {
                const descriptionField = document.querySelector('#department-description');
                descriptionField.value = descriptionEditor.getData();
            } else if (action === 'update_department' && editDescriptionEditor) {
                const descriptionField = document.querySelector('#edit-department-description');
                descriptionField.value = editDescriptionEditor.getData();
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

