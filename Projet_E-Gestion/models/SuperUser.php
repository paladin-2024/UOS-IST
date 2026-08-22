<?php
class SuperUser
{
    public $con;
    public $username, $login, $pwd;

    function __construct()
    {
        // Récupérer une instance de la connexion
        $this->con = Connexion::getInstance()->getPDO();
    }

    public function seConnecter($log, $pwd)
    {
        try {
            // Préparer la requête SQL avec des placeholders - Utiliser le rôle principal depuis t_user_roles
            $stmt = $this->con->prepare("
                SELECT u.*, r.idRole, r.nomRole 
                FROM t_users u 
                LEFT JOIN t_user_roles ur ON ur.idUser = u.idUser AND ur.isPrincipal = 1
                LEFT JOIN t_roles r ON r.idRole = ur.idRole
                WHERE u.loginUser = :loginUser AND u.etatUser = 1
            ");
            
            // Associer le paramètre
            $stmt->bindParam(':loginUser', $log, PDO::PARAM_STR);
            
            // Exécuter la requête
            $stmt->execute();
            
            // Récupérer l'utilisateur
            $user = $stmt->fetch(PDO::FETCH_OBJ);
            
            // Vérifier si l'utilisateur existe et si le mot de passe est correct
            if ($user && password_verify($pwd, $user->pw)) {
                // Vérifier si c'est la première connexion
                $isFirstLogin = ($user->dernier_connexion === null);
                
                // Mettre à jour la dernière connexion seulement si ce n'est pas la première connexion
                if (!$isFirstLogin) {
                    $this->miseAJourDerniereConnexion($user->idUser);
                }
                
                return $user; // Retourne les informations de l'utilisateur
            }
        } catch (PDOException $e) {
            die("Erreur lors de la connexion : " . $e->getMessage());
        }

        return false; // Retourne false si l'utilisateur n'existe pas ou si le mot de passe est incorrect
    }

    // Méthode pour mettre à jour la dernière connexion
    public function miseAJourDerniereConnexion($userId)
    {
        try {
            // Préparer la requête SQL
            $stmt = $this->con->prepare("UPDATE t_users SET dernier_connexion = NOW() WHERE idUser = :idUser");
            
            // Associer le paramètre
            $stmt->bindParam(':idUser', $userId, PDO::PARAM_INT);
            
            // Exécuter la requête
            $stmt->execute();
        } catch (PDOException $e) {
            die("Erreur lors de la mise à jour : " . $e->getMessage());
        }
    }

    public function logUserActivity($userId, $actionType, $description, $ipAddress, $userAgent)
    {
        try {
            // Prepare the SQL statement
            $stmt = $this->con->prepare("
                INSERT INTO user_activity_log (user_id, action_type, description, ip_address, user_agent)
                VALUES (:user_id, :action_type, :description, :ip_address, :user_agent)
            ");

            // Bind parameters
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action_type', $actionType, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);

            // Execute the statement
            $stmt->execute();
        } catch (PDOException $e) {
            die("Erreur lors de l'enregistrement de l'activité : " . $e->getMessage());
        }
    }

    public function getUserIpAddress() {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    public function isFirstLogin($userId)
{
    try {
        // Préparer la requête SQL pour vérifier si dernier_connexion est NULL ou si first_password_changed est à 0
        $stmt = $this->con->prepare("
            SELECT COUNT(*) as count 
            FROM t_users 
            WHERE idUser = :userId 
            AND (dernier_connexion IS NULL)
        ");
        
        // Associer le paramètre
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer le résultat
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si le compte est supérieur à 0, c'est la première connexion
        return ($result['count'] > 0);
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de première connexion : " . $e->getMessage());
        return false;
    }
}



public function changePassword($userId, $newPassword)
{
    try {
        // Hasher le nouveau mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Préparer la requête SQL
        $stmt = $this->con->prepare("
            UPDATE t_users 
            SET pw = :password 
            WHERE idUser = :userId
        ");
        
        // Associer les paramètres
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        // Exécuter la requête
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors du changement de mot de passe : " . $e->getMessage());
        return false;
    }
}
}
