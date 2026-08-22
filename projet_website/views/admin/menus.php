<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'menus';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Traitement des actions
if (isset($_POST['action'])) {
    try {
        // Action d'ajout de menu
if ($_POST['action'] === 'add_menu' && isset($_POST['name'], $_POST['position'])) {
    $stmt = $db->prepare("INSERT INTO menus (name, position, parent_id, url, icon, order_index) 
                        VALUES (:name, :position, :parent_id, :url, :icon, :order_index)");
    
    $parentId = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $icon = !empty($_POST['icon']) ? $_POST['icon'] : null;
    
    // Gérer l'URL en fonction du type de lien
    $url = '';
    if (isset($_POST['link_type']) && $_POST['link_type'] === 'page' && !empty($_POST['page_id'])) {
        // Récupérer le slug de la page
        $pageStmt = $db->prepare("SELECT slug FROM pages WHERE id = :id");
        $pageStmt->bindParam(':id', $_POST['page_id']);
        $pageStmt->execute();
        $pageSlug = $pageStmt->fetchColumn();
        
        if ($pageSlug) {
            $url = "details_page&slug=" . $pageSlug;
        }
    } else {
        $url = $_POST['url'];
    }
    
    // Déterminer l'ordre maximum actuel pour cette position et parent
    $orderStmt = $db->prepare("SELECT MAX(order_index) as max_order FROM menus WHERE position = :position AND (parent_id = :parent_id OR (parent_id IS NULL AND :parent_id IS NULL))");
    $orderStmt->bindParam(':position', $_POST['position']);
    $orderStmt->bindParam(':parent_id', $parentId);
    $orderStmt->execute();
    $maxOrder = $orderStmt->fetch(PDO::FETCH_ASSOC)['max_order'];
    $newOrder = $maxOrder ? $maxOrder + 1 : 1;
    
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':position', $_POST['position']);
    $stmt->bindParam(':parent_id', $parentId);
    $stmt->bindParam(':url', $url);
    $stmt->bindParam(':icon', $icon);
    $stmt->bindParam(':order_index', $newOrder);
    $stmt->execute();
    
    $_SESSION['success_message'] = "L'élément de menu a été ajouté avec succès.";
}

        
        // Action de mise à jour de menu
        // Action de mise à jour de menu
else if ($_POST['action'] === 'update_menu' && isset($_POST['menu_id'], $_POST['name'], $_POST['position'])) {
    $stmt = $db->prepare("UPDATE menus SET name = :name, position = :position, parent_id = :parent_id, 
                        url = :url, icon = :icon, is_active = :is_active WHERE id = :id");
    
    $parentId = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $icon = !empty($_POST['icon']) ? $_POST['icon'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Gérer l'URL en fonction du type de lien
    $url = '';
    if (isset($_POST['link_type']) && $_POST['link_type'] === 'page' && !empty($_POST['page_id'])) {
        // Récupérer le slug de la page
        $pageStmt = $db->prepare("SELECT slug FROM pages WHERE id = :id");
        $pageStmt->bindParam(':id', $_POST['page_id']);
        $pageStmt->execute();
        $pageSlug = $pageStmt->fetchColumn();
        
        if ($pageSlug) {
            $url = "details_page&slug=" . $pageSlug;
        }
    } else {
        $url = $_POST['url'];
    }
    
    $stmt->bindParam(':id', $_POST['menu_id']);
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':position', $_POST['position']);
    $stmt->bindParam(':parent_id', $parentId);
    $stmt->bindParam(':url', $url);
    $stmt->bindParam(':icon', $icon);
    $stmt->bindParam(':is_active', $isActive);
    $stmt->execute();
    
    $_SESSION['success_message'] = "L'élément de menu a été mis à jour avec succès.";
}

        
        // Action de suppression de menu
        else if ($_POST['action'] === 'delete_menu' && isset($_POST['menu_id'])) {
            // D'abord, mettre à jour les éléments enfants pour qu'ils n'aient plus de parent
            $updateStmt = $db->prepare("UPDATE menus SET parent_id = NULL WHERE parent_id = :id");
            $updateStmt->bindParam(':id', $_POST['menu_id']);
            $updateStmt->execute();
            
            // Ensuite, supprimer l'élément lui-même
            $stmt = $db->prepare("DELETE FROM menus WHERE id = :id");
            $stmt->bindParam(':id', $_POST['menu_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'élément de menu a été supprimé avec succès.";
        }
        
        // Action d'ajout d'une position de menu
        else if ($_POST['action'] === 'add_position' && isset($_POST['new-position'])) {
            // Aucune table ne stocke les positions, donc rien à insérer en base de données
            $_SESSION['success_message'] = "La nouvelle position a été ajoutée avec succès.";
        }
        
        // Action de réorganisation des menus
        else if ($_POST['action'] === 'reorder_menu' && isset($_POST['menu_order'])) {
            $order = json_decode($_POST['menu_order'], true);
            
            $db->beginTransaction();
            
            foreach ($order as $position => $items) {
                $index = 1;
                foreach ($items as $id) {
                    $stmt = $db->prepare("UPDATE menus SET order_index = :order WHERE id = :id");
                    $stmt->bindParam(':order', $index);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $index++;
                }
            }
            
            $db->commit();
            $_SESSION['success_message'] = "L'ordre des menus a été mis à jour avec succès.";
            
            // Pour les requêtes AJAX, retourner un statut JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => "L'ordre des menus a été mis à jour avec succès."]);
                exit;
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
        
        // Pour les requêtes AJAX, retourner un statut JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    // Redirection pour éviter la resoumission du formulaire (sauf pour AJAX)
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header('Location: menus');
        exit;
    }
}

// Récupérer les positions de menu distinctes
$positionStmt = $db->query("SELECT DISTINCT position FROM menus ORDER BY position");
$positions = $positionStmt->fetchAll(PDO::FETCH_COLUMN);

// Si aucune position n'existe encore, définir des positions par défaut
if (empty($positions)) {
    $positions = ['main', 'footer', 'sidebar'];
}

// Récupérer tous les menus regroupés par position
$menusByPosition = [];
foreach ($positions as $position) {
    // Récupérer d'abord les éléments de niveau supérieur
    $stmt = $db->prepare("SELECT * FROM menus WHERE position = :position AND parent_id IS NULL ORDER BY order_index");
    $stmt->bindParam(':position', $position);
    $stmt->execute();
    $menusByPosition[$position] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque élément parent, récupérer ses enfants
    foreach ($menusByPosition[$position] as &$parentMenu) {
        $childStmt = $db->prepare("SELECT * FROM menus WHERE parent_id = :parent_id ORDER BY order_index");
        $childStmt->bindParam(':parent_id', $parentMenu['id']);
        $childStmt->execute();
        $parentMenu['children'] = $childStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer tous les menus pour les listes déroulantes
$allMenusStmt = $db->query("SELECT id, name, position FROM menus ORDER BY position, order_index");
$allMenus = $allMenusStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les pages pour les options du menu
$pagesStmt = $db->query("SELECT id, title, slug FROM pages WHERE is_published = 1 ORDER BY title");
$pages = $pagesStmt->fetchAll(PDO::FETCH_ASSOC);


// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des menus -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-bars me-2"></i>Gestion des menus</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
        <i class="fas fa-plus me-2"></i>Ajouter un élément
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
                    Gérez les différents menus de votre site. Vous pouvez créer, modifier, supprimer et réorganiser les éléments de menu.
                    Chaque menu peut être placé à différentes positions sur le site (menu principal, pied de page, barre latérale, etc.).
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Navigation par onglets pour les différentes positions de menu -->
<ul class="nav nav-tabs mb-4" id="menu-positions-tab" role="tablist">
    <?php $firstTab = true; ?>
    <?php foreach ($positions as $position): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>" 
                    id="<?php echo $position; ?>-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#<?php echo $position; ?>-content" 
                    type="button" 
                    role="tab" 
                    aria-controls="<?php echo $position; ?>-content" 
                    aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                <?php echo ucfirst($position); ?>
            </button>
        </li>
        <?php $firstTab = false; ?>
    <?php endforeach; ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" 
                id="add-position-tab" 
                data-bs-toggle="modal" 
                data-bs-target="#addPositionModal" 
                type="button">
            <i class="fas fa-plus"></i> Nouvelle position
        </button>
    </li>
</ul>

<!-- Contenus des onglets -->
<div class="tab-content" id="menu-positions-content">
    <?php $firstTab = true; ?>
    <?php foreach ($positions as $position): ?>
        <div class="tab-pane fade <?php echo $firstTab ? 'show active' : ''; ?>" 
             id="<?php echo $position; ?>-content" 
             role="tabpanel" 
             aria-labelledby="<?php echo $position; ?>-tab">
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Menu "<?php echo ucfirst($position); ?>"</h5>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm save-menu-order" data-position="<?php echo $position; ?>">
                            <i class="fas fa-save me-1"></i>Enregistrer l'ordre
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($menusByPosition[$position])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Aucun élément de menu n'a été créé pour cette position.
                        </div>
                    <?php else: ?>
                        <div class="menu-items-container" id="menu-items-<?php echo $position; ?>">
                            <ul class="list-group menu-sortable" data-position="<?php echo $position; ?>">
                                <?php foreach ($menusByPosition[$position] as $menu): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center menu-item" data-id="<?php echo $menu['id']; ?>">
                                        <div class="d-flex align-items-center">
                                            <span class="menu-handle me-2"><i class="fas fa-grip-vertical"></i></span>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php if (!empty($menu['icon'])): ?>
                                                        <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-1"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($menu['name']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($menu['url']); ?>
                                                    <?php if (!$menu['is_active']): ?><span class="badge bg-warning text-dark ms-2">Inactif</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary edit-menu-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editMenuModal"
                                                    data-id="<?php echo $menu['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($menu['name']); ?>"
                                                    data-position="<?php echo htmlspecialchars($menu['position']); ?>"
                                                    data-parent="<?php echo htmlspecialchars($menu['parent_id'] ?? ''); ?>"
                                                    data-url="<?php echo htmlspecialchars($menu['url']); ?>"
                                                    data-icon="<?php echo htmlspecialchars($menu['icon'] ?? ''); ?>"
                                                    data-active="<?php echo $menu['is_active']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-menu-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteMenuModal"
                                                    data-id="<?php echo $menu['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($menu['name']); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </li>
                                    
                                    <?php if (!empty($menu['children'])): ?>
                                        <li class="list-group-item ps-5 submenu-container">
                                            <ul class="list-group menu-sortable" data-parent="<?php echo $menu['id']; ?>">
                                                <?php foreach ($menu['children'] as $child): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center menu-item" data-id="<?php echo $child['id']; ?>">
                                                        <div class="d-flex align-items-center">
                                                            <span class="menu-handle me-2"><i class="fas fa-grip-vertical"></i></span>
                                                            <div>
                                                                <div class="fw-bold">
                                                                    <?php if (!empty($child['icon'])): ?>
                                                                        <i class="<?php echo htmlspecialchars($child['icon']); ?> me-1"></i>
                                                                    <?php endif; ?>
                                                                    <?php echo htmlspecialchars($child['name']); ?>
                                                                </div>
                                                                <div class="small text-muted">
                                                                    <?php echo htmlspecialchars($child['url']); ?>
                                                                    <?php if (!$child['is_active']): ?><span class="badge bg-warning text-dark ms-2">Inactif</span><?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-secondary edit-menu-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editMenuModal"
                                                                    data-id="<?php echo $child['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($child['name']); ?>"
                                                                    data-position="<?php echo htmlspecialchars($child['position']); ?>"
                                                                    data-parent="<?php echo htmlspecialchars($child['parent_id']); ?>"
                                                                    data-url="<?php echo htmlspecialchars($child['url']); ?>"
                                                                    data-icon="<?php echo htmlspecialchars($child['icon'] ?? ''); ?>"
                                                                    data-active="<?php echo $child['is_active']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger delete-menu-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteMenuModal"
                                                                    data-id="<?php echo $child['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($child['name']); ?>">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-primary btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#addMenuModal" 
                            data-position="<?php echo $position; ?>">
                        <i class="fas fa-plus me-1"></i>Ajouter un élément
                    </button>
                </div>
            </div>
        </div>
        <?php $firstTab = false; ?>
    <?php endforeach; ?>
</div>

<!-- Aide sur les icônes -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Aide sur les icônes</h5>
            </div>
            <div class="card-body">
                <p>Vous pouvez utiliser des icônes Font Awesome dans vos menus. Voici quelques exemples d'icônes populaires :</p>
                <div class="row row-cols-2 row-cols-md-4 g-3">
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-home fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-home</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-graduation-cap</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-newspaper fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-newspaper</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-envelope fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-envelope</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-users</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-calendar-alt</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-book</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-flask fa-2x mb-2"></i>
                                <p class="card-text"><code>fas fa-flask</code></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <p>Pour voir toutes les icônes disponibles, consultez <a href="https://fontawesome.com/icons" target="_blank">la documentation de Font Awesome</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter une position de menu -->
<div class="modal fade" id="addPositionModal" tabindex="-1" aria-labelledby="addPositionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPositionModalLabel">Ajouter une position de menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-position" class="form-label">Nom de la position</label>
                        <input type="text" class="form-control" id="new-position" name="new-position" required>
                        <div class="form-text">
                            Utilisez un identifiant unique pour cette position (ex: "header", "footer-2", "sidebar-left").
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_position">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajouter un élément de menu -->
<div class="modal fade" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuModalLabel">Ajouter un élément de menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="menu-name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="menu-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="menu-position" class="form-label">Position</label>
                        <select class="form-select" id="menu-position" name="position" required>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position; ?>"><?php echo ucfirst($position); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="menu-parent" class="form-label">Parent (optionnel)</label>
                        <select class="form-select" id="menu-parent" name="parent_id">
                            <option value="">-- Aucun parent (élément de premier niveau) --</option>
                            <?php foreach ($allMenus as $menu): ?>
                                <option value="<?php echo $menu['id']; ?>" data-position="<?php echo htmlspecialchars($menu['position']); ?>">
                                    <?php echo htmlspecialchars($menu['name']); ?> (<?php echo $menu['position']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de lien</label>
                        <div class="form-check">
                            <input class="form-check-input link-type-radio" type="radio" name="link_type" id="link-type-url" value="url" checked>
                            <label class="form-check-label" for="link-type-url">
                                URL personnalisée
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input link-type-radio" type="radio" name="link_type" id="link-type-page" value="page">
                            <label class="form-check-label" for="link-type-page">
                                Page existante
                            </label>
                        </div>
                    </div>

                    <div class="mb-3 link-option" id="url-option">
                        <label for="menu-url" class="form-label">URL</label>
                        <input type="text" class="form-control" id="menu-url" name="url" required>
                        <div class="form-text">
                            Pour les liens internes, utilisez des chemins relatifs (ex: "formations" ou "contact").
                            Pour les liens externes, utilisez l'URL complète (ex: "https://example.com").
                        </div>
                    </div>

                    <div class="mb-3 link-option" id="page-option" style="display: none;">
                        <label for="menu-page" class="form-label">Sélectionner une page</label>
                        <select class="form-select" id="menu-page" name="page_id">
                            <option value="">-- Sélectionnez une page --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo $page['id']; ?>" data-slug="<?php echo htmlspecialchars($page['slug']); ?>">
                                    <?php echo htmlspecialchars($page['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="menu-icon" class="form-label">Icône (optionnel)</label>
                        <input type="text" class="form-control" id="menu-icon" name="icon" placeholder="ex: fas fa-home">
                        <div class="form-text">
                            Utilisez les classes Font Awesome (ex: "fas fa-home").
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_menu">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un élément de menu -->
<div class="modal fade" id="editMenuModal" tabindex="-1" aria-labelledby="editMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMenuModalLabel">Modifier un élément de menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit-menu-id" name="menu_id">
                    <div class="mb-3">
                        <label for="edit-menu-name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="edit-menu-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-menu-position" class="form-label">Position</label>
                        <select class="form-select" id="edit-menu-position" name="position" required>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position; ?>"><?php echo ucfirst($position); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-menu-parent" class="form-label">Parent (optionnel)</label>
                        <select class="form-select" id="edit-menu-parent" name="parent_id">
                            <option value="">-- Aucun parent (élément de premier niveau) --</option>
                            <?php foreach ($allMenus as $menu): ?>
                                <option value="<?php echo $menu['id']; ?>" data-position="<?php echo htmlspecialchars($menu['position']); ?>">
                                    <?php echo htmlspecialchars($menu['name']); ?> (<?php echo $menu['position']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de lien</label>
                        <div class="form-check">
                            <input class="form-check-input link-type-radio" type="radio" name="link_type" id="edit-link-type-url" value="url" checked>
                            <label class="form-check-label" for="edit-link-type-url">
                                URL personnalisée
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input link-type-radio" type="radio" name="link_type" id="edit-link-type-page" value="page">
                            <label class="form-check-label" for="edit-link-type-page">
                                Page existante
                            </label>
                        </div>
                    </div>

                    <div class="mb-3 link-option" id="edit-url-option">
                        <label for="edit-menu-url" class="form-label">URL</label>
                        <input type="text" class="form-control" id="edit-menu-url" name="url" required>
                        <div class="form-text">
                            Pour les liens internes, utilisez des chemins relatifs (ex: "formations" ou "contact").
                            Pour les liens externes, utilisez l'URL complète (ex: "https://example.com").
                        </div>
                    </div>

                    <div class="mb-3 link-option" id="edit-page-option" style="display: none;">
                        <label for="edit-menu-page" class="form-label">Sélectionner une page</label>
                        <select class="form-select" id="edit-menu-page" name="page_id">
                            <option value="">-- Sélectionnez une page --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo $page['id']; ?>" data-slug="<?php echo htmlspecialchars($page['slug']); ?>">
                                    <?php echo htmlspecialchars($page['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit-menu-icon" class="form-label">Icône (optionnel)</label>
                        <input type="text" class="form-control" id="edit-menu-icon" name="icon" placeholder="ex: fas fa-home">
                        <div class="form-text">
                            Utilisez les classes Font Awesome (ex: "fas fa-home").
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit-menu-active" name="is_active" checked>
                        <label class="form-check-label" for="edit-menu-active">
                            Actif
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_menu">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un élément de menu -->
<div class="modal fade" id="deleteMenuModal" tabindex="-1" aria-labelledby="deleteMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMenuModalLabel">Supprimer un élément de menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-menu-id" name="menu_id">
                    <p>Êtes-vous sûr de vouloir supprimer l'élément de menu <strong id="delete-menu-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible. Les éléments enfants seront déplacés au niveau supérieur.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_menu">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>

<script>
    // Gestion des types de liens (URL ou Page)
document.querySelectorAll('.link-type-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const modalId = this.closest('.modal').id;
        const prefix = modalId === 'addMenuModal' ? '' : 'edit-';
        
        if (this.value === 'url') {
            document.getElementById(`${prefix}url-option`).style.display = 'block';
            document.getElementById(`${prefix}page-option`).style.display = 'none';
            document.getElementById(`${prefix}menu-url`).required = true;
            document.getElementById(`${prefix}menu-page`).required = false;
        } else {
            document.getElementById(`${prefix}url-option`).style.display = 'none';
            document.getElementById(`${prefix}page-option`).style.display = 'block';
            document.getElementById(`${prefix}menu-url`).required = false;
            document.getElementById(`${prefix}menu-page`).required = true;
        }
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser Sortable.js pour le tri des menus
    const sortableLists = document.querySelectorAll('.menu-sortable');
    const sortableInstances = [];
    
    sortableLists.forEach(function(list) {
        const sortable = new Sortable(list, {
            handle: '.menu-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            group: list.dataset.parent ? 'shared' : 'parent',
            onEnd: function(evt) {
                // Mettre à jour l'ordre des éléments visuellement
                // La sauvegarde réelle se fait via le bouton "Enregistrer l'ordre"
            }
        });
        sortableInstances.push(sortable);
    });
    
    // Gestion du bouton d'enregistrement de l'ordre
    document.querySelectorAll('.save-menu-order').forEach(function(button) {
        button.addEventListener('click', function() {
            const position = this.dataset.position;
            const menuItems = document.querySelectorAll(`#menu-items-${position} .menu-sortable[data-position="${position}"] > .menu-item`);
            
            const menuOrder = {};
            menuOrder[position] = [];
            
            // Récupérer l'ordre des éléments de premier niveau
            menuItems.forEach(function(item) {
                menuOrder[position].push(parseInt(item.dataset.id));
            });
            
            // Récupérer l'ordre des sous-menus
            document.querySelectorAll(`#menu-items-${position} .menu-sortable[data-parent]`).forEach(function(subMenu) {
                const parentId = subMenu.dataset.parent;
                const parentKey = `${position}_${parentId}`;
                menuOrder[parentKey] = [];
                
                subMenu.querySelectorAll('.menu-item').forEach(function(item) {
                    menuOrder[parentKey].push(parseInt(item.dataset.id));
                });
            });
            
            // Envoyer l'ordre au serveur
            fetch('menus', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=reorder_menu&menu_order=${encodeURIComponent(JSON.stringify(menuOrder))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Afficher un message de succès temporaire
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show mt-3';
                    alert.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector(`#${position}-content .card-body`).prepend(alert);
                    
                    // Masquer l'alerte après 3 secondes
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 3000);
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'enregistrement de l\'ordre des menus.');
            });
        });
    });
    
    // Gestion du modal d'ajout de menu
    const addMenuModal = document.getElementById('addMenuModal');
    if (addMenuModal) {
        addMenuModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.dataset.position) {
                const positionSelect = this.querySelector('#menu-position');
                positionSelect.value = button.dataset.position;
                
                // Déclencher l'événement de changement pour filtrer les options de parent
                const changeEvent = new Event('change');
                positionSelect.dispatchEvent(changeEvent);

                // Déterminer si c'est une URL de page ou une URL personnalisée
    const url = button.getAttribute('data-url');
    if (url && url.startsWith('details_page&slug=')) {
        // C'est une URL de page, extraire le slug
        const slug = url.replace('details_page&slug=', '');
        
        // Trouver l'ID de la page correspondante
        const pageSelect = document.getElementById('edit-menu-page');
        let pageOption = null;
        
        for (const option of pageSelect.options) {
            if (option.dataset.slug === slug) {
                pageOption = option;
                break;
            }
        }
        
        if (pageOption) {
            // Sélectionner l'option de la page
            document.getElementById('edit-link-type-page').checked = true;
            document.getElementById('edit-link-type-url').checked = false;
            pageSelect.value = pageOption.value;
            
            // Déclencher l'événement change pour afficher/masquer les champs
            document.getElementById('edit-link-type-page').dispatchEvent(new Event('change'));
        } else {
            // Si la page n'existe plus, revenir au mode URL
            document.getElementById('edit-link-type-url').checked = true;
            document.getElementById('edit-menu-url').value = url;
            document.getElementById('edit-link-type-url').dispatchEvent(new Event('change'));
        }
    } else {
        // C'est une URL personnalisée
        document.getElementById('edit-link-type-url').checked = true;
        document.getElementById('edit-menu-url').value = url || '';
        document.getElementById('edit-link-type-url').dispatchEvent(new Event('change'));
    }
            }
        });
    }
    
    // Filtrer les options de parent en fonction de la position
    document.querySelectorAll('#menu-position, #edit-menu-position').forEach(function(select) {
        select.addEventListener('change', function() {
            const position = this.value;
            const parentSelect = this.closest('.modal-body').querySelector('[name="parent_id"]');
            const options = parentSelect.querySelectorAll('option');
            
            options.forEach(function(option) {
                if (option.value === '') {
                    // Ne rien faire pour l'option "Aucun parent"
                } else {
                    const optionPosition = option.dataset.position;
                    if (optionPosition === position) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                        if (option.selected) {
                            parentSelect.value = '';
                        }
                    }
                }
            });
        });
    });
    
    // Gestion du modal d'édition de menu
const editMenuModal = document.getElementById('editMenuModal');
if (editMenuModal) {
    // Utiliser l'événement bootstrap plutôt que l'écouteur standard
    editMenuModal.addEventListener('show.bs.modal', function(event) {
        // Récupérer le bouton qui a déclenché le modal
        const button = event.relatedTarget;
        if (!button) return;
        
        console.log('Ouverture du modal d\'édition avec les données:', {
            id: button.getAttribute('data-id'),
            name: button.getAttribute('data-name'),
            position: button.getAttribute('data-position'),
            parent: button.getAttribute('data-parent'),
            url: button.getAttribute('data-url'),
            icon: button.getAttribute('data-icon'),
            active: button.getAttribute('data-active')
        });
        
        // Remplir le formulaire avec les données du menu
        document.getElementById('edit-menu-id').value = button.getAttribute('data-id');
        document.getElementById('edit-menu-name').value = button.getAttribute('data-name');
        document.getElementById('edit-menu-position').value = button.getAttribute('data-position');
        document.getElementById('edit-menu-parent').value = button.getAttribute('data-parent') || '';
        document.getElementById('edit-menu-url').value = button.getAttribute('data-url');
        document.getElementById('edit-menu-icon').value = button.getAttribute('data-icon') || '';
        document.getElementById('edit-menu-active').checked = button.getAttribute('data-active') === '1';
        
        // Déclencher manuellement l'événement change pour filtrer les options de parent
        // Changez const event à const changeEvent pour éviter le conflit
        const changeEvent = new Event('change');
        document.getElementById('edit-menu-position').dispatchEvent(changeEvent);
    });
}


    
    // Gestion du modal de suppression de menu
    const deleteMenuModal = document.getElementById('deleteMenuModal');
    if (deleteMenuModal) {
        deleteMenuModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const menuId = button.getAttribute('data-id');
            const menuName = button.getAttribute('data-name');
            
            const idField = this.querySelector('#delete-menu-id');
            const nameSpan = this.querySelector('#delete-menu-name');
            
            if (idField) idField.value = menuId;
            if (nameSpan) nameSpan.textContent = menuName;
        });
    }
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>


