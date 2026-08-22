<?php
require_once dirname(__DIR__) . '/utils/Security.php';

class User
{
    public $con;

    function __construct()
    {
        $this->con = Connexion::getInstance()->getPDO();
    }

    // Fonction pour vérifier les doublons de nom et de login
    public function checkDuplicateUser($nomUser, $loginUser, $idRole)
    {
        $stmt = $this->con->prepare("SELECT * FROM t_users WHERE (nomUser = :nomUser AND idRole = :idRole) OR (loginUser = :loginUser)");
        $stmt->bindParam(':nomUser', $nomUser, PDO::PARAM_STR);
        $stmt->bindParam(':loginUser', $loginUser, PDO::PARAM_STR);
        $stmt->bindParam(':idRole', $idRole, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Méthode pour enregistrer une image sur le serveur et retourner le chemin
    private function uploadImage($imageFile)
    {
        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $targetDir = dirname(__DIR__) . '/uploads/';
            
            $validation = AppSecurity::validateImageUpload($imageFile);
            
            if (!$validation['success']) {
                error_log("Image upload failed: " . $validation['message']);
                return null;
            }

            $newFilename = 'USER_' . uniqid('', true) . '_' . bin2hex(random_bytes(8)) . '.' . $validation['extension'];
            $targetFilePath = $targetDir . $newFilename;

            if (move_uploaded_file($imageFile['tmp_name'], $targetFilePath)) {
                return $newFilename;
            }
        }
        return null;
    }

    // Méthode pour ajouter un utilisateur avec image
    function addUser($idRole, $nomUser, $loginUser, $pw, $imageFile, $etatUser, $dernier_connexion,$idAgent=null)
    {
        $imagePath = $this->uploadImage($imageFile);

        // Utilisez une image par défaut si aucune image n'est fournie
        if (!$imagePath) {
            $imagePath = "user.png";
        }

        $stmt = $this->con->prepare("INSERT INTO t_users (idRole, nomUser, loginUser, pw, imageUser, etatUser, dernier_connexion,idAgent) 
                                    VALUES (:idRole, :nomUser, :loginUser, :pw, :imageUser, :etatUser, :dernier_connexion,:idAgent)");
        $stmt->bindParam(':idRole', $idRole, PDO::PARAM_STR);
        $stmt->bindParam(':nomUser', $nomUser, PDO::PARAM_STR);
        $stmt->bindParam(':loginUser', $loginUser, PDO::PARAM_STR);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_STR);
        $stmt->bindParam(':pw', $pw, PDO::PARAM_STR);
        $stmt->bindParam(':imageUser', $imagePath, PDO::PARAM_STR);
        $stmt->bindParam(':etatUser', $etatUser, PDO::PARAM_INT);
        $stmt->bindParam(':dernier_connexion', $dernier_connexion, PDO::PARAM_STR);
        return $stmt->execute();
    }

    // Méthode pour mettre à jour un utilisateur avec possibilité de changer l'image
    public function updateUser($id, $idRole, $nomUser, $loginUser, $pw, $imageFile, $etatUser, $dernier_connexion)
    {
        $imagePath = $this->uploadImage($imageFile);

        $sql = "UPDATE t_users SET idRole = :idRole, nomUser = :nomUser, loginUser = :loginUser, etatUser = :etatUser, dernier_connexion = :dernier_connexion";
        $params = [
            ':idRole' => $idRole,
            ':nomUser' => $nomUser,
            ':loginUser' => $loginUser,
            ':etatUser' => $etatUser,
            ':dernier_connexion' => $dernier_connexion
        ];

        if ($pw) {
            $sql .= ", pw = :pw";
            $params[':pw'] = $pw;
        }

        if ($imagePath) {
            $sql .= ", imageUser = :imageUser";
            $params[':imageUser'] = $imagePath;
        }

        $sql .= " WHERE idUser = :idUser";
        $params[':idUser'] = $id;

        $stmt = $this->con->prepare($sql);
        return $stmt->execute($params);
    }

    // Méthode pour obtenir tous les utilisateurs
    function getUser()
    {
        $stmt = $this->con->query("SELECT * FROM t_users INNER JOIN t_roles ON t_roles.idRole=t_users.idRole WHERE t_users.idUser<>1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getUserListe($offset = 0, $limit = 10, $search = '') {
        $query = "SELECT * FROM t_users 
                  INNER JOIN t_roles ON t_roles.idRole=t_users.idRole 
                  WHERE t_users.idUser<>1";
        
        if (!empty($search)) {
            $query .= " AND (nomUser LIKE :search OR nomRole LIKE :search)";
        }
        
        $query .= " ORDER BY idUser DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->con->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Nouvelle méthode pour compter le total
    function countUser($search = '') {
        $query = "SELECT COUNT(*) FROM t_users 
                  INNER JOIN t_roles ON t_roles.idRole=t_users.idRole 
                  WHERE t_users.idUser<>1";
                  
        if (!empty($search)) {
            $query .= " AND (nomUser LIKE :search OR nomRole LIKE :search)";
        }
        
        $stmt = $this->con->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Gestion des permission
    public function saveUserPermissions($idRole, $permissions)
    {
        try {
            // Commencer une transaction
            $this->con->beginTransaction();

            // Supprimer d'abord toutes les permissions existantes pour cet utilisateur
            $query = "DELETE FROM t_user_permissions WHERE idRole = :idRole";
            $stmt = $this->con->prepare($query);
            $stmt->execute(['idRole' => $idRole]);

            // Insérer les nouvelles permissions
            if (!empty($permissions)) {
                $query = "INSERT INTO t_user_permissions (idRole, idPerm) VALUES (:idRole, :idPerm)";
                $stmt = $this->con->prepare($query);

                foreach ($permissions as $permission) {
                    $idPerm = $permission;

                    $stmt->execute([
                        'idRole' => $idRole,
                        'idPerm' => $idPerm ?? ''
                    ]);
                }
            }

            // Valider la transaction
            $this->con->commit();
            return true;
        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            $this->con->rollBack();
            return false;
        }
    }

    // Méthode pour obtenir un utilisateur par ID
    function getUserById($id)
    {
        $stmt = $this->con->prepare("SELECT * FROM t_users INNER JOIN t_roles ON t_roles.idRole=t_users.idRole WHERE t_users.idUser = :idUser");
        $stmt->bindParam(':idUser', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Méthode pour supprimer un utilisateur
    public function deleteUser($id)
    {
        try {
            $stmt = $this->con->prepare("DELETE FROM t_users WHERE idUser = :idUser");
            $stmt->bindParam(':idUser', $id, PDO::PARAM_INT);
            return $stmt->execute(); // Retourne true si réussi
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression de l'utilisateur (ID: $id) : " . $e->getMessage());
            return false;
        }
    }    

    // Méthode pour mettre à jour un utilisateur avec possibilité de changer l'image
    public function updateUserPassWord($id, $pw)
    {
        $sql = "UPDATE t_users SET pw = :pw WHERE idUser = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindParam(':pw', $pw, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    //GEstion des roles
    function getRolesById($id)
    {
        $stmt = $this->con->prepare("SELECT * FROM t_roles WHERE idRole = :idRole");
        $stmt->bindParam(':idRole', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Modification du login utilisateur
     */
    public function updateLogin($currentLogin, $newLogin, $confirmLogin, $userId)
    {
        try {
            // Vérification que le login actuel correspond
            if (!$this->verifyCurrentLogin($currentLogin, $userId)) {
                return ['success' => false, 'message' => 'Le login saisi ne correspond à celui de la BDD'];
            }

            // Vérification que les nouveaux logins correspondent
            if ($newLogin !== $confirmLogin) {
                return ['success' => false, 'message' => 'Les deux Logins ne correspondent pas'];
            }

            $sql = "UPDATE t_users SET loginUser = ? WHERE idUser = ?";
            $stmt = $this->con->prepare($sql);
            $result = $stmt->execute([$newLogin, $userId]);

            if ($result) {
                $_SESSION['login'] = $newLogin;
                return ['success' => true, 'message' => 'Login modifié avec succès'];
            }
            return ['success' => false, 'message' => 'Erreur lors de la modification du login'];
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue'];
        }
    }

    /**
     * Modification du nom utilisateur
     */
    public function updateName($newName, $userId)
    {
        try {
            if (empty(trim($newName))) {
                return ['success' => false, 'message' => 'Le nom ne peut pas être vide'];
            }

            $sql = "UPDATE t_users SET nomUser = ? WHERE idUser = ?";
            $stmt = $this->con->prepare($sql);
            $result = $stmt->execute([$newName, $userId]);

            if ($result) {
                $_SESSION['nomUser'] = $newName;
                return ['success' => true, 'message' => 'Nom modifié avec succès'];
            }
            return ['success' => false, 'message' => 'Erreur lors de la modification du nom'];
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du nom: " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue'];
        }
    }

    /**
     * Modification du mot de passe
     */
    public function updatePassword($currentPassword, $newPassword, $confirmPassword, $userId, $currentHashedPassword)
    {
        try {
            // Vérification que le mot de passe actuel correspond
            if (!password_verify($currentPassword, $currentHashedPassword)) {
                return ['success' => false, 'message' => 'Le mot de passe saisi ne correspond à celui de la BDD'];
            }

            // Vérification que les nouveaux mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'Les deux mots de passe ne correspondent pas'];
            }

            $hashNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE t_users SET pw = ? WHERE idUser = ?";
            $stmt = $this->con->prepare($sql);
            $result = $stmt->execute([$hashNewPassword, $userId]);

            if ($result) {
                $_SESSION['pw'] = $hashNewPassword;
                return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            }
            return ['success' => false, 'message' => 'Erreur lors de la modification du mot de passe'];
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du mot de passe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue'];
        }
    }

    /**
     * Modification de l'image de profil
     */
    public function updateProfileImage($imageFile, $userId)
    {
        try {
            $maxSize = 10000000; // 10 MB
            $allowedExtensions = ["jpeg", "jpg", "png"];

            if ($imageFile['error'] !== 0) {
                return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier'];
            }

            if ($imageFile['size'] > $maxSize) {
                return ['success' => false, 'message' => 'Une Image ne doit pas dépasser 10 Méga'];
            }

            $fileExtension = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedExtensions)) {
                return ['success' => false, 'message' => 'Erreur d\'extension (Seulement JPEG,JPG,PNG)'];
            }

            // Génération du nouveau nom de fichier
            $newFileName = uniqid("USER-", true) . '.' . $fileExtension;
            $uploadPath = dirname(__DIR__) . '/uploads/';
            $fullPath = $uploadPath . $newFileName;

            if (!move_uploaded_file($imageFile['tmp_name'], $fullPath)) {
                return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
            }

            // Mise à jour dans la base de données
            $sql = "UPDATE t_users SET imageUser = ? WHERE idUser = ?";
            $stmt = $this->con->prepare($sql);
            $result = $stmt->execute([$newFileName, $userId]);

            if ($result) {
                return ['success' => true, 'message' => 'Photo modifiée avec succès'];
            }
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour de la photo'];
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour de l'image: " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue'];
        }
    }

    /**
     * Vérification du login actuel
     */
    private function verifyCurrentLogin($login, $userId)
    {
        $sql = "SELECT loginUser FROM t_users WHERE idUser = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['loginUser'] === $login;
    }

    // Méthode pour vérifier les informations de connexion d'un utilisateur
    function seConnecter($log, $pwd)
    {
        $stmt = $this->con->prepare("SELECT * FROM t_users INNER JOIN t_roles ON t_roles.idRole=t_users.idRole WHERE loginUser = :loginUser AND pw = :pw");
        $stmt->bindParam(':loginUser', $log, PDO::PARAM_STR);
        $stmt->bindParam(':pw', $pwd, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
