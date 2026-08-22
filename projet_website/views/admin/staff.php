<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'staff';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les images
$uploadsPath = './uploads/';
$imagesPath = $uploadsPath . 'images/staff/';

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
        // Action d'ajout d'un membre du personnel
        if ($_POST['action'] === 'add_staff' && isset($_POST['full_name'], $_POST['slug'])) {
            // Gérer l'upload de l'image de profil
            $profileImage = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $profileImage = uploadFile($_FILES['profile_image'], $imagesPath);
                // Rendre le chemin relatif
                $profileImage = str_replace('./', '/', $profileImage);
            } else if (!empty($_POST['profile_image_url'])) {
                $profileImage = $_POST['profile_image_url'];
            }
            
            // Formater les liens sociaux en JSON
            $socialLinks = [];
            if (!empty($_POST['social_facebook'])) $socialLinks['facebook'] = $_POST['social_facebook'];
            if (!empty($_POST['social_twitter'])) $socialLinks['twitter'] = $_POST['social_twitter'];
            if (!empty($_POST['social_linkedin'])) $socialLinks['linkedin'] = $_POST['social_linkedin'];
            if (!empty($_POST['social_instagram'])) $socialLinks['instagram'] = $_POST['social_instagram'];
            $socialLinksJson = !empty($socialLinks) ? json_encode($socialLinks) : null;
            
            // Insérer le membre du personnel dans la base de données
            $stmt = $db->prepare("INSERT INTO staff (full_name, slug, position, department, email, phone, bio, 
                                expertise, profile_image, social_links, is_featured, order_index, is_active) 
                                VALUES (:full_name, :slug, :position, :department, :email, :phone, :bio, 
                                :expertise, :profile_image, :social_links, :is_featured, :order_index, :is_active)");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $position = !empty($_POST['position']) ? $_POST['position'] : null;
            $department = !empty($_POST['department']) ? $_POST['department'] : null;
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
            $bio = !empty($_POST['bio']) ? $_POST['bio'] : null;
            $expertise = !empty($_POST['expertise']) ? $_POST['expertise'] : null;
            $orderIndex = !empty($_POST['order_index']) ? $_POST['order_index'] : 0;
            
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':expertise', $expertise);
            $stmt->bindParam(':profile_image', $profileImage);
            $stmt->bindParam(':social_links', $socialLinksJson);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du personnel a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un membre du personnel
        else if ($_POST['action'] === 'update_staff' && isset($_POST['staff_id'], $_POST['full_name'], $_POST['slug'])) {
            // Récupérer l'image actuelle
            $currentImageStmt = $db->prepare("SELECT profile_image FROM staff WHERE id = :id");
            $currentImageStmt->bindParam(':id', $_POST['staff_id']);
            $currentImageStmt->execute();
            $currentImage = $currentImageStmt->fetchColumn();
            
            // Gérer l'upload de la nouvelle image
            $profileImage = $currentImage;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $profileImage = uploadFile($_FILES['profile_image'], $imagesPath);
                $profileImage = str_replace('./', '/', $profileImage);
                
                // Supprimer l'ancienne image si elle existe
                if ($currentImage && file_exists('.' . $currentImage) && !strpos($currentImage, 'http')) {
                    unlink('.' . $currentImage);
                }
            } else if (!empty($_POST['profile_image_url'])) {
                $profileImage = $_POST['profile_image_url'];
            }
            
            // Formater les liens sociaux en JSON
            $socialLinks = [];
            if (!empty($_POST['social_facebook'])) $socialLinks['facebook'] = $_POST['social_facebook'];
            if (!empty($_POST['social_twitter'])) $socialLinks['twitter'] = $_POST['social_twitter'];
            if (!empty($_POST['social_linkedin'])) $socialLinks['linkedin'] = $_POST['social_linkedin'];
            if (!empty($_POST['social_instagram'])) $socialLinks['instagram'] = $_POST['social_instagram'];
            $socialLinksJson = !empty($socialLinks) ? json_encode($socialLinks) : null;
            
            // Mettre à jour le membre du personnel
            $stmt = $db->prepare("UPDATE staff SET full_name = :full_name, slug = :slug, position = :position, 
                                department = :department, email = :email, phone = :phone, bio = :bio, 
                                expertise = :expertise, profile_image = :profile_image, social_links = :social_links, 
                                is_featured = :is_featured, order_index = :order_index, is_active = :is_active  
                                WHERE id = :id");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $position = !empty($_POST['position']) ? $_POST['position'] : null;
            $department = !empty($_POST['department']) ? $_POST['department'] : null;
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
            $bio = !empty($_POST['bio']) ? $_POST['bio'] : null;
            $expertise = !empty($_POST['expertise']) ? $_POST['expertise'] : null;
            $orderIndex = !empty($_POST['order_index']) ? $_POST['order_index'] : 0;
            
            $stmt->bindParam(':id', $_POST['staff_id']);
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':expertise', $expertise);
            $stmt->bindParam(':profile_image', $profileImage);
            $stmt->bindParam(':social_links', $socialLinksJson);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du personnel a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un membre du personnel
        else if ($_POST['action'] === 'delete_staff' && isset($_POST['staff_id'])) {
            // Vérifier d'abord si cette personne est désignée comme chef de département
            $checkDeptHeadStmt = $db->prepare("SELECT COUNT(*) FROM departments WHERE head_id = :staff_id");
            $checkDeptHeadStmt->bindParam(':staff_id', $_POST['staff_id']);
            $checkDeptHeadStmt->execute();
            
            if ($checkDeptHeadStmt->fetchColumn() > 0) {
                throw new Exception("Ce membre du personnel est actuellement désigné comme chef de département. Veuillez d'abord changer le chef de département avant de supprimer.");
            }
            
            // Récupérer l'image à supprimer
            $imageStmt = $db->prepare("SELECT profile_image FROM staff WHERE id = :id");
            $imageStmt->bindParam(':id', $_POST['staff_id']);
            $imageStmt->execute();
            $image = $imageStmt->fetchColumn();
            
            // Supprimer l'image si elle existe et n'est pas une URL externe
            if ($image && file_exists('.' . $image) && strpos($image, 'http') !== 0) {
                unlink('.' . $image);
            }
            
            // Supprimer le membre du personnel
            $stmt = $db->prepare("DELETE FROM staff WHERE id = :id");
            $stmt->bindParam(':id', $_POST['staff_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du personnel a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: staff');
    exit;
}

// Récupérer tous les membres du personnel
$staffStmt = $db->query("SELECT * FROM staff ORDER BY order_index, full_name");
$staffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les départements pour le dropdown
$departmentsStmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY name");
$departments = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion du personnel -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-tie me-2"></i>Gestion du personnel</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
        <i class="fas fa-plus me-2"></i>Ajouter un membre
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
                    Gérez les membres du personnel, enseignants et administrateurs de votre établissement. Vous pouvez ajouter, modifier et supprimer des informations sur le personnel.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau du personnel -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste du personnel</h5>
    </div>
    <div class="card-body">
        <?php if (empty($staffList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun membre du personnel n'a été ajouté.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom complet</th>
                            <th>Fonction</th>
                            <th>Département</th>
                            <th>Contact</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>À la une</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffList as $staff): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($staff['profile_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-user-circle fa-2x"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($staff['full_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($staff['position'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($staff['department'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($staff['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($staff['email']); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($staff['phone'])): ?>
                                        <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($staff['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($staff['order_index']); ?>
                                </td>
                                <td>
                                    <?php if ($staff['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($staff['is_featured']): ?>
                                        <span class="badge bg-warning text-dark">À la une</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../staff_details&slug=<?php echo htmlspecialchars($staff['slug']); ?>" target="_blank" class="btn btn-outline-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary edit-staff-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editStaffModal"
                                                data-id="<?php echo $staff['id']; ?>"
                                                data-full-name="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                data-slug="<?php echo htmlspecialchars($staff['slug']); ?>"
                                                data-position="<?php echo htmlspecialchars($staff['position'] ?? ''); ?>"
                                                data-department="<?php echo htmlspecialchars($staff['department'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>"
                                                data-bio="<?php echo htmlspecialchars($staff['bio'] ?? ''); ?>"
                                                data-expertise="<?php echo htmlspecialchars($staff['expertise'] ?? ''); ?>"
                                                data-profile-image="<?php echo htmlspecialchars($staff['profile_image'] ?? ''); ?>"
                                                data-social-links='<?php echo htmlspecialchars($staff['social_links'] ?? '{}'); ?>'
                                                data-is-featured="<?php echo $staff['is_featured']; ?>"
                                                data-is-active="<?php echo $staff['is_active']; ?>"
                                                data-order-index="<?php echo $staff['order_index']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-staff-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteStaffModal"
                                                data-id="<?php echo $staff['id']; ?>"
                                                data-full-name="<?php echo htmlspecialchars($staff['full_name']); ?>">
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

<!-- Modal Ajouter un membre du personnel -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalLabel">Ajouter un nouveau membre du personnel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="staff-full-name" class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="staff-full-name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="staff-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="staff-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL (ex: "jean-dupont").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="staff-position" class="form-label">Fonction / Poste</label>
                                <input type="text" class="form-control" id="staff-position" name="position">
                            </div>
                            <div class="mb-3">
                                <label for="staff-department" class="form-label">Département</label>
                                <select class="form-select" id="staff-department" name="department">
                                    <option value="">-- Sélectionnez un département --</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department['name']); ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="staff-email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="staff-email" name="email">
                            </div>
                            <div class="mb-3">
                                <label for="staff-phone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="staff-phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="staff-bio" class="form-label">Biographie</label>
                                <textarea class="form-control" id="staff-bio" name="bio" rows="5"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="staff-expertise" class="form-label">Expertise / Spécialités</label>
                                <textarea class="form-control" id="staff-expertise" name="expertise" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Photo de profil</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="profile-image-preview" src="/uploads/placeholder.jpg" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px; display: none;">
                                            <div id="no-image-preview" class="text-muted">
                                                <i class="fas fa-user-circle fa-5x mb-2"></i>
                                                <p>Aucune photo sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="profile-image-upload" name="profile_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="profile-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="profile-image-url" name="profile_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Réseaux sociaux</div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label for="social-facebook" class="form-label"><i class="fab fa-facebook me-1"></i> Facebook</label>
                                        <input type="url" class="form-control" id="social-facebook" name="social_facebook" placeholder="https://facebook.com/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="social-twitter" class="form-label"><i class="fab fa-twitter me-1"></i> Twitter</label>
                                        <input type="url" class="form-control" id="social-twitter" name="social_twitter" placeholder="https://twitter.com/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="social-linkedin" class="form-label"><i class="fab fa-linkedin me-1"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="social-linkedin" name="social_linkedin" placeholder="https://linkedin.com/in/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="social-instagram" class="form-label"><i class="fab fa-instagram me-1"></i> Instagram</label>
                                        <input type="url" class="form-control" id="social-instagram" name="social_instagram" placeholder="https://instagram.com/...">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="staff-order" class="form-label">Ordre d'affichage</label>
                                        <input type="number" class="form-control" id="staff-order" name="order_index" value="0" min="0">
                                        <div class="form-text">
                                            Détermine l'ordre d'affichage (les plus petits s'affichent en premier).
                                        </div>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="staff-featured" name="is_featured">
                                        <label class="form-check-label" for="staff-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="staff-active" name="is_active" checked>
                                        <label class="form-check-label" for="staff-active">
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
                    <button type="submit" class="btn btn-primary" name="action" value="add_staff">Ajouter le membre</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un membre du personnel -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStaffModalLabel">Modifier le membre du personnel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-staff-id" name="staff_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-staff-full-name" class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="edit-staff-full-name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-staff-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-position" class="form-label">Fonction / Poste</label>
                                <input type="text" class="form-control" id="edit-staff-position" name="position">
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-department" class="form-label">Département</label>
                                <select class="form-select" id="edit-staff-department" name="department">
                                    <option value="">-- Sélectionnez un département --</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department['name']); ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit-staff-email" name="email">
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-phone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="edit-staff-phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-bio" class="form-label">Biographie</label>
                                <textarea class="form-control" id="edit-staff-bio" name="bio" rows="5"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit-staff-expertise" class="form-label">Expertise / Spécialités</label>
                                <textarea class="form-control" id="edit-staff-expertise" name="expertise" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Photo de profil</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-profile-image-preview" src="" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            <div id="edit-no-image-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-user-circle fa-5x mb-2"></i>
                                                <p>Aucune photo sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="edit-profile-image-upload" name="profile_image" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit-profile-image-url" class="form-label">Ou utilisez une URL d'image</label>
                                            <input type="url" class="form-control" id="edit-profile-image-url" name="profile_image_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Réseaux sociaux</div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label for="edit-social-facebook" class="form-label"><i class="fab fa-facebook me-1"></i> Facebook</label>
                                        <input type="url" class="form-control" id="edit-social-facebook" name="social_facebook" placeholder="https://facebook.com/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="edit-social-twitter" class="form-label"><i class="fab fa-twitter me-1"></i> Twitter</label>
                                        <input type="url" class="form-control" id="edit-social-twitter" name="social_twitter" placeholder="https://twitter.com/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="edit-social-linkedin" class="form-label"><i class="fab fa-linkedin me-1"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="edit-social-linkedin" name="social_linkedin" placeholder="https://linkedin.com/in/...">
                                    </div>
                                    <div class="mb-2">
                                        <label for="edit-social-instagram" class="form-label"><i class="fab fa-instagram me-1"></i> Instagram</label>
                                        <input type="url" class="form-control" id="edit-social-instagram" name="social_instagram" placeholder="https://instagram.com/...">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="edit-staff-order" class="form-label">Ordre d'affichage</label>
                                        <input type="number" class="form-control" id="edit-staff-order" name="order_index" value="0" min="0">
                                        <div class="form-text">
                                            Détermine l'ordre d'affichage (les plus petits s'affichent en premier).
                                        </div>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-staff-featured" name="is_featured">
                                        <label class="form-check-label" for="edit-staff-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-staff-active" name="is_active">
                                        <label class="form-check-label" for="edit-staff-active">
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
                    <button type="submit" class="btn btn-primary" name="action" value="update_staff">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un membre du personnel -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-labelledby="deleteStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteStaffModalLabel">Supprimer un membre du personnel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-staff-id" name="staff_id">
                    <p>Êtes-vous sûr de vouloir supprimer <strong id="delete-staff-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce membre du personnel ainsi que toutes ses informations associées.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_staff">Supprimer</button>
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
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo'],
        language: 'fr',
    };
    
    // Initialiser CKEditor pour le formulaire d'ajout
    let bioEditor, expertiseEditor;
    ClassicEditor
        .create(document.querySelector('#staff-bio'), editorConfig)
        .then(editor => {
            bioEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    ClassicEditor
        .create(document.querySelector('#staff-expertise'), editorConfig)
        .then(editor => {
            expertiseEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Initialiser CKEditor pour le formulaire d'édition
    let editBioEditor, editExpertiseEditor;
    ClassicEditor
        .create(document.querySelector('#edit-staff-bio'), editorConfig)
        .then(editor => {
            editBioEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });

    ClassicEditor
        .create(document.querySelector('#edit-staff-expertise'), editorConfig)
        .then(editor => {
            editExpertiseEditor = editor;
        })
        .catch(error => {
            console.error(error);
        });
    
    // Générer le slug à partir du nom complet pour le nouveau formulaire
    document.getElementById('staff-full-name').addEventListener('keyup', function() {
        const name = this.value;
        const slug = name.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-')     // Remplacer les espaces par des tirets
            .replace(/--+/g, '-');    // Éviter les tirets multiples
        
        document.getElementById('staff-slug').value = slug;
    });
    
    // Gestion de la prévisualisation de l'image de profil pour l'ajout
    const profileImageUpload = document.getElementById('profile-image-upload');
    const profileImageUrl = document.getElementById('profile-image-url');
    const profileImagePreview = document.getElementById('profile-image-preview');
    const noImagePreview = document.getElementById('no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image
    function displayImagePreview(src) {
        if (src) {
            profileImagePreview.src = src;
            profileImagePreview.style.display = 'block';
            noImagePreview.style.display = 'none';
        } else {
            profileImagePreview.style.display = 'none';
            noImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image
    profileImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                profileImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        } else {
            displayImagePreview(null);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image
    profileImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            profileImageUpload.value = '';
        } else {
            displayImagePreview(null);
        }
    });
    
    // Gestion de la prévisualisation de l'image pour l'édition
    const editProfileImageUpload = document.getElementById('edit-profile-image-upload');
    const editProfileImageUrl = document.getElementById('edit-profile-image-url');
    const editProfileImagePreview = document.getElementById('edit-profile-image-preview');
    const editNoImagePreview = document.getElementById('edit-no-image-preview');
    
    // Fonction pour afficher l'aperçu de l'image en mode édition
    function displayEditImagePreview(src) {
        if (src) {
            editProfileImagePreview.src = src.startsWith('http') ? src : ".."+src;
            editProfileImagePreview.style.display = 'block';
            editNoImagePreview.style.display = 'none';
        } else {
            editProfileImagePreview.style.display = 'none';
            editNoImagePreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une image en mode édition
    editProfileImageUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayEditImagePreview(e.target.result);
                // Vider le champ URL si une image est uploadée
                editProfileImageUrl.value = '';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL d'image en mode édition
    editProfileImageUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayEditImagePreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            editProfileImageUpload.value = '';
        }
    });
    
    // Gestion du modal d'édition
    const editStaffModal = document.getElementById('editStaffModal');
    if (editStaffModal) {
        editStaffModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données du membre
            document.getElementById('edit-staff-id').value = button.getAttribute('data-id');
            document.getElementById('edit-staff-full-name').value = button.getAttribute('data-full-name');
            document.getElementById('edit-staff-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-staff-position').value = button.getAttribute('data-position');
            document.getElementById('edit-staff-department').value = button.getAttribute('data-department');
            document.getElementById('edit-staff-email').value = button.getAttribute('data-email');
            document.getElementById('edit-staff-phone').value = button.getAttribute('data-phone');
            document.getElementById('edit-staff-order').value = button.getAttribute('data-order-index');
            
            // Pour CKEditor, on doit mettre à jour le contenu des éditeurs
            const bio = button.getAttribute('data-bio');
            const expertise = button.getAttribute('data-expertise');
            
            if (editBioEditor) {
                editBioEditor.setData(bio || '');
            } else {
                setTimeout(() => {
                    if (editBioEditor) editBioEditor.setData(bio || '');
                }, 300);
            }
            
            if (editExpertiseEditor) {
                editExpertiseEditor.setData(expertise || '');
            } else {
                setTimeout(() => {
                    if (editExpertiseEditor) editExpertiseEditor.setData(expertise || '');
                }, 300);
            }
            
            // Mettre à jour l'aperçu de l'image
            const profileImage = button.getAttribute('data-profile-image');
            document.getElementById('edit-profile-image-url').value = profileImage || '';
            
            if (profileImage) {
                displayEditImagePreview(profileImage);
            } else {
                displayEditImagePreview(null);
            }
            
            // Mettre à jour les liens sociaux
            try {
                const socialLinks = JSON.parse(button.getAttribute('data-social-links'));
                document.getElementById('edit-social-facebook').value = socialLinks.facebook || '';
                document.getElementById('edit-social-twitter').value = socialLinks.twitter || '';
                document.getElementById('edit-social-linkedin').value = socialLinks.linkedin || '';
                document.getElementById('edit-social-instagram').value = socialLinks.instagram || '';
            } catch (error) {
                console.error('Erreur lors du parsing des liens sociaux:', error);
                // Vider les champs en cas d'erreur
                document.getElementById('edit-social-facebook').value = '';
                document.getElementById('edit-social-twitter').value = '';
                document.getElementById('edit-social-linkedin').value = '';
                document.getElementById('edit-social-instagram').value = '';
            }
            
            // Mettre à jour les checkboxes
            document.getElementById('edit-staff-featured').checked = button.getAttribute('data-is-featured') === '1';
            document.getElementById('edit-staff-active').checked = button.getAttribute('data-is-active') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deleteStaffModal = document.getElementById('deleteStaffModal');
    if (deleteStaffModal) {
        deleteStaffModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const staffId = button.getAttribute('data-id');
            const staffFullName = button.getAttribute('data-full-name');
            
            const idField = this.querySelector('#delete-staff-id');
            const nameSpan = this.querySelector('#delete-staff-name');
            
            if (idField) idField.value = staffId;
            if (nameSpan) nameSpan.textContent = staffFullName;
        });
    }

    // Assurer que le contenu de l'éditeur est soumis avec le formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('button[name="action"]').value;
            
            if (action === 'add_staff' && bioEditor && expertiseEditor) {
                const bioField = document.querySelector('#staff-bio');
                const expertiseField = document.querySelector('#staff-expertise');
                
                bioField.value = bioEditor.getData();
                expertiseField.value = expertiseEditor.getData();
            } else if (action === 'update_staff' && editBioEditor && editExpertiseEditor) {
                const bioField = document.querySelector('#edit-staff-bio');
                const expertiseField = document.querySelector('#edit-staff-expertise');
                
                bioField.value = editBioEditor.getData();
                expertiseField.value = editExpertiseEditor.getData();
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


