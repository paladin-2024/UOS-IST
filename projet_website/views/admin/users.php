<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'users';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Traitement des actions
if (isset($_POST['action'])) {
    try {
        // Action d'ajout d'un utilisateur
        if ($_POST['action'] === 'add_user' && isset($_POST['username'], $_POST['email'], $_POST['full_name'], $_POST['role'])) {
            // Vérifier si le nom d'utilisateur existe déjà
            $checkUsernameStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkUsernameStmt->bindParam(':username', $_POST['username']);
            $checkUsernameStmt->execute();
            if ($checkUsernameStmt->fetchColumn() > 0) {
                throw new Exception("Ce nom d'utilisateur existe déjà.");
            }

            // Vérifier si l'email existe déjà
            $checkEmailStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $checkEmailStmt->bindParam(':email', $_POST['email']);
            $checkEmailStmt->execute();
            if ($checkEmailStmt->fetchColumn() > 0) {
                throw new Exception("Cette adresse email est déjà utilisée.");
            }

            // Vérifier que les mots de passe correspondent
            if ($_POST['password'] !== $_POST['confirm_password']) {
                throw new Exception("Les mots de passe ne correspondent pas.");
            }

            // Hasher le mot de passe
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur dans la base de données
            $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role, is_active) 
                                VALUES (:username, :password, :email, :full_name, :role, :is_active)");
            
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->bindParam(':username', $_POST['username']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':role', $_POST['role']);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'utilisateur a été ajouté avec succès.";
        }
        
        // Action de mise à jour d'un utilisateur
        else if ($_POST['action'] === 'update_user' && isset($_POST['user_id'], $_POST['username'], $_POST['email'], $_POST['full_name'], $_POST['role'])) {
            // Vérifier si le nom d'utilisateur existe déjà (sauf pour cet utilisateur)
            $checkUsernameStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
            $checkUsernameStmt->bindParam(':username', $_POST['username']);
            $checkUsernameStmt->bindParam(':id', $_POST['user_id']);
            $checkUsernameStmt->execute();
            if ($checkUsernameStmt->fetchColumn() > 0) {
                throw new Exception("Ce nom d'utilisateur existe déjà.");
            }

            // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
            $checkEmailStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $checkEmailStmt->bindParam(':email', $_POST['email']);
            $checkEmailStmt->bindParam(':id', $_POST['user_id']);
            $checkEmailStmt->execute();
            if ($checkEmailStmt->fetchColumn() > 0) {
                throw new Exception("Cette adresse email est déjà utilisée.");
            }

            // Mettre à jour l'utilisateur
            if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
                // Si un nouveau mot de passe est fourni
                if ($_POST['password'] !== $_POST['confirm_password']) {
                    throw new Exception("Les mots de passe ne correspondent pas.");
                }
                
                // Hasher le nouveau mot de passe
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET username = :username, password = :password, email = :email, 
                                    full_name = :full_name, role = :role, is_active = :is_active WHERE id = :id");
                $stmt->bindParam(':password', $hashedPassword);
            } else {
                // Si aucun nouveau mot de passe n'est fourni
                $stmt = $db->prepare("UPDATE users SET username = :username, email = :email, 
                                    full_name = :full_name, role = :role, is_active = :is_active WHERE id = :id");
            }
            
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->bindParam(':id', $_POST['user_id']);
            $stmt->bindParam(':username', $_POST['username']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':role', $_POST['role']);
            $stmt->bindParam(':is_active', $isActive);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'utilisateur a été mis à jour avec succès.";
        }
        
        // Action de suppression d'un utilisateur
        else if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
            // Empêcher de supprimer son propre compte
            if ($_SESSION['id'] == $_POST['user_id']) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte.");
            }
            
            // Vérifier si c'est le dernier administrateur
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != :id");
            $stmt->bindParam(':id', $_POST['user_id']);
            $stmt->execute();
            
            $remainingAdmins = $stmt->fetchColumn();
            
            $userRoleStmt = $db->prepare("SELECT role FROM users WHERE id = :id");
            $userRoleStmt->bindParam(':id', $_POST['user_id']);
            $userRoleStmt->execute();
            $userRole = $userRoleStmt->fetchColumn();
            
            if ($userRole === 'admin' && $remainingAdmins === 0) {
                throw new Exception("Impossible de supprimer le dernier administrateur.");
            }
            
            // Supprimer l'utilisateur
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $_POST['user_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "L'utilisateur a été supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: users');
    exit;
}

// Récupérer tous les utilisateurs
$usersStmt = $db->query("SELECT * FROM users ORDER BY role, full_name");
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des utilisateurs -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-users-cog me-2"></i>Gestion des utilisateurs</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-2"></i>Ajouter un utilisateur
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
                    Gérez les comptes utilisateurs qui ont accès au panneau d'administration. Vous pouvez définir différents rôles avec des permissions spécifiques.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des utilisateurs</h5>
    </div>
    <div class="card-body">
        <?php if (empty($usersList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun utilisateur n'a été trouvé.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nom complet</th>
                            <th>Nom d'utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Dernière modification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">Administrateur</span>
                                    <?php elseif ($user['role'] === 'editor'): ?>
                                        <span class="badge bg-primary">Éditeur</span>
                                    <?php elseif ($user['role'] === 'manager'): ?>
                                        <span class="badge bg-success">Gestionnaire</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary edit-user-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                data-is-active="<?php echo $user['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($_SESSION['id'] != $user['id']): ?>
                                            <button type="button" class="btn btn-outline-danger delete-user-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteUserModal"
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger" disabled title="Vous ne pouvez pas supprimer votre propre compte">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal Ajouter un utilisateur -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Ajouter un nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user-full-name" class="form-label">Nom complet</label>
                        <input type="text" class="form-control" id="user-full-name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="user-username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="user-username" name="username" required pattern="[a-zA-Z0-9_-]{3,20}">
                        <div class="form-text">
                            Le nom d'utilisateur doit contenir entre 3 et 20 caractères (lettres, chiffres, tirets et underscores uniquement).
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="user-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="user-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="user-password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="user-password" name="password" required minlength="8">
                        <div class="form-text">
                            Le mot de passe doit contenir au moins 8 caractères.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="user-confirm-password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" class="form-control" id="user-confirm-password" name="confirm_password" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="user-role" class="form-label">Rôle</label>
                        <select class="form-select" id="user-role" name="role" required>
                            <option value="admin">Administrateur</option>
                            <option value="editor" selected>Éditeur</option>
                            <option value="manager">Gestionnaire</option>
                        </select>
                        <div class="form-text">
                            <ul>
                                <li><strong>Administrateur</strong>: Accès complet à toutes les fonctionnalités</li>
                                <li><strong>Éditeur</strong>: Peut créer et modifier du contenu, mais pas gérer les utilisateurs</li>
                                <li><strong>Gestionnaire</strong>: Peut gérer des sections spécifiques sans accès total</li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="user-active" name="is_active" checked>
                        <label class="form-check-label" for="user-active">
                            Utilisateur actif
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_user">Ajouter l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Modifier l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit-user-id" name="user_id">
                    <div class="mb-3">
                        <label for="edit-user-full-name" class="form-label">Nom complet</label>
                        <input type="text" class="form-control" id="edit-user-full-name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-user-username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="edit-user-username" name="username" required pattern="[a-zA-Z0-9_-]{3,20}">
                        <div class="form-text">
                            Le nom d'utilisateur doit contenir entre 3 et 20 caractères (lettres, chiffres, tirets et underscores uniquement).
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-user-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit-user-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-user-password" class="form-label">Nouveau mot de passe <small class="text-muted">(Laisser vide pour conserver l'actuel)</small></label>
                        <input type="password" class="form-control" id="edit-user-password" name="password" minlength="8">
                        <div class="form-text">
                            Le mot de passe doit contenir au moins 8 caractères.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-user-confirm-password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="edit-user-confirm-password" name="confirm_password" minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="edit-user-role" class="form-label">Rôle</label>
                        <select class="form-select" id="edit-user-role" name="role" required>
                            <option value="admin">Administrateur</option>
                            <option value="editor">Éditeur</option>
                            <option value="manager">Gestionnaire</option>
                        </select>
                        <div class="form-text">
                            <ul>
                                <li><strong>Administrateur</strong>: Accès complet à toutes les fonctionnalités</li>
                                <li><strong>Éditeur</strong>: Peut créer et modifier du contenu, mais pas gérer les utilisateurs</li>
                                <li><strong>Gestionnaire</strong>: Peut gérer des sections spécifiques sans accès total</li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit-user-active" name="is_active">
                        <label class="form-check-label" for="edit-user-active">
                            Utilisateur actif
                        </label>
                    </div>
                    <div id="self-edit-warning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Attention: Vous êtes en train de modifier votre propre compte. En cas de désactivation ou de changement de rôle, vous pourriez perdre l'accès à certaines fonctionnalités.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_user">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer un utilisateur -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Supprimer un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-user-id" name="user_id">
                    <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete-user-name"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement ce compte utilisateur.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_user">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal d'édition
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Récupérer l'ID utilisateur courant depuis la session
            const currentUserId = <?php echo $_SESSION['id'] ?? 0; ?>;
            
            // Remplir le formulaire avec les données de l'utilisateur
            const userId = button.getAttribute('data-id');
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-user-full-name').value = button.getAttribute('data-full-name');
            document.getElementById('edit-user-username').value = button.getAttribute('data-username');
            document.getElementById('edit-user-email').value = button.getAttribute('data-email');
            document.getElementById('edit-user-role').value = button.getAttribute('data-role');
            document.getElementById('edit-user-active').checked = button.getAttribute('data-is-active') === '1';
            
            // Vider les champs de mot de passe (ils sont optionnels en mode édition)
            document.getElementById('edit-user-password').value = '';
            document.getElementById('edit-user-confirm-password').value = '';
            
            // Afficher un avertissement si l'utilisateur modifie son propre compte
            const selfEditWarning = document.getElementById('self-edit-warning');
            if (parseInt(userId) === currentUserId) {
                selfEditWarning.classList.remove('d-none');
            } else {
                selfEditWarning.classList.add('d-none');
            }
        });
    }
    
    // Gestion du modal de suppression
    const deleteUserModal = document.getElementById('deleteUserModal');
    if (deleteUserModal) {
        deleteUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const userId = button.getAttribute('data-id');
            const userName = button.getAttribute('data-full-name') || button.getAttribute('data-username');
            
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-user-name').textContent = userName;
        });
    }
    
    // Validation des mots de passe correspondants lors de l'ajout
    const addUserForm = document.querySelector('#addUserModal form');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            const password = document.getElementById('user-password').value;
            const confirmPassword = document.getElementById('user-confirm-password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
        });
    }
    
    // Validation des mots de passe correspondants lors de la modification
    const editUserForm = document.querySelector('#editUserModal form');
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            const password = document.getElementById('edit-user-password').value;
            const confirmPassword = document.getElementById('edit-user-confirm-password').value;
            
            // Vérifier uniquement si un nouveau mot de passe est fourni
            if (password || confirmPassword) {
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Les nouveaux mots de passe ne correspondent pas.');
                    return false;
                }
            }
        });
    }
});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>
