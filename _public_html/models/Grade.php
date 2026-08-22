<?php
class Grade
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer tous les grades
    public function getGrades()
    {
        $query = "SELECT * FROM grade ORDER BY designation";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les grades par type d'agent
    public function getGradesByType($type_agent)
    {
        $query = "SELECT * FROM grade WHERE type_agent = :type_agent ORDER BY designation";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':type_agent', $type_agent, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajouter un grade
    public function addGrade($designation, $description, $type_agent)
    {
        $query = "INSERT INTO grade (designation, description, type_agent) 
                  VALUES (:designation, :description, :type_agent)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'description' => $description,
            'type_agent' => $type_agent
        ]);
    }

    // Vérifier les doublons pour un grade
    public function checkDuplicateGrade($designation, $type_agent)
    {
        $query = "SELECT COUNT(*) as count FROM grade 
                  WHERE designation = :designation AND type_agent = :type_agent";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'designation' => $designation,
            'type_agent' => $type_agent
        ]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    // Supprimer un grade
    public function deleteGrade($idgrade)
    {
        $query = "DELETE FROM grade WHERE idgrade = :idgrade";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idgrade' => $idgrade]);
    }

    // Récupérer un grade par son ID
    public function getGradeById($idgrade)
    {
        $query = "SELECT * FROM grade WHERE idgrade = :idgrade";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idgrade' => $idgrade]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour un grade
    public function updateGrade($idgrade, $designation, $description, $type_agent)
    {
        $query = "UPDATE grade 
                  SET designation = :designation, description = :description, type_agent = :type_agent 
                  WHERE idgrade = :idgrade";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idgrade' => $idgrade,
            'designation' => $designation,
            'description' => $description,
            'type_agent' => $type_agent
        ]);
    }





    /**
 * Récupère tous les grades
 * 
 * @return array Liste de tous les grades
 */
public function getAllGrades() {
    try {
        $query = "SELECT * FROM grade ORDER BY designation ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des grades: ' . $e->getMessage());
        return [];
    }
}

}
?>
