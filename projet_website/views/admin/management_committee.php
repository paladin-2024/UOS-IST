<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'management_committee';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les photos
$uploadsPath = './uploads/';
$photosPath = $uploadsPath . 'images/management/';

// Créer le répertoire d'upload s'il n'existe pas
if (!file_exists($photosPath)) {
    mkdir($photosPath, 0777, true);
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
    
    // Vérifier la taille du fichier (max 2Mo)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("Le fichier est trop volumineux. Taille maximale: 2 Mo");
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
        // Action d'ajout d'un membre
        if ($_POST['action'] === 'add_member' && isset($_POST['full_name'], $_POST['position'])) {
            // Gérer l'upload de la photo
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $photo = uploadFile($_FILES['photo'], $photosPath);
                // Rendre le chemin relatif
                $photo = str_replace('./', '/', $photo);
            }
            
            // Générer un slug à partir du nom
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['full_name'])));
            
            // Insérer le membre dans la base de données
            $stmt = $db->prepare("INSERT INTO staff (full_name, slug, position, department, email, phone, 
                                bio, expertise, profile_image, is_featured, order_index, is_active) 
                                VALUES (:full_name, :slug, :position, 'Management Committee', :email, :phone, 
                                :bio, :expertise, :profile_image, :is_featured, :order_index, :is_active)");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $bio = !empty($_POST['bio']) ? $_POST['bio'] : null;
            $expertise = !empty($_POST['academic_title']) ? $_POST['academic_title'] : null;
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':position', $_POST['position']);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':expertise', $expertise);
            $stmt->bindParam(':profile_image', $photo);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du comité a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un membre
        else if ($_POST['action'] === 'update_member' && isset($_POST['member_id'], $_POST['full_name'], $_POST['position'])) {
            // Récupérer la photo actuelle
            $currentPhotoStmt = $db->prepare("SELECT profile_image FROM staff WHERE id = :id");
            $currentPhotoStmt->bindParam(':id', $_POST['member_id']);
            $currentPhotoStmt->execute();
            $currentPhoto = $currentPhotoStmt->fetchColumn();
            
            // Gérer l'upload de la nouvelle photo
            $photo = $currentPhoto;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $photo = uploadFile($_FILES['photo'], $photosPath);
                $photo = str_replace('./', '/', $photo);
                
                // Supprimer l'ancienne photo s'il existe
                if ($currentPhoto && file_exists('.' . $currentPhoto)) {
                    unlink('.' . $currentPhoto);
                }
            }
            
            // Générer un slug à partir du nom
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['full_name'])));
            
            // Mettre à jour le membre
            $stmt = $db->prepare("UPDATE staff SET full_name = :full_name, slug = :slug, position = :position, 
                                email = :email, phone = :phone, bio = :bio, expertise = :expertise, 
                                profile_image = :profile_image, is_featured = :is_featured, 
                                order_index = :order_index, is_active = :is_active 
                                WHERE id = :id");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $bio = !empty($_POST['bio']) ? $_POST['bio'] : null;
            $expertise = !empty($_POST['academic_title']) ? $_POST['academic_title'] : null;
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':id', $_POST['member_id']);
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':position', $_POST['position']);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':expertise', $expertise);
            $stmt->bindParam(':profile_image', $photo);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du comité a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un membre
        else if ($_POST['action'] === 'delete_member' && isset($_POST['member_id'])) {
            // Récupérer la photo à supprimer
            $photoStmt = $db->prepare("SELECT profile_image FROM staff WHERE id = :id");
            $photoStmt->bindParam(':id', $_POST['member_id']);
            $photoStmt->execute();
            $photo = $photoStmt->fetchColumn();
            
            // Supprimer la photo s'il existe
            if ($photo && file_exists('.' . $photo)) {
                unlink('.' . $photo);
            }
            
            // Supprimer le membre
            $stmt = $db->prepare("DELETE FROM staff WHERE id = :id");
            $stmt->bindParam(':id', $_POST['member_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le membre du comité a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: management_committee');
    exit;
}

// Récupérer tous les membres du comité de gestion
$membersStmt = $db->query("SELECT * FROM staff WHERE department = 'Management Committee' ORDER BY order_index ASC, full_name ASC");
$membersList = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion du comité -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-users-cog me-2"></i>Gestion du comité de direction</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
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
                    Gérez les membres du comité de direction de l'institution. Ces membres seront affichés sur la page "À propos" ou "Gouvernance" du site.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des membres -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des membres du comité</h5>
    </div>
    <div class="card-body">
        <?php if (empty($membersList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun membre n'a été ajouté au comité de direction.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom complet</th>
                            <th>Poste</th>
                            <th>Titre académique</th>
                            <th>Email</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membersList as $member): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($member['profile_image'])): ?>
                                        <img src="..<?php echo htmlspecialchars($member['profile_image']); ?>" alt="<?php echo htmlspecialchars($member['full_name']); ?>" class="img-thumbnail" style="max-width: 70px; max-height: 70px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-user fa-2x"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($member['position']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($member['expertise'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php echo !empty($member['email']) ? htmlspecialchars($member['email']) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $member['order_index']; ?>
                                </td>
                                <td>
                                <?php if ($member['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary edit-member-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editMemberModal"
                                                data-id="<?php echo $member['id']; ?>"
                                                data-full-name="<?php echo htmlspecialchars($member['full_name']); ?>"
                                                data-position="<?php echo htmlspecialchars($member['position']); ?>"
                                                data-academic-title="<?php echo htmlspecialchars($member['expertise'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($member['email'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>"
                                                data-bio="<?php echo htmlspecialchars($member['bio'] ?? ''); ?>"
                                                data-photo="<?php echo htmlspecialchars($member['profile_image'] ?? ''); ?>"
                                                data-order-index="<?php echo $member['order_index']; ?>"
                                                data-is-featured="<?php echo $member['is_featured']; ?>"
                                                data-is-active="<?php echo $member['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-member-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteMemberModal"
                                                data-id="<?php echo $member['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($member['full_name']); ?>">
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

<!-- Modal Ajouter un membre -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMemberModalLabel">Ajouter un membre au comité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="member-name" class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="member-name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="member-position" class="form-label">Poste dans le comité</label>
                                <input type="text" class="form-control" id="member-position" name="position" required>
                                <div class="form-text">
                                    Par exemple: Directeur Général, Secrétaire Académique, etc.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="member-academic-title" class="form-label">Titre académique</label>
                                <input type="text" class="form-control" id="member-academic-title" name="academic_title" placeholder="Dr., Prof., MSc., etc.">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="member-email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="member-email" name="email" placeholder="nom@domaine.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="member-phone" class="form-label">Téléphone</label>
                                        <input type="text" class="form-control" id="member-phone" name="phone" placeholder="+243 ...">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="member-bio" class="form-label">Biographie</label>
                                <textarea class="form-control" id="member-bio" name="bio" rows="6"></textarea>
                                <div class="form-text">
                                    Décrivez l'expérience, les compétences et le parcours du membre.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">Photo</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="member-photo-preview" src="" alt="Aperçu de la photo" class="img-fluid img-thumbnail" style="max-height: 200px; display: none;">
                                            <div id="no-photo-preview" class="text-muted">
                                                <i class="fas fa-user fa-5x mb-2"></i>
                                                <p>Aucune photo sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="member-photo-upload" name="photo" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="member-order" class="form-label">Ordre d'affichage</label>
                                        <input type="number" class="form-control" id="member-order" name="order_index" value="0" min="0">
                                        <div class="form-text">
                                            Les membres avec un ordre plus bas s'afficheront en premier.
                                        </div>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="member-featured" name="is_featured">
                                        <label class="form-check-label" for="member-featured">
                                            Mettre en avant
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="member-active" name="is_active" checked>
                                        <label class="form-check-label" for="member-active">
                                            Activer le membre
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_member">Ajouter le membre</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un membre -->
<div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMemberModalLabel">Modifier le membre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-member-id" name="member_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-member-name" class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="edit-member-name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-member-position" class="form-label">Poste dans le comité</label>
                                <input type="text" class="form-control" id="edit-member-position" name="position" required>
                                <div class="form-text">
                                    Par exemple: Directeur Général, Secrétaire Académique, etc.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-member-academic-title" class="form-label">Titre académique</label>
                                <input type="text" class="form-control" id="edit-member-academic-title" name="academic_title" placeholder="Dr., Prof., MSc., etc.">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit-member-email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="edit-member-email" name="email" placeholder="nom@domaine.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit-member-phone" class="form-label">Téléphone</label>
                                        <input type="text" class="form-control" id="edit-member-phone" name="phone" placeholder="+243 ...">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-member-bio" class="form-label">Biographie</label>
                                <textarea class="form-control" id="edit-member-bio" name="bio" rows="6"></textarea>
                                <div class="form-text">
                                    Décrivez l'expérience, les compétences et le parcours du membre.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">Photo</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-member-photo-preview" src="" alt="Aperçu de la photo" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            <div id="edit-no-photo-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-user fa-5x mb-2"></i>
                                                <p>Aucune photo sélectionnée</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="edit-member-photo-upload" name="photo" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="edit-member-order" class="form-label">Ordre d'affichage</label>
                                        <input type="number" class="form-control" id="edit-member-order" name="order_index" value="0" min="0">
                                        <div class="form-text">
                                            Les membres avec un ordre plus bas s'afficheront en premier.
                                        </div>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-member-featured" name="is_featured">
                                        <label class="form-check-label" for="edit-member-featured">
                                            Mettre en avant
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-member-active" name="is_active">
                                        <label class="form-check-label" for="edit-member-active">
                                            Activer le membre
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_member">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un membre -->
<div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMemberModalLabel">Supprimer un membre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-member-id" name="member_id">
                    <p>Êtes-vous sûr de vouloir supprimer <strong id="delete-member-name"></strong> du comité de direction?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce membre.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_member">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la prévisualisation de la photo pour l'ajout
    const memberPhotoUpload = document.getElementById('member-photo-upload');
    const memberPhotoPreview = document.getElementById('member-photo-preview');
    const noPhotoPreview = document.getElementById('no-photo-preview');
    
    // Fonction pour afficher l'aperçu de la photo
    function displayPhotoPreview(src) {
        if (src) {
            memberPhotoPreview.src = src;
            memberPhotoPreview.style.display = 'block';
            noPhotoPreview.style.display = 'none';
        } else {
            memberPhotoPreview.style.display = 'none';
            noPhotoPreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une photo
    if (memberPhotoUpload) {
        memberPhotoUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    displayPhotoPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                displayPhotoPreview(null);
            }
        });
    }
    
    // Gestion de la prévisualisation de la photo pour l'édition
    const editMemberPhotoUpload = document.getElementById('edit-member-photo-upload');
    const editMemberPhotoPreview = document.getElementById('edit-member-photo-preview');
    const editNoPhotoPreview = document.getElementById('edit-no-photo-preview');
    
    // Fonction pour afficher l'aperçu de la photo en mode édition
    function displayEditPhotoPreview(src) {
        if (src) {
            editMemberPhotoPreview.src = src.startsWith('http') ? src : ".."+src;
            editMemberPhotoPreview.style.display = 'block';
            editNoPhotoPreview.style.display = 'none';
        } else {
            editMemberPhotoPreview.style.display = 'none';
            editNoPhotoPreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'une photo en mode édition
    if (editMemberPhotoUpload) {
        editMemberPhotoUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    displayEditPhotoPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Gestion du modal d'édition
    const editMemberModal = document.getElementById('editMemberModal');
    if (editMemberModal) {
        editMemberModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données du membre
            document.getElementById('edit-member-id').value = button.getAttribute('data-id');
            document.getElementById('edit-member-name').value = button.getAttribute('data-full-name');
            document.getElementById('edit-member-position').value = button.getAttribute('data-position');
            document.getElementById('edit-member-academic-title').value = button.getAttribute('data-academic-title');
            document.getElementById('edit-member-email').value = button.getAttribute('data-email');
            document.getElementById('edit-member-phone').value = button.getAttribute('data-phone');
            document.getElementById('edit-member-bio').value = button.getAttribute('data-bio');
            document.getElementById('edit-member-order').value = button.getAttribute('data-order-index');
            
            // Mettre à jour l'aperçu de la photo
            const photo = button.getAttribute('data-photo');
            if (photo) {
                displayEditPhotoPreview(photo);
            } else {
                displayEditPhotoPreview(null);
            }
            
            // Mettre à jour les checkboxes
            document.getElementById('edit-member-featured').checked = button.getAttribute('data-is-featured') === '1';
            document.getElementById('edit-member-active').checked = button.getAttribute('data-is-active') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deleteMemberModal = document.getElementById('deleteMemberModal');
    if (deleteMemberModal) {
        deleteMemberModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const memberId = button.getAttribute('data-id');
            const memberName = button.getAttribute('data-name');
            
            const idField = this.querySelector('#delete-member-id');
            const nameSpan = this.querySelector('#delete-member-name');
            
            if (idField) idField.value = memberId;
            if (nameSpan) nameSpan.textContent = memberName;
        });
    }
    
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
