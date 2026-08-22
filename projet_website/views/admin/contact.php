<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'contact';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Traitement des actions
if (isset($_POST['action'])) {
    try {
        // Action de marquer comme lu
        if ($_POST['action'] === 'mark_read' && isset($_POST['message_id'])) {
            $stmt = $db->prepare("UPDATE contact_submissions SET is_read = 1 WHERE id = :id");
            $stmt->bindParam(':id', $_POST['message_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le message a été marqué comme lu.";
        }
        
        // Action de marquer comme non lu
        else if ($_POST['action'] === 'mark_unread' && isset($_POST['message_id'])) {
            $stmt = $db->prepare("UPDATE contact_submissions SET is_read = 0 WHERE id = :id");
            $stmt->bindParam(':id', $_POST['message_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le message a été marqué comme non lu.";
        }
        
        // Action de suppression d'un message
        else if ($_POST['action'] === 'delete_message' && isset($_POST['message_id'])) {
            $stmt = $db->prepare("DELETE FROM contact_submissions WHERE id = :id");
            $stmt->bindParam(':id', $_POST['message_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Le message a été supprimé avec succès.";
        }
        
        // Action de suppression en masse
        else if ($_POST['action'] === 'bulk_delete' && isset($_POST['selected_messages'])) {
            $selectedMessages = $_POST['selected_messages'];
            $placeholders = str_repeat('?,', count($selectedMessages) - 1) . '?';
            
            $stmt = $db->prepare("DELETE FROM contact_submissions WHERE id IN ($placeholders)");
            $stmt->execute($selectedMessages);
            
            $_SESSION['success_message'] = count($selectedMessages) . " message(s) ont été supprimés avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: contact');
    exit;
}

// Gestion des filtres
$whereClause = "";
$params = [];

if (isset($_GET['read_status']) && in_array($_GET['read_status'], ['read', 'unread'])) {
    $isRead = ($_GET['read_status'] === 'read') ? 1 : 0;
    $whereClause .= "WHERE is_read = ?";
    $params[] = $isRead;
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $whereClause = !empty($whereClause) ? $whereClause . " AND " : "WHERE ";
    $whereClause .= "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $params = array_merge($params, [$search, $search, $search, $search]);
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Nombre de messages par page
$offset = ($page - 1) * $limit;

// Compter le nombre total de messages pour la pagination
$countQuery = "SELECT COUNT(*) FROM contact_submissions $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalMessages = $countStmt->fetchColumn();
$totalPages = ceil($totalMessages / $limit);

// Récupérer les messages avec pagination
$query = "SELECT * FROM contact_submissions $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des messages -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-envelope me-2"></i>Gestion des messages de contact</h1>
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

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="read_status" onchange="this.form.submit()">
                    <option value="">Tous les messages</option>
                    <option value="read" <?php echo isset($_GET['read_status']) && $_GET['read_status'] === 'read' ? 'selected' : ''; ?>>
                        Messages lus
                    </option>
                    <option value="unread" <?php echo isset($_GET['read_status']) && $_GET['read_status'] === 'unread' ? 'selected' : ''; ?>>
                        Messages non lus
                    </option>
                </select>
            </div>
            <div class="col-md-5 text-end">
                <?php if (isset($_GET['search']) || isset($_GET['read_status'])): ?>
                    <a href="contact" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Réinitialiser les filtres
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-danger ms-2" id="bulkDeleteBtn" disabled>
                    <i class="fas fa-trash me-1"></i>Supprimer la sélection
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des messages -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Liste des messages</h5>
        <span class="badge bg-primary"><?php echo $totalMessages; ?> message(s)</span>
    </div>
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun message trouvé.
            </div>
        <?php else: ?>
            <form id="bulkActionForm" action="" method="POST">
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th width="40"></th>
                                <th>Expéditeur</th>
                                <th>Sujet</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr class="<?php echo $message['is_read'] ? '' : 'table-active'; ?>">
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input message-checkbox" type="checkbox" name="selected_messages[]" value="<?php echo $message['id']; ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!$message['is_read']): ?>
                                            <span class="badge bg-warning rounded-circle p-1" title="Non lu">
                                                <i class="fas fa-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($message['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($message['email']); ?></small>
                                        <?php if (!empty($message['phone'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($message['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($message['subject'] ?: '(Pas de sujet)'); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary view-message-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewMessageModal"
                                                    data-id="<?php echo $message['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($message['name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($message['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($message['phone'] ?: ''); ?>"
                                                    data-subject="<?php echo htmlspecialchars($message['subject'] ?: ''); ?>"
                                                    data-message="<?php echo htmlspecialchars($message['message']); ?>"
                                                    data-date="<?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>"
                                                    data-ip="<?php echo htmlspecialchars($message['ip_address'] ?: ''); ?>"
                                                    data-read="<?php echo $message['is_read']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($message['is_read']): ?>
                                                <button type="button" class="btn btn-outline-warning mark-unread-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#markUnreadModal"
                                                        data-id="<?php echo $message['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($message['name']); ?>">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success mark-read-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#markReadModal"
                                                        data-id="<?php echo $message['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($message['name']); ?>">
                                                    <i class="fas fa-envelope-open"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo htmlspecialchars($message['subject'] ?: 'Votre message'); ?>" class="btn btn-outline-info">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger delete-message-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteMessageModal"
                                                    data-id="<?php echo $message['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($message['name']); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                        // Lien précédent
                        $prevLink = ($page > 1) ? '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) : '#';
                        $prevDisabled = ($page > 1) ? '' : 'disabled';
                        
                        // Lien suivant
                        $nextLink = ($page < $totalPages) ? '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) : '#';
                        $nextDisabled = ($page < $totalPages) ? '' : 'disabled';
                        ?>
                        
                        <li class="page-item <?php echo $prevDisabled; ?>">
                            <a class="page-link" href="<?php echo $prevLink; ?>" tabindex="-1" aria-disabled="<?php echo $prevDisabled ? 'true' : 'false'; ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>
                        
                        <?php
                        // Afficher les liens de pages
                        $startPage = max(1, min($page - 2, $totalPages - 4));
                        $endPage = min($totalPages, max($page + 2, 5));
                        
                        // Première page
                        if ($startPage > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        // Pages intermédiaires
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a></li>';
                        }
                        
                        // Dernière page
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $nextDisabled; ?>">
                            <a class="page-link" href="<?php echo $nextLink; ?>" aria-disabled="<?php echo $nextDisabled ? 'true' : 'false'; ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>

<!-- Modal Voir le message -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageModalLabel">Détails du message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="message-name"></h5>
                        <p class="message-email mb-1"></p>
                        <p class="message-phone mb-0"></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="message-date mb-1"></p>
                        <p class="message-ip mb-0 text-muted"></p>
                    </div>
                </div>
                <div class="message-subject-container mb-3">
                    <h6>Sujet:</h6>
                    <p class="message-subject lead"></p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Message:</h6>
                    </div>
                    <div class="card-body">
                        <p class="message-content"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form action="" method="POST" id="messageActionForm">
                    <input type="hidden" name="message_id" id="current-message-id">
                    <a href="#" class="btn btn-info message-reply-link" target="_blank">
                        <i class="fas fa-reply me-2"></i>Répondre
                    </a>
                    <button type="submit" class="btn btn-success message-read-btn d-none" name="action" value="mark_read">
                        <i class="fas fa-envelope-open me-2"></i>Marquer comme lu
                    </button>
                    <button type="submit" class="btn btn-warning message-unread-btn d-none" name="action" value="mark_unread">
                        <i class="fas fa-envelope me-2"></i>Marquer comme non lu
                    </button>
                    <button type="button" class="btn btn-danger" id="openDeleteFromView">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Marquer comme lu -->
<div class="modal fade" id="markReadModal" tabindex="-1" aria-labelledby="markReadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markReadModalLabel">Marquer comme lu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="read-message-id" name="message_id">
                    <p>Êtes-vous sûr de vouloir marquer le message de <strong id="read-message-name"></strong> comme lu ?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success" name="action" value="mark_read">Marquer comme lu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Marquer comme non lu -->
<div class="modal fade" id="markUnreadModal" tabindex="-1" aria-labelledby="markUnreadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markUnreadModalLabel">Marquer comme non lu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="unread-message-id" name="message_id">
                    <p>Êtes-vous sûr de vouloir marquer le message de <strong id="unread-message-name"></strong> comme non lu ?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning" name="action" value="mark_unread">Marquer comme non lu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un message -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-labelledby="deleteMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMessageModalLabel">Supprimer un message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-message-id" name="message_id">
                    <p>Êtes-vous sûr de vouloir supprimer le message de <strong id="delete-message-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce message.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_message">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression en masse -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteModalLabel">Supprimer les messages sélectionnés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer <span id="selected-count">0</span> messages ?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span>Cette action est irréversible et supprimera définitivement tous les messages sélectionnés.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Script pour gérer les messages et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal pour voir un message
    const viewMessageModal = document.getElementById('viewMessageModal');
    if (viewMessageModal) {
        viewMessageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Récupérer les données du message
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const email = button.getAttribute('data-email');
            const phone = button.getAttribute('data-phone');
            const subject = button.getAttribute('data-subject');
            const message = button.getAttribute('data-message');
            const date = button.getAttribute('data-date');
            const ip = button.getAttribute('data-ip');
            const isRead = button.getAttribute('data-read') === '1';
            
            // Remplir le modal avec les données
            document.getElementById('current-message-id').value = id;
            this.querySelector('.message-name').textContent = name;
            this.querySelector('.message-email').textContent = email;
            this.querySelector('.message-phone').textContent = phone || '';
            this.querySelector('.message-subject').textContent = subject || '(Pas de sujet)';
            this.querySelector('.message-content').textContent = message;
            this.querySelector('.message-date').textContent = 'Reçu le: ' + date;
            this.querySelector('.message-ip').textContent = ip ? 'IP: ' + ip : '';
            
            // Préparer le lien de réponse
            const replyLink = this.querySelector('.message-reply-link');
            replyLink.href = 'mailto:' + email + '?subject=Re: ' + (subject || 'Votre message');
            
            // Afficher le bouton approprié selon l'état de lecture
            const readBtn = this.querySelector('.message-read-btn');
            const unreadBtn = this.querySelector('.message-unread-btn');
            
            if (isRead) {
                readBtn.classList.add('d-none');
                unreadBtn.classList.remove('d-none');
            } else {
                readBtn.classList.remove('d-none');
                unreadBtn.classList.add('d-none');
                
                // Marquer automatiquement comme lu lors de l'ouverture
                setTimeout(() => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const action = document.createElement('input');
                    action.type = 'hidden';
                    action.name = 'action';
                    action.value = 'mark_read';
                    
                    const messageId = document.createElement('input');
                    messageId.type = 'hidden';
                    messageId.name = 'message_id';
                    messageId.value = id;
                    
                    form.appendChild(action);
                    form.appendChild(messageId);
                    document.body.appendChild(form);
                    form.submit();
                }, 1000);
            }
            
            // Gestion du bouton de suppression depuis la vue
            document.getElementById('openDeleteFromView').addEventListener('click', function() {
                // Fermer le modal de vue
                const viewModal = bootstrap.Modal.getInstance(viewMessageModal);
                viewModal.hide();
                
                // Ouvrir le modal de suppression
                document.getElementById('delete-message-id').value = id;
                document.getElementById('delete-message-name').textContent = name;
                
                setTimeout(() => {
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
                    deleteModal.show();
                }, 500);
            });
        });
    }
    
        // Gestion du modal pour marquer comme non lu
        const markUnreadModal = document.getElementById('markUnreadModal');
    if (markUnreadModal) {
        markUnreadModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            document.getElementById('unread-message-id').value = button.getAttribute('data-id');
            document.getElementById('unread-message-name').textContent = button.getAttribute('data-name');
        });
    }
    
    // Gestion du modal pour supprimer un message
    const deleteMessageModal = document.getElementById('deleteMessageModal');
    if (deleteMessageModal) {
        deleteMessageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            document.getElementById('delete-message-id').value = button.getAttribute('data-id');
            document.getElementById('delete-message-name').textContent = button.getAttribute('data-name');
        });
    }
    
    // Gestion de la sélection en masse
    const selectAllCheckbox = document.getElementById('selectAll');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    // Fonction pour mettre à jour l'état du bouton de suppression en masse
    function updateBulkDeleteButton() {
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        bulkDeleteBtn.disabled = checkedCount === 0;
        document.getElementById('selected-count').textContent = checkedCount;
    }
    
    // Événement pour la case à cocher "Tout sélectionner"
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });
    }
    
    // Événements pour les cases à cocher individuelles
    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Mettre à jour l'état de la case "Tout sélectionner"
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else {
                // Vérifier si toutes les cases sont cochées
                const allChecked = Array.from(messageCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
            
            updateBulkDeleteButton();
        });
    });
    
    // Événement pour le bouton de suppression en masse
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
            bulkDeleteModal.show();
        });
    }
    
    // Confirmation de suppression en masse
    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');
    if (confirmBulkDeleteBtn) {
        confirmBulkDeleteBtn.addEventListener('click', function() {
            document.getElementById('bulkActionForm').submit();
        });
    }
    
    // Initialiser l'état du bouton de suppression en masse
    updateBulkDeleteButton();
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>

