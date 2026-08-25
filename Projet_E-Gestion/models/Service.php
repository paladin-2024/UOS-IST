<?php
class Service
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer tous les services
    public function getService($idStructure = null)
    {
        $query = "SELECT
            s.\"idService\" as \"idService\",
            str.\"idStructure\" as \"idStructure\",
            s.designation as designationService,
            s.\"Responsable\" as responsable,
            str.designation as designationStructure
        FROM service AS s
        INNER JOIN structure AS str ON s.\"Structure_idStructure\" = str.\"idStructure\"";

        if ($idStructure !== null) {
            $query .= " WHERE str.\"idStructure\" = :idStructure";
        }

        $stmt = $this->db->prepare($query);

        if ($idStructure !== null) {
            $stmt->bindParam(':idStructure', $idStructure, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajouter un service
    public function addService($designation, $responsable, $idStructure)
    {
        $query = "INSERT INTO service (designation, \"Responsable\", \"Structure_idStructure\")
                  VALUES (:designation, :responsable, :idStructure)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'responsable' => $responsable,
            'idStructure' => $idStructure,
        ]);
    }

    // Vérifier les doublons pour un service
    public function checkDuplicateService($designation, $idStructure)
    {
        $query = "SELECT COUNT(*) as count FROM service
                  WHERE designation = :designation AND \"Structure_idStructure\" = :idStructure";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'designation' => $designation,
            'idStructure' => $idStructure,
        ]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    // Supprimer un service
    public function deleteService($idService)
    {
        $query = "DELETE FROM service WHERE \"idService\" = :idService";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idService' => $idService]);
    }

    // Récupérer un service par son ID
    public function getServiceById($idService)
    {
        $query = "SELECT *,service.designation as designationService FROM service WHERE \"idService\" = :idService";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idService' => $idService]);
        return $stmt->fetch();
    }

    // Mettre à jour un service
    public function updateService($idService, $designation, $responsable, $idStructure)
    {
        $query = "UPDATE service
                  SET designation = :designation, \"Responsable\" = :responsable, \"Structure_idStructure\" = :idStructure
                  WHERE \"idService\" = :idService";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idService' => $idService,
            'designation' => $designation,
            'responsable' => $responsable,
            'idStructure' => $idStructure,
        ]);
    }
}
?>
