<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'partners';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Définir le chemin pour stocker les logos
$uploadsPath = './uploads/';
$logosPath = $uploadsPath . 'images/partners/';

// Créer le répertoire d'upload s'il n'existe pas
if (!file_exists($logosPath)) {
    mkdir($logosPath, 0777, true);
}

// Fonction pour uploader un fichier
function uploadFile($file, $targetPath, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']) {
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
        // Action d'ajout d'un partenaire
        if ($_POST['action'] === 'add_partner' && isset($_POST['name'], $_POST['slug'])) {
            // Gérer l'upload du logo
            $logo = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $logo = uploadFile($_FILES['logo'], $logosPath);
                // Rendre le chemin relatif
                $logo = str_replace('./', '/', $logo);
            } else if (!empty($_POST['logo_url'])) {
                $logo = $_POST['logo_url'];
            }
            
            // Insérer le partenaire dans la base de données
            $stmt = $db->prepare("INSERT INTO partners (name, slug, description, logo, website_url, 
                                partnership_type, is_featured, order_index, is_active) 
                                VALUES (:name, :slug, :description, :logo, :website_url, 
                                :partnership_type, :is_featured, :order_index, :is_active)");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $websiteUrl = !empty($_POST['website_url']) ? $_POST['website_url'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':logo', $logo);
            $stmt->bindParam(':website_url', $websiteUrl);
            $stmt->bindParam(':partnership_type', $_POST['partnership_type']);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le partenaire a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un partenaire
        else if ($_POST['action'] === 'update_partner' && isset($_POST['partner_id'], $_POST['name'], $_POST['slug'])) {
            // Récupérer le logo actuel
            $currentLogoStmt = $db->prepare("SELECT logo FROM partners WHERE id = :id");
            $currentLogoStmt->bindParam(':id', $_POST['partner_id']);
            $currentLogoStmt->execute();
            $currentLogo = $currentLogoStmt->fetchColumn();
            
            // Gérer l'upload du nouveau logo
            $logo = $currentLogo;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $logo = uploadFile($_FILES['logo'], $logosPath);
                $logo = str_replace('./', '/', $logo);
                
                // Supprimer l'ancien logo s'il existe
                if ($currentLogo && file_exists('.' . $currentLogo) && !strpos($currentLogo, 'http')) {
                    unlink('.' . $currentLogo);
                }
            } else if (!empty($_POST['logo_url'])) {
                $logo = $_POST['logo_url'];
            }
            
            // Mettre à jour le partenaire
            $stmt = $db->prepare("UPDATE partners SET name = :name, slug = :slug, description = :description, 
                                logo = :logo, website_url = :website_url, partnership_type = :partnership_type, 
                                is_featured = :is_featured, order_index = :order_index, is_active = :is_active 
                                WHERE id = :id");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $websiteUrl = !empty($_POST['website_url']) ? $_POST['website_url'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':id', $_POST['partner_id']);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':slug', $_POST['slug']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':logo', $logo);
            $stmt->bindParam(':website_url', $websiteUrl);
            $stmt->bindParam(':partnership_type', $_POST['partnership_type']);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le partenaire a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un partenaire
        else if ($_POST['action'] === 'delete_partner' && isset($_POST['partner_id'])) {
            // Récupérer le logo à supprimer
            $logoStmt = $db->prepare("SELECT logo FROM partners WHERE id = :id");
            $logoStmt->bindParam(':id', $_POST['partner_id']);
            $logoStmt->execute();
            $logo = $logoStmt->fetchColumn();
            
            // Supprimer le logo s'il existe et n'est pas une URL externe
            if ($logo && file_exists('.' . $logo) && strpos($logo, 'http') !== 0) {
                unlink('.' . $logo);
            }
            
            // Supprimer le partenaire
            $stmt = $db->prepare("DELETE FROM partners WHERE id = :id");
            $stmt->bindParam(':id', $_POST['partner_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le partenaire a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: partners');
    exit;
}

// Récupérer tous les partenaires
$partnersStmt = $db->query("SELECT * FROM partners ORDER BY order_index ASC, name ASC");
$partnersList = $partnersStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des partenaires -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-handshake me-2"></i>Gestion des partenaires</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
        <i class="fas fa-plus me-2"></i>Ajouter un partenaire
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
                    Gérez les partenaires de votre institution tels que les universités partenaires, hôpitaux, centres de recherche et sponsors.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des partenaires -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des partenaires</h5>
    </div>
    <div class="card-body">
        <?php if (empty($partnersList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun partenaire n'a été ajouté.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Site web</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>À la une</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partnersList as $partner): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($partner['logo'])): ?>
                                        <img src="..<?php echo htmlspecialchars($partner['logo']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="img-thumbnail" style="max-width: 70px; max-height: 50px;">
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-building fa-2x"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($partner['name']); ?>
                                </td>
                                <td>
                                    <?php 
                                        $typeLabels = [
                                            'academique' => '<span class="badge bg-primary">Académique</span>',
                                            'hospitalier' => '<span class="badge bg-info">Hospitalier</span>',
                                            'recherche' => '<span class="badge bg-warning text-dark">Recherche</span>',
                                            'financier' => '<span class="badge bg-success">Financier</span>',
                                            'autre' => '<span class="badge bg-secondary">Autre</span>'
                                        ];
                                        echo $typeLabels[$partner['partnership_type']] ?? '<span class="badge bg-secondary">Non défini</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($partner['website_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($partner['website_url']); ?>" target="_blank" class="text-truncate">
                                            <i class="fas fa-external-link-alt me-1"></i>Site web
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $partner['order_index']; ?>
                                </td>
                                <td>
                                    <?php if ($partner['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($partner['is_featured']): ?>
                                        <span class="badge bg-warning text-dark">À la une</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Standard</span>
                                        <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary edit-partner-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPartnerModal"
                                                data-id="<?php echo $partner['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($partner['name']); ?>"
                                                data-slug="<?php echo htmlspecialchars($partner['slug']); ?>"
                                                data-description="<?php echo htmlspecialchars($partner['description'] ?? ''); ?>"
                                                data-logo="<?php echo htmlspecialchars($partner['logo'] ?? ''); ?>"
                                                data-website-url="<?php echo htmlspecialchars($partner['website_url'] ?? ''); ?>"
                                                data-partnership-type="<?php echo htmlspecialchars($partner['partnership_type']); ?>"
                                                data-order-index="<?php echo $partner['order_index']; ?>"
                                                data-is-featured="<?php echo $partner['is_featured']; ?>"
                                                data-is-active="<?php echo $partner['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-partner-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deletePartnerModal"
                                                data-id="<?php echo $partner['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($partner['name']); ?>">
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

<!-- Modal Ajouter un partenaire -->
<div class="modal fade" id="addPartnerModal" tabindex="-1" aria-labelledby="addPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPartnerModalLabel">Ajouter un nouveau partenaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="partner-name" class="form-label">Nom du partenaire</label>
                                <input type="text" class="form-control" id="partner-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="partner-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="partner-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL (ex: "universite-partenaire").
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="partner-description" class="form-label">Description</label>
                                <textarea class="form-control" id="partner-description" name="description" rows="4"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="partner-website" class="form-label">Site web</label>
                                <input type="url" class="form-control" id="partner-website" name="website_url" placeholder="https://...">
                            </div>
                            <div class="mb-3">
                                <label for="partner-type" class="form-label">Type de partenariat</label>
                                <select class="form-select" id="partner-type" name="partnership_type" required>
                                    <option value="academique">Académique</option>
                                    <option value="hospitalier">Hospitalier</option>
                                    <option value="recherche">Recherche</option>
                                    <option value="financier">Financier</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="partner-order" class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="partner-order" name="order_index" value="0" min="0">
                                <div class="form-text">
                                    Les partenaires avec un ordre plus bas s'afficheront en premier.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">Logo du partenaire</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="partner-logo-preview" src="/uploads/placeholder.jpg" alt="Aperçu du logo" class="img-fluid img-thumbnail" style="max-height: 150px; display: none;">
                                            <div id="no-logo-preview" class="text-muted">
                                                <i class="fas fa-building fa-5x mb-2"></i>
                                                <p>Aucun logo sélectionné</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="partner-logo-upload" name="logo" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP, SVG. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="partner-logo-url" class="form-label">Ou utilisez une URL de logo</label>
                                            <input type="url" class="form-control" id="partner-logo-url" name="logo_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="partner-featured" name="is_featured">
                                        <label class="form-check-label" for="partner-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="partner-active" name="is_active" checked>
                                        <label class="form-check-label" for="partner-active">
                                            Activer le partenaire
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_partner">Ajouter le partenaire</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un partenaire -->
<div class="modal fade" id="editPartnerModal" tabindex="-1" aria-labelledby="editPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPartnerModalLabel">Modifier le partenaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit-partner-id" name="partner_id">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit-partner-name" class="form-label">Nom du partenaire</label>
                                <input type="text" class="form-control" id="edit-partner-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-partner-slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="edit-partner-slug" name="slug" required>
                                <div class="form-text">
                                    L'identifiant unique dans l'URL.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-partner-description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit-partner-description" name="description" rows="4"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit-partner-website" class="form-label">Site web</label>
                                <input type="url" class="form-control" id="edit-partner-website" name="website_url" placeholder="https://...">
                            </div>
                            <div class="mb-3">
                                <label for="edit-partner-type" class="form-label">Type de partenariat</label>
                                <select class="form-select" id="edit-partner-type" name="partnership_type" required>
                                    <option value="academique">Académique</option>
                                    <option value="hospitalier">Hospitalier</option>
                                    <option value="recherche">Recherche</option>
                                    <option value="financier">Financier</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit-partner-order" class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="edit-partner-order" name="order_index" value="0" min="0">
                                <div class="form-text">
                                    Les partenaires avec un ordre plus bas s'afficheront en premier.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">Logo du partenaire</label>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="image-preview mb-3 text-center">
                                            <img id="edit-partner-logo-preview" src="" alt="Aperçu du logo" class="img-fluid img-thumbnail" style="max-height: 150px;">
                                            <div id="edit-no-logo-preview" class="text-muted" style="display: none;">
                                                <i class="fas fa-building fa-5x mb-2"></i>
                                                <p>Aucun logo sélectionné</p>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" id="edit-partner-logo-upload" name="logo" accept="image/*">
                                                <div class="form-text">
                                                    Formats acceptés: JPG, PNG, GIF, WEBP, SVG. Taille max: 2 Mo.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit-partner-logo-url" class="form-label">Ou utilisez une URL de logo</label>
                                            <input type="url" class="form-control" id="edit-partner-logo-url" name="logo_url" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">Paramètres</div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-partner-featured" name="is_featured">
                                        <label class="form-check-label" for="edit-partner-featured">
                                            Mettre à la une
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="edit-partner-active" name="is_active">
                                        <label class="form-check-label" for="edit-partner-active">
                                            Activer le partenaire
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_partner">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un partenaire -->
<div class="modal fade" id="deletePartnerModal" tabindex="-1" aria-labelledby="deletePartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
                <h5 class="modal-title" id="deletePartnerModalLabel">Supprimer un partenaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-partner-id" name="partner_id">
                    <p>Êtes-vous sûr de vouloir supprimer le partenaire <strong id="delete-partner-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce partenaire.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_partner">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Générer le slug à partir du nom pour le nouveau formulaire
    document.getElementById('partner-name').addEventListener('keyup', function() {
        const name = this.value;
        const slug = name.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '-')     // Remplacer les espaces par des tirets
            .replace(/--+/g, '-');    // Éviter les tirets multiples
        
        document.getElementById('partner-slug').value = slug;
    });
    
    // Gestion de la prévisualisation du logo pour l'ajout
    const partnerLogoUpload = document.getElementById('partner-logo-upload');
    const partnerLogoUrl = document.getElementById('partner-logo-url');
    const partnerLogoPreview = document.getElementById('partner-logo-preview');
    const noLogoPreview = document.getElementById('no-logo-preview');
    
    // Fonction pour afficher l'aperçu du logo
    function displayLogoPreview(src) {
        if (src) {
            partnerLogoPreview.src = src;
            partnerLogoPreview.style.display = 'block';
            noLogoPreview.style.display = 'none';
        } else {
            partnerLogoPreview.style.display = 'none';
            noLogoPreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'un logo
    partnerLogoUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayLogoPreview(e.target.result);
                // Vider le champ URL si un fichier est uploadé
                partnerLogoUrl.value = '';
            };
            reader.readAsDataURL(file);
        } else {
            displayLogoPreview(null);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL de logo
    partnerLogoUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayLogoPreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            partnerLogoUpload.value = '';
        } else {
            displayLogoPreview(null);
        }
    });
    
    // Gestion de la prévisualisation du logo pour l'édition
    const editPartnerLogoUpload = document.getElementById('edit-partner-logo-upload');
    const editPartnerLogoUrl = document.getElementById('edit-partner-logo-url');
    const editPartnerLogoPreview = document.getElementById('edit-partner-logo-preview');
    const editNoLogoPreview = document.getElementById('edit-no-logo-preview');
    
    // Fonction pour afficher l'aperçu du logo en mode édition
    function displayEditLogoPreview(src) {
        if (src) {
            editPartnerLogoPreview.src = src.startsWith('http') ? src : ".."+src;
            editPartnerLogoPreview.style.display = 'block';
            editNoLogoPreview.style.display = 'none';
        } else {
            editPartnerLogoPreview.style.display = 'none';
            editNoLogoPreview.style.display = 'block';
        }
    }
    
    // Prévisualisation lors de l'upload d'un logo en mode édition
    editPartnerLogoUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                displayEditLogoPreview(e.target.result);
                // Vider le champ URL si un fichier est uploadé
                editPartnerLogoUrl.value = '';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Prévisualisation lors de la saisie d'une URL de logo en mode édition
    editPartnerLogoUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            displayEditLogoPreview(url);
            // Vider le champ d'upload si une URL est spécifiée
            editPartnerLogoUpload.value = '';
        }
    });
    
    // Gestion du modal d'édition
    const editPartnerModal = document.getElementById('editPartnerModal');
    if (editPartnerModal) {
        editPartnerModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données du partenaire
            document.getElementById('edit-partner-id').value = button.getAttribute('data-id');
            document.getElementById('edit-partner-name').value = button.getAttribute('data-name');
            document.getElementById('edit-partner-slug').value = button.getAttribute('data-slug');
            document.getElementById('edit-partner-description').value = button.getAttribute('data-description');
            document.getElementById('edit-partner-website').value = button.getAttribute('data-website-url');
            document.getElementById('edit-partner-type').value = button.getAttribute('data-partnership-type');
            document.getElementById('edit-partner-order').value = button.getAttribute('data-order-index');
            
            // Mettre à jour l'aperçu du logo
            const logo = button.getAttribute('data-logo');
            document.getElementById('edit-partner-logo-url').value = logo || '';
            
            if (logo) {
                displayEditLogoPreview(logo);
            } else {
                displayEditLogoPreview(null);
            }
            
            // Mettre à jour les checkboxes
            document.getElementById('edit-partner-featured').checked = button.getAttribute('data-is-featured') === '1';
            document.getElementById('edit-partner-active').checked = button.getAttribute('data-is-active') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deletePartnerModal = document.getElementById('deletePartnerModal');
    if (deletePartnerModal) {
        deletePartnerModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const partnerId = button.getAttribute('data-id');
            const partnerName = button.getAttribute('data-name');
            
            const idField = this.querySelector('#delete-partner-id');
            const nameSpan = this.querySelector('#delete-partner-name');
            
            if (idField) idField.value = partnerId;
            if (nameSpan) nameSpan.textContent = partnerName;
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

