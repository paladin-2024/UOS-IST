<?php
class Module
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer tous les modules
    public function getAllModules()
    {
        $query = "SELECT * FROM t_modules";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer tous les modules
    public function getModules()
    {
        $query = "SELECT * FROM t_modules";
        return $this->db->query($query);
    }

    // Récupérer les permissions pour un module, avec une recherche et une limite
    public function getPermissionByMod($id, $search = '', $limit = 20)
    {
        // Valider le paramètre $limit pour éviter les problèmes de sécurité
        $limit = intval($limit); // S'assurer que $limit est un entier

        // Construire la requête avec la limite directement injectée
        $query = '
            SELECT
                m."idMod",
                m."nomMod",
                p."idPerm",
                m.package,
                p."codePerm",
                p."nomPerm",
                p."descPerm"
            FROM
                t_modules m
            INNER JOIN
                t_permissions p
                ON m."idMod" = p."idMod"
            WHERE
                p."idMod" = ?
                AND (
                    p."codePerm" LIKE ? OR
                    p."nomPerm" LIKE ? OR
                    p."descPerm" LIKE ?
                )
            ORDER BY
                p."idPerm" DESC
            LIMIT ' . $limit . '
        ';

        // Préparer les paramètres pour la requête
        $searchTerm = "%$search%";

        // Préparer et exécuter la requête
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id, $searchTerm, $searchTerm, $searchTerm]);

        return $stmt;
    }



    function getModuleListe($offset = 0, $limit = 10, $search = '') {
        $query = "SELECT * FROM t_modules";

        if (!empty($search)) {
            $query .= ' WHERE ("nomMod" LIKE :search)';
        }

        $query .= ' ORDER BY "idMod" DESC LIMIT :limit OFFSET :offset';

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
    function countModule($search = '') {
        $query = "SELECT COUNT(*) FROM t_modules";

        if (!empty($search)) {
            $query .= ' WHERE ("nomMod" LIKE :search)';
        }

        $stmt = $this->db->prepare($query);

        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchColumn();
    }


    //GEt Module by id
    function getModuleById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM t_modules WHERE "idMod" = :idMod');
        $stmt->bindParam(':idMod', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    //GEt Module by id
    function getModulePermissionByModule($idMod, $offset = 0, $limit = 10, $search = '') {
        $query = 'SELECT
                            m."idMod",
                            m."nomMod",
                            p."idPerm",
                            m.package,
                            p."codePerm",
                            p."nomPerm",
                            p."descPerm"
                        FROM t_modules m
                        INNER JOIN t_permissions p ON m."idMod" = p."idMod"
                        WHERE p."idMod" = :idMod ';

        if (!empty($search)) {
            $query .= ' AND (m."nomMod" LIKE :search OR p."nomPerm" LIKE :search)';
        }

        $query .= ' ORDER BY p."nomPerm" ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($query);

        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':idMod', $idMod, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Nouvelle méthode pour compter le total
    function countPermUserModule($idMod, $search = '') {
        $query = 'SELECT DISTINCT COUNT(*)
                        FROM t_modules m
                        INNER JOIN t_permissions p ON m."idMod" = p."idMod"
                        WHERE p."idMod" = :idMod ';

        if (!empty($search)) {
            $query .= ' AND (m."nomMod" LIKE :search OR p."nomPerm" LIKE :search)';
        }

        $stmt = $this->db->prepare($query);

        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':idMod', $idMod, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPermissionByModule($idMod)
    {
        try {
            // Préparer la requête SQL pour récupérer les permissions par module
            $sql = 'SELECT DISTINCT
                            m."idMod",
                            m."nomMod",
                            p."idPerm",
                            m.package,
                            p."codePerm",
                            p."nomPerm",
                            p."descPerm"
                        FROM t_modules m
                        INNER JOIN t_permissions p ON m."idMod" = p."idMod"
                        WHERE p."idMod" = :idMod
                        ORDER BY p."nomPerm" DESC';
            $stmt = $this->db->prepare($sql);

            // Lier le paramètre :idMod à la valeur fournie
            $stmt->bindParam(':idMod', $idMod, PDO::PARAM_INT);

            // Exécuter la requête
            $stmt->execute();

            // Retourner le résultat sous forme de tableau associatif (plusieurs lignes)
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Enregistrer l'erreur dans les logs
            error_log("Erreur dans getPermissionByModule : " . $e->getMessage());

            // Retourner false en cas d'échec
            return false;
        }
    }

    // Verifier les modules
    public function checkDuplicateModule($nomMod, $package)
    {
        try {
            $sql = 'SELECT COUNT(*) AS count FROM t_modules WHERE "nomMod" = :nomMod AND package = :package';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nomMod', $nomMod, PDO::PARAM_STR);
            $stmt->bindParam(':package', $package, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0; // Retourne `true` si un doublon est trouvé, sinon `false`
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des doublons pour le module : " . $e->getMessage());
            return false; // Par précaution, retourne `false` en cas d'erreur
        }
    }

    // Ajouter un module
    public function addModule($nomMod, $package)
    {
        $query = 'INSERT INTO t_modules ("nomMod", package) VALUES (:nomMod, :package)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nomMod', $nomMod);
        $stmt->bindParam(':package', $package);
        return $stmt->execute();
    }

    // Modifier un module
    public function updateModule($idMod, $nomMod, $package)
    {
        $query = 'UPDATE t_modules SET "nomMod" = :nomMod, package = :package WHERE "idMod" = :idMod';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nomMod', $nomMod);
        $stmt->bindParam(':package', $package);
        $stmt->bindParam(':idMod', $idMod);
        return $stmt->execute();
    }

    //Gerer permissions
    public function checkDuplicatePermission($codePerm, $nomPerm)
    {
        try {
            $sql = 'SELECT COUNT(*) AS count
                    FROM t_permissions
                    WHERE ("codePerm" = :codePerm AND "nomPerm" = :nomPerm)';

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':codePerm', $codePerm, PDO::PARAM_STR);
            $stmt->bindParam(':nomPerm', $nomPerm, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0; // Retourne `true` si un doublon est trouvé, sinon `false`
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des doublons pour la permission : " . $e->getMessage());
            return false; // Par précaution, retourne `false` en cas d'erreur
        }
    }

    public function addPermission($idMod, $codePerm, $nomPerm, $descPerm)
    {
        try {
            $sql = 'INSERT INTO t_permissions ("idMod", "codePerm", "nomPerm", "descPerm")
                VALUES (:idMod, :codePerm, :nomPerm, :descPerm)';

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':idMod', $idMod, PDO::PARAM_INT);
            $stmt->bindParam(':codePerm', $codePerm, PDO::PARAM_STR);
            $stmt->bindParam(':nomPerm', $nomPerm, PDO::PARAM_STR);
            $stmt->bindParam(':descPerm', $descPerm, PDO::PARAM_STR);

            return $stmt->execute(); // Retourne `true` si l'insertion réussit, sinon `false`
        } catch (PDOException $e) {
            error_log("Erreur lors de l'ajout d'une permission : " . $e->getMessage());
            return false; // Retourne `false` en cas d'erreur
        }
    }

    public function updatePermission($idPerm, $idMod, $codePerm, $nomPerm, $descPerm)
    {
        $query = 'UPDATE t_permissions SET
                "idMod" = :idMod,
                "codePerm" = :codePerm,
                "nomPerm" = :nomPerm,
                "descPerm" = :descPerm
              WHERE "idPerm" = :idPerm';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idPerm', $idPerm);
        $stmt->bindParam(':idMod', $idMod);
        $stmt->bindParam(':codePerm', $codePerm);
        $stmt->bindParam(':nomPerm', $nomPerm);
        $stmt->bindParam(':descPerm', $descPerm);

        return $stmt->execute();
    }

    public function getPermissionsByRole($idRole){
        $query = 'SELECT
                    m."idMod",
                    m."nomMod",
                    p."idPerm",
                    m.package,
                    p."codePerm",
                    p."nomPerm",
                    p."descPerm",
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM t_user_permissions up
                            WHERE up."idPerm" = p."idPerm"
                            AND up."idRole" = :idRole
                        ) THEN 1
                        ELSE 0
                    END as is_checked
                FROM t_modules m
                INNER JOIN t_permissions p ON m."idMod" = p."idMod"
                ORDER BY m."nomMod", p."idPerm"';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idRole', $idRole, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Fonction pour la liste permission d'un role
    public function getUserAllPermissionsByRoleListe($idRole, $offset = 0, $limit=20, $search = '') {
        $query = 'SELECT
                    m."idMod",
                    m."nomMod",
                    p."idPerm",
                    m.package,
                    p."codePerm",
                    p."nomPerm",
                    p."descPerm",
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM t_user_permissions up
                            WHERE up."idPerm" = p."idPerm"
                            AND up."idRole" = :idRole
                        ) THEN 1
                        ELSE 0
                    END as is_checked
                FROM t_modules m
                INNER JOIN t_permissions p ON m."idMod" = p."idMod"';


        if (!empty($search)) {
            $query .= ' WHERE (m."nomMod" LIKE :search OR p."nomPerm" LIKE :search)';
        }

        $query .= ' ORDER BY p."nomPerm" ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($query);

        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':idRole', $idRole, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function countUserAllPermissionsByRole($idRole, $search = '') {
        $query = 'SELECT DISTINCT COUNT(*)
                        FROM t_modules m
                        INNER JOIN t_permissions p ON m."idMod" = p."idMod"
                        INNER JOIN t_user_permissions up ON up."idPerm" = p."idPerm"
                        WHERE up."idRole" = :idRole';

        if (!empty($search)) {
            $query .= ' AND (m."nomMod" LIKE :search OR p."nomPerm" LIKE :search)';
        }

        $stmt = $this->db->prepare($query);

        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':idRole', $idRole, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * Récupère tous les modules et permissions pour un rôle donné, avec leur état (activé ou non).
     */
    public function getUserAllPermissionsByRole($idRole)
    {
        $query = 'SELECT DISTINCT
                m."idMod",
                m."nomMod",
                p."idPerm",
                m.package,
                p."codePerm",
                p."nomPerm",
                p."descPerm",
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM t_user_permissions up
                        WHERE up."idPerm" = p."idPerm"
                        AND up."idRole" = :idRole
                    ) THEN 1
                    ELSE 0
                END as is_checked
             FROM t_permissions p
             INNER JOIN t_modules m ON m."idMod" = p."idMod"
             ORDER BY m."idMod", p."idPerm"';

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idRole' => $idRole]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
    * Récupère tous les modules et permissions pour un ou plusieurs rôles donnés.
    */
    public function getUserModulesAndPermissions($roles)
    {
    if (!is_array($roles)) {
    $roles = [$roles]; // Si un seul rôle, convertir en array
    }
    $roles = array_values(array_filter($roles, function ($r) { return $r !== null && $r !== ''; }));
    if (empty($roles)) {
        return [];
    }
    $placeholders = str_repeat('?,', count($roles) - 1) . '?';
    $query = 'SELECT
    m."idMod",
    m."nomMod",
       m.package,
       p."codePerm",
       p."nomPerm",
       p."descPerm"
    FROM t_user_permissions up
             INNER JOIN t_permissions p ON up."idPerm" = p."idPerm"
         INNER JOIN t_modules m ON m."idMod" = p."idMod"
         WHERE up."idRole" IN (' . $placeholders . ')
             ORDER BY m."idMod", p."descPerm"';

        $stmt = $this->db->prepare($query);
        $stmt->execute($roles);

        $modules = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $moduleId = $row['idMod'];
            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'nom' => $row['nomMod'],
                    'package' => $row['package'],
                    'permissions' => []
                ];
            }

            $modules[$moduleId]['permissions'][] = [
                'code' => $row['codePerm'],
                'nom' => $row['nomPerm'],
                'description' => $row['descPerm']
            ];
        }

        return $modules;
    }

    /**
    * Génère le HTML du menu de navigation
    */
    public function generateNavMenu($roles)
    {
    $modules = $this->getUserModulesAndPermissions($roles);
        $current_view = isset($_GET['view']) ? $_GET['view'] : '';

        $html = '<aside id="sidebar" class="sidebar">
                 <ul class="sidebar-nav" id="sidebar-nav">';

        foreach ($modules as $module) {
            $hasMultiplePerms = count($module['permissions']) > 1;

            if ($hasMultiplePerms) {
                $moduleId = strtolower(str_replace(' ', '', $module['nom']));
                $html .= $this->generateModuleWithSubmenu($module, $moduleId, $current_view);
            } else {
                $html .= $this->generateSimpleModule($module, $current_view);
            }
        }

        // Ajout du lien de déconnexion
        $html .= '<li class="nav-item">
                    <a class="nav-link d-flex align-items-center collapsed" href="index.php?view=deconnexion">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Déconnexion</span>
                    </a>
                  </li>';

        $html .= '</ul></aside>';
        return $html;
    }

    /**
     * Génère un module avec sous-menu
     */
    private function generateModuleWithSubmenu($module, $moduleId, $current_view)
    {
        $isActive = false;
        foreach ($module['permissions'] as $perm) {
            if ($current_view === $module['package'] . '/' . $perm['nom']) {
                $isActive = true;
                break;
            }
        }

        $html = '<li class="nav-item">
                    <a class="nav-link ' . ($isActive ? '' : 'collapsed') . '"
                       data-bs-target="#' . $moduleId . '-nav"
                       data-bs-toggle="collapse"
                       href="#"
                       aria-expanded="' . ($isActive ? 'true' : 'false') . '">
                        <i class="bi bi-menu-app-fill"></i>
                        <span>' . htmlspecialchars($module['nom']) . '</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <ul id="' . $moduleId . '-nav"
                        class="nav-content collapse ' . ($isActive ? 'show' : '') . '"
                        data-bs-parent="#sidebar-nav">';

        foreach ($module['permissions'] as $perm) {
            $permView = $module['package'] . '/' . $perm['nom'];
            $isCurrentView = ($current_view === $permView);

            $html .= '<li>
                        <a href="' . $permView . '"
                           class="' . ($isCurrentView ? 'active' : '') . '">
                            <i class="bi bi-circle"></i>
                            <span>' . htmlspecialchars($perm['description']) . '</span>
                        </a>
                     </li>';
        }

        $html .= '</ul></li>';
        return $html;
    }

    private function generateSimpleModule($module, $current_view)
    {
        $perm = $module['permissions'][0];
        $permView = $module['package'] . '/' . $perm['nom'];
        $isCurrentView = ($current_view === $permView);

        return '<li class="nav-item">
                    <a class="nav-link ' . ($isCurrentView ? 'active' : 'collapsed') . '"
                       href="' . $permView . '">
                        <i class="bi bi-menu-app-fill"></i>
                        <span>' . htmlspecialchars($module['nom']) . '</span>
                    </a>
                </li>';
    }

    public function deletePermission($idPerm)
    {
        try {
            $sql = 'DELETE FROM t_permissions WHERE "idPerm" = :idPerm';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':idPerm', $idPerm, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting permission: " . $e->getMessage());
            return false;
        }
    }


    public function getModuleIdByPermission($idPerm)
    {
        try {
            $sql = 'SELECT "idMod" FROM t_permissions WHERE "idPerm" = :idPerm';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':idPerm', $idPerm, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['idMod'] : null;
        } catch (PDOException $e) {
            error_log("Error retrieving module ID by permission: " . $e->getMessage());
            return null;
        }
    }

}
