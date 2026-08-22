<?php

class UniteRecherche
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer toutes les unités de recherche
    public function getResearchUnits($search = '')
    {
        $query = "SELECT ur.* FROM unite_recherche ur";
        
        if (!empty($search)) {
            $query .= " WHERE ur.designation_UR LIKE :search";
        }
        
        $query .= " ORDER BY ur.designation_UR ASC";
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les sections associées à une unité de recherche
    public function getSectionsByResearchUnit($idUniteRecherche)
    {
        $query = "SELECT s.*, urs.idur_section 
                  FROM section s
                  INNER JOIN unite_recherche_section urs ON s.idsection = urs.idsection
                  WHERE urs.idunite_recherche = :idUniteRecherche
                  ORDER BY s.designationSection ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUniteRecherche', $idUniteRecherche, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les spécialisations par unité de recherche et section
    public function getSpecialisationsByResearchUnitAndSection($idUniteRecherche, $idSection)
    {
        $query = "SELECT s.*, sec.idAnnee, a.designation as annee_designation 
          FROM specialisation s
          LEFT JOIN section sec ON s.idsection = sec.idsection
          JOIN annee_acad a ON sec.idAnnee = a.idannee_acad
          WHERE s.idUnite_recherche = :idUniteRecherche
          AND s.idsection = :idSection
          ORDER BY s.designation ASC";

        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUniteRecherche', $idUniteRecherche, PDO::PARAM_INT);
        $stmt->bindParam(':idSection', $idSection, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Créer une unité de recherche
    public function createResearchUnit($designation, $description, $idUser)
    {
        $query = "INSERT INTO unite_recherche (designation_UR, description, idUser, dateCreation) 
                  VALUES (:designation, :description, :idUser, NOW())";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'designation' => $designation,
            'description' => $description,
            'idUser' => $idUser
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }

    // Associer une unité de recherche à une section
    public function associateResearchUnitToSection($idUniteRecherche, $idSection)
    {
        // Vérifier si l'association existe déjà
        $checkQuery = "SELECT COUNT(*) FROM unite_recherche_section 
                       WHERE idunite_recherche = :idUniteRecherche 
                       AND idsection = :idSection";
        
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute([
            'idUniteRecherche' => $idUniteRecherche,
            'idSection' => $idSection
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return true; // L'association existe déjà
        }
        
        // Créer l'association
        $query = "INSERT INTO unite_recherche_section (idunite_recherche, idsection) 
                  VALUES (:idUniteRecherche, :idSection)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idUniteRecherche' => $idUniteRecherche,
            'idSection' => $idSection
        ]);
    }

    // Créer une spécialisation
    public function createSpecialisation($designation, $idUniteRecherche, $idSection)
    {
        $query = "INSERT INTO specialisation (designation, dateCreation, idUnite_recherche, idsection) 
                  VALUES (:designation, NOW(), :idUniteRecherche, :idSection)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'idUniteRecherche' => $idUniteRecherche,
            'idSection' => $idSection
        ]);
    }

    // Mettre à jour une unité de recherche
    public function updateResearchUnit($idUniteRecherche, $designation, $description)
    {
        $query = "UPDATE unite_recherche 
                  SET designation_UR = :designation, description = :description 
                  WHERE idunite_recherche = :idUniteRecherche";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idUniteRecherche' => $idUniteRecherche,
            'designation' => $designation,
            'description' => $description
        ]);
    }

    // Supprimer une unité de recherche
    public function deleteResearchUnit($idUniteRecherche)
    {
        // Supprimer d'abord les associations avec les sections
        $query1 = "DELETE FROM unite_recherche_section WHERE idunite_recherche = :idUniteRecherche";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute(['idUniteRecherche' => $idUniteRecherche]);
        
        // Supprimer les spécialisations associées
        $query2 = "DELETE FROM specialisation WHERE idUnite_recherche = :idUniteRecherche";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->execute(['idUniteRecherche' => $idUniteRecherche]);
        
        // Supprimer l'unité de recherche
        $query3 = "DELETE FROM unite_recherche WHERE idunite_recherche = :idUniteRecherche";
        $stmt3 = $this->db->prepare($query3);
        return $stmt3->execute(['idUniteRecherche' => $idUniteRecherche]);
    }

    // Mettre à jour une spécialisation
    public function updateSpecialisation($idSpecialisation, $designation)
    {
        $query = "UPDATE specialisation 
                  SET designation = :designation 
                  WHERE idSpecialisation = :idSpecialisation";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idSpecialisation' => $idSpecialisation,
            'designation' => $designation
        ]);
    }

    // Supprimer une spécialisation
    public function deleteSpecialisation($idSpecialisation)
    {
        // Supprimer d'abord les associations avec les enseignants
        $query1 = "DELETE FROM enseignant_specialisation WHERE idSpecialisation = :idSpecialisation";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute(['idSpecialisation' => $idSpecialisation]);
        
        // Supprimer la spécialisation
        $query2 = "DELETE FROM specialisation WHERE idSpecialisation = :idSpecialisation";
        $stmt2 = $this->db->prepare($query2);
        return $stmt2->execute(['idSpecialisation' => $idSpecialisation]);
    }

    // Affecter un enseignant à une spécialisation
    public function assignTeacherToSpecialisation($idAgent, $idSpecialisation, $idUser)
    {
        // Vérifier si l'affectation existe déjà
        $checkQuery = "SELECT COUNT(*) FROM enseignant_specialisation 
                    WHERE idAgent = :idAgent AND idSpecialisation = :idSpecialisation";
        
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute([
            'idAgent' => $idAgent,
            'idSpecialisation' => $idSpecialisation
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return 'exists'; // L'affectation existe déjà - valeur spécifique
        }
        
        // Créer l'affectation
        $query = "INSERT INTO enseignant_specialisation (idAgent, idSpecialisation, dateAffectation, idUser) 
                VALUES (:idAgent, :idSpecialisation, NOW(), :idUser)";
        
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'idAgent' => $idAgent,
            'idSpecialisation' => $idSpecialisation,
            'idUser' => $idUser
        ]);
        
        return $success ? 'success' : 'error'; // Valeurs distinctes pour succès ou échec
    }


    // Récupérer les spécialisations d'un enseignant
    public function getTeacherSpecialisations($idAgent)
    {
        $query = "SELECT s.*, ur.designation_UR, sec.designationSection, es.id as idAffectation
                  FROM enseignant_specialisation es
                  INNER JOIN specialisation s ON es.idSpecialisation = s.idSpecialisation
                  INNER JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
                  INNER JOIN section sec ON s.idsection = sec.idsection
                  WHERE es.idAgent = :idAgent
                  ORDER BY ur.designation_UR, s.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Supprimer l'affectation d'un enseignant à une spécialisation
    public function removeTeacherFromSpecialisation($idAffectation)
    {
        $query = "DELETE FROM enseignant_specialisation WHERE id = :idAffectation";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idAffectation' => $idAffectation]);
    }

    // Récupérer les enseignants par spécialisation
    public function getTeachersBySpecialisation($idSpecialisation)
    {
        $query = "SELECT a.*, es.dateAffectation, es.id as idAffectation
                  FROM agent a
                  INNER JOIN enseignant_specialisation es ON a.idAgent = es.idAgent
                  WHERE es.idSpecialisation = :idSpecialisation
                  ORDER BY a.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // À ajouter dans la classe UniteRecherche
    public function deleteResearchUnitSections($idUniteRecherche)
    {
        $query = "DELETE FROM unite_recherche_section WHERE idunite_recherche = :idUniteRecherche";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idUniteRecherche' => $idUniteRecherche]);
    }

    // Récupérer les enseignants pour l'exportation Excel
// Récupérer les enseignants pour l'exportation Excel
public function getTeachersForExport($idUniteRecherche = 'all', $idSection = 'all')
{
    $query = "SELECT a.*, g.designation as gradeDesignation, s.designation as serviceDesignation,
              ur.idunite_recherche, ur.designation_UR, 
              sec.idsection, sec.designationSection,
              sp.idSpecialisation, sp.designation, es.dateAffectation
              FROM enseignant_specialisation es
              INNER JOIN agent a ON es.idAgent = a.idAgent
              INNER JOIN specialisation sp ON es.idSpecialisation = sp.idSpecialisation
              INNER JOIN unite_recherche ur ON sp.idUnite_recherche = ur.idunite_recherche
              INNER JOIN section sec ON sp.idsection = sec.idsection
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              LEFT JOIN service s ON a.idService = s.idService
              WHERE 1=1";
    
    $params = [];
    
    // Filtrer par unité de recherche si spécifié
    if ($idUniteRecherche !== 'all') {
        $query .= " AND ur.idunite_recherche = :idUniteRecherche";
        $params['idUniteRecherche'] = $idUniteRecherche;
    }
    
    // Filtrer par section si spécifié
    if ($idSection !== 'all') {
        $query .= " AND sec.idsection = :idSection";
        $params['idSection'] = $idSection;
    }
    
    $query .= " ORDER BY ur.designation_UR, sec.designationSection, sp.designation, a.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



 


}
