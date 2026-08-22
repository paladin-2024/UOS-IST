<?php
class Projet
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    public function getProjectsByUser($userId, $search = '', $limit = 20) {
        $query = "SELECT * FROM projet WHERE userId = :userId";
        
        if (!empty($search)) {
            $query .= " AND (nomProjet LIKE :search OR description LIKE :search)";
        }
        
        $query .= " LIMIT :limit";
        $stmt = $this->db->prepare($query);
        
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjetByUserStructure($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT p.*,s.*
            FROM projet p
            INNER JOIN structure s ON p.Structure_idStructure = s.idStructure
            INNER JOIN user_structure us ON s.idStructure = us.idStructure
            WHERE us.idUser = :userId
        ";
        
        if (!empty($search)) {
            $query .= " AND (p.nomProjet LIKE :search 
                        OR p.description LIKE :search 
                        OR p.statut LIKE :search)";
        }
        
        $query .= " ORDER BY p.nomProjet ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByProject($projectId) {
        $stmt = $this->db->prepare("SELECT * FROM user_projet p 
        INNER JOIN t_users u ON p.idUser=u.idUser WHERE Projet_idProjet = :projectId");
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addProject($nomProjet, $description, $dateDebut, $dateFin, $statut, $structureId) {
        $stmt = $this->db->prepare("INSERT INTO projet (nomProjet, description, dateDebut, dateFin, statut, Structure_idStructure) 
                                    VALUES (:nomProjet, :description, :dateDebut, :dateFin, :statut, :structureId)");
        
        return $stmt->execute([
            ':nomProjet' => $nomProjet,
            ':description' => $description,
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin,
            ':statut' => $statut,
            ':structureId' => $structureId
        ]);
    }

    public function updateProject($data) {
        $stmt = $this->db->prepare("UPDATE projet SET nomProjet = :nomProjet, description = :description, 
                                    dateDebut = :dateDebut, dateFin = :dateFin, statut = :statut, 
                                    Structure_idStructure = :structureId WHERE idProjet = :idProjet");
        return $stmt->execute($data);
    }

    public function deleteProject($projectId) {
        $stmt = $this->db->prepare("DELETE FROM projet WHERE idProjet = :idProjet");
        return $stmt->execute([':idProjet' => $projectId]);
    }

    // New methods

    public function getProjectById($projectId) {
        $stmt = $this->db->prepare("SELECT * FROM projet WHERE idProjet = :idProjet");
        $stmt->bindValue(':idProjet', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function checkDuplicateProject($nomProjet, $structureId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM projet WHERE nomProjet = :nomProjet AND Structure_idStructure = :structureId");
        $stmt->bindValue(':nomProjet', $nomProjet, PDO::PARAM_STR);
        $stmt->bindValue(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function getAllProjects($limit = 100) {
        $stmt = $this->db->prepare("SELECT * FROM projet ORDER BY dateDebut DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isUserInProject($userId, $projectId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_projet WHERE idUser = :userId AND Projet_idProjet = :projectId");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function addUserToProject($userId, $projectId) {
        if ($this->isUserInProject($userId, $projectId)) {
            return false; // User is already in the project
        }

        $stmt = $this->db->prepare("INSERT INTO user_projet (idUser, Projet_idProjet) VALUES (:userId, :projectId)");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteUserFromProject($userProjectId) {
        $stmt = $this->db->prepare("DELETE FROM user_projet WHERE iduser_projet = :userProjectId");
        $stmt->bindValue(':userProjectId', $userProjectId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getProjetByUserAccess($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT DISTINCT p.*
            FROM projet p
            LEFT JOIN user_structure us ON p.Structure_idStructure = us.idStructure
            LEFT JOIN user_projet up ON p.idProjet = up.Projet_idProjet
            WHERE us.idUser = :userId AND up.idUser = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (p.nomProjet LIKE :search 
                        OR p.description LIKE :search 
                        OR p.statut LIKE :search)";
        }

        $query .= " ORDER BY p.nomProjet ASC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivitiesByProject($projectId, $search = '', $limit = 200) {
        $query = "SELECT * FROM activite_projet WHERE Projet_idProjet = :projectId";

        if (!empty($search)) {
            $query .= " AND (intitule LIKE :search OR etatActivite LIKE :search)";
        }

        $query .= " ORDER BY dateDebut ASC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivitiesByProjectWithAccess($projectId, $userId, $search = '', $limit = 200) {
        $query = "
            SELECT ap.* 
            FROM activite_projet ap
            INNER JOIN user_activite_projet uap ON ap.idActivite_projet = uap.Activite_projet_idActivite_projet
            WHERE ap.Projet_idProjet = :projectId AND uap.idUser = :userId
        ";
    
        if (!empty($search)) {
            $query .= " AND (ap.intitule LIKE :search OR ap.etatActivite LIKE :search)";
        }
    
        $query .= " ORDER BY ap.dateDebut ASC LIMIT :limit";
    
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
    
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByActivity($activityId) {
        $stmt = $this->db->prepare("
            SELECT uap.*, u.nomUser 
            FROM user_activite_projet uap
            JOIN t_users u ON uap.idUser = u.idUser
            WHERE uap.Activite_projet_idActivite_projet = :activityId
        ");
        $stmt->bindValue(':activityId', $activityId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addActivity($intitule, $dateDebut, $dateFin, $budget, $etatActivite, $projetId) {
        $stmt = $this->db->prepare("INSERT INTO activite_projet (intitule, dateDebut, dateFin, budget, etatActivite, Projet_idProjet) 
                                    VALUES (:intitule, :dateDebut, :dateFin, :budget, :etatActivite, :projetId)");
        return $stmt->execute([
            ':intitule' => $intitule,
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin,
            ':budget' => $budget,
            ':etatActivite' => $etatActivite,
            ':projetId' => $projetId
        ]);
    }

    public function updateActivity($data) {
        $stmt = $this->db->prepare("UPDATE activite_projet SET intitule = :intitule, dateDebut = :dateDebut, 
                                    dateFin = :dateFin, budget = :budget, etatActivite = :etatActivite, 
                                    Projet_idProjet = :projetId WHERE idActivite_projet = :idActivite_projet");
        return $stmt->execute($data);
    }

    public function isUserInActivity($userId, $activityId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_activite_projet WHERE idUser = :userId AND Activite_projet_idActivite_projet = :activityId");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':activityId', $activityId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function addUserToActivity($userId, $activityId) {
        if ($this->isUserInActivity($userId, $activityId)) {
            return false; // User is already associated with the activity
        }

        $stmt = $this->db->prepare("INSERT INTO user_activite_projet (idUser, Activite_projet_idActivite_projet) VALUES (:userId, :activityId)");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':activityId', $activityId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteUserFromActivity($userActivityId) {
        $stmt = $this->db->prepare("DELETE FROM user_activite_projet WHERE iduser_activite_projet = :userActivityId");
        $stmt->bindValue(':userActivityId', $userActivityId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getDocumentsByActivity($activityId) {
        $stmt = $this->db->prepare("
            SELECT * FROM doc_activite 
            WHERE Activite_projet_idActivite_projet = :activityId
            ORDER BY dateEnregistrement DESC
        ");
        $stmt->bindValue(':activityId', $activityId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDocumentToActivity($titre, $description, $dateDocument, $fichier, $userId, $activityId) {
        $stmt = $this->db->prepare("
            INSERT INTO doc_activite (titre, description, dateDocument, dateEnregistrement, fichier, idUser, Activite_projet_idActivite_projet) 
            VALUES (:titre, :description, :dateDocument, NOW(), :fichier, :userId, :activityId)
        ");
        return $stmt->execute([
            ':titre' => $titre,
            ':description' => $description,
            ':dateDocument' => $dateDocument,
            ':fichier' => $fichier,
            ':userId' => $userId,
            ':activityId' => $activityId
        ]);
    }

    public function updateDocument($data) {
        $stmt = $this->db->prepare("
            UPDATE doc_activite 
            SET titre = :titre, description = :description, dateDocument = :dateDocument, fichier = :fichier 
            WHERE idDoc_activite = :idDoc_activite
        ");
        return $stmt->execute($data);
    }

    public function deleteDocument($documentId) {
        $stmt = $this->db->prepare("DELETE FROM doc_activite WHERE idDoc_activite = :documentId");
        $stmt->bindValue(':documentId', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getDocumentById($documentId) {
        $stmt = $this->db->prepare("
            SELECT * FROM doc_activite 
            WHERE idDoc_activite = :documentId
        ");
        $stmt->bindValue(':documentId', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTotalDocumentsByProject($projectId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as totalDocuments
            FROM doc_activite da
            INNER JOIN activite_projet ap ON da.Activite_projet_idActivite_projet = ap.idActivite_projet
            WHERE ap.Projet_idProjet = :projectId
        ");
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['totalDocuments'];
    }
}