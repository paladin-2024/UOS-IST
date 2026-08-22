<?php

class Unite {
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    public function getUEs($search = '') {
        $query = "SELECT ue.*, s.numeroSemestre, p.designationPromotion, a.designation as annee
                  FROM ue
                  JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad";
        
        if (!empty($search)) {
            $query .= " WHERE ue.codeUE LIKE :search 
                       OR ue.designationUE LIKE :search 
                       OR s.numeroSemestre LIKE :search
                       OR p.designationPromotion LIKE :search";
        }
        
        $query .= " ORDER BY p.designationPromotion ASC, s.numeroSemestre ASC, ue.codeUE ASC";
    
        $stmt = $this->db->prepare($query);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUEById($idUE) {
        $query = "SELECT ue.*, s.numeroSemestre, p.designationPromotion, a.designation as annee
                  FROM ue
                  JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
                  WHERE ue.idUE = :idUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createUE($codeUE, $designationUE, $CMI, $TD, $TP, $semestre_idsemestre) {
        $query = "INSERT INTO ue (codeUE, designationUE, CMI, TD, TP, semestre_idsemestre) 
                  VALUES (:codeUE, :designationUE, :CMI, :TD, :TP, :semestre_idsemestre)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':codeUE', $codeUE);
        $stmt->bindParam(':designationUE', $designationUE);
        $stmt->bindParam(':CMI', $CMI);
        $stmt->bindParam(':TD', $TD);
        $stmt->bindParam(':TP', $TP);
        $stmt->bindParam(':semestre_idsemestre', $semestre_idsemestre);
        
        return $stmt->execute();
    }
    
    public function updateUE($idUE, $codeUE, $designationUE, $CMI, $TD, $TP, $semestre_idsemestre) {
        $query = "UPDATE ue 
                  SET codeUE = :codeUE, 
                      designationUE = :designationUE, 
                      CMI = :CMI, 
                      TD = :TD, 
                      TP = :TP, 
                      semestre_idsemestre = :semestre_idsemestre 
                  WHERE idUE = :idUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':codeUE', $codeUE);
        $stmt->bindParam(':designationUE', $designationUE);
        $stmt->bindParam(':CMI', $CMI);
        $stmt->bindParam(':TD', $TD);
        $stmt->bindParam(':TP', $TP);
        $stmt->bindParam(':semestre_idsemestre', $semestre_idsemestre);
        $stmt->bindParam(':idUE', $idUE);
        
        return $stmt->execute();
    }
    
    public function deleteUE($idUE) {
        // Vérifier s'il y a des ECUE liés à cette UE
        $query = "SELECT COUNT(*) FROM ecue WHERE UE_idUE = :idUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            // Supprimer d'abord les ECUE liés
            $query = "DELETE FROM ecue WHERE UE_idUE = :idUE";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idUE', $idUE);
            $stmt->execute();
        }
        
        // Ensuite supprimer l'UE
        $query = "DELETE FROM ue WHERE idUE = :idUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE);
        
        return $stmt->execute();
    }
    
    public function getECUEs($idUE) {
        $query = "SELECT e.*, u.codeUE, u.designationUE
                  FROM ecue e
                  JOIN ue u ON e.UE_idUE = u.idUE
                  WHERE e.UE_idUE = :idUE
                  ORDER BY e.designationECUE ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getECUEById($idECUE) {
        $query = "SELECT e.*, u.codeUE, u.designationUE
                  FROM ecue e
                  JOIN ue u ON e.UE_idUE = u.idUE
                  WHERE e.idECUE = :idECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idECUE', $idECUE);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createECUE($designationECUE, $CMI, $TD, $TP, $UE_idUE) {
        $query = "INSERT INTO ecue (designationECUE, CMI, TD, TP, UE_idUE) 
                  VALUES (:designationECUE, :CMI, :TD, :TP, :UE_idUE)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationECUE', $designationECUE);
        $stmt->bindParam(':CMI', $CMI);
        $stmt->bindParam(':TD', $TD);
        $stmt->bindParam(':TP', $TP);
        $stmt->bindParam(':UE_idUE', $UE_idUE);
        
        return $stmt->execute();
    }
    
    public function updateECUE($idECUE, $designationECUE, $CMI, $TD, $TP) {
        $query = "UPDATE ecue 
                  SET designationECUE = :designationECUE, 
                      CMI = :CMI, 
                      TD = :TD, 
                      TP = :TP 
                  WHERE idECUE = :idECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationECUE', $designationECUE);
        $stmt->bindParam(':CMI', $CMI);
        $stmt->bindParam(':TD', $TD);
        $stmt->bindParam(':TP', $TP);
        $stmt->bindParam(':idECUE', $idECUE);
        
        return $stmt->execute();
    }
    
    public function deleteECUE($idECUE) {
        $query = "DELETE FROM ecue WHERE idECUE = :idECUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idECUE', $idECUE);
        
        return $stmt->execute();
    }
}