<?php
class Role
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();;
    }

    // Récupérer tous les Roles
    public function getAllRoles()
    {
        $query = "SELECT * FROM t_roles";
        return $this->db->query($query);
    }

    function getRoleListe($offset = 0, $limit = 10, $search = '') {
        $query = "SELECT * FROM t_roles";
        
        if (!empty($search)) {
            $query .= " WHERE (nomRole LIKE :search)";
        }
        
        $query .= " ORDER BY idRole DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        
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
    function countRole($search = '') {
        $query = "SELECT COUNT(*) FROM t_roles";
                  
        if (!empty($search)) {
            $query .= " WHERE (nomRole LIKE :search)";
        }
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    //GEt Roles by id
    function getRolesById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM t_roles WHERE idRole = :idRole");
        $stmt->bindParam(':idRole', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Verifier les Roles
    public function checkDuplicateRole($nomRole)
    {
        try {
            $sql = "SELECT COUNT(*) AS count FROM t_roles WHERE nomRole = :nomRole";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nomRole', $nomRole, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0; // Retourne `true` si un doublon est trouvé, sinon `false`
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des doublons pour le module : " . $e->getMessage());
            return false; // Par précaution, retourne `false` en cas d'erreur
        }
    }

    // Ajouter un role
    public function addRole($nomRole)
    {
        $query = "INSERT INTO t_roles (nomRole) VALUES (:nomRole)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nomRole', $nomRole);
        return $stmt->execute();
    }

    // Modifier un role
    public function updateRole($idRole, $nomRole)
    {
        $query = "UPDATE t_roles SET nomRole = :nomRole WHERE idRole = :idRole";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nomRole', $nomRole);
        $stmt->bindParam(':idRole', $idRole);
        return $stmt->execute();
    }

    function hasPermission($userId, $pageName)
{
    // Cas spécial pour la page "index" : donner l'accès à tous
    if ($pageName === 'index' || $pageName === '406' || $pageName === 'permissions' || $pageName === 'userPermissions') {
        return true; // L'accès est toujours accordé pour la page "index"
    }

    // Récupérer l'ID du rôle de l'utilisateur
    $stmt = $this->db->prepare("SELECT idRole FROM t_users WHERE idUser = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier si l'utilisateur existe et a un rôle associé
    if ($user) {
        $roleId = $user['idRole'];
        
        // Vérifier si le rôle a la permission d'accéder à la page
        $stmt = $this->db->prepare("SELECT up.idUP 
                                    FROM t_user_permissions up
                                    JOIN t_permissions p ON up.idPerm = p.idPerm
                                    WHERE up.idRole = :roleId 
                                    AND p.nomPerm = :pageName");
        $stmt->bindParam(':roleId', $roleId, PDO::PARAM_INT);
        $stmt->bindParam(':pageName', $pageName, PDO::PARAM_STR);
        $stmt->execute();
        
        // Si la permission existe, l'utilisateur peut accéder à la page
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
    }
    
    // Si l'utilisateur n'a pas la permission, on retourne false
    return false;
}

}
