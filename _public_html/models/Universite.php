<?php

class Universite {
    private $db;
    private $heuresParCredit;
    private const DIVISEUR_CREDITS_DEFAULT = 25;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
        // Initialiser heuresParCredit depuis la configuration de l'université
        $configQuery = $this->db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
        $config = $configQuery->fetch(PDO::FETCH_ASSOC);
        $this->heuresParCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : self::DIVISEUR_CREDITS_DEFAULT;
    }

    public function getAcademicYears($search = '') {
        $query = "SELECT * FROM annee_acad";
        if (!empty($search)) {
            $query .= " WHERE designation LIKE :search";
        }
        $query .= " ORDER BY designation DESC";

        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'année académique active
     * @return array|false Données de l'année académique active ou false si aucune
     */
    public function getActiveAcademicYear() {
        $query = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Active une année académique et désactive toutes les autres
     * @param int $idAnneeAcad ID de l'année académique à activer
     * @return bool Succès de l'opération
     */
    public function setActiveAcademicYear($idAnneeAcad) {
        try {
            $this->db->beginTransaction();
            
            // Désactiver toutes les années
            $deactivateQuery = "UPDATE annee_acad SET est_active = 0";
            $this->db->exec($deactivateQuery);
            
            // Activer l'année sélectionnée
            $activateQuery = "UPDATE annee_acad SET est_active = 1 WHERE idannee_acad = :idAnneeAcad";
            $stmt = $this->db->prepare($activateQuery);
            $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Désactive une année académique
     * @param int $idAnneeAcad ID de l'année académique à désactiver
     * @return bool Succès de l'opération
     */
    public function deactivateAcademicYear($idAnneeAcad) {
        $query = "UPDATE annee_acad SET est_active = 0 WHERE idannee_acad = :idAnneeAcad";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
 * Récupère toutes les années académiques
 * @return array Liste des années académiques
 */
public function getAllAcademicYears() {
    $conn = Connexion::getInstance()->getPDO();
    $query = "SELECT idannee_acad, designation, \"dateCreation\" 
              FROM annee_acad 
              ORDER BY \"dateCreation\" DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    public function createAcademicYear($designation) {
        $dateCreation = date('Y-m-d H:i:s');
        $query = "INSERT INTO annee_acad (designation, \"dateCreation\") VALUES (:designation, :dateCreation)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':dateCreation', $dateCreation);
        return $stmt->execute();
    }

    public function updateAcademicYear($id, $designation) {
        $query = "UPDATE annee_acad SET designation = :designation WHERE idannee_acad = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteAcademicYear($id) {
        $query = "DELETE FROM annee_acad WHERE idannee_acad = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getSections($search = '', $anneeAcadId = null) {
        $query = "SELECT section.*, annee_acad.designation AS anneeDesignation,
                  (SELECT CONCAT(rs.noms, ' - ', rs.fonction) 
                   FROM responsable_section rs 
                   WHERE rs.section_idsection = section.idsection 
                   AND rs.est_chef = 1 
                   AND rs.annee_acad_idannee_acad = section.idAnnee
                   LIMIT 1) AS chef_section
                  FROM section 
                  JOIN annee_acad ON section.idAnnee = annee_acad.idannee_acad
                  WHERE 1=1";
        
        // Filtre par année académique
        if (!empty($anneeAcadId)) {
            $query .= " AND section.idAnnee = :anneeAcadId";
        }
        
        // Filtre par recherche
        if (!empty($search)) {
            $query .= " AND (section.designationSection LIKE :search OR annee_acad.designation LIKE :search)";
        }
        
        $query .= " ORDER BY annee_acad.designation DESC, section.designationSection ASC";

        $stmt = $this->db->prepare($query);
        
        if (!empty($anneeAcadId)) {
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        }
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSection($designationSection, $idAnnee, $adresse = null, $telephone = null, $email = null, $boite_postale = null, $site_web = null) {
        $dateCreation = date('Y-m-d H:i:s');
        $query = "INSERT INTO section (\"designationSection\", \"dateCreation\", \"idAnnee\", adresse, telephone, email, boite_postale, site_web) 
                  VALUES (:designationSection, :dateCreation, :idAnnee, :adresse, :telephone, :email, :boite_postale, :site_web)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationSection', $designationSection);
        $stmt->bindParam(':dateCreation', $dateCreation);
        $stmt->bindParam(':idAnnee', $idAnnee);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':boite_postale', $boite_postale);
        $stmt->bindParam(':site_web', $site_web);
        return $stmt->execute();
    }

    public function updateSection($id, $designationSection, $idAnnee, $adresse = null, $telephone = null, $email = null, $boite_postale = null, $site_web = null) {
        $query = "UPDATE section SET \"designationSection\" = :designationSection, \"idAnnee\" = :idAnnee, 
                  adresse = :adresse, telephone = :telephone, email = :email, 
                  boite_postale = :boite_postale, site_web = :site_web 
                  WHERE idsection = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationSection', $designationSection);
        $stmt->bindParam(':idAnnee', $idAnnee);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':boite_postale', $boite_postale);
        $stmt->bindParam(':site_web', $site_web);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Récupère les détails complets d'une section
     */
    public function getSectionById($id) {
        $query = "SELECT s.*, aa.designation AS anneeDesignation,
                  (SELECT CONCAT(rs.noms, ' - ', rs.fonction) 
                   FROM responsable_section rs 
                   WHERE rs.section_idsection = s.idsection 
                   AND rs.est_chef = 1 
                   AND rs.annee_acad_idannee_acad = s.\"idAnnee\"
                   LIMIT 1) AS chef_section
                  FROM section s
                  JOIN annee_acad aa ON s.\"idAnnee\" = aa.idannee_acad
                  WHERE s.idsection = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteSection($id) {
        $query = "DELETE FROM section WHERE idsection = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getManagersBySection($sectionId) {
        $query = "SELECT rs.*, aa.designation AS anneeDesignation 
                  FROM responsable_section rs
                  JOIN annee_acad aa ON rs.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE rs.section_idsection = :sectionId ORDER BY aa.designation DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function createManager($noms, $fonction, $signature, $idUser, $sectionId, $anneeAcadId) {
        $query = "INSERT INTO responsable_section (noms, fonction, signature, \"idUser\", section_idsection, annee_acad_idannee_acad) 
                  VALUES (:noms, :fonction, :signature, :idUser, :sectionId, :anneeAcadId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':signature', $signature);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':sectionId', $sectionId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        return $stmt->execute();
    }

    public function updateManager($id, $noms, $fonction, $signature, $idUser, $anneeAcadId) {
        $query = "UPDATE responsable_section SET noms = :noms, fonction = :fonction, 
                  \"idUser\" = :idUser, annee_acad_idannee_acad = :anneeAcadId";
        
        // Only update the signature if a new one is provided
        if ($signature !== null) {
            $query .= ", signature = :signature";
        }
        
        $query .= " WHERE idresponsable_section = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        if ($signature !== null) {
            $stmt->bindParam(':signature', $signature);
        }
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteManager($id) {
        $query = "DELETE FROM responsable_section WHERE idresponsable_section = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    
    public function getDepartements($search = '') {
        $query = "SELECT d.*, s.\"designationSection\" AS sectionDesignation,a.designation as annee 
                  FROM departement d
                  JOIN section s ON d.section_idsection = s.idsection
                  JOIN annee_acad a ON a.idannee_acad=s.\"idAnnee\"";
        if (!empty($search)) {
            $query .= " WHERE d.designationDepartement LIKE :search OR s.\"designationSection\" LIKE :search";
        }
        $query .= " ORDER BY annee DESC";

        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDepartement($designationDepartement, $sectionId) {
        $dateCreation = date('Y-m-d H:i:s');
        $query = "INSERT INTO departement (designationDepartement, \"dateCreation\", section_idsection) 
                  VALUES (:designationDepartement, :dateCreation, :sectionId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationDepartement', $designationDepartement);
        $stmt->bindParam(':dateCreation', $dateCreation);
        $stmt->bindParam(':sectionId', $sectionId);
        return $stmt->execute();
    }

    public function updateDepartement($id, $designationDepartement, $sectionId) {
        $query = "UPDATE departement SET designationDepartement = :designationDepartement, section_idsection = :sectionId 
                  WHERE iddepartement = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationDepartement', $designationDepartement);
        $stmt->bindParam(':sectionId', $sectionId);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteDepartement($id) {
        $query = "DELETE FROM departement WHERE iddepartement = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Methods for managing department managers
    public function getManagersByDepartement($departementId) {
        $query = "SELECT rd.*, aa.designation AS anneeDesignation 
                  FROM responsable_departement rd
                  JOIN annee_acad aa ON rd.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE rd.departement_iddepartement = :departementId ORDER BY aa.designation DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departementId', $departementId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createManagerDepartement($noms, $fonction, $signature, $idUser, $departementId, $anneeAcadId) {
        $query = "INSERT INTO responsable_departement (noms, fonction, signature, \"idUser\", departement_iddepartement, annee_acad_idannee_acad) 
                  VALUES (:noms, :fonction, :signature, :idUser, :departementId, :anneeAcadId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':signature', $signature);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':departementId', $departementId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        return $stmt->execute();
    }

    public function updateManagerDepartement($id, $noms, $fonction, $signature, $idUser, $anneeAcadId) {
        $query = "UPDATE responsable_departement SET noms = :noms, fonction = :fonction, 
                  \"idUser\" = :idUser, annee_acad_idannee_acad = :anneeAcadId";
        
        // Only update the signature if a new one is provided
        if ($signature !== null) {
            $query .= ", signature = :signature";
        }
        
        $query .= " WHERE idresponsable_departement = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        if ($signature !== null) {
            $stmt->bindParam(':signature', $signature);
        }
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteManagerDepartement($id) {
        $query = "DELETE FROM responsable_departement WHERE idresponsable_departement = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Other existing methods...

    public function getPromotions($search = '', $anneeAcadId = null) {
        $query = "SELECT p.*, o.\"designationOrientation\" AS orientationDesignation, aa.designation AS anneeDesignation
                  FROM promotion p
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad";

        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(p.\"designationPromotion\" LIKE :search OR o.\"designationOrientation\" LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($anneeAcadId !== null) {
            $whereConditions[] = "p.annee_acad_idannee_acad = :anneeAcadId";
            $params[':anneeAcadId'] = $anneeAcadId;
        }

        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }

        $query .= " ORDER BY p.\"designationPromotion\" ASC";

        $stmt = $this->db->prepare($query);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode pour créer une promotion (mise à jour)
    public function createPromotion($designationPromotion, $cycle, $orientationId, $anneeAcadId, $estTerminale = 0) {
        $dateCreation = date('Y-m-d H:i:s');
    
        $query = "INSERT INTO promotion (\"designationPromotion\", cycle, \"dateCreation\", orientation_idorientation, annee_acad_idannee_acad, est_terminale)
                  VALUES (:designationPromotion, :cycle, :dateCreation, :orientationId, :anneeAcadId, :estTerminale)";
    
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationPromotion', $designationPromotion);
        $stmt->bindParam(':cycle', $cycle);
        $stmt->bindParam(':dateCreation', $dateCreation);
        $stmt->bindParam(':orientationId', $orientationId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':estTerminale', $estTerminale, PDO::PARAM_INT);
    
        return $stmt->execute();
    }
    

// Méthode pour mettre à jour une promotion (mise à jour)
public function updatePromotion($promotionId, $designationPromotion, $cycle, $orientationId, $anneeAcadId, $estTerminale = 0) {
    $query = "UPDATE promotion 
              SET \"designationPromotion\" = :designationPromotion, 
                  cycle = :cycle, 
                  orientation_idorientation = :orientationId, 
                  annee_acad_idannee_acad = :anneeAcadId,
                  est_terminale = :estTerminale
              WHERE idpromotion = :promotionId";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->bindParam(':designationPromotion', $designationPromotion);
    $stmt->bindParam(':cycle', $cycle);
    $stmt->bindParam(':orientationId', $orientationId);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
    $stmt->bindParam(':estTerminale', $estTerminale, PDO::PARAM_INT);

    return $stmt->execute();
}


    public function deletePromotion($id) {
        try {
            $this->db->beginTransaction();

            // Delete associated students
            $queryStudents = "DELETE FROM etudiant WHERE promotion_idpromotion = :id";
            $stmtStudents = $this->db->prepare($queryStudents);
            $stmtStudents->bindParam(':id', $id);
            $stmtStudents->execute();

            // Delete associated semesters (which contain courses)
            $querySemesters = "DELETE FROM semestre WHERE promotion_idpromotion = :id";
            $stmtSemesters = $this->db->prepare($querySemesters);
            $stmtSemesters->bindParam(':id', $id);
            $stmtSemesters->execute();

            // Delete associated promotion chiefs
            $queryChefs = "DELETE FROM chef_promotion WHERE promotion_idpromotion = :id";
            $stmtChefs = $this->db->prepare($queryChefs);
            $stmtChefs->bindParam(':id', $id);
            $stmtChefs->execute();

            // Delete the promotion
            $query = "DELETE FROM promotion WHERE idpromotion = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $result = $stmt->execute();

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getPromotionByDesignationAndYear($designationPromotion, $anneeAcadId) {
        $query = "SELECT * FROM promotion WHERE \"designationPromotion\" = :designationPromotion AND annee_acad_idannee_acad = :anneeAcadId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationPromotion', $designationPromotion);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
    public function getStudents($search = '', $limit = 100, $offset = 0, $anneeId = null, $orientationId = null, $promotionId = null, $includeInactive = false) {
        $query = "SELECT e.*, p.\"designationPromotion\", o.\"designationOrientation\",
                  a.designation as annee
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN annee_acad a ON a.idannee_acad = e.annee_acad_idannee_acad
                  WHERE 1=1";
        
        // Only filter by est_actif if explicitly requested
        if ($includeInactive === false) {
            $query .= " AND e.est_actif = 1";
        }

        if ($anneeId !== null && $anneeId !== '' && $anneeId !== 'all') {
            $query .= " AND e.annee_acad_idannee_acad = :anneeId";
        }

        if ($orientationId !== null && $orientationId !== '' && $orientationId !== 'all') {
            $query .= " AND p.orientation_idorientation = :orientationId";
        }

        if ($promotionId !== null && $promotionId !== '' && $promotionId !== 'all') {
            $query .= " AND e.promotion_idpromotion = :promotionId";
        }

        if (!empty($search)) {
            $query .= " AND (e.noms LIKE :search OR e.matricule LIKE :search)";
        }

        $query .= " ORDER BY e.matricule DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($query);

        if ($anneeId !== null && $anneeId !== '' && $anneeId !== 'all') {
            $stmt->bindValue(':anneeId', (int) $anneeId, PDO::PARAM_INT);
        }

        if ($orientationId !== null && $orientationId !== '' && $orientationId !== 'all') {
            $stmt->bindValue(':orientationId', (int) $orientationId, PDO::PARAM_INT);
        }

        if ($promotionId !== null && $promotionId !== '' && $promotionId !== 'all') {
            $stmt->bindValue(':promotionId', (int) $promotionId, PDO::PARAM_INT);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStudents2($search = '') {
        $query = "SELECT e.*, p.\"designationPromotion\", o.\"designationOrientation\",
                  a.designation as annee
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN annee_acad a ON a.idannee_acad=e.annee_acad_idannee_acad";
        if (!empty($search)) {
            $query .= " WHERE e.noms LIKE :search OR e.matricule LIKE :search";
        }
        $query .= " ORDER BY e.matricule DESC";
    
        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function createStudent($matricule, $noms, $lieuNaissance, $dateNaissance, $adressemail, $telephone, $sexe, $nationalite, $anneeAcadId, $promotionId, $idUser) {
        $dateEnregistrement = date('Y-m-d H:i:s');
        $defaultPwd = password_hash("12345678", PASSWORD_BCRYPT);
        $query = "INSERT INTO etudiant (matricule, noms, \"lieuNaissance\", \"dateNaissance\", adressemail, telephone, sexe, nationalite, pwd, \"dateEnregistrement\", annee_acad_idannee_acad, promotion_idpromotion, \"idUser\") 
                  VALUES (:matricule, :noms, :lieuNaissance, :dateNaissance, :adressemail, :telephone, :sexe, :nationalite, :pwd, :dateEnregistrement, :anneeAcadId, :promotionId, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':lieuNaissance', $lieuNaissance);
        $stmt->bindParam(':dateNaissance', $dateNaissance);
        $stmt->bindParam(':adressemail', $adressemail);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':sexe', $sexe);
        $stmt->bindParam(':nationalite', $nationalite);
        $stmt->bindParam(':pwd', $defaultPwd);
        $stmt->bindParam(':dateEnregistrement', $dateEnregistrement);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':promotionId', $promotionId);
        $stmt->bindParam(':idUser', $idUser);
        return $stmt->execute();
    }

    public function updateStudent($id, $matricule, $noms, $lieuNaissance, $dateNaissance, $adressemail, $telephone, $sexe, $nationalite, $anneeAcadId, $promotionId, $idUser) {
    $query = "UPDATE etudiant SET matricule = :matricule, noms = :noms, \"lieuNaissance\" = :lieuNaissance, \"dateNaissance\" = :dateNaissance,
    adressemail = :adressemail, telephone = :telephone, sexe = :sexe, nationalite = :nationalite, annee_acad_idannee_acad = :anneeAcadId,
    promotion_idpromotion = :promotionId, \"idUser\" = :idUser WHERE idetudiant = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':matricule', $matricule);
    $stmt->bindParam(':noms', $noms);
    $stmt->bindParam(':lieuNaissance', $lieuNaissance);
    $stmt->bindParam(':dateNaissance', $dateNaissance);
    $stmt->bindParam(':adressemail', $adressemail);
    $stmt->bindParam(':telephone', $telephone);
    $stmt->bindParam(':sexe', $sexe);
    $stmt->bindParam(':nationalite', $nationalite);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':id', $id);
    return $stmt->execute();
    }

    /**
     * Met à jour le profil d'un étudiant (utilisé par les étudiants pour modifier leurs propres informations)
     */
    public function updateStudentProfile($id, $noms, $lieuNaissance, $dateNaissance, $sexe, $nationalite, $adressemail, $telephone, $adresse, $personne_contact, $telephone_contact, $photo = null) {
        $query = "UPDATE etudiant SET noms = :noms, \"lieuNaissance\" = :lieuNaissance, \"dateNaissance\" = :dateNaissance,
                  sexe = :sexe, nationalite = :nationalite, adressemail = :adressemail, telephone = :telephone,
                  adresse = :adresse, personne_contact = :personne_contact, telephone_contact = :telephone_contact";

        // Ajouter la photo seulement si elle est fournie
        if ($photo !== null) {
            $query .= ", photo = :photo";
        }

        $query .= " WHERE idetudiant = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':lieuNaissance', $lieuNaissance);
        $stmt->bindParam(':dateNaissance', $dateNaissance);
        $stmt->bindParam(':sexe', $sexe);
        $stmt->bindParam(':nationalite', $nationalite);
        $stmt->bindParam(':adressemail', $adressemail);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':personne_contact', $personne_contact);
        $stmt->bindParam(':telephone_contact', $telephone_contact);
        $stmt->bindParam(':id', $id);

        // Lier la photo seulement si elle est fournie
        if ($photo !== null) {
            $stmt->bindParam(':photo', $photo);
        }

        return $stmt->execute();
    }

    public function deleteStudent($id) {
        $query = "DELETE FROM etudiant WHERE idetudiant = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Other student management methods...

    // User-Student Association Methods
    public function getUserStudents($userId) {
        $query = "SELECT ue.*, e.noms, e.matricule FROM user_etudiant ue
                  JOIN etudiant e ON ue.matriculeEtudiant = e.matricule
                  WHERE ue.\"idUser\" = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function associateUserToStudent($matriculeEtudiant, $userId) {
        $query = "INSERT INTO user_etudiant (matriculeEtudiant, \"idUser\") VALUES (:matriculeEtudiant, :userId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matriculeEtudiant', $matriculeEtudiant);
        $stmt->bindParam(':userId', $userId);
        return $stmt->execute();
    }

    public function dissociateUserFromStudent($id) {
        $query = "DELETE FROM user_etudiant WHERE iduser_etudiant = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getStudentByMatriculeAndYear($matricule, $anneeAcadId) {
        $query = "SELECT e.*, 
                         p.\"designationPromotion\", 
                         a.designation AS annee
                  FROM etudiant e
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                  WHERE e.matricule = :matricule AND e.annee_acad_idannee_acad = :anneeAcadId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStudentsByPromotion($promotionId) {
        $query = "SELECT e.matricule, e.noms, e.\"lieuNaissance\", e.\"dateNaissance\", e.adressemail, e.telephone, e.sexe, e.nationalite, 
                         p.\"designationPromotion\", a.designation AS annee
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                  WHERE e.promotion_idpromotion = :promotionId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeachers($search = '') {
        $query = "SELECT e.idenseignant, e.\"nomEnseignant\", e.grade, e.\"idAgent\", e.\"idDepartement\", 
                         a.noms AS agentName, a.\"lieuNaissance\", a.\"dateNaissance\", a.sexe, a.telephone, a.email,
                         d.designationDepartement AS departmentName
                  FROM enseignant e
                  JOIN agent a ON e.\"idAgent\" = a.\"idAgent\"
                  JOIN departement d ON e.\"idDepartement\" = d.iddepartement";
        if (!empty($search)) {
            $query .= " WHERE e.\"nomEnseignant\" LIKE :search OR a.noms LIKE :search OR d.designationDepartement LIKE :search";
        }
        $query .= " ORDER BY e.\"nomEnseignant\" ASC";
    
        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllDepartments() {
        $query = "SELECT iddepartement, designationDepartement FROM departement ORDER BY designationDepartement ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllAgents() {
        $query = "SELECT \"idAgent\", noms FROM agent ORDER BY noms ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Créer un nouvel enseignant
 */
public function createTeacher($nomEnseignant, $grade, $idAgent, $idDepartement, $userId) {
    $query = "INSERT INTO enseignant (\"nomEnseignant\", grade, \"idAgent\", \"idDepartement\", \"idUser\", \"dateEnregistrement\") 
              VALUES (:nomEnseignant, :grade, :idAgent, :idDepartement, :userId, NOW())";
    
    $stmt = $this->db->prepare($query);
    $result = $stmt->execute([
        'nomEnseignant' => $nomEnseignant,
        'grade' => $grade,
        'idAgent' => $idAgent,
        'idDepartement' => $idDepartement,
        'userId' => $userId
    ]);
    
    if ($result) {
        return $this->db->lastInsertId(); // Retourner l'ID de l'enseignant créé
    }
    
    return false;
}


    public function updateTeacher($id, $grade,$idDepartement) {
        $query = "UPDATE enseignant SET grade = :grade, 
                  \"idDepartement\" = :idDepartement WHERE idenseignant = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':grade', $grade);
        $stmt->bindParam(':idDepartement', $idDepartement);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteTeacher($id) {
        $query = "DELETE FROM enseignant WHERE idenseignant = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function checkDuplicateTeacher($nomEnseignant, $idAgent) {
        $query = "SELECT COUNT(*) FROM enseignant WHERE \"nomEnseignant\" = :nomEnseignant AND \"idAgent\" = :idAgent";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nomEnseignant', $nomEnseignant, PDO::PARAM_STR);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        return $count > 0;
    }

    // Existing methods...

    public function getResearchUnits($search = '') {
        $query = "SELECT ur.*, d.designationDepartement AS departmentDesignation 
                  FROM unite_recherche ur
                  JOIN departement d ON ur.departement_iddepartement = d.iddepartement";
        if (!empty($search)) {
            $query .= " WHERE ur.\"designation_UR\" LIKE :search OR d.designationDepartement LIKE :search";
        }
        $query .= " ORDER BY ur.\"designation_UR\" ASC";
    
        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeachersWithResearchUnits($search = '') {
        $query = "SELECT e.idenseignant, e.\"nomEnseignant\", e.grade, d.designationDepartement AS departmentName, 
                         ur.\"designation_UR\" AS researchUnitName
                  FROM enseignant e
                  JOIN departement d ON e.\"idDepartement\" = d.iddepartement
                  LEFT JOIN enseignant_uniterecherche eur ON e.idenseignant = eur.\"idUser\"
                  LEFT JOIN unite_recherche ur ON eur.\"idUnite_recherche\" = ur.idunite_recherche";
        if (!empty($search)) {
            $query .= " WHERE e.\"nomEnseignant\" LIKE :search OR ur.\"designation_UR\" LIKE :search";
        }
        $query .= " ORDER BY e.\"nomEnseignant\" ASC";

        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeachersByResearchUnit($researchUnitId) {
        $query = "SELECT e.idenseignant, e.\"nomEnseignant\", e.grade, d.designationDepartement AS departmentName
                  FROM enseignant e
                  JOIN enseignant_uniterecherche eur ON e.idenseignant = eur.\"idUser\"
                  JOIN departement d ON e.\"idDepartement\" = d.iddepartement
                  WHERE eur.\"idUnite_recherche\" = :researchUnitId
                  ORDER BY e.\"nomEnseignant\" ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':researchUnitId', $researchUnitId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to create a specialization
    public function createSpecialisation($designation, $idUniteRecherche) {
        $dateCreation = date('Y-m-d H:i:s');
        $query = "INSERT INTO specialisation (designation, \"idUnite_recherche\", \"dateCreation\") 
                  VALUES (:designation, :idUniteRecherche, :dateCreation)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':idUniteRecherche', $idUniteRecherche);
        $stmt->bindParam(':dateCreation', $dateCreation);
        return $stmt->execute();
    }

    // Method to update a specialization
    public function updateSpecialisation($id, $designation, $idUniteRecherche) {
        $query = "UPDATE specialisation SET designation = :designation, \"idUnite_recherche\" = :idUniteRecherche 
                  WHERE \"idSpecialisation\" = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation);
        $stmt->bindParam(':idUniteRecherche', $idUniteRecherche);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Method to delete a specialization
    public function deleteSpecialisation($id) {
        $query = "DELETE FROM specialisation WHERE \"idSpecialisation\" = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Method to get specializations by research unit
    public function getSpecialisationsByResearchUnit($researchUnitId) {
        $query = "SELECT * FROM specialisation WHERE \"idUnite_recherche\" = :researchUnitId ORDER BY designation ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':researchUnitId', $researchUnitId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    


    // Method to assign a teacher to a specialization
    public function assignTeacherToSpecialisation($idEnseignant, $idSpecialisation) {
        $query = "INSERT INTO enseignant_uniterecherche (\"idUser\", \"idSpecialisation\") 
                  VALUES (:idEnseignant, :idSpecialisation)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEnseignant', $idEnseignant);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation);
        return $stmt->execute();
    }

    // Method to remove a teacher from a specialization
    public function removeTeacherFromSpecialisation($idEnseignant, $idSpecialisation) {
        $query = "DELETE FROM enseignant_uniterecherche WHERE \"idUser\" = :idEnseignant AND \"idSpecialisation\" = :idSpecialisation";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEnseignant', $idEnseignant);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation);
        return $stmt->execute();
    }

    // Method to get teachers by specialization
    public function getTeachersBySpecialisation($specialisationId) {
        $query = "SELECT e.idenseignant, e.\"nomEnseignant\", e.grade, d.designationDepartement AS departmentName
                  FROM enseignant e
                  JOIN enseignant_uniterecherche eur ON e.idenseignant = eur.\"idUser\"
                  JOIN departement d ON e.\"idDepartement\" = d.iddepartement
                  WHERE eur.\"idSpecialisation\" = :specialisationId
                  ORDER BY e.\"nomEnseignant\" ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':specialisationId', $specialisationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Other existing methods.

    public function createResearchUnit($designationUR, $description, $idDepartement) {
        $dateCreation = date('Y-m-d H:i:s');
        $query = "INSERT INTO unite_recherche (\"designation_UR\", description, \"dateCreation\", departement_iddepartement) 
                  VALUES (:designationUR, :description, :dateCreation, :idDepartement)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationUR', $designationUR);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':dateCreation', $dateCreation);
        $stmt->bindParam(':idDepartement', $idDepartement);
        return $stmt->execute();
    }

    public function updateResearchUnit($id, $designationUR, $description, $idDepartement) {
        $query = "UPDATE unite_recherche SET \"designation_UR\" = :designationUR, description = :description, 
                  departement_iddepartement = :idDepartement WHERE idunite_recherche = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationUR', $designationUR);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':idDepartement', $idDepartement);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function deleteResearchUnit($id) {
        $query = "DELETE FROM unite_recherche WHERE idunite_recherche = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Méthode pour récupérer tous les sujets de recherche
    public function getSujets($search = '') {
        $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                         CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                         CONCAT(d.\"nomEnseignant\", ' (', d.grade, ')') as directeur,
                         CONCAT(enc.\"nomEnseignant\", ' (', enc.grade, ')') as encadreur
                  FROM sujets s
                  LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                  LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN enseignant d ON s.\"idDirecteur\" = d.idenseignant
                  LEFT JOIN enseignant enc ON s.\"idEncadreur\" = enc.idenseignant";
        
        if (!empty($search)) {
            $query .= " WHERE s.intitule LIKE :search 
                       OR spec.designation LIKE :search 
                       OR e.noms LIKE :search
                       OR d.\"nomEnseignant\" LIKE :search
                       OR enc.\"nomEnseignant\" LIKE :search";
        }
        
        $query .= " ORDER BY s.idsujets DESC";

        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSujetsNonValides($search = '') {
        $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                         CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                         CONCAT(d.\"nomEnseignant\", ' (', d.grade, ')') as directeur,
                         CONCAT(enc.\"nomEnseignant\", ' (', enc.grade, ')') as encadreur
                  FROM sujets s
                  LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                  LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN enseignant d ON s.\"idDirecteur\" = d.idenseignant
                  LEFT JOIN enseignant enc ON s.\"idEncadreur\" = enc.idenseignant
                  WHERE (s.\"idDirecteur\" IS NULL OR s.statut_validation = 'En attente' OR s.statut_validation = 'Rejeté')
                  AND s.etudiant_idetudiant IS NOT NULL";
        
        if (!empty($search)) {
            $query .= " AND (s.intitule LIKE :search 
                       OR spec.designation LIKE :search 
                       OR e.noms LIKE :search
                       OR d.\"nomEnseignant\" LIKE :search
                       OR enc.\"nomEnseignant\" LIKE :search)";
        }
        
        $query .= " ORDER BY s.idsujets DESC";
    
        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSujetsByStatus($status) {
        $query = "SELECT COUNT(*) as total 
                  FROM sujets 
                  WHERE statut_validation = :status AND etudiant_idetudiant IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Méthode pour créer un nouveau sujet
    public function createSujet($intitule, $cycle, $idSpecialisation, $anneeAcadId, $idUser, $etatSujet, $etudiantId, $directeurId, $encadreurId) {
        try {
            $query = "INSERT INTO sujets (intitule, cycle, \"idSpecialisation\", annee_acad_idannee_acad, 
                                        \"idUser\", \"etatSujet\", etudiant_idetudiant, \"idDirecteur\", \"idEncadreur\")
                      VALUES (:intitule, :cycle, :idSpecialisation, :anneeAcadId, 
                             :idUser, :etatSujet, :etudiantId, :directeurId, :encadreurId)";
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intitule', $intitule);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->bindParam(':idSpecialisation', $idSpecialisation);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId);
            $stmt->bindParam(':idUser', $idUser);
            $stmt->bindParam(':etatSujet', $etatSujet);
            $stmt->bindParam(':etudiantId', $etudiantId);
            $stmt->bindParam(':directeurId', $directeurId);
            $stmt->bindParam(':encadreurId', $encadreurId);
    
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function createSujet2($intitule, $cycle, $idSpecialisation, $anneeAcadId, $idUser) {
        try {
            $query = "INSERT INTO sujets (intitule, cycle, \"idSpecialisation\", annee_acad_idannee_acad, 
                                        \"idUser\")
                      VALUES (:intitule, :cycle, :idSpecialisation, :anneeAcadId, 
                             :idUser)";
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intitule', $intitule);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->bindParam(':idSpecialisation', $idSpecialisation);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId);
            $stmt->bindParam(':idUser', $idUser);
    
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // Méthode pour mettre à jour un sujet
    public function updateSujet($idSujet, $intitule, $cycle, $idSpecialisation, $anneeAcadId, $etatSujet, $etudiantId, $directeurId, $encadreurId) {
        try {
            $query = "UPDATE sujets 
                      SET intitule = :intitule,
                          cycle = :cycle,
                          \"idSpecialisation\" = :idSpecialisation,
                          annee_acad_idannee_acad = :anneeAcadId,
                          \"etatSujet\" = :etatSujet,
                          etudiant_idetudiant = :etudiantId,
                          \"idDirecteur\" = :directeurId,
                          \"idEncadreur\" = :encadreurId
                      WHERE idsujets = :idSujet";
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intitule', $intitule);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->bindParam(':idSpecialisation', $idSpecialisation);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId);
            $stmt->bindParam(':etatSujet', $etatSujet);
            $stmt->bindParam(':etudiantId', $etudiantId);
            $stmt->bindParam(':directeurId', $directeurId);
            $stmt->bindParam(':encadreurId', $encadreurId);
            $stmt->bindParam(':idSujet', $idSujet);
    
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }


    public function updateSujet2($idSujet, $intitule, $idSpecialisation) {
        try {
            $query = "UPDATE sujets 
                      SET intitule = :intitule,
                          \"idSpecialisation\" = :idSpecialisation
                      WHERE idsujets = :idSujet";
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intitule', $intitule);
            $stmt->bindParam(':idSpecialisation', $idSpecialisation);
            $stmt->bindParam(':idSujet', $idSujet);
    
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // Méthode pour supprimer un sujet
    public function deleteSujet($idSujet) {
        $query = "DELETE FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet);
        return $stmt->execute();
    }

    // Méthode pour valider un sujet par le directeur
    public function validerSujet($idSujet, $etatSujet) {
        $query = "UPDATE sujets 
                 SET \"etatSujet\" = :etatSujet
                 WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etatSujet', $etatSujet);
        $stmt->bindParam(':idSujet', $idSujet);
        return $stmt->execute();
    }

    // Méthode pour assigner un encadreur à un sujet
    public function assignerEncadreur($idSujet, $idEncadreur) {
        $query = "UPDATE sujets 
                 SET \"idEncadreur\" = :idEncadreur
                 WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEncadreur', $idEncadreur);
        $stmt->bindParam(':idSujet', $idSujet);
        return $stmt->execute();
    }

    // Méthode pour qu'un étudiant choisisse un sujet
    public function choisirSujet($idSujet, $idEtudiant) {
        $query = "UPDATE sujets 
                 SET etudiant_idetudiant = :idEtudiant
                 WHERE idsujets = :idSujet 
                 AND etudiant_idetudiant IS NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant);
        $stmt->bindParam(':idSujet', $idSujet);
        return $stmt->execute();
    }

    // Méthode pour obtenir les sujets disponibles pour un cycle
    public function getSujetsDisponibles($cycle) {
        $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee
                 FROM sujets s
                 JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                 JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                 WHERE s.cycle = :cycle 
                 AND s.etudiant_idetudiant IS NULL
                 AND s.\"etatSujet\" = 'Validé'
                 ORDER BY aa.designation DESC, s.intitule ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cycle', $cycle);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode pour vérifier si un sujet est déjà pris
    public function isSujetPris($idSujet) {
        $query = "SELECT etudiant_idetudiant FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['etudiant_idetudiant'] !== null;
    }
/**
 * Récupère toutes les spécialisations avec les informations de leur unité de recherche
 * @param string $search Terme de recherche optionnel
 * @return array Liste des spécialisations
 */
public function getAllSpecialisations($search = '') {
    $query = "SELECT s.*, ur.\"designation_UR\" as unite_recherche 
              FROM specialisation s
              JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche";
    
    if (!empty($search)) {
        $query .= " WHERE s.designation LIKE :search OR ur.\"designation_UR\" LIKE :search";
    }
    
    $query .= " ORDER BY s.designation ASC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère une spécialisation par son ID avec les informations de son unité de recherche
 * @param int $id ID de la spécialisation
 * @return array|false Données de la spécialisation ou false si non trouvée
 */
public function getSpecialisationById($id) {
    $query = "SELECT s.*, ur.\"designation_UR\" as unite_recherche, ur.description as ur_description 
              FROM specialisation s
              JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              WHERE s.\"idSpecialisation\" = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les unités de recherche
 * @param string $search Terme de recherche optionnel
 * @return array Liste des unités de recherche
 */
public function getAllUniteRecherche($search = '') {
    $query = "SELECT * FROM unite_recherche";
    
    if (!empty($search)) {
        $query .= " WHERE \"designation_UR\" LIKE :search OR description LIKE :search";
    }
    
    $query .= " ORDER BY \"designation_UR\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère les sections associées à une unité de recherche
 * @param int $idUniteRecherche ID de l'unité de recherche
 * @return array Liste des sections
 */
public function getSectionsByUniteRecherche($idUniteRecherche) {
    $query = "SELECT s.* 
              FROM section s
              JOIN unite_recherche_section urs ON s.idsection = urs.idsection
              WHERE urs.idunite_recherche = :idUniteRecherche
              ORDER BY s.\"designationSection\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idUniteRecherche', $idUniteRecherche, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Récupère les spécialisations par unité de recherche
 * @param int $uniteRechercheId ID de l'unité de recherche
 * @return array Liste des spécialisations
 */
public function getSpecialisationsByUniteRecherche($uniteRechercheId) {
    $query = "SELECT s.*, ur.\"designation_UR\" as unite_recherche
              FROM specialisation s
              LEFT JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              WHERE s.\"idUnite_recherche\" = :uniteRechercheId
              ORDER BY s.designation ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':uniteRechercheId', $uniteRechercheId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les spécialisations par département
 * @param int $departementId ID du département
 * @return array Liste des spécialisations
 */
public function getSpecialisationsByOrientation($orientationId)
{
    $query = "SELECT s.*, ur.\"designation_UR\" as unite_recherche
              FROM specialisation s
              INNER JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              INNER JOIN section sec ON s.idsection = sec.idsection
              INNER JOIN orientation o ON o.section_idsection = sec.idsection
              WHERE o.idorientation = :orientationId";
              
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSpecialisations()
{
    $query = "SELECT s.*, ur.\"designation_UR\" as unite_recherche
              FROM specialisation s 
              INNER JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              ORDER BY s.designation ASC";
              
    $stmt = $this->db->query($query);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Vérifie si une spécialisation existe déjà
 * @param string $designation Désignation de la spécialisation
 * @param int $uniteRechercheId ID de l'unité de recherche
 * @return bool True si la spécialisation existe, false sinon
 */
public function specialisationExists($designation, $uniteRechercheId) {
    $query = "SELECT COUNT(*) FROM specialisation 
              WHERE designation = :designation 
              AND \"idUnite_recherche\" = :uniteRechercheId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->bindParam(':uniteRechercheId', $uniteRechercheId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Récupère les sujets pour une année académique spécifique
 * @param int $anneeId ID de l'année académique
 * @return array Liste des sujets
 */
public function getSujetsByAnnee($anneeId) {
    $query = "SELECT s.*, spec.designation as specialisation, 
                     CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                     CONCAT(d.\"nomEnseignant\", ' (', d.grade, ')') as directeur,
                     CONCAT(enc.\"nomEnseignant\", ' (', enc.grade, ')') as encadreur
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN enseignant d ON s.\"idDirecteur\" = d.idenseignant
              LEFT JOIN enseignant enc ON s.\"idEncadreur\" = enc.idenseignant
              WHERE s.annee_acad_idannee_acad = :anneeId
              ORDER BY s.intitule ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations d'une année académique
 * @param int $id ID de l'année académique
 * @return array|false Informations de l'année académique ou false si non trouvée
 */
public function getAcademicYearById($id) {
    $query = "SELECT * FROM annee_acad WHERE idannee_acad = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les spécialisations auxquelles un enseignant est affecté
 * @param int $userId ID de l'utilisateur connecté
 * @return array Liste des spécialisations
 */
public function getSpecialisationsForEnseignant($idEnseignant) {
    $query = "SELECT DISTINCT s.\"idSpecialisation\", s.designation, o.\"designationOrientation\" as departement
              FROM specialisation s
              JOIN enseignant_uniterecherche eur ON s.\"idSpecialisation\" = eur.\"idSpecialisation\"
              JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
              JOIN section sec ON urs.idsection = sec.idsection
              JOIN orientation o ON sec.idsection = o.section_idsection
              JOIN enseignant e ON eur.\"idUser\" = e.idEnseignant
              WHERE e.idenseignant = :idEnseignant
              ORDER BY s.designation ASC";
   
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idEnseignant', $idEnseignant, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'ID de l'enseignant à partir de l'ID utilisateur
 * @param int $userId ID de l'utilisateur
 * @return int|false ID de l'enseignant ou false si non trouvé
 */
public function getEnseignantIdByUserId($userId) {
    $query = "SELECT e.idenseignant 
              FROM enseignant e
              JOIN t_users u ON e.\"idAgent\" = u.\"idAgent\"
              WHERE u.\"idUser\" = :userId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['idenseignant'] : false;
}

/**
 * Vérifie si un utilisateur est un enseignant
 */
public function isEnseignant($userId) {
    $query = "SELECT e.* 
              FROM enseignant e
              JOIN t_users u ON e.\"idAgent\" = u.\"idAgent\"
              WHERE u.\"idUser\" = :userId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

/**
 * Récupère les informations de l'enseignant
 */


/**
 * Récupère les sujets créés par un enseignant spécifique
 */
public function getSujetsByEnseignant($userId, $search = '') {
    $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                     ur.\"designation_UR\" as uniteRecherche
                     CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                     CONCAT(d.\"nomEnseignant\", ' (', d.grade, ')') as directeur,
                     CONCAT(enc.\"nomEnseignant\", ' (', enc.grade, ')') as encadreur
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN unite_recherche ur ON spec.\"idUnite_recherche\"=ur.idunite_recherche
              LEFT JOIN 
              LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN enseignant d ON s.\"idDirecteur\" = d.idenseignant
              LEFT JOIN enseignant enc ON s.\"idEncadreur\" = enc.idenseignant
              WHERE s.\"idUser\" = :userId";
    
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                   OR spec.designation LIKE :search)";
    }
    
    $query .= " ORDER BY s.idsujets DESC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets où l'enseignant est directeur ou encadreur
 */
public function getSujetsByDirecteurOrEncadreur($idEnseignant, $search = '') {
    $query = "SELECT s.*, 
                     spec.designation as specialisation, 
                     aa.designation as annee,
                     CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                     p.\"designationPromotion\" as promotion,
                     s.statut_validation
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE (s.\"idDirecteur\" = :idEnseignant OR s.\"idEncadreur\" = :idEnseignant)
              AND s.etudiant_idetudiant IS NOT NULL AND s.statut_validation='Validé'";
    
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                   OR spec.designation LIKE :search
                   OR e.noms LIKE :search
                   OR p.\"designationPromotion\" LIKE :search)";
    }
    
    $query .= " ORDER BY s.idsujets DESC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idEnseignant', $idEnseignant, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateTacheDirecteur($tacheId, $commentaire, $fichier, $validation) {
    $query = "UPDATE taches 
              SET observationDirecteur = :commentaire,
                  fichierObsDirecteur = COALESCE(:fichier, fichierObsDirecteur),
                  validation = :validation
              WHERE idtaches = :tacheId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':fichier', $fichier);
    $stmt->bindParam(':validation', $validation);
    $stmt->bindParam(':tacheId', $tacheId);
    
    return $stmt->execute();
}

public function updateTacheEncadreur($tacheId, $commentaire, $fichier, $validation) {
    $query = "UPDATE taches 
              SET observationEncadreur = :commentaire,
                  fichierObsEncadreur = COALESCE(:fichier, fichierObsEncadreur),
                  validation = :validation
              WHERE idtaches = :tacheId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':fichier', $fichier);
    $stmt->bindParam(':validation', $validation);
    $stmt->bindParam(':tacheId', $tacheId);
    
    return $stmt->execute();
}

public function getTacheDetails($tacheId) {
    $query = "SELECT t.*, s.\"idDirecteur\", s.\"idEncadreur\" 
              FROM taches t
              JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE t.idtaches = :tacheId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':tacheId', $tacheId);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getSujetsByEnseignantAndYear($enseignantId, $anneeAcadId) {
    $query = "SELECT s.*, 
                     spec.designation as specialisation, 
                     CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                     p.\"designationPromotion\" as promotion
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE (s.\"idDirecteur\" = :enseignantId OR s.\"idEncadreur\" = :enseignantId)
              AND s.annee_acad_idannee_acad = :anneeAcadId
              AND s.etudiant_idetudiant IS NOT NULL
              AND s.statut_validation='Validé'
              ORDER BY p.\"designationPromotion\" ASC, s.intitule ASC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':enseignantId', $enseignantId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les sujets d'un étudiant avec les détails
 * @param int $etudiantId ID de l'étudiant
 * @return array Liste des sujets
 */
public function getSujetsByEtudiant($etudiantId) {
    $query = "SELECT s.*, 
                     spec.designation as specialisation, 
                     aa.designation as annee,
                     CONCAT(d.noms, ' (', gDir.designation, ')') as directeur,
                     CONCAT(enc.noms, ' (', gEnc.designation, ')') as encadreur,
                     d.\"idAgent\" as \"idDirecteur\",
                     enc.\"idAgent\" as \"idEncadreur\"
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
              LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
              LEFT JOIN grade gDir ON d.grade_id=gDir.idgrade
              LEFT JOIN grade gEnc ON enc.grade_id=gEnc.idgrade
              WHERE s.etudiant_idetudiant = :etudiantId
              ORDER BY aa.designation DESC, s.intitule ASC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les étudiants avec leurs sujets
 * @param string $search Terme de recherche optionnel
 * @return array Liste des étudiants avec leurs sujets
 */
public function getAllEtudiantsWithSujets($search = '') {
    $query = "SELECT DISTINCT e.*, 
                p.\"designationPromotion\" as promotion,
                d.designationDepartement as departement,
                aa.designation as annee_academique
             FROM etudiant e
             JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
             JOIN departement d ON p.departement_iddepartement = d.iddepartement
             JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
             JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
             WHERE s.etudiant_idetudiant IS NOT NULL";

    if (!empty($search)) {
        $query .= " AND (e.noms LIKE :search 
                    OR e.matricule LIKE :search 
                    OR p.\"designationPromotion\" LIKE :search)";
    }

    $query .= " ORDER BY e.noms ASC";

    $stmt = $this->db->prepare($query);

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt->bindParam(':search', $searchTerm);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getCurrentAcademicYear() {
    $query = "SELECT * FROM annee_acad 
              WHERE est_active=1 LIMIT 1";
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getEnseignantsByUniteRecherche() {
    $query = "SELECT ur.*, o.\"designationOrientation\",
              COUNT(DISTINCT es.\"idAgent\") as nombre_enseignants
              FROM unite_recherche ur
              JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
              JOIN section s ON urs.idsection = s.idsection
              JOIN orientation o ON o.section_idsection = s.idsection
              LEFT JOIN specialisation spec ON spec.\"idUnite_recherche\" = ur.idunite_recherche
              LEFT JOIN enseignant_specialisation es ON spec.\"idSpecialisation\" = es.\"idSpecialisation\"
              GROUP BY ur.idunite_recherche, ur.\"designation_UR\", o.\"designationOrientation\"
              ORDER BY o.\"designationOrientation\", ur.\"designation_UR\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEtudiantsByProfesseurAndSection() {
    $currentYear = $this->getCurrentAcademicYear();
    $currentYearId = $currentYear['idannee_acad'];

    $query = "
        SELECT
            COALESCE(s.\"designationSection\", 'section non définie') AS \"designationSection\",
            COALESCE(o.\"designationOrientation\", 'orientation non définie') AS \"designationOrientation\",
            a.noms AS \"nomEnseignant\",
            a.\"idAgent\",
            g.designation AS grade,
            COUNT(DISTINCT CASE WHEN suj.statut_validation = 'Validé' AND suj.etudiant_idetudiant IS NOT NULL THEN suj.etudiant_idetudiant END) as sujets_valides,
            COUNT(DISTINCT CASE WHEN suj.statut_validation = 'En attente' AND suj.etudiant_idetudiant IS NOT NULL THEN suj.etudiant_idetudiant END) as sujets_en_attente,
            COUNT(DISTINCT CASE WHEN suj.etudiant_idetudiant IS NOT NULL THEN suj.etudiant_idetudiant END) as total_etudiants
        FROM agent a
        JOIN grade g ON a.grade_id = g.idgrade
        LEFT JOIN agent_section ags ON a.\"idAgent\" = ags.\"idAgent\" AND ags.\"estPrincipal\" = 1
        LEFT JOIN section s ON ags.idsection = s.idsection
        LEFT JOIN orientation o ON s.idsection = o.section_idsection
        LEFT JOIN sujets suj ON (suj.\"idDirecteur\" = a.\"idAgent\" OR suj.\"idEncadreur\" = a.\"idAgent\")
                             AND suj.annee_acad_idannee_acad = :currentYearId
        WHERE a.type_agent = 'Enseignant'
        GROUP BY a.\"idAgent\",
                 COALESCE(s.\"designationSection\", 'section non définie'),
                 COALESCE(o.\"designationOrientation\", 'orientation non définie'),
                 a.noms, g.designation
        HAVING total_etudiants > 0
        ORDER BY \"designationSection\", \"nomEnseignant\"";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':currentYearId', $currentYearId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}




public function getEvolutionBySection($sectionId) {
    try {
        // Validation du paramètre
        $sectionId = intval($sectionId);
        if ($sectionId <= 0) {
            throw new Exception("ID de section invalide");
        }
        
        $query = "SELECT aa.designation as annee,
                  COUNT(DISTINCT e.idetudiant) as total_etudiants,
                  COUNT(DISTINCT CASE WHEN s.statut_validation = 'Validé' THEN e.idetudiant END) as etudiants_valides,
                  ROUND((COUNT(DISTINCT CASE WHEN s.statut_validation = 'Validé' THEN e.idetudiant END) / 
                        NULLIF(COUNT(DISTINCT e.idetudiant), 0)) * 100, 2) as pourcentage_reussite
                  FROM section sec
                  JOIN orientation o ON o.section_idsection = sec.idsection
                  JOIN promotion p ON p.orientation_idorientation = o.idorientation
                  JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN sujets s ON s.etudiant_idetudiant = e.idetudiant
                  WHERE sec.idsection = :sectionId
                  GROUP BY aa.idannee_acad, aa.designation
                  ORDER BY aa.designation ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucun résultat n'est trouvé, retourner un tableau vide
        if (empty($result)) {
            return [];
        }
        
        // Enrichir les données avec des informations supplémentaires
        foreach ($result as &$row) {
            // Convertir en nombres pour assurer une représentation correcte dans le graphique
            $row['total_etudiants'] = intval($row['total_etudiants']);
            $row['etudiants_valides'] = intval($row['etudiants_valides']);
            $row['pourcentage_reussite'] = floatval($row['pourcentage_reussite']);
            // Ajouter le nombre d'étudiants en attente
            $row['etudiants_en_attente'] = $row['total_etudiants'] - $row['etudiants_valides'];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Erreur dans getEvolutionBySection: " . $e->getMessage());
        throw $e;
    }
}

// Add these methods to the Universite class

/**
 * Get the current university configuration
 * @return array|false Configuration data or false if not found
 */
public function getConfigurationUniversite() {
    $query = "SELECT * FROM configuration_universite WHERE id = 1";
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Save or update university configuration
 * @param array $data Configuration data
 * @return bool Success status
 */
public function saveConfiguration($data) {
    try {
        // Vérifier si une configuration existe déjà
        $existing = $this->getConfigurationUniversite();
        
        if ($existing) {
            // Mise à jour
            $query = "UPDATE configuration_universite SET 
            type_etablissement = :type_etablissement,
            nom = :nom,
            nom_application = :nom_application,
                  sigle = :sigle,
                  adresse = :adresse,
                  ville = :ville,
                  telephone = :telephone,
                  email = :email,
                  site_web = :site_web,
                  ministere_tutelle = :ministere_tutelle,
                  nom_responsable = :nom_responsable,
                  titre_responsable = :titre_responsable,
                  nom_responsable_academique = :nom_responsable_academique,
                  titre_responsable_academique = :titre_responsable_academique,
                  nom_secretaire_general = :nom_secretaire_general,
                  titre_secretaire_general = :titre_secretaire_general,
                  credit_heure = :credit_heure,
                  ponderation_cc_defaut = :ponderation_cc_defaut,
                  ponderation_ex_defaut = :ponderation_ex_defaut,
                  flexpay_actif = :flexpay_actif,
                  flexpay_merchant = :flexpay_merchant,
                  flexpay_token = :flexpay_token,
                  flexpay_callback_url = :flexpay_callback_url,
                  flexpay_timeout = :flexpay_timeout,
                  flexpay_endpoint_mobile_money = :flexpay_endpoint_mobile_money,
                  flexpay_endpoint_card_payment = :flexpay_endpoint_card_payment,
                  flexpay_endpoint_check_transaction = :flexpay_endpoint_check_transaction";
            // Ajouter les champs de fichiers s'ils sont fournis
            if (isset($data['logo'])) {
                $query .= ", logo = :logo";
            }
            if (isset($data['signature_responsable'])) {
                $query .= ", signature_responsable = :signature_responsable";
            }
            if (isset($data['cachet'])) {
                $query .= ", cachet = :cachet";
            }
            $query .= " WHERE id = :id";
        } else {
            // Nouvelle insertion
            $query = "INSERT INTO configuration_universite
            (type_etablissement, nom, nom_application, sigle, adresse, ville, telephone,
            email, site_web, ministere_tutelle, nom_responsable, titre_responsable,
            nom_responsable_academique, titre_responsable_academique,
            nom_secretaire_general, titre_secretaire_general,
            credit_heure, ponderation_cc_defaut, ponderation_ex_defaut,
            flexpay_actif, flexpay_merchant, flexpay_token, flexpay_callback_url,
            flexpay_timeout, flexpay_endpoint_mobile_money, flexpay_endpoint_card_payment,
            flexpay_endpoint_check_transaction";
                   
            if (isset($data['logo'])) $query .= ", logo";
            if (isset($data['signature_responsable'])) $query .= ", signature_responsable";
            if (isset($data['cachet'])) $query .= ", cachet";
                   
            $query .= ") VALUES (
            :type_etablissement, :nom, :nom_application, :sigle, :adresse, :ville, :telephone,
            :email, :site_web, :ministere_tutelle, :nom_responsable, :titre_responsable,
            :nom_responsable_academique, :titre_responsable_academique,
            :nom_secretaire_general, :titre_secretaire_general,
            :credit_heure, :ponderation_cc_defaut, :ponderation_ex_defaut,
            :flexpay_actif, :flexpay_merchant, :flexpay_token, :flexpay_callback_url,
            :flexpay_timeout, :flexpay_endpoint_mobile_money, :flexpay_endpoint_card_payment,
            :flexpay_endpoint_check_transaction";
                   
            if (isset($data['logo'])) $query .= ", :logo";
            if (isset($data['signature_responsable'])) $query .= ", :signature_responsable";
            if (isset($data['cachet'])) $query .= ", :cachet";
                   
            $query .= ")";
        }
        $stmt = $this->db->prepare($query);
        
        // Bind des paramètres de base
         $params = [
             ':type_etablissement' => $data['type_etablissement'],
             ':nom' => $data['nom'],
             ':nom_application' => isset($data['nom_application']) ? $data['nom_application'] : 'E-GESTION',
             ':sigle' => $data['sigle'],
             ':adresse' => $data['adresse'],
             ':ville' => $data['ville'],
             ':telephone' => $data['telephone'],
             ':email' => $data['email'],
             ':site_web' => $data['site_web'],
             ':ministere_tutelle' => $data['ministere_tutelle'],
             ':nom_responsable' => $data['nom_responsable'],
             ':titre_responsable' => $data['titre_responsable'],
             ':nom_responsable_academique' => isset($data['nom_responsable_academique']) ? $data['nom_responsable_academique'] : '',
             ':titre_responsable_academique' => isset($data['titre_responsable_academique']) ? $data['titre_responsable_academique'] : '',
             ':nom_secretaire_general' => isset($data['nom_secretaire_general']) ? $data['nom_secretaire_general'] : '',
             ':titre_secretaire_general' => isset($data['titre_secretaire_general']) ? $data['titre_secretaire_general'] : 'Secrétaire Général Académique',
             ':credit_heure' => isset($data['credit_heure']) ? intval($data['credit_heure']) : 25,
             ':ponderation_cc_defaut' => isset($data['ponderation_cc_defaut']) ? floatval($data['ponderation_cc_defaut']) : 0.4,
             ':ponderation_ex_defaut' => isset($data['ponderation_ex_defaut']) ? floatval($data['ponderation_ex_defaut']) : 0.6,
             ':flexpay_actif' => isset($data['flexpay_actif']) ? intval($data['flexpay_actif']) : 0,
             ':flexpay_merchant' => $data['flexpay_merchant'] ?? '',
             ':flexpay_token' => $data['flexpay_token'] ?? '',
             ':flexpay_callback_url' => $data['flexpay_callback_url'] ?? '',
             ':flexpay_timeout' => isset($data['flexpay_timeout']) ? intval($data['flexpay_timeout']) : 30,
             ':flexpay_endpoint_mobile_money' => $data['flexpay_endpoint_mobile_money'] ?? 'https://backend.flexpay.cd/api/rest/v1/paymentService',
             ':flexpay_endpoint_card_payment' => $data['flexpay_endpoint_card_payment'] ?? 'https://backend.flexpay.cd/api/rest/v1.1/pay',
             ':flexpay_endpoint_check_transaction' => $data['flexpay_endpoint_check_transaction'] ?? 'https://backend.flexpay.cd/api/rest/v1/check/'
         ];
        // Bind des fichiers si présents
        if (isset($data['logo'])) $params[':logo'] = $data['logo'];
        if (isset($data['signature_responsable'])) $params[':signature_responsable'] = $data['signature_responsable'];
        if (isset($data['cachet'])) $params[':cachet'] = $data['cachet'];
        
        if ($existing) {
            $params[':id'] = $existing['id'];
        }
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Erreur dans saveConfiguration: " . $e->getMessage());
        throw $e;
    }
}


/**
 * Déposer un nouveau travail scientifique
 */
public function deposerTravail($data) {
    $query = "INSERT INTO travaux_scientifiques (
        titre, 
        type_document, 
        nom_auteur,
        type_auteur, 
        departement_id, 
        specialisation_id, 
        annee_academique_id, 
        directeur_id, 
        mots_cles, 
        resume, 
        fichier_path,
        est_public";
    
    // Ajouter les champs spécifiques aux thèses si nécessaire
    if ($data['type_document'] === 'Thèse') {
        $query .= ", 
        anneeThese,
        universiteThese,
        faculteThese,
        specialisationThese";
    }
    
    $query .= ") VALUES (
        :titre, 
        :type_document, 
        :nom_auteur,
        :type_auteur, 
        :departement_id,
        :specialisation_id, 
        :annee_academique_id, 
        :directeur_id,
        :mots_cles, 
        :resume, 
        :fichier_path,
        :est_public";
    
    // Ajouter les valeurs pour les champs spécifiques aux thèses
    if ($data['type_document'] === 'Thèse') {
        $query .= ",
        :anneeThese,
        :universiteThese,
        :faculteThese,
        :specialisationThese";
    }
    
    $query .= ")";
    
    $stmt = $this->db->prepare($query);
    
    // Paramètres de base
    $params = [
        ':titre' => $data['titre'],
        ':type_document' => $data['type_document'],
        ':nom_auteur' => $data['nom_auteur'],
        ':type_auteur' => $data['type_auteur'],
        ':departement_id' => $data['departement_id'],
        ':specialisation_id' => $data['specialisation_id'],
        ':annee_academique_id' => $data['annee_academique_id'],
        ':directeur_id' => $data['directeur_id'] ?? null,
        ':mots_cles' => $data['mots_cles'],
        ':resume' => $data['resume'],
        ':fichier_path' => $data['fichier_path'],
        ':est_public' => isset($data['est_public']) ? $data['est_public'] : false
    ];
    
    // Ajouter les paramètres spécifiques aux thèses si nécessaire
    if ($data['type_document'] === 'Thèse') {
        $params[':anneeThese'] = $data['anneeThese'] ?? null;
        $params[':universiteThese'] = $data['universiteThese'] ?? null;
        $params[':faculteThese'] = $data['faculteThese'] ?? null;
        $params[':specialisationThese'] = $data['specialisationThese'] ?? null;
    }
    
    return $stmt->execute($params);
}

/**
 * Récupérer tous les travaux
 */
public function getTravaux($search = '', $filters = []) {
    $query = "SELECT t.*, 
        o.\"designationOrientation\", 
        s.designation as specialisation,
        aa.designation as annee,
        e.\"nomEnseignant\" as directeur,
        COUNT(c.id) as nb_consultations
    FROM travaux_scientifiques t
    LEFT JOIN orientation o ON t.orientation_id = o.idorientation
    LEFT JOIN specialisation s ON t.specialisation_id = s.\"idSpecialisation\"
    LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
    LEFT JOIN enseignant e ON t.directeur_id = e.idenseignant
    LEFT JOIN consultations c ON t.id = c.travail_id
    WHERE 1=1";

    if (!empty($search)) {
        $query .= " AND (t.titre LIKE :search 
                   OR t.mots_cles LIKE :search 
                   OR t.resume LIKE :search
                   OR t.nom_auteur LIKE :search
                   OR o.\"designationOrientation\" LIKE :search";
        
        // Ajouter la recherche dans les champs spécifiques aux thèses
        $query .= " OR t.universiteThese LIKE :search
                   OR t.\"faculteThese\" LIKE :search
                   OR t.specialisationThese LIKE :search";
        
        $query .= ")";
    }

    // Appliquer les filtres
    if (!empty($filters['type_document'])) {
        // Vérifier si c'est un tableau ou une chaîne
        if (is_array($filters['type_document'])) {
            $placeholders = [];
            foreach ($filters['type_document'] as $index => $type) {
                $placeholders[] = ":type_document_" . $index;
            }
            $query .= " AND t.type_document IN (" . implode(", ", $placeholders) . ")";
        } else {
            $query .= " AND t.type_document = :type_document";
        }
    }
    
    if (!empty($filters['type_auteur'])) {
        $query .= " AND t.type_auteur = :type_auteur";
    }
    if (!empty($filters['orientation_id'])) {
        $query .= " AND t.orientation_id = :orientation_id";
    }
    if (!empty($filters['annee_academique_id'])) {
        $query .= " AND t.annee_academique_id = :annee_academique_id";
    }
    if (isset($filters['est_public'])) {
        $query .= " AND t.est_public = :est_public";
    }
    if (!empty($filters['statut'])) {
        $query .= " AND t.statut = :statut";
    }
    
    // Filtres spécifiques aux thèses
    if (!empty($filters['anneeThese'])) {
        $query .= " AND t.\"anneeThese\" = :anneeThese";
    }
    if (!empty($filters['universiteThese'])) {
        $query .= " AND t.universiteThese LIKE :universiteThese";
    }
    if (!empty($filters['faculteThese'])) {
        $query .= " AND t.\"faculteThese\" LIKE :faculteThese";
    }

    // Ajouter le GROUP BY pour le comptage des consultations
    $query .= " GROUP BY t.id, t.titre, t.type_document, t.nom_auteur, t.type_auteur, 
                t.orientation_id, t.specialisation_id, t.annee_academique_id, 
                t.directeur_id, t.mots_cles, t.resume, t.fichier_path, 
                t.date_depot, t.statut, t.est_public, 
                t.\"anneeThese\", t.universiteThese, t.\"faculteThese\", t.specialisationThese,
                o.\"designationOrientation\", s.designation, aa.designation, e.\"nomEnseignant\"";

    // Trier par date de dépôt décroissante (plus récent en premier)
    $query .= " ORDER BY t.date_depot DESC";

    // Ajouter la limite si spécifiée
    if (!empty($filters['limit'])) {
        $query .= " LIMIT :limit";
    }

    $stmt = $this->db->prepare($query);

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt->bindParam(':search', $searchTerm);
    }

    // Bind des filtres
    if (!empty($filters['type_document'])) {
        if (is_array($filters['type_document'])) {
            foreach ($filters['type_document'] as $index => $type) {
                $stmt->bindValue(":type_document_" . $index, $type);
            }
        } else {
            $stmt->bindParam(':type_document', $filters['type_document']);
        }
    }
    
    if (!empty($filters['type_auteur'])) {
        $stmt->bindParam(':type_auteur', $filters['type_auteur']);
    }
    if (!empty($filters['orientation_id'])) {
        $stmt->bindParam(':orientation_id', $filters['orientation_id']);
    }
    if (!empty($filters['annee_academique_id'])) {
        $stmt->bindParam(':annee_academique_id', $filters['annee_academique_id']);
    }
    if (isset($filters['est_public'])) {
        $stmt->bindParam(':est_public', $filters['est_public']);
    }
    if (!empty($filters['statut'])) {
        $stmt->bindParam(':statut', $filters['statut']);
    }
    
    // Bind des filtres spécifiques aux thèses
    if (!empty($filters['anneeThese'])) {
        $stmt->bindParam(':anneeThese', $filters['anneeThese']);
    }
    if (!empty($filters['universiteThese'])) {
        $universiteThese = '%' . $filters['universiteThese'] . '%';
        $stmt->bindParam(':universiteThese', $universiteThese);
    }
    if (!empty($filters['faculteThese'])) {
        $faculteThese = '%' . $filters['faculteThese'] . '%';
        $stmt->bindParam(':faculteThese', $faculteThese);
    }
    
    if (!empty($filters['limit'])) {
        $stmt->bindParam(':limit', $filters['limit'], PDO::PARAM_INT);
    }

    $stmt->execute();
    $travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter des informations supplémentaires pour chaque travail
    foreach ($travaux as &$travail) {
        // Récupérer les statistiques détaillées des consultations
        $statsConsultations = $this->getConsultationsStats($travail['id']);
        $travail['stats_consultations'] = $statsConsultations;

        // Formater la date de dépôt
        $travail['date_depot_formatee'] = date('d/m/Y', strtotime($travail['date_depot']));

        // Ajouter une classe CSS pour le type de document
        $travail['type_class'] = $this->getTypeDocumentClass($travail['type_document']);
        
        // Ajouter un indicateur pour savoir si c'est une thèse
        $travail['est_these'] = ($travail['type_document'] === 'Thèse');
    }

    return $travaux;
}


/**
 * Obtenir la classe CSS correspondant au type de document
 */
private function getTypeDocumentClass($type) {
    return match($type) {
        'Thèse' => 'bg-primary',
        'Mémoire' => 'bg-success',
        'Rapport de stage' => 'bg-info',
        'Article scientifique' => 'bg-danger',
        'Projet tutoré' => 'bg-warning',
        default => 'bg-secondary'
    };
}
/**
 * Mettre à jour le statut d'un travail
 */
public function updateStatutTravail($id, $statut, $est_public = null) {
    $query = "UPDATE travaux_scientifiques SET statut = :statut";
    
    if ($est_public !== null) {
        $query .= ", est_public = :est_public";
    }
    
    $query .= " WHERE id = :id";
    
    $stmt = $this->db->prepare($query);
    $params = [
        ':id' => $id,
        ':statut' => $statut
    ];
    
    if ($est_public !== null) {
        $params[':est_public'] = $est_public;
    }
    
    return $stmt->execute($params);
}

/**
 * Supprimer un travail
 */
public function deleteTravail($id) {
    // D'abord récupérer le chemin du fichier
    $query = "SELECT fichier_path FROM travaux_scientifiques WHERE id = :id";
    $stmt = $this->db->prepare($query);
    $stmt->execute([':id' => $id]);
    $travail = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Supprimer le fichier physique
    if ($travail && file_exists($travail['fichier_path'])) {
        unlink($travail['fichier_path']);
    }
    
    // Supprimer l'enregistrement
    $query = "DELETE FROM travaux_scientifiques WHERE id = :id";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([':id' => $id]);
}

/**
 * Récupérer un travail par son ID
 */
public function getTravailById($id) {
    $query = "SELECT t.*,
        o.\"designationOrientation\",
        s.designation as specialisation,
        aa.designation as annee,
        e.\"nomEnseignant\" as directeur
    FROM travaux_scientifiques t
    LEFT JOIN orientation o ON t.orientation_id = o.idorientation
    LEFT JOIN specialisation s ON t.specialisation_id = s.\"idSpecialisation\"
    LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
    LEFT JOIN enseignant e ON t.directeur_id = e.idenseignant
    WHERE t.id = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Valider un travail scientifique
 */
public function validerTravail($id, $estPublic = true) {
    $query = "UPDATE travaux_scientifiques 
              SET statut = 'Validé', est_public = :est_public 
              WHERE id = :id";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        ':id' => $id,
        ':est_public' => $estPublic
    ]);
}

    
public function countTravaux() {
    $query = "SELECT COUNT(*) FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé'";
    return $this->db->query($query)->fetchColumn();
}

public function countAuteurs() {
    $query = "SELECT COUNT(DISTINCT nom_auteur) FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé'";
    return $this->db->query($query)->fetchColumn();
}

public function countInstitutions() {
    $query = "SELECT COUNT(DISTINCT departement_id) FROM travaux_scientifiques WHERE est_public = 1 AND statut = 'Validé'";
    return $this->db->query($query)->fetchColumn();
}

public function countConsultations() {
    $query = "SELECT COUNT(*) FROM consultations";
    return $this->db->query($query)->fetchColumn();
}


/**
 * Enregistrer une consultation de travail avec vérification de doublon
 * @param int $travailId ID du travail consulté
 * @return bool Succès de l'opération
 */
public function addConsultation($travailId) {
    try {
        // Vérifier si l'utilisateur a déjà consulté ce travail dans les dernières 24 heures
        $query = "SELECT COUNT(*) FROM consultations 
                  WHERE travail_id = :travail_id 
                  AND ip_address = :ip_address 
                  AND date_consultation > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':travail_id' => $travailId,
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            // Déjà consulté récemment
            return true;
        }
        
        // Ajouter la nouvelle consultation
        $query = "INSERT INTO consultations (travail_id, ip_address) 
                  VALUES (:travail_id, :ip_address)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':travail_id' => $travailId,
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement de la consultation: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir le nombre de consultations d'un travail
 * @param int $travailId ID du travail
 * @return int Nombre de consultations
 */
public function getConsultationsCount($travailId) {
    $query = "SELECT COUNT(*) FROM consultations WHERE travail_id = :travail_id";
    $stmt = $this->db->prepare($query);
    $stmt->execute([':travail_id' => $travailId]);
    return $stmt->fetchColumn();
}

/**
 * Obtenir les statistiques de consultation d'un travail
 * @param int $travailId ID du travail
 * @return array Statistiques de consultation
 */
public function getConsultationsStats($travailId) {
    $query = "SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT ip_address) as unique_visitors,
                DATE(MIN(date_consultation)) as first_view,
                DATE(MAX(date_consultation)) as last_view
              FROM consultations 
              WHERE travail_id = :travail_id";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([':travail_id' => $travailId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations détaillées d'un département par son ID
 * @param int $id ID du département
 * @return array|false Informations du département ou false si non trouvé
 */
public function getDepartementById($id) {
    try {
        // Requête principale pour les informations du département
        $query = "SELECT d.*,
                    s.\"designationSection\",
                    s.idsection,
                    COUNT(DISTINCT t.id) as total_travaux,
                    COUNT(DISTINCT CASE WHEN t.type_document = 'Thèse' THEN t.id END) as total_theses,
                    COUNT(DISTINCT CASE WHEN t.type_document = 'Mémoire' THEN t.id END) as total_memoires,
                    COUNT(DISTINCT CASE WHEN t.type_document = 'Article scientifique' THEN t.id END) as total_articles,
                    COUNT(DISTINCT CASE WHEN t.type_document = 'Rapport de stage' THEN t.id END) as total_rapports,
                    COUNT(DISTINCT CASE WHEN t.type_document = 'Projet tutoré' THEN t.id END) as total_projets,
                    COUNT(DISTINCT e.idenseignant) as total_enseignants,
                    COUNT(DISTINCT ur.idunite_recherche) as total_unites_recherche
                FROM departement d
                LEFT JOIN section s ON d.section_idsection = s.idsection
                LEFT JOIN travaux_scientifiques t ON t.departement_id = d.iddepartement 
                    AND t.est_public = 1 
                    AND t.statut = 'Validé'
                LEFT JOIN enseignant e ON e.\"idDepartement\" = d.iddepartement
                LEFT JOIN unite_recherche ur ON ur.departement_iddepartement = d.iddepartement
                WHERE d.iddepartement = :id
                GROUP BY d.iddepartement";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $departement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$departement) {
            return false;
        }

        // Récupérer les spécialisations du département
        $query = "SELECT s.*
                FROM specialisation s
                JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                WHERE ur.departement_iddepartement = :departement_id
                ORDER BY s.designation";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departement_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $departement['specialisations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les responsables actuels du département
        $query = "SELECT rd.*, e.\"nomEnseignant\", e.grade
                FROM responsable_departement rd
                JOIN enseignant e ON rd.\"idUser\" = e.idenseignant
                WHERE rd.departement_iddepartement = :departement_id
                AND rd.annee_acad_idannee_acad = (
                    SELECT MAX(idannee_acad) FROM annee_acad
                )";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departement_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $departement['responsables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les unités de recherche du département
        $query = "SELECT *
                FROM unite_recherche
                WHERE departement_iddepartement = :departement_id
                ORDER BY \"designation_UR\"";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departement_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $departement['unites_recherche'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les statistiques supplémentaires
        $departement['stats'] = [
            'total_travaux' => $departement['total_travaux'],
            'total_theses' => $departement['total_theses'],
            'total_memoires' => $departement['total_memoires'],
            'total_articles' => $departement['total_articles'],
            'total_rapports' => $departement['total_rapports'],
            'total_projets' => $departement['total_projets'],
            'total_enseignants' => $departement['total_enseignants'],
            'total_unites_recherche' => $departement['total_unites_recherche']
        ];

        // Supprimer les champs de comptage du tableau principal
        unset(
            $departement['total_travaux'],
            $departement['total_theses'],
            $departement['total_memoires'],
            $departement['total_articles'],
            $departement['total_rapports'],
            $departement['total_projets'],
            $departement['total_enseignants'],
            $departement['total_unites_recherche']
        );

        return $departement;

    } catch (Exception $e) {
        error_log("Erreur dans getDepartementById: " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour un travail scientifique
 * @param int $id ID du travail à mettre à jour
 * @param array $data Données du travail à mettre à jour
 * @return bool Succès de la mise à jour
 */
public function updateTravail($id, $data) {
    try {
        // Récupérer l'ancien fichier si nécessaire
        $oldData = null;
        if (isset($data['fichier_path'])) {
            $query = "SELECT fichier_path FROM travaux_scientifiques WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Construire la requête de mise à jour
        $query = "UPDATE travaux_scientifiques SET 
            titre = :titre,
            type_document = :type_document,
            nom_auteur = :nom_auteur,
            type_auteur = :type_auteur,
            departement_id = :departement_id,
            specialisation_id = :specialisation_id,
            annee_academique_id = :annee_academique_id,
            directeur_id = :directeur_id,
            mots_cles = :mots_cles,
            resume = :resume";

        // Ajouter le champ fichier_path si un nouveau fichier est fourni
        if (isset($data['fichier_path'])) {
            $query .= ", fichier_path = :fichier_path";
        }

        // Ajouter le champ est_public s'il est fourni
        if (isset($data['est_public'])) {
            $query .= ", est_public = :est_public";
        }
        
        // Gérer les champs spécifiques aux thèses
        if ($data['type_document'] === 'Thèse') {
            $query .= ", 
                anneeThese = :anneeThese,
                universiteThese = :universiteThese,
                faculteThese = :faculteThese,
                specialisationThese = :specialisationThese";
        } else {
            // Si ce n'est pas une thèse, mettre ces champs à NULL
            $query .= ", 
                anneeThese = NULL,
                universiteThese = NULL,
                faculteThese = NULL,
                specialisationThese = NULL";
        }

        $query .= " WHERE id = :id";

        // Préparer et exécuter la requête
        $stmt = $this->db->prepare($query);
        
        // Paramètres de base
        $params = [
            ':id' => $id,
            ':titre' => $data['titre'],
            ':type_document' => $data['type_document'],
            ':nom_auteur' => $data['nom_auteur'],
            ':type_auteur' => $data['type_auteur'],
            ':departement_id' => $data['departement_id'],
            ':specialisation_id' => $data['specialisation_id'],
            ':annee_academique_id' => $data['annee_academique_id'],
            ':directeur_id' => $data['directeur_id'] ?? null,
            ':mots_cles' => $data['mots_cles'],
            ':resume' => $data['resume']
        ];

        // Ajouter le paramètre fichier_path si présent
        if (isset($data['fichier_path'])) {
            $params[':fichier_path'] = $data['fichier_path'];
        }

        // Ajouter le paramètre est_public si présent
        if (isset($data['est_public'])) {
            $params[':est_public'] = $data['est_public'];
        }
        
        // Ajouter les paramètres spécifiques aux thèses si nécessaire
        if ($data['type_document'] === 'Thèse') {
            $params[':anneeThese'] = $data['anneeThese'] ?? null;
            $params[':universiteThese'] = $data['universiteThese'] ?? null;
            $params[':faculteThese'] = $data['faculteThese'] ?? null;
            $params[':specialisationThese'] = $data['specialisationThese'] ?? null;
        }

        $success = $stmt->execute($params);

        // Si la mise à jour a réussi et qu'un nouveau fichier a été uploadé,
        // supprimer l'ancien fichier
        if ($success && isset($data['fichier_path']) && $oldData && $oldData['fichier_path'] !== $data['fichier_path']) {
            if (file_exists($oldData['fichier_path'])) {
                unlink($oldData['fichier_path']);
            }
        }

        return $success;
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du travail: " . $e->getMessage());
        return false;
    }
}

public function getFrais($search = '') {
    $query = "SELECT f.*, p.\"designationPromotion\", aa.designation AS anneeDesignation 
              FROM frais f
              JOIN promotion p ON f.promotion_idpromotion = p.idpromotion
              JOIN annee_acad aa ON f.annee_acad_idannee_acad = aa.idannee_acad";
    
    if (!empty($search)) {
        $query .= " WHERE f.designation LIKE :search 
                   OR p.\"designationPromotion\" LIKE :search 
                   OR aa.designation LIKE :search";
    }
    
    $query .= " ORDER BY aa.designation DESC, p.\"designationPromotion\" ASC";

    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getFraisById($id) {
    $query = "SELECT f.*, p.\"designationPromotion\", aa.designation AS anneeDesignation 
              FROM frais f
              JOIN promotion p ON f.promotion_idpromotion = p.idpromotion
              JOIN annee_acad aa ON f.annee_acad_idannee_acad = aa.idannee_acad
              WHERE f.idfrais = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getFraisByPromotionAndYear($promotionId, $anneeAcadId) {
    try {
        $query = "SELECT f.*, 
                         p.\"designationPromotion\",
                         aa.designation as anneeDesignation
                  FROM frais f
                  JOIN promotion p ON f.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad aa ON f.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE f.promotion_idpromotion = :promotionId 
                  AND f.annee_acad_idannee_acad = :anneeAcadId
                  ORDER BY f.designation ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();

        $frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrichir les données avec des informations supplémentaires si nécessaire
        foreach ($frais as &$f) {
            // Formater les montants
            $f['montant'] = floatval($f['montant']);
            
            // Formater la date de création
            if (!empty($f['dateCreation'])) {
                $f['dateCreation'] = date('d/m/Y H:i', strtotime($f['dateCreation']));
            }

            // Convertir estObligatoire en booléen
            $f['estObligatoire'] = (bool)$f['estObligatoire'];
        }

        return $frais;

    } catch (Exception $e) {
        error_log("Erreur dans getFraisByPromotionAndYear: " . $e->getMessage());
        return [];
    }
}

public function createFrais($designation, $montant, $devise, $promotionId, $anneeAcadId, $description = '', $estObligatoire = true) {
    $dateCreation = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO frais (designation, montant, devise, description, \"estObligatoire\", \"dateCreation\", promotion_idpromotion, annee_acad_idannee_acad) 
              VALUES (:designation, :montant, :devise, :description, :estObligatoire, :dateCreation, :promotionId, :anneeAcadId)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designation', $designation);
    $stmt->bindParam(':montant', $montant);
    $stmt->bindParam(':devise', $devise);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':estObligatoire', $estObligatoire, PDO::PARAM_BOOL);
    $stmt->bindParam(':dateCreation', $dateCreation);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
    
    return $stmt->execute();
}

public function updateFrais($id, $designation, $montant, $devise, $promotionId, $anneeAcadId, $description = '', $estObligatoire = true) {
    $query = "UPDATE frais 
              SET designation = :designation, 
                  montant = :montant, 
                  devise = :devise, 
                  description = :description, 
                  \"estObligatoire\" = :estObligatoire, 
                  promotion_idpromotion = :promotionId, 
                  annee_acad_idannee_acad = :anneeAcadId 
              WHERE idfrais = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designation', $designation);
    $stmt->bindParam(':montant', $montant);
    $stmt->bindParam(':devise', $devise);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':estObligatoire', $estObligatoire, PDO::PARAM_BOOL);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
    $stmt->bindParam(':id', $id);
    
    return $stmt->execute();
}

public function deleteFrais($id) {
    // Vérifier d'abord si des paiements sont associés à ce frais
    $query = "SELECT COUNT(*) FROM paiement WHERE frais_idfrais = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        // Des paiements existent, ne pas supprimer
        return false;
    }
    
    // Aucun paiement associé, procéder à la suppression
    $query = "DELETE FROM frais WHERE idfrais = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    return $stmt->execute();
}

public function getPaiements($search = '', $filters = []) {
    $query = "SELECT p.*, 
                     f.designation AS fraisDesignation, 
                     f.montant AS montantTotal, 
                     f.devise,
                     e.noms AS nomEtudiant, 
                     e.matricule,
                     pr.\"designationPromotion\",
                     aa.designation AS anneeDesignation
              FROM paiement p
              JOIN frais f ON p.frais_idfrais = f.idfrais
              JOIN etudiant e ON p.etudiant_idetudiant = e.idetudiant
              JOIN promotion pr ON e.promotion_idpromotion = pr.idpromotion
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE 1=1";
    
    // Ajouter les conditions de recherche
    if (!empty($search)) {
        $query .= " AND (e.noms LIKE :search 
                   OR e.matricule LIKE :search 
                   OR f.designation LIKE :search)";
    }
    
    // Ajouter les filtres
    if (!empty($filters['etudiantId'])) {
        $query .= " AND p.etudiant_idetudiant = :etudiantId";
    }
    
    if (!empty($filters['fraisId'])) {
        $query .= " AND p.frais_idfrais = :fraisId";
    }
    
    if (!empty($filters['anneeAcadId'])) {
        $query .= " AND p.annee_acad_idannee_acad = :anneeAcadId";
    }
    
    if (!empty($filters['promotionId'])) {
        $query .= " AND pr.idpromotion = :promotionId";
    }
    
    if (isset($filters['estComplet'])) {
        $query .= " AND p.\"estComplet\" = :estComplet";
    }
    
    // Tri par date de paiement décroissante
    $query .= " ORDER BY p.\"datePaiement\" DESC";
    
    $stmt = $this->db->prepare($query);
    
    // Bind des paramètres de recherche
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    // Bind des paramètres de filtres
    if (!empty($filters['etudiantId'])) {
        $stmt->bindParam(':etudiantId', $filters['etudiantId'], PDO::PARAM_INT);
    }
    
    if (!empty($filters['fraisId'])) {
        $stmt->bindParam(':fraisId', $filters['fraisId'], PDO::PARAM_INT);
    }
    
    if (!empty($filters['anneeAcadId'])) {
        $stmt->bindParam(':anneeAcadId', $filters['anneeAcadId'], PDO::PARAM_INT);
    }
    
    if (!empty($filters['promotionId'])) {
        $stmt->bindParam(':promotionId', $filters['promotionId'], PDO::PARAM_INT);
    }
    
    if (isset($filters['estComplet'])) {
        $stmt->bindParam(':estComplet', $filters['estComplet'], PDO::PARAM_BOOL);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPaiementById($id) {
    $query = "SELECT p.*, 
                     f.designation AS fraisDesignation, 
                     f.montant AS montantTotal, 
                     f.devise,
                     e.noms AS nomEtudiant, 
                     e.matricule,
                     pr.\"designationPromotion\",
                     aa.designation AS anneeDesignation
              FROM paiement p
              JOIN frais f ON p.frais_idfrais = f.idfrais
              JOIN etudiant e ON p.etudiant_idetudiant = e.idetudiant
              JOIN promotion pr ON e.promotion_idpromotion = pr.idpromotion
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE p.idpaiement = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function createPaiement($etudiantId, $fraisId, $montantPaye, $referencePaiement, $anneeAcadId, $modePaiement, $userId, $commentaire = '') {
    try {
        // Récupérer le montant total du frais
        $query = "SELECT montant FROM frais WHERE idfrais = :fraisId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':fraisId', $fraisId);
        $stmt->execute();
        $frais = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$frais) {
            return false;
        }
        
        $montantTotal = $frais['montant'];
        $estComplet = ($montantPaye >= $montantTotal);
        $datePaiement = date('Y-m-d H:i:s');
        
        // Insérer le paiement
        $query = "INSERT INTO paiement (
                    etudiant_idetudiant, 
                    frais_idfrais, 
                    \"montantPaye\", 
                    \"referencePaiement\", 
                    \"datePaiement\", 
                    \"estComplet\", 
                    \"modePaiement\", 
                    commentaire, 
                    annee_acad_idannee_acad, 
                    \"idUser\"
                  ) VALUES (
                    :etudiantId, 
                    :fraisId, 
                    :montantPaye, 
                    :referencePaiement, 
                    :datePaiement, 
                    :estComplet, 
                    :modePaiement, 
                    :commentaire, 
                    :anneeAcadId, 
                    :userId
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->bindParam(':fraisId', $fraisId);
        $stmt->bindParam(':montantPaye', $montantPaye);
        $stmt->bindParam(':referencePaiement', $referencePaiement);
        $stmt->bindParam(':datePaiement', $datePaiement);
        $stmt->bindParam(':estComplet', $estComplet, PDO::PARAM_BOOL);
        $stmt->bindParam(':modePaiement', $modePaiement);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':userId', $userId);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur lors de la création du paiement: " . $e->getMessage());
        return false;
    }
}


public function updatePaiement($id, $montantPaye, $referencePaiement, $modePaiement, $commentaire = '') {
    try {
        // Récupérer les informations actuelles du paiement
        $query = "SELECT p.*, f.montant AS montantTotal 
                  FROM paiement p
                  JOIN frais f ON p.frais_idfrais = f.idfrais
                  WHERE p.idpaiement = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paiement) {
            return false;
        }
        
        // Déterminer si le paiement est complet
        $estComplet = ($montantPaye >= $paiement['montantTotal']);
        
        // Mettre à jour le paiement
        $query = "UPDATE paiement 
                  SET \"montantPaye\" = :montantPaye, 
                      \"referencePaiement\" = :referencePaiement, 
                      \"estComplet\" = :estComplet, 
                      \"modePaiement\" = :modePaiement, 
                      commentaire = :commentaire 
                  WHERE idpaiement = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':montantPaye', $montantPaye);
        $stmt->bindParam(':referencePaiement', $referencePaiement);
        $stmt->bindParam(':estComplet', $estComplet, PDO::PARAM_BOOL);
        $stmt->bindParam(':modePaiement', $modePaiement);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du paiement: " . $e->getMessage());
        return false;
    }
}

public function deletePaiement($id) {
    $query = "DELETE FROM paiement WHERE idpaiement = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    return $stmt->execute();
}

public function getEtatPaiementsEtudiant($etudiantId, $anneeAcadId) {
    try {
        // Récupérer les informations de l'étudiant
        $query = "SELECT e.*, p.\"designationPromotion\" 
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  WHERE e.idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return false;
        }
        
        // Récupérer tous les frais pour la promotion de l'étudiant
        $query = "SELECT f.* 
                  FROM frais f
                  WHERE f.promotion_idpromotion = :promotionId 
                  AND f.annee_acad_idannee_acad = :anneeAcadId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $etudiant['promotion_idpromotion']);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->execute();
        $frais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer tous les paiements de l'étudiant
        $query = "SELECT p.*, f.designation AS fraisDesignation, f.montant AS montantTotal 
                  FROM paiement p
                  JOIN frais f ON p.frais_idfrais = f.idfrais
                  WHERE p.etudiant_idetudiant = :etudiantId 
                  AND p.annee_acad_idannee_acad = :anneeAcadId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->execute();
        $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les paiements par frais
        $paiementsParFrais = [];
        foreach ($paiements as $paiement) {
            $fraisId = $paiement['frais_idfrais'];
            if (!isset($paiementsParFrais[$fraisId])) {
                $paiementsParFrais[$fraisId] = [
                    'fraisId' => $fraisId,
                    'designation' => $paiement['fraisDesignation'],
                    'montantTotal' => $paiement['montantTotal'],
                    'montantPaye' => 0,
                    'estComplet' => false,
                    'paiements' => []
                ];
            }
            
            $paiementsParFrais[$fraisId]['montantPaye'] += $paiement['montantPaye'];
            $paiementsParFrais[$fraisId]['paiements'][] = $paiement;
            
            // Mettre à jour l'état complet
            if ($paiementsParFrais[$fraisId]['montantPaye'] >= $paiementsParFrais[$fraisId]['montantTotal']) {
                $paiementsParFrais[$fraisId]['estComplet'] = true;
            }
        }
        
        // Ajouter les frais non payés
        foreach ($frais as $f) {
            if (!isset($paiementsParFrais[$f['idfrais']])) {
                $paiementsParFrais[$f['idfrais']] = [
                    'fraisId' => $f['idfrais'],
                    'designation' => $f['designation'],
                    'montantTotal' => $f['montant'],
                    'montantPaye' => 0,
                    'estComplet' => false,
                    'paiements' => []
                ];
            }
        }
        
        // Calculer les totaux
        $totalAPayer = 0;
        $totalPaye = 0;
        $totalRestant = 0;
        
        foreach ($paiementsParFrais as $fraisId => $info) {
            $totalAPayer += $info['montantTotal'];
            $totalPaye += $info['montantPaye'];
        }
        
        $totalRestant = $totalAPayer - $totalPaye;
        $pourcentagePaye = ($totalAPayer > 0) ? ($totalPaye / $totalAPayer) * 100 : 0;
        
        return [
            'etudiant' => $etudiant,
            'paiementsParFrais' => $paiementsParFrais,
            'totalAPayer' => $totalAPayer,
            'totalPaye' => $totalPaye,
            'totalRestant' => $totalRestant,
            'pourcentagePaye' => $pourcentagePaye
        ];
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de l'état des paiements: " . $e->getMessage());
        return false;
    }
}


public function getEtudiants($search = '') {
    try {
        // Récupérer d'abord l'année académique en cours
        $currentYear = $this->getCurrentAcademicYear();
        if (!$currentYear) {
            throw new Exception("Impossible de déterminer l'année académique en cours");
        }

        // Construire la requête
        $query = "SELECT e.*, 
                         p.\"designationPromotion\", 
                         d.designationDepartement, 
                         s.\"designationSection\",
                         aa.designation as annee
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN departement d ON p.departement_iddepartement = d.iddepartement
                  JOIN section s ON d.section_idsection = s.idsection
                  JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE e.annee_acad_idannee_acad = :anneeId";

        // Ajouter la condition de recherche si un terme est fourni
        if (!empty($search)) {
            $query .= " AND (e.noms LIKE :search 
                           OR e.matricule LIKE :search 
                           OR p.\"designationPromotion\" LIKE :search
                           OR d.designationDepartement LIKE :search)";
        }

        // Trier les résultats
        $query .= " ORDER BY s.\"designationSection\", d.designationDepartement, 
                            p.\"designationPromotion\", e.noms ASC";

        $stmt = $this->db->prepare($query);
        
        // Bind des paramètres
        $stmt->bindParam(':anneeId', $currentYear['idannee_acad'], PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->execute();
        
        // Récupérer les résultats
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrichir les données avec des informations supplémentaires pour chaque étudiant
        foreach ($etudiants as &$etudiant) {
            // Récupérer l'état des paiements
            $etatPaiements = $this->getEtatPaiementsEtudiant(
                $etudiant['idetudiant'], 
                $currentYear['idannee_acad']
            );

            // Ajouter les informations de paiement à l'étudiant
            if ($etatPaiements) {
                $etudiant['paiements'] = [
                    'totalAPayer' => $etatPaiements['totalAPayer'],
                    'totalPaye' => $etatPaiements['totalPaye'],
                    'totalRestant' => $etatPaiements['totalRestant'],
                    'pourcentagePaye' => $etatPaiements['pourcentagePaye']
                ];
            }

            // Formater la date de naissance
            if (!empty($etudiant['dateNaissance'])) {
                $etudiant['dateNaissance'] = date('d/m/Y', strtotime($etudiant['dateNaissance']));
            }

            // Formater la date d'enregistrement
            if (!empty($etudiant['dateEnregistrement'])) {
                $etudiant['dateEnregistrement'] = date('d/m/Y H:i', strtotime($etudiant['dateEnregistrement']));
            }
        }

        return $etudiants;

    } catch (Exception $e) {
        error_log("Erreur dans getEtudiants: " . $e->getMessage());
        return [];
    }
}

public function getEtudiantById($id) {
    try {
        $query = "SELECT e.*, 
                         p.\"designationPromotion\", 
                         p.idpromotion as promotion_idpromotion,
                         d.\"designationOrientation\", 
                         s.\"designationSection\",
                         aa.designation as annee,
                         aa.idannee_acad
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN orientation d ON p.orientation_idorientation = d.idorientation
                  JOIN section s ON d.section_idsection = s.idsection
                  JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE e.idetudiant = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etudiant) {
            return false;
        }

        // Récupérer l'état des paiements de l'étudiant
        $etatPaiements = $this->getEtatPaiementsEtudiant(
            $etudiant['idetudiant'],
            $etudiant['idannee_acad']
        );

        // Ajouter les informations de paiement à l'étudiant
        if ($etatPaiements) {
            $etudiant['paiements'] = [
                'totalAPayer' => $etatPaiements['totalAPayer'],
                'totalPaye' => $etatPaiements['totalPaye'],
                'totalRestant' => $etatPaiements['totalRestant'],
                'pourcentagePaye' => $etatPaiements['pourcentagePaye']
            ];
        }

        // Formater les dates
        if (!empty($etudiant['dateNaissance'])) {
            $etudiant['dateNaissance'] = date('d/m/Y', strtotime($etudiant['dateNaissance']));
        }

        if (!empty($etudiant['dateEnregistrement'])) {
            $etudiant['dateEnregistrement'] = date('d/m/Y H:i', strtotime($etudiant['dateEnregistrement']));
        }

        return $etudiant;

    } catch (Exception $e) {
        error_log("Erreur dans getEtudiantById: " . $e->getMessage());
        return false;
    }
}

public function getFraisForEtudiant($etudiantId, $anneeAcadId) {
    try {
        // 1. Récupérer la promotion de l'étudiant
        $query = "SELECT promotion_idpromotion FROM etudiant WHERE idetudiant = :etudiantId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return [];
        }
        
        $promotionId = $etudiant['promotion_idpromotion'];
        
        // 2. Récupérer les frais associés à cette promotion pour l'année académique spécifiée
        $query = "SELECT f.*, 
                  COALESCE((SELECT SUM(p.\"montantPaye\") FROM paiement p 
                           WHERE p.frais_idfrais = f.idfrais AND p.etudiant_idetudiant = :etudiantId), 0) as \"montantPaye\"
                  FROM frais f
                  WHERE f.promotion_idpromotion = :promotionId 
                  AND f.annee_acad_idannee_acad = :anneeAcadId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        $frais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Calculer le montant restant pour chaque frais
        foreach ($frais as &$f) {
            $f['montantRestant'] = $f['montant'] - ($f['montantPaye'] ?? 0);
            
            // Ne pas afficher les frais déjà complètement payés
            if ($f['montantRestant'] <= 0) {
                continue;
            }
        }
        
        // Filtrer les frais avec un montant restant > 0
        return array_filter($frais, function($f) {
            return ($f['montantRestant'] ?? $f['montant']) > 0;
        });
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des frais pour l'étudiant: " . $e->getMessage());
        return [];
    }
}

public function getEtudiantsByAnneeAcad($anneeAcadId) {
    $query = "SELECT idetudiant, matricule, noms,promotion_idpromotion FROM etudiant WHERE annee_acad_idannee_acad = :anneeAcadId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEtudiantsByAnneeAcadAndCycle($anneeAcadId, $cycle) {
    $query = "SELECT e.idetudiant, e.matricule, e.noms 
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE e.annee_acad_idannee_acad = :anneeAcadId
              AND p.cycle = :cycle
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getSessions($search = '') {
    $query = "SELECT * FROM session";
    if (!empty($search)) {
        $query .= " WHERE \"designSession\" LIKE :search";
    }
    $query .= " ORDER BY \"designSession\" ASC";

    $stmt = $this->db->prepare($query);
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



public function createSession($designSession,$description) {
    $dateCreation = date('Y-m-d H:i:s');
    $query = "INSERT INTO session (\"designSession\",description, \"dateCreation\") VALUES (:designSession, :dateCreation,:descri)";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designSession', $designSession);
    $stmt->bindParam(':descri', $description);
    $stmt->bindParam(':dateCreation', $dateCreation);
    return $stmt->execute();
}

public function updateSession($id, $designSession,$description) {
    $query = "UPDATE session SET \"designSession\" = :designSession,description=:descri WHERE idsession = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designSession', $designSession);
    $stmt->bindParam(':descri', $description);
    $stmt->bindParam(':id', $id);
    return $stmt->execute();
}

public function deleteSession($id) {
    $query = "DELETE FROM session WHERE idsession = :id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id);
    return $stmt->execute();
}

public function getSemestres($search = '') {
    $query = "SELECT s.*, p.\"designationPromotion\",a.designation as annee 
              FROM semestre s
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN annee_acad a ON p.annee_acad_idannee_acad=a.idannee_acad";
    
    if (!empty($search)) {
        $query .= " WHERE s.\"numeroSemestre\" LIKE :search OR p.\"designationPromotion\" LIKE :search";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\" ASC, s.\"numeroSemestre\" ASC";

    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function createSemestre($numeroSemestre, $promotion_idpromotion) {
    $dateEnregistrement = date('Y-m-d H:i:s');
    $query = "INSERT INTO semestre (\"numeroSemestre\", \"dateEnregistrement\", promotion_idpromotion) 
              VALUES (:numeroSemestre, :dateEnregistrement, :promotion_idpromotion)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':numeroSemestre', $numeroSemestre);
    $stmt->bindParam(':dateEnregistrement', $dateEnregistrement);
    $stmt->bindParam(':promotion_idpromotion', $promotion_idpromotion);
    
    return $stmt->execute();
}

public function updateSemestre($idsemestre, $numeroSemestre, $promotion_idpromotion) {
    $query = "UPDATE semestre 
              SET \"numeroSemestre\" = :numeroSemestre, 
                  promotion_idpromotion = :promotion_idpromotion 
              WHERE idsemestre = :idsemestre";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':numeroSemestre', $numeroSemestre);
    $stmt->bindParam(':promotion_idpromotion', $promotion_idpromotion);
    $stmt->bindParam(':idsemestre', $idsemestre);
    
    return $stmt->execute();
}

public function deleteSemestre($idsemestre) {
    $query = "DELETE FROM semestre WHERE idsemestre = :idsemestre";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idsemestre', $idsemestre);
    
    return $stmt->execute();
}

public function getSemestreById($idsemestre) {
    $query = "SELECT s.*, p.\"designationPromotion\" 
              FROM semestre s
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              WHERE s.idsemestre = :idsemestre";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idsemestre', $idsemestre);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupérer les sections d'un agent
 */
public function getAgentSections($idAgent) {
    $query = "SELECT ase.*, s.\"designationSection\" 
              FROM agent_section ase
              INNER JOIN section s ON ase.idsection = s.idsection
              WHERE ase.\"idAgent\" = :idAgent
              ORDER BY ase.\"estPrincipal\" DESC, s.\"designationSection\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idAgent' => $idAgent]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifier si un agent est déjà affecté à une section
 */
public function checkDuplicateAgentSection($idAgent, $idSection) {
    $query = "SELECT COUNT(*) as count 
              FROM agent_section 
              WHERE \"idAgent\" = :idAgent AND idsection = :idSection";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idAgent' => $idAgent,
        'idSection' => $idSection
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Ajouter une section à un agent
 */
public function addAgentSection($idAgent, $idSection, $estPrincipal) {
    // Si cette section doit être principale, mettre à jour les autres sections pour qu'elles ne soient plus principales
    if ($estPrincipal) {
        $updateQuery = "UPDATE agent_section 
                        SET \"estPrincipal\" = 0 
                        WHERE \"idAgent\" = :idAgent";
        
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->execute(['idAgent' => $idAgent]);
    }
    
    // Ajouter la nouvelle affectation
    $query = "INSERT INTO agent_section (\"idAgent\", idsection, \"dateAffectation\", \"estPrincipal\") 
              VALUES (:idAgent, :idSection, NOW(), :estPrincipal)";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'idAgent' => $idAgent,
        'idSection' => $idSection,
        'estPrincipal' => $estPrincipal
    ]);
}

/**
 * Définir une section comme principale pour un agent
 */
public function setAgentSectionAsPrincipal($idAgentSection) {
    // Récupérer l'ID de l'agent
    $query = "SELECT \"idAgent\" FROM agent_section WHERE idagent_section = :id";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['id' => $idAgentSection]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return false;
    }
    
    $idAgent = $result['idAgent'];
    
    // Mettre à jour toutes les sections de cet agent pour qu'elles ne soient plus principales
    $updateQuery = "UPDATE agent_section 
                    SET \"estPrincipal\" = 0 
                    WHERE \"idAgent\" = :idAgent";
    
    $updateStmt = $this->db->prepare($updateQuery);
    $updateStmt->execute(['idAgent' => $idAgent]);
    
    // Définir la section sélectionnée comme principale
    $setPrincipalQuery = "UPDATE agent_section 
                          SET \"estPrincipal\" = 1 
                          WHERE idagent_section = :id";
    
    $setPrincipalStmt = $this->db->prepare($setPrincipalQuery);
    return $setPrincipalStmt->execute(['id' => $idAgentSection]);
}

/**
 * Supprimer une affectation d'agent à une section
 */
public function deleteAgentSection($idAgentSection) {
    // Vérifier si c'est une section principale
    $checkQuery = "SELECT \"estPrincipal\", \"idAgent\" FROM agent_section WHERE idagent_section = :id";
    $checkStmt = $this->db->prepare($checkQuery);
    $checkStmt->execute(['id' => $idAgentSection]);
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    // Supprimer l'affectation
    $deleteQuery = "DELETE FROM agent_section WHERE idagent_section = :id";
    $deleteStmt = $this->db->prepare($deleteQuery);
    $result = $deleteStmt->execute(['id' => $idAgentSection]);
    
    // Si c'était une section principale et que la suppression a réussi, définir une autre section comme principale
    if ($result && $section && $section['estPrincipal'] == 1) {
        $idAgent = $section['idAgent'];
        
        // Vérifier s'il reste des sections pour cet agent
        $countQuery = "SELECT COUNT(*) as count FROM agent_section WHERE \"idAgent\" = :idAgent";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute(['idAgent' => $idAgent]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($countResult['count'] > 0) {
            // Définir la première section restante comme principale
            $updateQuery = "UPDATE agent_section 
                            SET \"estPrincipal\" = 1 
                            WHERE \"idAgent\" = :idAgent 
                            ORDER BY \"dateAffectation\" ASC 
                            LIMIT 1";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute(['idAgent' => $idAgent]);
        }
    }
    
    return $result;
}
public function createOrientation($designationOrientation, $sectionId) {
    // Préparer la requête d'insertion
    $query = "INSERT INTO orientation (\"designationOrientation\", section_idsection) VALUES (:designation, :sectionId)";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec les paramètres
    return $stmt->execute([
        'designation' => $designationOrientation,
        'sectionId' => $sectionId
    ]);
}

public function updateOrientation($orientationId, $designationOrientation, $sectionId) {
    // Préparer la requête de mise à jour
    $query = "UPDATE orientation 
              SET \"designationOrientation\" = :designation, 
                  section_idsection = :sectionId 
              WHERE idorientation = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec les paramètres
    return $stmt->execute([
        'designation' => $designationOrientation,
        'sectionId' => $sectionId,
        'id' => $orientationId
    ]);
}

public function deleteOrientation($orientationId) {
    // Préparer la requête de suppression
    $query = "DELETE FROM orientation WHERE idorientation = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec le paramètre
    return $stmt->execute(['id' => $orientationId]);
}

// Méthodes pour les responsables d'orientation
public function getManagersByOrientation($orientationId) {
    // Préparer la requête pour récupérer les responsables
    $query = "SELECT r.*, a.designation as anneeDesignation 
              FROM responsable_orientation r 
              JOIN annee_acad a ON r.annee_acad_idannee_acad = a.idannee_acad 
              WHERE r.orientation_idorientation = :orientationId 
              ORDER BY r.\"dateEnregistrement\" DESC";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec le paramètre
    $stmt->execute(['orientationId' => $orientationId]);
    
    // Retourner tous les résultats
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function createOrientationManager($noms, $fonction, $signature, $userId, $orientationId, $idAnnee) {
    // Préparer la requête d'insertion
    $query = "INSERT INTO responsable_orientation 
              (noms, fonction, signature, \"idUser\", \"dateEnregistrement\", orientation_idorientation, annee_acad_idannee_acad) 
              VALUES (:noms, :fonction, :signature, :userId, NOW(), :orientationId, :idAnnee)";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec les paramètres
    return $stmt->execute([
        'noms' => $noms,
        'fonction' => $fonction,
        'signature' => $signature,
        'userId' => $userId,
        'orientationId' => $orientationId,
        'idAnnee' => $idAnnee
    ]);
}

public function getOrientationManagerById($managerId) {
    // Préparer la requête pour récupérer un responsable spécifique
    $query = "SELECT * FROM responsable_orientation WHERE idresponsable_orientation = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec le paramètre
    $stmt->execute(['id' => $managerId]);
    
    // Retourner le résultat
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function updateOrientationManager($managerId, $noms, $fonction, $signature, $userId, $idAnnee) {
    // Préparer la requête de mise à jour
    $query = "UPDATE responsable_orientation 
              SET noms = :noms, 
                  fonction = :fonction, 
                  signature = :signature, 
                  \"idUser\" = :userId, 
                  annee_acad_idannee_acad = :idAnnee 
              WHERE idresponsable_orientation = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec les paramètres
    return $stmt->execute([
        'noms' => $noms,
        'fonction' => $fonction,
        'signature' => $signature,
        'userId' => $userId,
        'idAnnee' => $idAnnee,
        'id' => $managerId
    ]);
}

public function deleteOrientationManager($managerId) {
    // Préparer la requête de suppression
    $query = "DELETE FROM responsable_orientation WHERE idresponsable_orientation = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec le paramètre
    return $stmt->execute(['id' => $managerId]);
}

public function getUserById($userId) {
    // Préparer la requête pour récupérer un utilisateur
    $query = "SELECT * FROM t_users WHERE \"idUser\" = :id";
    $stmt = $this->db->prepare($query);
    
    // Exécuter la requête avec le paramètre
    $stmt->execute(['id' => $userId]);
    
    // Retourner le résultat
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getOrientations($search = '', $anneeAcadId = null) {
// Préparer la requête de base avec le chef d'orientation
$query = "SELECT o.*, s.\"designationSection\" as sectionDesignation, a.designation as anneeDesignation, a.idannee_acad,
(SELECT CONCAT(ro.noms, ' - ', ro.fonction) 
FROM responsable_orientation ro 
WHERE ro.orientation_idorientation = o.idorientation 
AND ro.est_chef = 1 
AND ro.annee_acad_idannee_acad = a.idannee_acad
LIMIT 1) AS chef_orientation
FROM orientation o
JOIN section s ON o.section_idsection = s.idsection
JOIN annee_acad a ON s.\"idAnnee\" = a.idannee_acad
WHERE 1=1";

$params = [];

// Ajouter la condition de recherche si nécessaire
if (!empty($search)) {
$query .= " AND o.\"designationOrientation\" LIKE :search";
$params['search'] = '%' . $search . '%';
}

// Ajouter la condition pour l'année académique si spécifiée
if (!empty($anneeAcadId)) {
$query .= " AND a.idannee_acad = :anneeAcadId";
$params['anneeAcadId'] = $anneeAcadId;
}

// Ajouter l'ordre de tri
$query .= " ORDER BY o.\"designationOrientation\" ASC";

// Préparer et exécuter la requête
$stmt = $this->db->prepare($query);
$stmt->execute($params);

// Retourner tous les résultats
return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getOrientationsForExport($idSection = 'all', $idOrientation = 'all') {
    // Construire la requête de base
    $query = "SELECT o.*, s.\"designationSection\" as sectionDesignation, 
                     r.idresponsable_orientation, r.noms, r.fonction, r.\"dateEnregistrement\",
                     a.designation as anneeDesignation
              FROM orientation o 
              JOIN section s ON o.section_idsection = s.idsection 
              LEFT JOIN responsable_orientation r ON o.idorientation = r.orientation_idorientation
              LEFT JOIN annee_acad a ON r.annee_acad_idannee_acad = a.idannee_acad
              WHERE 1=1";
    
    $params = [];
    
    // Ajouter les filtres si nécessaire
    if ($idSection !== 'all') {
        $query .= " AND o.section_idsection = :idSection";
        $params['idSection'] = $idSection;
    }
    
    if ($idOrientation !== 'all') {
        $query .= " AND o.idorientation = :idOrientation";
        $params['idOrientation'] = $idOrientation;
    }
    
    // Ajouter l'ordre de tri
    $query .= " ORDER BY s.\"designationSection\", o.\"designationOrientation\", r.\"dateEnregistrement\" DESC";
    
    // Préparer et exécuter la requête
    $stmt = $this->db->prepare($query);
    $stmt->execute($params);
    
    // Retourner tous les résultats
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les promotions par orientation
public function getPromotionsByOrientation($orientationId) {
    $query = "SELECT p.idpromotion, p.\"designationPromotion\", aa.designation as anneeDesignation
              FROM promotion p
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE p.orientation_idorientation = :orientationId
              ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer l'orientation d'une promotion
public function getPromotionOrientation($promotionId) {
    $query = "SELECT orientation_idorientation 
              FROM promotion 
              WHERE idpromotion = :promotionId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['orientation_idorientation'] : null;
}

public function getPromotionById($promotionId) {
    $query = "SELECT p.*, o.\"designationOrientation\", aa.designation as anneeDesignation
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE p.idpromotion = :promotionId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getUserSections($userId) {
    $query = "SELECT section_idsection 
              FROM responsable_section 
              WHERE \"idUser\" = :userId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Récupère toutes les promotions pour une section donnée
 * @param int $sectionId ID de la section
 * @param int|null $anneeAcadId ID de l'année académique (optionnel)
 * @return array Liste des promotions
 */
public function getPromotionsBySection($sectionId, $anneeAcadId = null) {
    $query = "SELECT p.*, aa.designation as anneeDesignation
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE s.idsection = :sectionId";
    
    if ($anneeAcadId !== null) {
        $query .= " AND p.annee_acad_idannee_acad = :anneeAcadId";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    
    if ($anneeAcadId !== null) {
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère toutes les UEs pour une année académique donnée
 * @param int $anneeAcadId ID de l'année académique
 * @param string $search Terme de recherche optionnel
 * @return array Liste des UEs
 */
public function getAllUEs($anneeAcadId, $search = '')
{
    $query = "SELECT ue.\"idUE\", ue.\"codeUE\", ue.\"designationUE\", ue.description, 
                     ue.semestre_idsemestre, s.\"numeroSemestre\", 
                     p.idpromotion, p.\"designationPromotion\", 
                     sec.idsection, sec.\"designationSection\"
              FROM ue 
              JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section sec ON o.section_idsection = sec.idsection
              WHERE p.annee_acad_idannee_acad = :anneeAcadId";
    
    // Ajouter la condition de recherche si un terme est fourni
    if (!empty($search)) {
        $query .= " AND (ue.\"designationUE\" LIKE :search 
                   OR ue.\"codeUE\" LIKE :search
                   OR p.\"designationPromotion\" LIKE :search
                   OR sec.\"designationSection\" LIKE :search)";
    }
    
    $query .= " ORDER BY sec.\"designationSection\", p.\"designationPromotion\", s.\"numeroSemestre\", ue.\"designationUE\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPromotionsByAnneeAcad($anneeAcadId, $search = '') {
    $query = "SELECT p.*, o.\"designationOrientation\", aa.designation AS anneeDesignation 
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE p.annee_acad_idannee_acad = :anneeAcadId";
    
    if (!empty($search)) {
        $query .= " AND (p.\"designationPromotion\" LIKE :search OR o.\"designationOrientation\" LIKE :search)";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\" ASC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEtudiantByMatricule($matricule, $anneeAcadId) {
    $query = "SELECT e.*, p.\"designationPromotion\" 
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE e.matricule = :matricule
              AND e.annee_acad_idannee_acad = :anneeAcadId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les semestres associés à une promotion
 * @param int $promotionId ID de la promotion
 * @return array Liste des semestres
 */
public function getSemestresByPromotion($promotionId) {
    $query = "SELECT * FROM semestre 
              WHERE promotion_idpromotion = :promotionId 
              ORDER BY \"numeroSemestre\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Dans la classe Universite, modifiez la fonction getUEsBySemestre
public function getUEsBySemestre($semestreId) {
    $ues = []; // Récupérer les UEs de base
    
    // Pour chaque UE, calculer le nombre de crédits
    foreach ($ues as &$ue) {
        $ecues = $this->getECUEsByUE($ue['idUE']); // Supposons que cette fonction existe
        $totalCredits = 0;
        
        foreach ($ecues as $ecue) {
            $totalCredits += ($ecue['CMI'] + $ecue['TD'] + $ecue['TP']) / $this->heuresParCredit;
        }
        
        $ue['nombre_credits'] = $totalCredits;
    }
    
    return $ues;
}


/**
 * Récupère tous les ECUEs associés à une UE spécifique
 * 
 * @param int $ueId L'identifiant de l'UE
 * @return array Un tableau contenant les informations des ECUEs
 */
public function getECUEsByUE($ueId) {
    try {
        $query = "SELECT \"idECUE\", \"designationECUE\", CMI, TD, TP 
                  FROM ecue 
                  WHERE \"idUE\" = ? 
                  ORDER BY \"designationECUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$ueId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Gérer l'erreur ou la journaliser
        error_log("Erreur lors de la récupération des ECUEs pour l'UE $ueId: " . $e->getMessage());
        return [];
    }
}



public function getFraisByAcademicYear($idAnneeAcad) {
    try {
        $query = "SELECT f.*
                  FROM frais f
                  WHERE f.annee_acad_id = :idAnneeAcad
                  ORDER BY f.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("getFraisByAcademicYear error: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupérer le parcours complet d'un étudiant dans le système actuel
 */
public function getParcoursEtudiantSysteme($matricule, $annee_id, $promotion_id) {
    $parcours = [];
    
    try {
        // Récupérer le crédit horaire depuis la configuration
        $configQuery = $this->db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
        $config = $configQuery->fetch(PDO::FETCH_ASSOC);
        $creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;
        
        // Récupérer les semestres où l'étudiant a des moyennes d'UE enregistrées
        $query = "SELECT DISTINCT s.idsemestre, s.\"numeroSemestre\" as nom_semestre, s.\"numeroSemestre\"
                 FROM semestre s
                 INNER JOIN ue u ON u.semestre_idsemestre = s.idsemestre
                 INNER JOIN moyenne_ue mu ON mu.\"idUE\" = u.\"idUE\"
                 WHERE mu.matricule = :matricule 
                 AND mu.annee_acad_idannee_acad = :annee_id
                 AND u.promotion_idpromotion = :promotion_id
                 ORDER BY s.\"numeroSemestre\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':annee_id', $annee_id, PDO::PARAM_INT);
        $stmt->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
        $stmt->execute();
        $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($semestres as $semestre) {
            // Récupérer les UE du semestre avec leurs moyennes
            // Calculer les crédits à partir des ECUE (CMI + TD + TP) / credit_heure
            $queryUE = "SELECT DISTINCT 
                           u.\"idUE\", u.\"designationUE\" as designation, u.\"codeUE\" as code,
                           COALESCE(mu.moyenne_brute, 0) as moyenne,
                           COALESCE(mu.est_validee, 0) as est_valide,
                           COALESCE(mu.credits_obtenus, 0) as credits_valides,
                           COALESCE(
                               (SELECT SUM((e.CMI + e.TD + e.TP) / $creditHeure) FROM ecue e WHERE e.\"UE_idUE\" = u.\"idUE\"),
                               0
                           ) as credits_total,
                           mu.type_validation
                       FROM ue u
                       INNER JOIN moyenne_ue mu ON mu.\"idUE\" = u.\"idUE\" 
                           AND mu.matricule = :matricule 
                           AND mu.annee_acad_idannee_acad = :annee_id
                       WHERE u.semestre_idsemestre = :semestre_id 
                       AND u.promotion_idpromotion = :promotion_id
                       ORDER BY u.\"designationUE\"";
            
            $stmtUE = $this->db->prepare($queryUE);
            $stmtUE->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmtUE->bindParam(':annee_id', $annee_id, PDO::PARAM_INT);
            $stmtUE->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
            $stmtUE->bindParam(':semestre_id', $semestre['idsemestre'], PDO::PARAM_INT);
            $stmtUE->execute();
            $ues = $stmtUE->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($ues)) {
                $parcours['Semestre ' . $semestre['nom_semestre']] = [
                    'semestre_id' => $semestre['idsemestre'],
                    'numero' => $semestre['numeroSemestre'],
                    'ues' => $ues
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Erreur getParcoursEtudiantSysteme: " . $e->getMessage());
    }
    
    return $parcours;
}

/**
 * Récupérer les promotions d'une année académique spécifique
 */
public function getPromotionsByYear($anneeId) {
    try {
        $query = "SELECT p.idpromotion, p.\"designationPromotion\", p.cycle, 
                         o.\"designationOrientation\", p.est_terminale
                  FROM promotion p
                  INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  WHERE p.annee_acad_idannee_acad = :annee_id
                  ORDER BY p.\"designationPromotion\" ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erreur getPromotionsByYear: " . $e->getMessage());
        return [];
    }
}

public function getEcuesByPromotion($idPromotion) {
    $query = "SELECT e.*, u.\"designationUE\", s.\"numeroSemestre\", 
              (SELECT a.noms FROM enseignant_ecue ee 
               JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\" 
               WHERE ee.\"idECUE\" = e.\"idECUE\" 
               AND ee.poste = 'Titulaire' 
               LIMIT 1) as enseignant_titulaire
              FROM ecue e
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              WHERE p.idpromotion = :idPromotion
              ORDER BY s.\"numeroSemestre\", u.\"designationUE\", e.\"designationECUE\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idPromotion', $idPromotion, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getSujetsValidesForSoutenance($idAnneeAcad) {
    $query = "SELECT s.idsujets, s.intitule, s.cycle, s.etudiant_idetudiant,
              s.\"idDirecteur\", s.\"idEncadreur\", s.commentaire_commission,
              e.noms as etudiant_nom, e.matricule,
              p.\"designationPromotion\" as promotion,
              sp.designation as specialisation,
              sp.\"idSpecialisation\",
              d.noms as directeur_nom, 
              en.noms as encadreur_nom,
              (SELECT COUNT(*) FROM soutenance WHERE sujets_idsujets = s.idsujets) as has_soutenance
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
              LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
              LEFT JOIN agent en ON s.\"idEncadreur\" = en.\"idAgent\"
              WHERE s.\"etatSujet\" = 'Validé'
              AND s.annee_acad_idannee_acad = :idAnneeAcad
              AND s.etudiant_idetudiant IS NOT NULL
              AND s.\"idDirecteur\" IS NOT NULL
              HAVING has_soutenance = 0
              ORDER BY e.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getEtudiantsByAnnee($idAnneeAcad) {
    $query = "SELECT e.*, 
              p.\"designationPromotion\", 
              p.cycle,
              o.\"designationOrientation\"
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE e.annee_acad_idannee_acad = :idAnneeAcad
              ORDER BY e.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getAllUEsBySection($sectionId, $anneeAcadId, $search = '')
{
    $query = "SELECT ue.\"idUE\", ue.\"codeUE\", ue.\"designationUE\", ue.description, 
                     ue.semestre_idsemestre, s.\"numeroSemestre\", 
                     p.idpromotion, p.\"designationPromotion\", 
                     sec.idsection, sec.\"designationSection\"
              FROM ue 
              JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section sec ON o.section_idsection = sec.idsection
              WHERE sec.idsection = :sectionId
              AND p.annee_acad_idannee_acad = :anneeAcadId";
    
    // Ajouter la condition de recherche si un terme est fourni
    if (!empty($search)) {
        $query .= " AND (ue.\"designationUE\" LIKE :search 
                   OR ue.\"codeUE\" LIKE :search
                   OR p.\"designationPromotion\" LIKE :search)";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\", s.\"numeroSemestre\", ue.\"designationUE\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les semestres avec les informations associées
 * @return array Liste de tous les semestres
 */
public function getAllSemestres($anneeAcadId = null)
{
    $query = "SELECT s.idsemestre, s.\"numeroSemestre\", 
                     p.idpromotion, p.\"designationPromotion\", p.cycle,
                     sec.idsection, sec.\"designationSection\"
              FROM semestre s
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section sec ON o.section_idsection = sec.idsection";
    
    if ($anneeAcadId) {
        $query .= " WHERE p.annee_acad_idannee_acad = :anneeAcadId";
    }
    
    $query .= " ORDER BY sec.\"designationSection\", p.\"designationPromotion\", s.\"numeroSemestre\"";
    
    $stmt = $this->db->prepare($query);
    
    if ($anneeAcadId) {
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Créer une nouvelle UE
public function createUE($codeUE, $designationUE, $description, $semestre_idsemestre) {
    $query = "INSERT INTO ue (\"codeUE\", \"designationUE\", description, semestre_idsemestre) 
              VALUES (:codeUE, :designationUE, :description, :semestre_idsemestre)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':codeUE', $codeUE);
    $stmt->bindParam(':designationUE', $designationUE);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':semestre_idsemestre', $semestre_idsemestre, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Crée une UE dans plusieurs semestres à la fois
 * 
 * @param string $codeUE Code de l'UE
 * @param string $designationUE Désignation de l'UE
 * @param string $description Description de l'UE
 * @param array $semestres Tableau des IDs de semestres
 * @return bool Succès de l'opération
 */
public function createUEMultiple($codeUE, $designationUE, $description, $semestres) {
    $success = true;
    $this->db->beginTransaction();
    
    try {
        foreach ($semestres as $semestre_id) {
            $query = "INSERT INTO ue (\"codeUE\", \"designationUE\", description, semestre_idsemestre)
                      VALUES (:codeUE, :designationUE, :description, :semestre_idsemestre)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':codeUE', $codeUE);
            $stmt->bindParam(':designationUE', $designationUE);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':semestre_idsemestre', $semestre_id, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion pour le semestre ID: " . $semestre_id);
            }
        }
        
        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}


// Mettre à jour une UE existante
public function updateUE($idUE, $codeUE, $designationUE, $description, $semestre_idsemestre) {
    $query = "UPDATE ue 
              SET \"codeUE\" = :codeUE, 
                  \"designationUE\" = :designationUE, 
                  description = :description, 
                  semestre_idsemestre = :semestre_idsemestre 
              WHERE \"idUE\" = :idUE";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':codeUE', $codeUE);
    $stmt->bindParam(':designationUE', $designationUE);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':semestre_idsemestre', $semestre_idsemestre, PDO::PARAM_INT);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    
    return $stmt->execute();
}


public function getEtudiantsByPromotion($promotionId, $anneeAcadId) {
    $query = "SELECT e.idetudiant, e.matricule, e.noms, e.adressemail, e.telephone, 
                     e.sexe, e.nationalite, p.\"designationPromotion\",e.promotion_idpromotion
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE e.promotion_idpromotion = :promotionId 
              AND e.annee_acad_idannee_acad = :anneeAcadId
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEtudiantsByPromotionAndNom($promotionId, $anneeAcadId, $search = '') {
    $query = "SELECT e.*,p.*,a.designation as annee
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN annee_acad a ON e.annee_acad_idannee_acad=a.idannee_acad
              WHERE e.promotion_idpromotion = :promotionId
              AND e.annee_acad_idannee_acad = :anneeAcadId";
    
    if (!empty($search)) {
        $query .= " AND e.noms LIKE :search";
    }
    
    $query .= " ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchParam = '%' . $search . '%';
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



public function deleteUE($idUE) {
    try {
        // Vérifier d'abord si l'UE existe
        $checkQuery = "SELECT * FROM ue WHERE \"idUE\" = :idUE";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            // L'UE n'existe pas
            return ['success' => false, 'reason' => 'not_found'];
        }
        
        // Vérifier si l'UE a des ECUEs associées
        $checkEcuesQuery = "SELECT COUNT(*) FROM ecue WHERE ue_idUE = :idUE";
        $checkEcuesStmt = $this->db->prepare($checkEcuesQuery);
        $checkEcuesStmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $checkEcuesStmt->execute();
        
        if ($checkEcuesStmt->fetchColumn() > 0) {
            // L'UE a des ECUEs associées, impossible de supprimer
            return ['success' => false, 'reason' => 'has_ecues'];
        }
        
        // Vérifier d'autres relations potentielles
        // Par exemple, vérifier si l'UE est utilisée dans d'autres tables
        
        // Commencer une transaction
        $this->db->beginTransaction();
        
        // Supprimer l'UE
        $query = "DELETE FROM ue WHERE \"idUE\" = :idUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        // Vérifier si des lignes ont été affectées
        if ($result && $stmt->rowCount() > 0) {
            $this->db->commit();
            return ['success' => true];
        } else {
            $this->db->rollBack();
            return ['success' => false, 'reason' => 'delete_failed'];
        }
    } catch (PDOException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        error_log("Erreur lors de la suppression de l'UE: " . $e->getMessage());
        return ['success' => false, 'reason' => 'exception', 'message' => $e->getMessage()];
    }
}




public function getUEById($idUE) {
    $query = "SELECT ue.*, s.\"numeroSemestre\", p.\"designationPromotion\" 
              FROM ue 
              JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              WHERE ue.\"idUE\" = :idUE";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// Méthode à ajouter dans la classe Universite
public function getSujetsValidesParSection($idSection, $anneeAcadId)
{
    $query = "SELECT s.*, e.noms, e.matricule
              FROM sujets s
              JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE o.section_idsection = :idSection
              AND s.annee_acad_idannee_acad = :anneeAcadId
              AND s.statut_validation = 'Validé'
              AND s.\"etatSujet\"='Validé'
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idSection' => $idSection,
        'anneeAcadId' => $anneeAcadId
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSujetsAvecDepotParSection($idSection, $anneeAcadId)
{
    $query = "SELECT s.*, e.noms, e.matricule
              FROM sujets s
              JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN depot_memoire d ON s.idsujets = d.sujets_idsujets
              WHERE o.section_idsection = :idSection
              AND s.annee_acad_idannee_acad = :anneeAcadId
              AND s.statut_validation = 'Validé'
              AND NOT EXISTS (
                  SELECT 1 FROM soutenance sou 
                  WHERE sou.sujets_idsujets = s.idsujets
              )
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idSection' => $idSection,
        'anneeAcadId' => $anneeAcadId
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEtudiantsBySection($idSection, $anneeAcadId)
{
    $query = "SELECT e.*
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE o.section_idsection = :idSection
              AND e.annee_acad_idannee_acad = :anneeAcadId
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idSection' => $idSection,
        'anneeAcadId' => $anneeAcadId
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getPromotionsByResponsable($idAnneeAcad, $idUser) {
    $query = "SELECT DISTINCT p.* 
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              JOIN responsable_section rs ON s.idsection = rs.section_idsection
              WHERE p.annee_acad_idannee_acad = :idAnneeAcad
              AND rs.\"idUser\" = :idUser
              AND rs.annee_acad_idannee_acad = :idAnneeAcad
              ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idAnneeAcad' => $idAnneeAcad,
        'idUser' => $idUser
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function isUserSectionResponsable($idUser, $idAnneeAcad) {
    $query = "SELECT COUNT(*) as count 
              FROM responsable_section 
              WHERE \"idUser\" = :idUser
              AND annee_acad_idannee_acad = :idAnneeAcad";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idUser' => $idUser,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}


public function isUserSectionResponsableForEcue($idUser, $idAnneeAcad, $idECUE) {
    $query = "SELECT COUNT(*) as count 
              FROM responsable_section rs
              JOIN section s ON rs.section_idsection = s.idsection
              JOIN orientation o ON s.idsection = o.section_idsection
              JOIN promotion p ON o.idorientation = p.orientation_idorientation
              JOIN semestre sem ON p.idpromotion = sem.promotion_idpromotion
              JOIN ue u ON sem.idsemestre = u.semestre_idsemestre
              JOIN ecue e ON u.\"idUE\" = e.\"UE_idUE\"
              WHERE rs.\"idUser\" = :idUser
              AND rs.annee_acad_idannee_acad = :idAnneeAcad
              AND e.\"idECUE\" = :idECUE";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idUser' => $idUser,
        'idAnneeAcad' => $idAnneeAcad,
        'idECUE' => $idECUE
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Récupère les informations d'un ECUE par son ID
 * @param int $idECUE ID de l'ECUE
 * @return array|false Données de l'ECUE ou false si non trouvé
 */
public function getEcueById($idECUE) {
    $query = "SELECT * FROM ecue WHERE \"idECUE\" = :idECUE";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idECUE' => $idECUE]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer toutes les salles
public function getSalles($search = '')
{
    $query = "SELECT * FROM salle";
    
    if (!empty($search)) {
        $query .= " WHERE \"designationSalle\" LIKE :search";
    }
    
    $query .= " ORDER BY \"designationSalle\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Créer une nouvelle salle
public function createSalle($designationSalle)
{
    $query = "INSERT INTO salle (\"designationSalle\", \"dateCreation\") VALUES (:designationSalle, NOW())";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'designationSalle' => $designationSalle
    ]);
}

// Mettre à jour une salle
public function updateSalle($idSalle, $designationSalle)
{
    $query = "UPDATE salle SET \"designationSalle\" = :designationSalle WHERE \"idSalle\" = :idSalle";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'idSalle' => $idSalle,
        'designationSalle' => $designationSalle
    ]);
}

// Supprimer une salle
public function deleteSalle($idSalle)
{
    $query = "DELETE FROM salle WHERE \"idSalle\" = :idSalle";
    $stmt = $this->db->prepare($query);
    return $stmt->execute(['idSalle' => $idSalle]);
}

// Récupérer une salle par son ID
public function getSalleById($idSalle)
{
    $query = "SELECT * FROM salle WHERE \"idSalle\" = :idSalle";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idSalle' => $idSalle]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// Ajouter ces méthodes à la classe Universite

/**
 * Récupérer tous les jurys
 */
public function getJurys($search = '', $anneeAcadId = null)
{
    $query = "SELECT b.*, a1.noms as president_nom, a2.noms as secretaire_nom, a.designation as annee_academique
              FROM bureau_jury_deliberation b
              JOIN agent a1 ON b.president_id = a1.\"idAgent\"
              JOIN agent a2 ON b.secretaire_id = a2.\"idAgent\"
              JOIN annee_acad a ON b.annee_acad_idannee_acad = a.idannee_acad";

    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(b.designation LIKE :search OR b.numero_decision LIKE :search OR a1.noms LIKE :search OR a2.noms LIKE :search OR a.designation LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($anneeAcadId !== null) {
        $whereConditions[] = "b.annee_acad_idannee_acad = :anneeAcadId";
        $params[':anneeAcadId'] = $anneeAcadId;
    }

    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }

    $query .= " ORDER BY b.date_creation DESC";

    $stmt = $this->db->prepare($query);

    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Créer un nouveau jury
 */
public function createJury($designation, $numeroDecision, $dateDecision, $presidentId, $secretaireId, $anneeAcadId, $commentaire, $idUser)
{
    $query = "INSERT INTO bureau_jury_deliberation (designation, numero_decision, date_creation, date_decision, 
                                                   president_id, secretaire_id, annee_acad_idannee_acad, 
                                                   est_actif, commentaire, \"idUser\") 
              VALUES (:designation, :numeroDecision, NOW(), :dateDecision, 
                      :presidentId, :secretaireId, :anneeAcadId, 
                      1, :commentaire, :idUser)";
    
    $stmt = $this->db->prepare($query);
    
    return $stmt->execute([
        'designation' => $designation,
        'numeroDecision' => $numeroDecision,
        'dateDecision' => $dateDecision,
        'presidentId' => $presidentId,
        'secretaireId' => $secretaireId,
        'anneeAcadId' => $anneeAcadId,
        'commentaire' => $commentaire,
        'idUser' => $idUser
    ]);
}

/**
 * Modifier un jury existant
 */
public function updateJury($idJury, $designation, $numeroDecision, $dateDecision, $presidentId, $secretaireId, $anneeAcadId, $estActif, $commentaire)
{
    $query = "UPDATE bureau_jury_deliberation 
              SET designation = :designation, 
                  numero_decision = :numeroDecision, 
                  date_decision = :dateDecision, 
                  president_id = :presidentId, 
                  secretaire_id = :secretaireId, 
                  annee_acad_idannee_acad = :anneeAcadId, 
                  est_actif = :estActif, 
                  commentaire = :commentaire 
              WHERE idbureau = :idJury";
    
    $stmt = $this->db->prepare($query);
    
    return $stmt->execute([
        'idJury' => $idJury,
        'designation' => $designation,
        'numeroDecision' => $numeroDecision,
        'dateDecision' => $dateDecision,
        'presidentId' => $presidentId,
        'secretaireId' => $secretaireId,
        'anneeAcadId' => $anneeAcadId,
        'estActif' => $estActif,
        'commentaire' => $commentaire
    ]);
}

/**
 * Supprimer un jury
 */
public function deleteJury($idJury)
{
    try {
        $this->db->beginTransaction();
        
        // Supprimer d'abord les associations avec les promotions
        $query1 = "DELETE FROM bureau_jury_promotion WHERE idbureau = :idJury";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute(['idJury' => $idJury]);
        
        // Supprimer les membres du jury
        $query2 = "DELETE FROM membre_bureau_jury WHERE idbureau = :idJury";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->execute(['idJury' => $idJury]);
        
        // Supprimer le jury lui-même
        $query3 = "DELETE FROM bureau_jury_deliberation WHERE idbureau = :idJury";
        $stmt3 = $this->db->prepare($query3);
        $stmt3->execute(['idJury' => $idJury]);
        
        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        return false;
    }
}

/**
 * Récupérer les membres d'un jury
 */
public function getJuryMembers($juryId)
{
    $query = "SELECT m.*, a.noms, DATE_FORMAT(m.date_ajout, '%d/%m/%Y %H:%i') as date_ajout 
              FROM membre_bureau_jury m
              JOIN agent a ON m.\"idAgent\" = a.\"idAgent\"
              WHERE m.idbureau = :juryId
              ORDER BY m.date_ajout";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute(['juryId' => $juryId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ajouter un membre à un jury
 */
public function addJuryMember($juryId, $memberId, $fonction)
{
    $query = "INSERT INTO membre_bureau_jury (idbureau, \"idAgent\", fonction, date_ajout) 
              VALUES (:juryId, :memberId, :fonction, NOW())";
    
    $stmt = $this->db->prepare($query);
    
    return $stmt->execute([
        'juryId' => $juryId,
        'memberId' => $memberId,
        'fonction' => $fonction
    ]);
}

/**
 * Supprimer un membre d'un jury
 */
public function removeJuryMember($memberId)
{
    $query = "DELETE FROM membre_bureau_jury WHERE idmembre = :memberId";
    $stmt = $this->db->prepare($query);
    return $stmt->execute(['memberId' => $memberId]);
}

/**
 * Récupérer les promotions associées à un jury
 */
public function getPromotionsByJury($juryId)
{
    $query = "SELECT bp.*, p.\"designationPromotion\", p.cycle, o.\"designationOrientation\" as orientationDesignation, 
                    a.designation as anneeDesignation
              FROM bureau_jury_promotion bp
              JOIN promotion p ON bp.idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
              WHERE bp.idbureau = :juryId
              ORDER BY p.\"designationPromotion\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute(['juryId' => $juryId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Assigner une promotion à un jury
 */
public function assignPromotionToJury($juryId, $promotionId, $userId)
{
    // Vérifier si cette promotion est déjà assignée à ce jury
    $checkQuery = "SELECT COUNT(*) as count FROM bureau_jury_promotion 
                  WHERE idbureau = :juryId AND idpromotion = :promotionId";
    
    $checkStmt = $this->db->prepare($checkQuery);
    $checkStmt->execute([
        'juryId' => $juryId,
        'promotionId' => $promotionId
    ]);
    
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        return false; // Déjà assignée
    }
    
    // Insérer la nouvelle association
    $query = "INSERT INTO bureau_jury_promotion (idbureau, idpromotion, date_association, \"idUser\") 
              VALUES (:juryId, :promotionId, NOW(), :userId)";
    
    $stmt = $this->db->prepare($query);
    
    return $stmt->execute([
        'juryId' => $juryId,
        'promotionId' => $promotionId,
        'userId' => $userId
    ]);
}

/**
 * Supprimer l'association entre un jury et une promotion
 */
public function removePromotionFromJury($associationId)
{
    $query = "DELETE FROM bureau_jury_promotion WHERE id = :associationId";
    $stmt = $this->db->prepare($query);
    return $stmt->execute(['associationId' => $associationId]);
}

/**
 * Récupérer un jury par son ID
 */
public function getJuryById($juryId)
{
    $query = "SELECT b.*, a1.noms as president_nom, a2.noms as secretaire_nom, a.designation as annee_academique
              FROM bureau_jury_deliberation b
              JOIN agent a1 ON b.president_id = a1.\"idAgent\"
              JOIN agent a2 ON b.secretaire_id = a2.\"idAgent\"
              JOIN annee_acad a ON b.annee_acad_idannee_acad = a.idannee_acad
              WHERE b.idbureau = :juryId";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute(['juryId' => $juryId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les jurys présidés par un agent
 * @param int $agentId ID de l'agent
 * @return array Liste des jurys présidés
 */
public function getJurysPresidesByAgent($agentId)
{
    $query = "SELECT * FROM bureau_jury_deliberation 
              WHERE president_id = :agentId AND est_actif = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un agent est président d'un jury spécifique
 * @param int $agentId ID de l'agent
 * @param int $juryId ID du bureau de jury
 * @return bool True si l'agent est président du jury, false sinon
 */
public function isAgentJuryPresident($agentId, $juryId)
{
    $query = "SELECT COUNT(*) AS count FROM bureau_jury_deliberation 
              WHERE president_id = :agentId AND idbureau = :juryId AND est_actif = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
    $stmt->bindParam(':juryId', $juryId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Récupère la configuration de délibération pour un jury, une session et une année
 * @param int $idBureau ID du bureau de jury
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array|null Configuration de délibération ou null si inexistante
 */
public function getDeliberationConfig($idBureau, $sessionId, $anneeId)
{
    try {
        $query = "SELECT * FROM configuration_deliberation 
                  WHERE idbureau = :idBureau 
                  AND session_idsession = :sessionId 
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idBureau', $idBureau, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Retourner explicitement null si aucun résultat n'est trouvé
        return $result !== false ? $result : null;
    } catch (PDOException $e) {
        error_log("Erreur dans getDeliberationConfig: " . $e->getMessage());
        return null;
    }
}


public function saveDeliberationConfig($configParams)
{
    // Vérifier si une configuration existe déjà
    $existingConfig = $this->getDeliberationConfig(
        $configParams['idbureau'],
        $configParams['session_idsession'],
        $configParams['annee_acad_idannee_acad']
    );
   
    if ($existingConfig) {
        // Mettre à jour la configuration existante
        $query = "UPDATE configuration_deliberation SET
                  compensation_intra_ue = :compensation_intra_ue,
                  seuil_compensation_intra_ue = :seuil_compensation_intra_ue,
                  compensation_inter_ue = :compensation_inter_ue,
                  seuil_compensation_inter_ue = :seuil_compensation_inter_ue,
                  exiger_meme_credit_ue = :exiger_meme_credit_ue,
                  compensation_inter_semestre = :compensation_inter_semestre,
                  seuil_compensation_inter_semestre = :seuil_compensation_inter_semestre,
                  limiter_compensation_annee = :limiter_compensation_annee,
                  note_passage = :note_passage,
                  pourcentage_passage_semestre = :pourcentage_passage_semestre,
                  calculer_moyenne_avec_notes_vides = :calculer_moyenne_avec_notes_vides,
                  date_creation = NOW(),
                  \"idUser\" = :idUser
                  WHERE idconfig = :idconfig";
       
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idconfig', $existingConfig['idconfig'], PDO::PARAM_INT);
    } else {
        // Créer une nouvelle configuration
        $query = "INSERT INTO configuration_deliberation (
                  idbureau, session_idsession, annee_acad_idannee_acad,
                  compensation_intra_ue, seuil_compensation_intra_ue,
                  compensation_inter_ue, seuil_compensation_inter_ue,
                  exiger_meme_credit_ue, compensation_inter_semestre,
                  seuil_compensation_inter_semestre, limiter_compensation_annee,
                  note_passage, pourcentage_passage_semestre,
                  calculer_moyenne_avec_notes_vides,
                  date_creation, \"idUser\")
                  VALUES (
                  :idbureau, :session_idsession, :annee_acad_idannee_acad,
                  :compensation_intra_ue, :seuil_compensation_intra_ue,
                  :compensation_inter_ue, :seuil_compensation_inter_ue,
                  :exiger_meme_credit_ue, :compensation_inter_semestre,
                  :seuil_compensation_inter_semestre, :limiter_compensation_annee,
                  :note_passage, :pourcentage_passage_semestre,
                  :calculer_moyenne_avec_notes_vides,
                  NOW(), :idUser)";
       
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idbureau', $configParams['idbureau'], PDO::PARAM_INT);
        $stmt->bindParam(':session_idsession', $configParams['session_idsession'], PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_idannee_acad', $configParams['annee_acad_idannee_acad'], PDO::PARAM_INT);
    }
   
    // Paramètres communs pour l'insertion et la mise à jour
    $stmt->bindParam(':compensation_intra_ue', $configParams['compensation_intra_ue'], PDO::PARAM_INT);
    $stmt->bindParam(':seuil_compensation_intra_ue', $configParams['seuil_compensation_intra_ue'], PDO::PARAM_STR);
    $stmt->bindParam(':compensation_inter_ue', $configParams['compensation_inter_ue'], PDO::PARAM_INT);
    $stmt->bindParam(':seuil_compensation_inter_ue', $configParams['seuil_compensation_inter_ue'], PDO::PARAM_STR);
    $stmt->bindParam(':exiger_meme_credit_ue', $configParams['exiger_meme_credit_ue'], PDO::PARAM_INT);
    $stmt->bindParam(':compensation_inter_semestre', $configParams['compensation_inter_semestre'], PDO::PARAM_INT);
    $stmt->bindParam(':seuil_compensation_inter_semestre', $configParams['seuil_compensation_inter_semestre'], PDO::PARAM_STR);
    $stmt->bindParam(':limiter_compensation_annee', $configParams['limiter_compensation_annee'], PDO::PARAM_INT);
    $stmt->bindParam(':note_passage', $configParams['note_passage'], PDO::PARAM_STR);
    $stmt->bindParam(':pourcentage_passage_semestre', $configParams['pourcentage_passage_semestre'], PDO::PARAM_STR);
    $stmt->bindParam(':calculer_moyenne_avec_notes_vides', $configParams['calculer_moyenne_avec_notes_vides'], PDO::PARAM_INT);
    $stmt->bindParam(':idUser', $configParams['idUser'], PDO::PARAM_INT);
   
    return $stmt->execute();
}


/**
 * Récupère toutes les sessions disponibles
 * @return array Liste des sessions
 */
public function getAllSessions()
{
    $query = "SELECT * FROM session ORDER BY \"designSession\" ASC";
    $stmt = $this->db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les délibérations non clôturées pour un jury
 * @param int $idBureau ID du bureau de jury
 * @param int $anneeId ID de l'année académique (optionnel)
 * @return array Liste des délibérations
 */
public function getOpenDeliberationsByJury($idBureau, $anneeId = null)
{
    $query = "SELECT d.*, p.\"designationPromotion\", s.\"designSession\"
              FROM deliberation d
              JOIN promotion p ON d.idpromotion = p.idpromotion
              JOIN session s ON d.session_idsession = s.idsession
              WHERE d.idbureau = :idBureau 
              AND d.statut IN ('En préparation', 'Effectuée')";
    
    if ($anneeId) {
        $query .= " AND d.annee_acad_id = :anneeId";
    }
    
    $query .= " ORDER BY d.date_creation DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idBureau', $idBureau, PDO::PARAM_INT);
    
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si des notes ont été saisies pour une délibération
 * @param int $deliberationId ID de la délibération
 * @return bool True si des notes existent, false sinon
 */
public function hasDeliberationNotes($deliberationId)
{
    // Récupérer les informations de la délibération
    $query = "SELECT d.idpromotion, d.session_idsession, d.annee_acad_id
              FROM deliberation d
              WHERE d.iddeliberation = :deliberationId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $deliberation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$deliberation) {
        return false;
    }
    
    // Vérifier si des notes existent pour cette promotion, session et année
    $query = "SELECT COUNT(*) AS count
              FROM etudiant e
              JOIN cotes_grille cg ON e.matricule = cg.matricule
              WHERE e.promotion_idpromotion = :promotionId
              AND cg.session_idsession = :sessionId
              AND cg.annee_acad_id = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $deliberation['idpromotion'], PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $deliberation['session_idsession'], PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $deliberation['annee_acad_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// Dans la classe Universite

// Méthode pour récupérer les statistiques d'un ECUE (réussites, échecs, manques, moyenne, max, taux de réussite)
public function getStatistiquesEcue($ecueId, $sessionId, $anneeId, $promotionId) {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN MF >= 10 THEN 1 ELSE 0 END) as reussite,
                SUM(CASE WHEN MF < 10 AND MF IS NOT NULL THEN 1 ELSE 0 END) as echec,
                SUM(CASE WHEN MF IS NULL THEN 1 ELSE 0 END) as manquant,
                AVG(MF) as moyenne,
                MAX(MF) as max
              FROM cotes_grille cg
              JOIN etudiant e ON cg.matricule = e.matricule
              WHERE \"ECUE_idECUE\" = :ecueId
              AND session_idsession = :sessionId
              AND annee_acad_id = :anneeId
              AND e.promotion_idpromotion = :promotionId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer le taux de réussite
    $taux_reussite = 0;
    if ($result['total'] > 0) {
        $taux_reussite = ($result['reussite'] / $result['total']) * 100;
    }
    
    return [
        'reussite' => $result['reussite'] ?? 0,
        'echec' => $result['echec'] ?? 0,
        'manquant' => $result['manquant'] ?? 0,
        'moyenne' => $result['moyenne'] ?? 0,
        'max' => $result['max'] ?? 0,
        'taux_reussite' => $taux_reussite
    ];
}

// Dans la classe Universite 

// Méthode pour vérifier si un agent a accès à une promotion via un jury
public function canAgentAccessPromotion($agentId, $promotionId) {
    $query = "SELECT COUNT(*) FROM bureau_jury_deliberation bj
              JOIN bureau_jury_promotion bjp ON bj.idbureau = bjp.idbureau
              WHERE (bj.president_id = :agentId OR bj.secretaire_id = :agentId 
                    OR EXISTS (SELECT 1 FROM membre_bureau_jury mbj WHERE mbj.idbureau = bj.idbureau AND mbj.\"idAgent\" = :agentId))
              AND bjp.idpromotion = :promotionId
              AND bj.est_actif = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn() > 0;
}

// Méthode pour récupérer les bureaux de jury où un agent est membre
public function getJuryBureauxByAgent($agentId) {
    $query = "SELECT DISTINCT bj.* FROM bureau_jury_deliberation bj
              WHERE (bj.president_id = :agentId 
              OR bj.secretaire_id = :agentId
              OR EXISTS (SELECT 1 FROM membre_bureau_jury mbj WHERE mbj.idbureau = bj.idbureau AND mbj.\"idAgent\" = :agentId))
              AND bj.est_actif = 1
              ORDER BY bj.date_creation DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Méthode pour récupérer les cotes d'un ECUE
public function getCotesGrille($ecueId, $sessionId, $anneeId) {
    $query = "SELECT * FROM cotes_grille 
              WHERE \"ECUE_idECUE\" = :ecueId 
              AND session_idsession = :sessionId 
              AND annee_acad_id = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Méthode pour sauvegarder une cote dans la table cotes_grille
public function saveCoteGrille($ecueId, $sessionId, $anneeId, $matricule, $cc, $ex, $mf, $userId) {
    try {
        // Vérifier si une cote existe déjà pour cet étudiant/ECUE/session/année
        $checkQuery = "SELECT idpoints FROM cotes_grille WHERE 
                       \"ECUE_idECUE\" = :ecueId AND 
                       session_idsession = :sessionId AND 
                       matricule = :matricule AND 
                       annee_acad_id = :anneeId";
       
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $checkStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $checkStmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $checkStmt->bindParam(':matricule', $matricule);
        $checkStmt->execute();
       
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
       
        if ($existing) {
            // Mise à jour d'une cote existante
            $query = "UPDATE cotes_grille SET 
                     CC = :cc, 
                     EX = :ex, 
                     MF = :mf, 
                     date_compilation = NOW(), 
                     \"idUser\" = :userId 
                     WHERE idpoints = :idpoints";
           
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idpoints', $existing['idpoints'], PDO::PARAM_INT);
            $stmt->bindParam(':cc', $cc, PDO::PARAM_STR);
            $stmt->bindParam(':ex', $ex, PDO::PARAM_STR);
            $stmt->bindParam(':mf', $mf, PDO::PARAM_STR);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
           
            return $stmt->execute();
        } else {
            // Insertion d'une nouvelle cote
            $query = "INSERT INTO cotes_grille 
                     (\"ECUE_idECUE\", session_idsession, annee_acad_id, matricule, CC, EX, MF, date_compilation, \"idUser\") 
                     VALUES 
                     (:ecueId, :sessionId, :anneeId, :matricule, :cc, :ex, :mf, NOW(), :userId)";
           
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->bindParam(':cc', $cc, PDO::PARAM_STR);
            $stmt->bindParam(':ex', $ex, PDO::PARAM_STR);
            $stmt->bindParam(':mf', $mf, PDO::PARAM_STR);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
           
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        error_log('Erreur dans saveCoteGrille: ' . $e->getMessage());
        return false;
    }
}


// Méthode pour récupérer la configuration du calcul de la moyenne
public function getConfigurationMoyenne($ecueId, $sessionId, $anneeId) {
    $query = "SELECT * FROM configuration_moyenne 
              WHERE \"idECUE\" = :ecueId 
              AND session_idsession = :sessionId 
              AND annee_acad_id = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Retourner les valeurs par défaut depuis la configuration de l'université
    if (!$result) {
        $configDefaut = $this->getPonderationsDefaut();
        return [
            'ponderation_cc' => $configDefaut['ponderation_cc'],
            'ponderation_ex' => $configDefaut['ponderation_ex']
        ];
    }
    
    return $result;
}

/**
 * Récupérer les pondérations par défaut depuis la configuration de l'université
 */
public function getPonderationsDefaut() {
    $connexion = Connexion::getInstance()->getPDO();
    
    $stmt = $connexion->prepare("
        SELECT ponderation_cc_defaut, ponderation_ex_defaut 
        FROM configuration_universite 
        LIMIT 1
    ");
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return [
            'ponderation_cc' => 0.4,
            'ponderation_ex' => 0.6
        ];
    }
    
    return [
        'ponderation_cc' => (float)$result['ponderation_cc_defaut'],
        'ponderation_ex' => (float)$result['ponderation_ex_defaut']
    ];
}

// Méthode pour récupérer les ECUEs d'un semestre
public function getEcuesBySemestre($semestreId) {
    $query = "SELECT e.*, ue.\"idUE\", ue.\"codeUE\", ue.\"designationUE\" 
              FROM ecue e
              JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
              WHERE ue.semestre_idsemestre = :semestreId
              AND e.\"estVisible\" = 1
              ORDER BY ue.\"designationUE\", e.\"designationECUE\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getCotesGrilleByEcue($ecueId, $sessionId, $anneeId) {
    try {
        $query = "SELECT * FROM cotes_grille 
                 WHERE \"ECUE_idECUE\" = :ecueId 
                 AND session_idsession = :sessionId 
                 AND annee_acad_id = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur dans getCotesGrilleByEcue: ' . $e->getMessage());
        return [];
    }
}

/**
 * Enregistre l'historique des modifications de cotes
 */
public function saveHistoriqueCotes($ecueId, $sessionId, $anneeId, $matricule, 
                                   $cc_avant, $ex_avant, $mf_avant, 
                                   $cc_apres, $ex_apres, $mf_apres, 
                                   $motif, $userId) {
    try {
        $query = "INSERT INTO historique_cotes 
                 (\"ECUE_idECUE\", session_idsession, annee_acad_id, matricule, 
                 cc_avant, ex_avant, mf_avant, 
                 cc_apres, ex_apres, mf_apres, 
                 motif, \"idUser\") 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            $ecueId, $sessionId, $anneeId, $matricule, 
            $cc_avant, $ex_avant, $mf_avant, 
            $cc_apres, $ex_apres, $mf_apres, 
            $motif, $userId
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement de l'historique des cotes: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les cotes d'un étudiant pour un ECUE, une session et une année académique
 */
public function getCoteGrille($ecueId, $sessionId, $anneeId, $matricule) {
    try {
        $query = "SELECT CC, EX, MF FROM cotes_grille 
                 WHERE \"ECUE_idECUE\" = ? AND session_idsession = ? 
                 AND annee_acad_id = ? AND matricule = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$ecueId, $sessionId, $anneeId, $matricule]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des cotes: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les frais disponibles
 */
public function getAllFees() {
$query = "SELECT * FROM frais ORDER BY designation ASC";
$stmt = $this->db->prepare($query);
$stmt->execute();
return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les frais affectés à une promotion spécifique
 */
public function getFeesForPromotion($promotionId) {
    $query = "SELECT f.* FROM frais f
              JOIN affectation_frais af ON f.id = af.frais_id
              WHERE af.promotion_id = :promotionId
              ORDER BY f.designation ASC";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['promotionId' => $promotionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un agent est président d'un jury
 */
public function isJuryPresident($agentId) {
    try {
        $query = "SELECT COUNT(*) FROM bureau_jury_deliberation 
                 WHERE president_id = ? AND est_actif = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$agentId]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du statut de président: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère l'historique des modifications de cotes pour un étudiant
 */
/**
 * Récupère l'historique des modifications de cotes pour un étudiant
 */
/**
 * Récupère l'historique des modifications de cotes pour un étudiant
 */
public function getHistoriqueCotes($ecueId, $sessionId, $anneeId, $matricule) {
    try {
        // Vérifier que les paramètres sont valides
        if (empty($ecueId) || empty($sessionId) || empty($anneeId) || empty($matricule)) {
            error_log("Paramètres invalides pour getHistoriqueCotes");
            return [];
        }
        
        // Requête SQL pour récupérer l'historique des cotes avec jointure sur t_users
        $query = "SELECT h.*, u.\"nomUser\" as nom_utilisateur 
                 FROM historique_cotes h
                 LEFT JOIN t_users u ON h.\"idUser\" = u.\"idUser\"
                 WHERE h.\"ECUE_idECUE\" = :ecueId 
                 AND h.session_idsession = :sessionId 
                 AND h.annee_acad_id = :anneeId 
                 AND h.matricule = :matricule
                 ORDER BY h.date_modification DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->execute();
        
        // Récupérer les résultats
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log pour le débogage
        error_log("Requête historique_cotes pour ECUE=$ecueId, Session=$sessionId, Annee=$anneeId, Matricule=$matricule: " . count($result) . " résultats");
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur dans getHistoriqueCotes: " . $e->getMessage());
        return [];
    }
}



/**
 * Récupère les informations d'une session par son ID
 */
public function getSessionById($sessionId) {
    try {
        $query = "SELECT * FROM session WHERE idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des informations de session: " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les informations d'une session par son ID
 */
public function getSessionById2($sessionId) {
    try {
        $query = "SELECT * FROM session WHERE idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si un résultat a été trouvé
        if (!$result) {
            error_log("Aucune session trouvée avec l'ID: " . $sessionId);
            return null;
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des informations de session: " . $e->getMessage());
        return null;
    }
}


/**
 * Récupère le nombre de modifications pour chaque étudiant
 */
public function getHistoriqueCountByEcue($ecueId, $sessionId, $anneeId) {
    try {
        $query = "SELECT matricule, COUNT(*) as count 
                 FROM historique_cotes 
                 WHERE \"ECUE_idECUE\" = ? 
                 AND session_idsession = ? 
                 AND annee_acad_id = ? 
                 GROUP BY matricule";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$ecueId, $sessionId, $anneeId]);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['matricule']] = $row['count'];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage de l'historique: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère une délibération existante pour les paramètres donnés
 * 
 * @param int $bureauId ID du bureau de jury
 * @param int $promotionId ID de la promotion
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array|null Données de la délibération ou null si aucune n'existe
 */
public function getDeliberationExistante($bureauId, $promotionId, $sessionId, $anneeId) {
    try {
        $query = "SELECT d.* 
                  FROM deliberation d 
                  WHERE d.idbureau = :bureauId 
                  AND d.idpromotion = :promotionId 
                  AND d.session_idsession = :sessionId 
                  AND d.annee_acad_id = :anneeId
                  ORDER BY d.date_creation DESC
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération de la délibération existante: ' . $e->getMessage());
        return null;
    }
}

/**
 * Récupère la configuration de délibération pour un bureau de jury
 * 
 * @param int $bureauId ID du bureau de jury
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Configuration de délibération
 */
public function getConfigurationDeliberation($bureauId, $sessionId, $anneeId) {
    try {
        $query = "SELECT * FROM configuration_deliberation 
                  WHERE idbureau = :bureauId 
                  AND session_idsession = :sessionId 
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si aucune configuration n'existe, retourner les valeurs par défaut
        if (!$result) {
            return [
                'compensation_intra_ue' => 1,
                'seuil_compensation_intra_ue' => 8.00,
                'compensation_inter_ue' => 1,
                'seuil_compensation_inter_ue' => 8.00,
                'exiger_meme_credit_ue' => 1,
                'compensation_inter_semestre' => 0,
                'seuil_compensation_inter_semestre' => 8.00,
                'limiter_compensation_annee' => 1,
                'note_passage' => 10.00,
                'pourcentage_passage_semestre' => 50.00
            ];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération de la configuration de délibération: ' . $e->getMessage());
        // Retourner les valeurs par défaut en cas d'erreur
        return [
            'compensation_intra_ue' => 1,
            'seuil_compensation_intra_ue' => 8.00,
            'compensation_inter_ue' => 1,
            'seuil_compensation_inter_ue' => 8.00,
            'exiger_meme_credit_ue' => 1,
            'compensation_inter_semestre' => 0,
            'seuil_compensation_inter_semestre' => 8.00,
            'limiter_compensation_annee' => 1,
            'note_passage' => 10.00,
            'pourcentage_passage_semestre' => 50.00
        ];
    }
}

/**
 * Récupère les bureaux de jury associés à une promotion
 * 
 * @param int $promotionId ID de la promotion
 * @param bool $actifSeulement Si true, ne récupère que les jurys actifs
 * @return array Liste des bureaux de jury
 */
public function getJurysByPromotion($promotionId, $actifSeulement = true) {
    try {
        $query = "SELECT j.* 
                  FROM jury j
                  JOIN jury_promotion jp ON j.idbureau = jp.idbureau
                  WHERE jp.idpromotion = :promotionId";
        
        if ($actifSeulement) {
            $query .= " AND jp.actif = 1";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des jurys par promotion: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère les séances de délibération selon les filtres
 * @param int $bureauId ID du bureau
 * @param int $anneeId ID de l'année académique
 * @param int $sessionId ID de la session
 * @return array Tableau des délibérations
 */
public function getDeliberationsByFilters($bureauId, $anneeId, $sessionId) {
    $query = "SELECT d.*, p.\"designationPromotion\", u.\"nomUser\" as nom_createur
              FROM deliberation d
              JOIN promotion p ON d.idpromotion = p.idpromotion
              JOIN t_users u ON d.\"idUser\" = u.\"idUser\"
              WHERE d.idbureau = :bureauId
              AND d.session_idsession = :sessionId
              AND d.annee_acad_id = :anneeId
              ORDER BY d.date_deliberation DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère une délibération par son ID
 * @param int $id ID de la délibération
 * @return array|false Délibération ou false si non trouvée
 */
public function getDeliberationById($id) {
    $query = "SELECT d.*, p.\"designationPromotion\", u.\"nomUser\" as nom_createur
              FROM deliberation d
              JOIN promotion p ON d.idpromotion = p.idpromotion
              JOIN t_users u ON d.\"idUser\" = u.\"idUser\"
              WHERE d.iddeliberation = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si une délibération existe pour ces filtres
 * @param int $bureauId ID du bureau
 * @param int $promotionId ID de la promotion
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array|false Délibération si elle existe, sinon false
 */
public function getDeliberationByFilters($bureauId, $promotionId, $sessionId, $anneeId) {
    $query = "SELECT * FROM deliberation 
              WHERE idbureau = :bureauId
              AND idpromotion = :promotionId
              AND session_idsession = :sessionId
              AND annee_acad_id = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crée une nouvelle délibération
 * @param int $bureauId ID du bureau
 * @param int $promotionId ID de la promotion
 * @param string $dateDeliberation Date de la délibération
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param string $commentaire Commentaire
 * @param int $userId ID de l'utilisateur créateur
 * @return int|false ID de la délibération créée ou false si échec
 */
public function createDeliberation($bureauId, $promotionId, $dateDeliberation, $sessionId, $anneeId, $commentaire, $userId) {
    $this->db->beginTransaction();
    
    try {
        $query = "INSERT INTO deliberation 
                  (idbureau, idpromotion, date_deliberation, session_idsession, commentaire, statut, \"idUser\", annee_acad_id, date_creation) 
                  VALUES (:bureauId, :promotionId, :dateDeliberation, :sessionId, :commentaire, 'En préparation', :userId, :anneeId, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':dateDeliberation', $dateDeliberation);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $deliberationId = $this->db->lastInsertId();
        
        
        $this->db->commit();
        return $deliberationId;
    } catch (Exception $e) {
        $this->db->rollBack();
        return false;
    }
}

/**
 * Met à jour le statut d'une délibération
 * @param int $deliberationId ID de la délibération
 * @param string $nouveauStatut Nouveau statut
 * @param string $motif Motif du changement
 * @param int $userId ID de l'utilisateur
 * @return bool Succès ou échec
 */
public function updateDeliberationStatus($deliberationId, $nouveauStatut, $motif, $userId) {
    $this->db->beginTransaction();
    
    try {
        $query = "UPDATE deliberation 
                  SET statut = :statut 
                  WHERE iddeliberation = :deliberationId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':statut', $nouveauStatut);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->execute();
        
        
        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        return false;
    }
}

/**
 * Initialise le processus de délibération
 * @param int $deliberationId ID de la délibération
 * @param int $userId ID de l'utilisateur
 * @return bool Succès ou échec
 */
public function initializeDeliberationProcess($deliberationId, $userId) {
    $this->db->beginTransaction();
    
    try {
        // Étapes du processus
        $etapes = [
            'Initialisation', 
            'Calcul ECUE', 
            'Calcul UE', 
            'Compensation intra-UE', 
            'Compensation inter-UE',
            'Compensation inter-semestre', 
            'Décisions jury', 
            'Finalisation', 
            'Validation'
        ];
        
        // Supprimer les étapes existantes si la délibération a déjà été lancée
        $query = "DELETE FROM processus_deliberation WHERE iddeliberation = :deliberationId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Création des étapes du processus
        foreach ($etapes as $etape) {
            $query = "INSERT INTO processus_deliberation (iddeliberation, etape, statut, progression, \"idUser\") 
                      VALUES (:deliberationId, :etape, 'En attente', 0, :userId)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
            $stmt->bindParam(':etape', $etape);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Récupérer la configuration de délibération pour cette session
        $deliberation = $this->getDeliberationById($deliberationId);
        
        // Configurer la délibération si pas déjà fait
        $configExists = $this->checkDeliberationConfigExists($deliberation['idbureau'], $deliberation['session_idsession'], $deliberation['annee_acad_id']);
        
        
        // Marquer l'étape d'initialisation comme en cours
        $query = "UPDATE processus_deliberation 
                  SET statut = 'En cours', date_debut = NOW() 
                  WHERE iddeliberation = :deliberationId AND etape = 'Initialisation'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->execute();
        
       
        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        return false;
    }
}

/**
 * Récupère les documents liés à une délibération
 * @param int $deliberationId ID de la délibération
 * @return array Tableau des documents
 */
public function getDeliberationDocuments($deliberationId) {
    // Déterminer les types de documents disponibles
    $documents = [];
    
    // Vérifier si un PV existe
    $deliberation = $this->getDeliberationById($deliberationId);
    
    if ($deliberation && !empty($deliberation['proces_verbal'])) {
        $documents[] = [
            'id' => 'pv_' . $deliberationId,
            'nom' => 'Procès-verbal de délibération',
            'type' => 'pdf',
            'url' => 'uploads/pv/' . $deliberation['proces_verbal'],
            'date_creation' => $deliberation['date_creation']
        ];
    }
    
    // Vérifier les documents générés pour les résultats
    $query = "SELECT * FROM documents_deliberation 
              WHERE iddeliberation = :deliberationId 
              ORDER BY date_creation DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $docsFromDb = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($docsFromDb as $doc) {
        $documents[] = [
            'id' => $doc['id'],
            'nom' => $doc['titre'],
            'type' => $doc['type_document'],
            'url' => 'uploads/deliberation/' . $doc['fichier'],
            'date_creation' => $doc['date_creation']
        ];
    }
    
    return $documents;
}

/**
 * Vérifie si une configuration de délibération existe
 * @param int $bureauId ID du bureau
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return bool True si existe, false sinon
 */
private function checkDeliberationConfigExists($bureauId, $sessionId, $anneeId) {
    $query = "SELECT COUNT(*) as count FROM configuration_deliberation 
              WHERE idbureau = :bureauId 
              AND session_idsession = :sessionId 
              AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Récupère les informations d'une année académique par son ID
 * 
 * @param int $id ID de l'année académique
 * @return array|null Les données de l'année académique ou null si non trouvée
 */
public function getAnneeAcademiqueById($id) {
    try {
        $query = "SELECT * FROM annee_acad WHERE idannee_acad = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'année académique: " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère toutes les UE pour une promotion donnée
 * @param int $promotionId ID de la promotion
 * @return array Liste des UE
 */
public function getUEsByPromotion($promotionId) {
    $query = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\", u.description, s.\"numeroSemestre\"
              FROM ue u
              JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              WHERE s.promotion_idpromotion = :promotionId
              ORDER BY u.\"codeUE\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les semestres regroupés par numéro
 * @param string $search Terme de recherche (optionnel)
 * @return array Liste des semestres regroupés
 */
public function getSemestresGroupes($search = '') {
    $query = "SELECT DISTINCT \"numeroSemestre\" 
              FROM semestre 
              WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND \"numeroSemestre\" LIKE :search";
    }
    
    $query .= " ORDER BY \"numeroSemestre\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les instances d'un semestre par son numéro
 * @param string $numeroSemestre Numéro du semestre
 * @param string $search Terme de recherche (optionnel)
 * @return array Liste des instances du semestre
 */
public function getSemestresByNumero($numeroSemestre, $search = '') {
    $query = "SELECT s.idsemestre, s.\"numeroSemestre\", s.\"dateEnregistrement\", 
                     p.idpromotion, p.\"designationPromotion\", aa.designation as annee
              FROM semestre s
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE s.\"numeroSemestre\" = :numeroSemestre";
    
    if (!empty($search)) {
        $query .= " AND (p.\"designationPromotion\" LIKE :search OR aa.designation LIKE :search)";
    }
    
    $query .= " ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':numeroSemestre', $numeroSemestre, PDO::PARAM_STR);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les semestres par leurs IDs
 * @param array $ids Tableau d'IDs de semestres
 * @return array Liste des semestres
 */
public function getSemestresByIds($ids) {
    if (empty($ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $query = "SELECT s.idsemestre, s.\"numeroSemestre\", s.\"dateEnregistrement\", 
                     s.promotion_idpromotion, p.\"designationPromotion\", aa.designation as annee
              FROM semestre s
              JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              WHERE s.idsemestre IN ($placeholders)
              ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    // Binder les valeurs
    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Met à jour le numéro d'un semestre
 * @param int $id ID du semestre
 * @param string $numeroSemestre Nouveau numéro de semestre
 * @return bool Succès de l'opération
 */
public function updateSemestreNumero($id, $numeroSemestre) {
    $query = "UPDATE semestre SET \"numeroSemestre\" = :numeroSemestre WHERE idsemestre = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':numeroSemestre', $numeroSemestre, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}


/**
 * Récupère les UE regroupées par code et désignation
 * @param int $anneeAcadId ID de l'année académique
 * @param string $search Terme de recherche (optionnel)
 * @param int $sectionId ID de la section (optionnel)
 * @return array Liste des UE regroupées
 */
public function getUEsGroupees($anneeAcadId, $search = '', $sectionId = 0, $promotionId = 0) {
    $query = "SELECT DISTINCT u.\"codeUE\", u.\"designationUE\", s.\"designationSection\"
              FROM ue u
              JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
              JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              WHERE p.annee_acad_idannee_acad = :anneeAcadId";
    
    if (!empty($search)) {
        $query .= " AND (u.\"codeUE\" LIKE :search OR u.\"designationUE\" LIKE :search OR sem.\"numeroSemestre\" LIKE :search 
                         OR p.\"designationPromotion\" LIKE :search OR s.\"designationSection\" LIKE :search)";
    }
    
    if ($sectionId > 0) {
        $query .= " AND s.idsection = :sectionId";
    }
    
    if ($promotionId > 0) {
        $query .= " AND p.idpromotion = :promotionId";
    }
    
    $query .= " ORDER BY s.\"designationSection\", u.\"codeUE\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    if ($sectionId > 0) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    
    if ($promotionId > 0) {
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les instances d'une UE par son code et sa désignation
 * @param string $codeUE Code de l'UE
 * @param string $designationUE Désignation de l'UE
 * @param int $anneeAcadId ID de l'année académique
 * @param string $search Terme de recherche (optionnel)
 * @param int $sectionId ID de la section (optionnel)
 * @param int $promotionId ID de la promotion (optionnel)
 * @return array Liste des instances de l'UE
 */
public function getUEsByCodeDesignation($codeUE, $designationUE, $anneeAcadId, $search = '', $sectionId = 0, $promotionId = 0) {
    $query = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\", u.description, u.semestre_idsemestre,
                     sem.\"numeroSemestre\", p.\"designationPromotion\", p.idpromotion as promotion_idpromotion, s.\"designationSection\"
              FROM ue u
              JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
              JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              WHERE u.\"codeUE\" = :codeUE 
              AND u.\"designationUE\" = :designationUE
              AND p.annee_acad_idannee_acad = :anneeAcadId";
    
    if (!empty($search)) {
        $query .= " AND (sem.\"numeroSemestre\" LIKE :search OR p.\"designationPromotion\" LIKE :search 
                         OR s.\"designationSection\" LIKE :search)";
    }
    
    if ($sectionId > 0) {
        $query .= " AND s.idsection = :sectionId";
    }
    
    if ($promotionId > 0) {
        $query .= " AND p.idpromotion = :promotionId";
    }
    
    $query .= " ORDER BY sem.\"numeroSemestre\", p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':codeUE', $codeUE, PDO::PARAM_STR);
    $stmt->bindParam(':designationUE', $designationUE, PDO::PARAM_STR);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    
    if ($sectionId > 0) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    
    if ($promotionId > 0) {
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les UE par leurs IDs
 * @param array $ids Tableau d'IDs d'UE
 * @return array Liste des UE
 */
public function getUEsByIds($ids) {
    if (empty($ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $query = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\", u.description, u.semestre_idsemestre,
                     sem.\"numeroSemestre\", p.\"designationPromotion\", s.\"designationSection\"
              FROM ue u
              JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
              JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              WHERE u.\"idUE\" IN ($placeholders)
              ORDER BY s.\"designationSection\", p.\"designationPromotion\", sem.\"numeroSemestre\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    // Binder les valeurs
    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Met à jour une UE dans le cadre d'une mise à jour groupée
 * @param int $id ID de l'UE
 * @param string $codeUE Nouveau code UE
 * @param string $designationUE Nouvelle désignation
 * @param string $description Nouvelle description
 * @return bool Succès de l'opération
 */
public function updateUEGroupe($id, $codeUE, $designationUE, $description) {
    $query = "UPDATE ue SET 
              \"codeUE\" = :codeUE, 
              \"designationUE\" = :designationUE, 
              description = :description 
              WHERE \"idUE\" = :id";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':codeUE', $codeUE, PDO::PARAM_STR);
    $stmt->bindParam(':designationUE', $designationUE, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Récupère les informations d'un étudiant par son matricule
 * 
 * @param string $matricule Matricule de l'étudiant
 * @return array|false Données de l'étudiant ou false si non trouvé
 */
public function getStudentByMatricule($matricule) {
    try {
        $query = "SELECT e.*, p.\"designationPromotion\", o.\"designationOrientation\", 
                  a.designation as annee, s.\"designationSection\"
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN section s ON o.section_idsection = s.idsection
                  JOIN annee_acad a ON a.idannee_acad=e.annee_acad_idannee_acad
                  WHERE e.matricule = :matricule";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : false;
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de l'étudiant par matricule: " . $e->getMessage());
        return false;
    }
}



/**
 * Récupère les statistiques d'inscription pour une année académique
 * @param int $anneeId ID de l'année académique
 * @return array Statistiques d'inscription
 */
public function getInscriptionsStatsByYear($anneeId) {
    $stats = [];

    // Récupérer les statistiques par promotion
    $query = "SELECT p.idpromotion, p.\"designationPromotion\",
              COUNT(*) as total,
              SUM(CASE WHEN e.sexe = 'Masculin' THEN 1 ELSE 0 END) as masculin,
              SUM(CASE WHEN e.sexe = 'Feminin' THEN 1 ELSE 0 END) as feminin
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE e.annee_acad_idannee_acad = :anneeId
              GROUP BY p.idpromotion, p.\"designationPromotion\"
              ORDER BY p.\"designationPromotion\"";

    $stmt = $this->db->prepare($query);
    $stmt->execute(['anneeId' => $anneeId]);
    $stats['promotions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer le total comme somme des totaux par promotion
    $stats['total'] = array_sum(array_column($stats['promotions'], 'total'));
    $stats['masculin'] = array_sum(array_column($stats['promotions'], 'masculin'));
    $stats['feminin'] = array_sum(array_column($stats['promotions'], 'feminin'));

    return $stats;
}

/**
 * Récupère les statistiques d'inscription pour une section et une année académique
 * @param int $sectionId ID de la section
 * @param int $anneeId ID de l'année académique
 * @return array Statistiques d'inscription
 */
public function getInscriptionsStatsBySectionAndYear($sectionId, $anneeId) {
    $stats = [];

    // Récupérer les statistiques par promotion
    $query = "SELECT p.idpromotion, p.\"designationPromotion\",
              COUNT(*) as total,
              SUM(CASE WHEN e.sexe = 'Masculin' THEN 1 ELSE 0 END) as masculin,
              SUM(CASE WHEN e.sexe = 'Feminin' THEN 1 ELSE 0 END) as feminin
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE e.annee_acad_idannee_acad = :anneeId
              AND o.section_idsection = :sectionId
              GROUP BY p.idpromotion, p.\"designationPromotion\"
              ORDER BY p.\"designationPromotion\"";

    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'anneeId' => $anneeId,
        'sectionId' => $sectionId
    ]);
    $stats['promotions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer le total comme somme des totaux par promotion
    $stats['total'] = array_sum(array_column($stats['promotions'], 'total'));
    $stats['masculin'] = array_sum(array_column($stats['promotions'], 'masculin'));
    $stats['feminin'] = array_sum(array_column($stats['promotions'], 'feminin'));

    return $stats;
}


// Ajouter cette méthode à la classe Universite

public function uploadStudentPhoto($idEtudiant, $file) {
    try {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Seules les images JPEG et PNG sont acceptées.');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('La taille de l\'image ne doit pas dépasser 5MB.');
        }
        
        // Créer le dossier de destination s'il n'existe pas
        $uploadDir = dirname(__DIR__) . '/uploads/photos/etudiants/' . date('Y');
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Impossible de créer le dossier de destination.');
            }
        }
        
        // Générer un nom de fichier unique basé sur le matricule
        $student = $this->getStudentById($idEtudiant);
        $fileName = $student['matricule'] . '_' . uniqid() . '.jpg';
        $destination = $uploadDir . '/' . $fileName;
        
        // Compresser et standardiser l'image
        $this->processAndSaveImage($file['tmp_name'], $destination);
        
        // Mettre à jour le chemin de la photo dans la base de données
        $relativePath = 'uploads/photos/etudiants/' . date('Y') . '/' . $fileName;
        $sql = "UPDATE etudiant SET photo = ? WHERE idetudiant = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$relativePath, $idEtudiant]);
        
        return $relativePath;
    } catch (Exception $e) {
        throw $e;
    }
}

private function processAndSaveImage($sourceFile, $destination) {
    // Déterminer le type d'image
    $imageInfo = getimagesize($sourceFile);
    $mime = $imageInfo['mime'];
    
    // Créer une image source
    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourceFile);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourceFile);
            break;
        default:
            throw new Exception('Format d\'image non supporté.');
    }
    
    // Standardiser à 300x400 pixels pour les photos d'identité
    $newWidth = 300;
    $newHeight = 400;
    
    // Créer une nouvelle image
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Préserver la transparence pour les PNG
    if ($mime == 'image/png') {
        imagecolortransparent($thumb, imagecolorallocate($thumb, 0, 0, 0));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    
    // Redimensionner
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, 
                        imagesx($source), imagesy($source));
    
    // Sauvegarder l'image
    imagejpeg($thumb, $destination, 80); // qualité 80%
    
    // Libérer la mémoire
    imagedestroy($source);
    imagedestroy($thumb);
    
    return true;
}

// Mettre à jour la méthode getStudentById pour inclure l'URL de la photo
public function getStudentById($idEtudiant) {
    $sql = "SELECT e.*, p.\"designationPromotion\", a.designation as annee, e.photo 
            FROM etudiant e
            LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
            WHERE e.idetudiant = ?";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$idEtudiant]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Ajoute une section à un agent
 * @param int $idAgent L'ID de l'agent
 * @param int $idSection L'ID de la section
 * @param int $estPrincipal 1 si c'est la section principale, 0 sinon
 * @return bool True si l'ajout a réussi, false sinon
 */
public function addAgentSection2($idAgent, $idSection, $estPrincipal = 0) {
    try {
        // Si c'est une section principale, mettre à jour toutes les autres sections de l'agent pour qu'elles ne soient plus principales
        if ($estPrincipal == 1) {
            $sqlUpdate = "UPDATE agent_section SET \"estPrincipal\" = 0 WHERE \"idAgent\" = :idAgent";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
            $stmtUpdate->execute();
        }
        
        // Vérifier si l'agent est déjà affecté à cette section
        $sqlCheck = "SELECT idagent_section FROM agent_section WHERE \"idAgent\" = :idAgent AND idsection = :idSection";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmtCheck->bindParam(':idSection', $idSection, PDO::PARAM_INT);
        $stmtCheck->execute();
        
        if ($stmtCheck->rowCount() > 0) {
            // Si l'agent est déjà affecté à cette section, mettre à jour le statut principal si nécessaire
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            $idAgentSection = $row['idagent_section'];
            
            $sqlUpdateSection = "UPDATE agent_section SET \"estPrincipal\" = :estPrincipal WHERE idagent_section = :idAgentSection";
            $stmtUpdateSection = $this->db->prepare($sqlUpdateSection);
            $stmtUpdateSection->bindParam(':estPrincipal', $estPrincipal, PDO::PARAM_INT);
            $stmtUpdateSection->bindParam(':idAgentSection', $idAgentSection, PDO::PARAM_INT);
            return $stmtUpdateSection->execute();
        } else {
            // Sinon, ajouter une nouvelle affectation
            $dateAffectation = date('Y-m-d');
            
            $sql = "INSERT INTO agent_section (\"idAgent\", idsection, \"dateAffectation\", \"estPrincipal\") 
                    VALUES (:idAgent, :idSection, :dateAffectation, :estPrincipal)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
            $stmt->bindParam(':idSection', $idSection, PDO::PARAM_INT);
            $stmt->bindParam(':dateAffectation', $dateAffectation);
            $stmt->bindParam(':estPrincipal', $estPrincipal, PDO::PARAM_INT);
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'une section à un agent: " . $e->getMessage());
        return false;
    }
}


public function getTeacherResearcherCount($anneeId = null) {
    // Un enseignant chercheur est un agent de type "Enseignant" qui est rattaché à au moins une unité de recherche
    $query = "SELECT COUNT(DISTINCT a.\"idAgent\") as total 
              FROM agent a
              INNER JOIN enseignant_specialisation es ON a.\"idAgent\" = es.\"idAgent\"
              INNER JOIN specialisation s ON es.\"idSpecialisation\" = s.\"idSpecialisation\"
              INNER JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
              WHERE a.type_agent = 'Enseignant'";
    
    if ($anneeId) {
        // Si on veut filtrer par année académique, on peut limiter aux enseignants
        // qui ont encadré des étudiants cette année
        $query .= " AND a.\"idAgent\" IN (
            SELECT DISTINCT \"idDirecteur\" FROM sujets WHERE annee_acad_idannee_acad = :anneeId
            UNION
            SELECT DISTINCT \"idEncadreur\" FROM sujets WHERE annee_acad_idannee_acad = :anneeId
        )";
    }
    
    $stmt = $this->db->prepare($query);
    
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Récupère le nombre total d'unités de recherche
 * @return int Nombre d'unités de recherche
 */
public function getResearchUnitCount() {
    $query = "SELECT COUNT(*) as total FROM unite_recherche";
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Récupère le nombre total d'étudiants en recherche (ayant un sujet)
 * @param int|null $anneeId ID de l'année académique (optionnel)
 * @return int Nombre d'étudiants en recherche
 */
public function getResearchStudentCount($anneeId = null) {
    $query = "SELECT COUNT(DISTINCT etudiant_idetudiant) as total 
              FROM sujets 
              WHERE etudiant_idetudiant IS NOT NULL";
    
    if ($anneeId) {
        $query .= " AND annee_acad_idannee_acad = :anneeId";
    }
    
    $stmt = $this->db->prepare($query);
    
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Récupère le nombre total de sujets validés
 * @param int|null $anneeId ID de l'année académique (optionnel)
 * @return int Nombre de sujets validés
 */
public function getValidatedSubjectCount($anneeId = null) {
    $query = "SELECT COUNT(*) as total 
              FROM sujets 
              WHERE statut_validation = 'Validé'";
    
    if ($anneeId) {
        $query .= " AND annee_acad_idannee_acad = :anneeId";
    }
    
    $stmt = $this->db->prepare($query);
    
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

public function getProfileCompletionStats() {
    $students = $this->getStudents2();
    $totalStudents = count($students);
    
    $completeCount = 0;
    $partialCount = 0;
    $inProgressCount = 0;
    $notStartedCount = 0;
    
    $missingFieldsStats = [
        'photo' => 0,
        'lieuNaissance' => 0,
        'dateNaissance' => 0,
        'adressemail' => 0,
        'telephone' => 0,
        'adresse' => 0,
        'personne_contact' => 0,
        'telephone_contact' => 0
    ];
    
    foreach ($students as $student) {
        $completedFields = 0;
        $totalFields = 8; // Total number of fields we're tracking
        
        // Check each field and count missing fields
        if (!empty($student['photo'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['photo']++;
        }
        
        if (!empty($student['lieuNaissance'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['lieuNaissance']++;
        }
        
        if (!empty($student['dateNaissance'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['dateNaissance']++;
        }
        
        if (!empty($student['adressemail'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['adressemail']++;
        }
        
        if (!empty($student['telephone'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['telephone']++;
        }
        
        if (!empty($student['adresse'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['adresse']++;
        }
        
        if (!empty($student['personne_contact'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['personne_contact']++;
        }
        
        if (!empty($student['telephone_contact'])) {
            $completedFields++;
        } else {
            $missingFieldsStats['telephone_contact']++;
        }
        
        $completionPercentage = ($completedFields / $totalFields) * 100;
        
        // Categorize by completion status
        if ($completionPercentage == 100) {
            $completeCount++;
        } else if ($completionPercentage >= 75) {
            $partialCount++;
        } else if ($completionPercentage > 0) {
            $inProgressCount++;
        } else {
            $notStartedCount++;
        }
    }
    
    return [
        'totalStudents' => $totalStudents,
        'completeCount' => $completeCount,
        'partialCount' => $partialCount,
        'inProgressCount' => $inProgressCount,
        'notStartedCount' => $notStartedCount,
        'missingFieldsStats' => $missingFieldsStats
    ];
}

/**
 * Get completion statistics by promotion
 * @return array Completion statistics grouped by promotion
 */
public function getCompletionStatsByPromotion() {
    $query = "SELECT 
                p.idpromotion,
                p.\"designationPromotion\",
                COUNT(e.idetudiant) as total_students
              FROM promotion p
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
              GROUP BY p.idpromotion, p.\"designationPromotion\"
              ORDER BY p.\"designationPromotion\"";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [];
    
    foreach ($promotions as $promotion) {
        $students = $this->getStudentsByPromotion($promotion['idpromotion']);
        
        $complete = 0;
        $partial = 0;
        $inProgress = 0;
        $notStarted = 0;
        
        foreach ($students as $student) {
            $completedFields = 0;
            $totalFields = 8;
            
            if (!empty($student['photo'])) $completedFields++;
            if (!empty($student['lieuNaissance'])) $completedFields++;
            if (!empty($student['dateNaissance'])) $completedFields++;
            if (!empty($student['adressemail'])) $completedFields++;
            if (!empty($student['telephone'])) $completedFields++;
            if (!empty($student['adresse'])) $completedFields++;
            if (!empty($student['personne_contact'])) $completedFields++;
            if (!empty($student['telephone_contact'])) $completedFields++;
            
            $completionPercentage = ($completedFields / $totalFields) * 100;
            
            if ($completionPercentage == 100) {
                $complete++;
            } else if ($completionPercentage >= 75) {
                $partial++;
            } else if ($completionPercentage > 0) {
                $inProgress++;
            } else {
                $notStarted++;
            }
        }
        
        $stats[] = [
            'promotion' => $promotion['designationPromotion'],
            'total' => $promotion['total_students'],
            'complete' => $complete,
            'partial' => $partial,
            'inProgress' => $inProgress,
            'notStarted' => $notStarted
        ];
    }
    
    return $stats;
}

public function logProfileReminder($studentId, $userId) {
    // In a full implementation, you would create a table for this
    // For now, we'll just use the journal_activites table
    
    $query = "INSERT INTO journal_activites 
              (user_type, user_id, type_activite, id_element, description, date_activite) 
              VALUES 
              ('admin', :userId, 'rappel_profil', :studentId, 'Rappel envoyé pour compléter le profil', NOW())";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
    
    return $stmt->execute();
}

public function getStudentTemponByMatricule($matricule) {
    $query = "SELECT * FROM etudiant_tempon WHERE matricule = :matricule";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':matricule', $matricule);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Met à jour le choix de classe préparatoire et les informations de l'étudiant
 */
public function updatePreparatoireChoice($id, $selectedClass, $noms, $lieuNaissance, $dateNaissance, $sexe, $nationalite, $adressemail, $telephone, $adresse, $personne_contact, $telephone_contact, $photo) {
    try {
        $this->db->beginTransaction();
        
        // Mettre à jour les informations dans la table etudiant_tempon
        $query = "UPDATE etudiant_tempon SET 
                  noms = :noms,
                  \"lieuNaissance\" = :lieuNaissance,
                  \"dateNaissance\" = :dateNaissance,
                  adressemail = :adressemail,
                  telephone = :telephone,
                  adresse = :adresse,
                  personne_contact = :personne_contact,
                  telephone_contact = :telephone_contact,
                  photo = :photo,
                  sexe = :sexe,
                  nationalite = :nationalite,
                  promotion_designation = :promotion_designation
                  WHERE idetudiant = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':lieuNaissance', $lieuNaissance);
        $stmt->bindParam(':dateNaissance', $dateNaissance);
        $stmt->bindParam(':adressemail', $adressemail);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':personne_contact', $personne_contact);
        $stmt->bindParam(':telephone_contact', $telephone_contact);
        $stmt->bindParam(':photo', $photo);
        $stmt->bindParam(':sexe', $sexe);
        $stmt->bindParam(':nationalite', $nationalite);
        $stmt->bindParam(':promotion_designation', $selectedClass);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if (!$result) {
            $this->db->rollBack();
            return false;
        }
        
        $this->db->commit();
        return true;
    } catch (PDOException $e) {
        $this->db->rollBack();
        error_log("Erreur lors de la mise à jour du choix de classe préparatoire: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les étudiants par classe préparatoire
 */
public function getStudentsByPreparatoireClass($preparatoireClass) {
    $query = "SELECT * FROM etudiant_tempon e1
              WHERE promotion_designation = :preparatoireClass
              AND e1.idetudiant = (
                  SELECT MIN(e2.idetudiant) 
                  FROM etudiant_tempon e2 
                  WHERE e2.matricule = e1.matricule
              )
              ORDER BY noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':preparatoireClass', $preparatoireClass);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function addPreparatoireStudent($matricule, $noms, $lieuNaissance, $dateNaissance, $adressemail, $telephone, $sexe, $nationalite, $anneeAcademique, $adresse, $personne_contact, $telephone_contact) {
    try {
        $query = "INSERT INTO etudiant_tempon (
                    matricule, noms, \"lieuNaissance\", \"dateNaissance\", adressemail, telephone, 
                    sexe, nationalite, annee_academique, adresse, personne_contact, telephone_contact
                  ) VALUES (
                    :matricule, :noms, :lieuNaissance, :dateNaissance, :adressemail, :telephone,
                    :sexe, :nationalite, :anneeAcademique, :adresse, :personne_contact, :telephone_contact
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':lieuNaissance', $lieuNaissance);
        $stmt->bindParam(':dateNaissance', $dateNaissance);
        $stmt->bindParam(':adressemail', $adressemail);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':sexe', $sexe);
        $stmt->bindParam(':nationalite', $nationalite);
        $stmt->bindParam(':anneeAcademique', $anneeAcademique);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':personne_contact', $personne_contact);
        $stmt->bindParam(':telephone_contact', $telephone_contact);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'un étudiant préparatoire: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les statistiques des étudiants par classe préparatoire pour une année académique donnée
 * 
 * @param int $anneeId ID de l'année académique
 * @return array Tableau contenant les statistiques des classes préparatoires
 */
public function getPreparatoireStatsByYear($anneeId) {
    $stats = [
        'classes' => [],
        'total' => 0,
        'choix_faits' => 0
    ];
    
    try {
        // Récupérer le nombre total d'étudiants pour cette année académique
        $queryTotal = "SELECT COUNT(DISTINCT matricule) as total 
                      FROM etudiant_tempon 
                      WHERE annee_acad_idannee_acad = :anneeId";
        $stmtTotal = $this->db->prepare($queryTotal);
        $stmtTotal->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
        $stats['total'] = $resultTotal['total'] ?? 0;
        
        // Récupérer les statistiques par classe préparatoire
        $query = "SELECT 
                    e1.promotion_designation as designation, 
                    COUNT(DISTINCT e1.matricule) as total,
                    SUM(CASE WHEN e1.sexe = 'M' THEN 1 ELSE 0 END) as masculin,
                    SUM(CASE WHEN e1.sexe = 'F' THEN 1 ELSE 0 END) as feminin
                  FROM 
                    etudiant_tempon e1
                  WHERE 
                    e1.annee_acad_idannee_acad = :anneeId
                    AND e1.idetudiant = (
                        SELECT MIN(e2.idetudiant)
                        FROM etudiant_tempon e2
                        WHERE e2.matricule = e1.matricule
                    )
                  GROUP BY 
                    e1.promotion_designation
                  ORDER BY 
                    e1.promotion_designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['classes'] = $classes;
        
        // Calculer le nombre total d'étudiants ayant fait un choix
        $stats['choix_faits'] = array_sum(array_column($classes, 'total'));
        
        // Si aucune donnée n'est trouvée, créer des entrées par défaut pour les classes A, B et C
        if (empty($stats['classes'])) {
            $stats['classes'] = [
                ['designation' => 'Préparatoire A', 'total' => 0, 'masculin' => 0, 'feminin' => 0],
                ['designation' => 'Préparatoire B', 'total' => 0, 'masculin' => 0, 'feminin' => 0],
                ['designation' => 'Préparatoire C', 'total' => 0, 'masculin' => 0, 'feminin' => 0]
            ];
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des statistiques préparatoires: " . $e->getMessage());
        return [
            'classes' => [
                ['designation' => 'Préparatoire A', 'total' => 0, 'masculin' => 0, 'feminin' => 0],
                ['designation' => 'Préparatoire B', 'total' => 0, 'masculin' => 0, 'feminin' => 0],
                ['designation' => 'Préparatoire C', 'total' => 0, 'masculin' => 0, 'feminin' => 0]
            ],
            'total' => 0,
            'choix_faits' => 0
        ];
    }
}

public function getJuryBureauxByPresident($agentId) {
    try {
        $query = "SELECT * FROM bureau_jury_deliberation 
                 WHERE president_id = :agentId AND est_actif = 1
                 ORDER BY date_creation DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getJuryBureauxByPresident: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère tous les membres d'un bureau de jury (sauf le président si spécifié)
 * @param int $bureauId ID du bureau de jury
 * @param bool $excludePresident Si true, exclut le président de la liste
 * @return array Liste des membres du jury
 */
public function getJuryMembersByBureau($bureauId, $excludePresident = false) {
    try {
        $query = "SELECT a.\"idAgent\", a.noms, a.telephone, a.email, 
                CASE 
                    WHEN b.president_id = a.\"idAgent\" THEN 'Président' 
                    WHEN b.secretaire_id = a.\"idAgent\" THEN 'Secrétaire'
                    ELSE 'Membre'
                END as role
                FROM agent a
                JOIN membre_bureau_jury m ON a.\"idAgent\" = m.\"idAgent\"
                JOIN bureau_jury_deliberation b ON m.idbureau = b.idbureau
                WHERE b.idbureau = :bureauId ";
        
        if ($excludePresident) {
            $query .= "AND b.president_id != a.\"idAgent\" ";
        }
        
        $query .= "ORDER BY role, a.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getJuryMembersByBureau: " . $e->getMessage());
        return [];
    }
}

/**
 * Ajoute une autorisation d'encodage pour un membre du jury
 * @param int $bureauId ID du bureau de jury
 * @param int $agentId ID de l'agent (membre du jury)
 * @param int $ecueId ID du cours (ECUE)
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param int $userId ID de l'utilisateur qui ajoute l'autorisation
 * @return bool True si l'ajout a réussi, false sinon
 */
public function addJuryMemberAuthorization($bureauId, $agentId, $ecueId, $sessionId, $anneeId, $userId) {
    try {
        // Vérifier si l'agent est bien membre de ce jury
        $query = "SELECT COUNT(*) AS count FROM membre_bureau_jury 
                  WHERE idbureau = :bureauId AND \"idAgent\" = :agentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // L'agent n'est pas membre de ce jury
            return false;
        }
        
        // Insertion de l'autorisation (ignorera les doublons avec IGNORE)
        $query = "INSERT IGNORE INTO jury_membre_autorisations 
                 (idbureau, \"idAgent\", \"idECUE\", session_idsession, annee_acad_idannee_acad, \"idUser\") 
                 VALUES (:bureauId, :agentId, :ecueId, :sessionId, :anneeId, :userId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur dans addJuryMemberAuthorization: " . $e->getMessage());
        return false;
    }
}

public function removeJuryMemberAuthorization($authorizationId) {
    try {
        $pdo = Connexion::getInstance()->getPDO();
        $query = "DELETE FROM jury_membre_autorisations WHERE id_autorisation = :authorizationId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':authorizationId', $authorizationId, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur dans removeJuryMemberAuthorization: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les autorisations d'encodage pour un bureau de jury
 * @param int $bureauId ID du bureau de jury
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Liste des autorisations d'encodage
 */
public function getJuryMemberAuthorizations($bureauId, $sessionId, $anneeId) {
    try {
        $query = "SELECT a.*, 
                  agent.noms as nom_agent, 
                  ecue.\"designationECUE\", 
                  ue.\"designationUE\"
                  FROM jury_membre_autorisations a
                  JOIN agent ON agent.idAgent = a.\"idAgent\"
                  JOIN ecue ON ecue.\"idECUE\" = a.\"idECUE\"
                  JOIN ue ON ue.\"idUE\" = ecue.\"UE_idUE\"
                  WHERE a.idbureau = :bureauId 
                  AND a.session_idsession = :sessionId 
                  AND a.annee_acad_idannee_acad = :anneeId
                  ORDER BY agent.noms, ecue.\"designationECUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getJuryMemberAuthorizations: " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifie si un agent a l'autorisation d'encoder les points pour un cours spécifique
 * @param int $agentId ID de l'agent
 * @param int $ecueId ID du cours
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return bool True si l'agent est autorisé, false sinon
 */
public function hasEncodingAuthorization($agentId, $ecueId, $sessionId, $anneeId) {
    try {
        // Un président de jury ou un admin a toujours accès
        $queryPresident = "SELECT COUNT(*) AS count 
                         FROM bureau_jury_deliberation b
                         JOIN membre_bureau_jury m ON b.idbureau = m.idbureau
                         WHERE (b.president_id = :agentId OR b.secretaire_id = :agentId)
                         AND b.est_actif = 1";
                         
        $stmtPresident = $this->db->prepare($queryPresident);
        $stmtPresident->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmtPresident->execute();
        $resultPresident = $stmtPresident->fetch(PDO::FETCH_ASSOC);
        
        if ($resultPresident['count'] > 0) {
            return true; // L'agent est président ou secrétaire d'un jury
        }
        
        // Vérifier les autorisations spécifiques pour ce cours
        $query = "SELECT COUNT(*) AS count 
                FROM jury_membre_autorisations 
                WHERE \"idAgent\" = :agentId 
                AND \"idECUE\" = :ecueId 
                AND session_idsession = :sessionId 
                AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Erreur dans hasEncodingAuthorization: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste des cours qu'un agent est autorisé à encoder
 * @param int $agentId ID de l'agent
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Liste des cours autorisés
 */
public function getAuthorizedEcuesForAgent($agentId, $sessionId, $anneeId) {
    try {
        
        // Vérifier si l'agent est président d'un jury
        $isPresident = $this->isJuryPresident($agentId);
        
        if ($isPresident) {
            // Si président, récupérer tous les cours des promos liées à ses jurys
            $query = "SELECT DISTINCT e.*
                     FROM ecue e
                     JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                     JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                     JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                     JOIN bureau_jury_deliberation b ON b.idbureau IN (
                         SELECT idbureau FROM bureau_jury_deliberation WHERE president_id = :agentId AND est_actif = 1
                     )
                     JOIN bureau_jury_promotion bp ON b.idbureau = bp.idbureau AND p.idpromotion = bp.idpromotion
                     ORDER BY e.\"designationECUE\"";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Sinon, récupérer seulement les cours autorisés
            $query = "SELECT DISTINCT e.*
                     FROM ecue e
                     JOIN jury_membre_autorisations a ON e.\"idECUE\" = a.\"idECUE\"
                     WHERE a.\"idAgent\" = :agentId
                     AND a.session_idsession = :sessionId
                     AND a.annee_acad_idannee_acad = :anneeId
                     ORDER BY e.\"designationECUE\"";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Erreur dans getAuthorizedEcuesForAgent: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère les documents obligatoires par cycle
 * 
 * @param string $cycle Le cycle (Premier, Deuxieme, Troisieme)
 * @return array|false Les documents ou false en cas d'erreur
 */
public function getRequiredDocumentsByCycle($cycle) {
    try {
        $cycle = filter_var($cycle, FILTER_SANITIZE_STRING);
        
        $query = "SELECT * FROM documents_obligatoires 
                 WHERE cycle = :cycle OR cycle = 'Tous' 
                 ORDER BY est_obligatoire DESC, designation ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cycle', $cycle);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des documents obligatoires: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère un document obligatoire par son ID
 * 
 * @param int $docId L'ID du document
 * @return array|false Le document ou false en cas d'erreur
 */
public function getRequiredDocumentById($docId) {
    try {
        $docId = intval($docId);
        
        $query = "SELECT * FROM documents_obligatoires WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $docId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération du document obligatoire: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les documents d'un étudiant
 * 
 * @param int $studentId L'ID de l'étudiant
 * @return array|false Les documents ou false en cas d'erreur
 */
public function getStudentDocuments($studentId) {
    try {
        $studentId = intval($studentId);
        
        $query = "SELECT * FROM etudiant_documents WHERE idetudiant = :idetudiant ORDER BY date_ajout DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idetudiant', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des documents étudiant: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute un document pour un étudiant
 * 
 * @param array $documentData Les données du document
 * @return bool True si succès, False sinon
 */
public function addStudentDocument($documentData) {
    try {
        $query = "INSERT INTO etudiant_documents (
                idetudiant, 
                matricule,
                document_obligatoire_id, 
                type_document, 
                titre, 
                description, 
                chemin_fichier, 
                date_ajout,
                annee_acad_id,
                \"idUser\",
                statut
            ) VALUES (
                :idetudiant, 
                :matricule,
                :document_obligatoire_id, 
                :type_document, 
                :titre, 
                :description, 
                :chemin_fichier, 
                NOW(),
                :annee_acad_id,
                :idUser,
                :statut
            )";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':idetudiant', $documentData['idetudiant'], PDO::PARAM_INT);
        $stmt->bindParam(':matricule', $documentData['matricule'], PDO::PARAM_STR);
        $stmt->bindParam(':document_obligatoire_id', $documentData['document_obligatoire_id'], PDO::PARAM_INT);
        $stmt->bindParam(':type_document', $documentData['type_document'], PDO::PARAM_STR);
        $stmt->bindParam(':titre', $documentData['titre'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $documentData['description'], PDO::PARAM_STR);
        $stmt->bindParam(':chemin_fichier', $documentData['chemin_fichier'], PDO::PARAM_STR);
        $stmt->bindParam(':annee_acad_id', $documentData['annee_acad_id'], PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $_SESSION['idUser'] ?? 1, PDO::PARAM_INT);
        $stmt->bindParam(':statut', $documentData['statut'], PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('Erreur lors de l\'ajout du document étudiant: ' . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour un document pour un étudiant
 * 
 * @param array $documentData Les données du document
 * @return bool True si succès, False sinon
 */
public function updateStudentDocument($documentData) {
    try {
        $query = "UPDATE etudiant_documents SET
                  titre = :titre,
                  description = :description,
                  chemin_fichier = :chemin_fichier,
                  statut = :statut,
                  date_ajout = NOW(),
                  \"idUser\" = :idUser
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':titre', $documentData['titre'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $documentData['description'], PDO::PARAM_STR);
        $stmt->bindParam(':chemin_fichier', $documentData['chemin_fichier'], PDO::PARAM_STR);
        $stmt->bindParam(':statut', $documentData['statut'], PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $_SESSION['idUser'] ?? 1, PDO::PARAM_INT);
        $stmt->bindParam(':id', $documentData['id'], PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('Erreur lors de la mise à jour du document étudiant: ' . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un étudiant est actif pour une année académique donnée
 * 
 * @param int $studentId L'ID de l'étudiant
 * @param int $academicYearId L'ID de l'année académique
 * @return bool True si actif, False sinon
 */
public function isStudentActiveForYear($studentId, $academicYearId) {
    try {
        $query = "SELECT est_actif FROM etudiant 
                  WHERE idetudiant = :idetudiant 
                  AND annee_acad_idannee_acad = :idannee_acad";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idetudiant', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':idannee_acad', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['est_actif'] == 1);
    } catch (PDOException $e) {
        error_log('Erreur lors de la vérification de l\'état actif de l\'étudiant: ' . $e->getMessage());
        return false;
    }
}































    /**
     * Sauvegarder la configuration du choix d'orientation
     * @param int $promotionSourceId ID de la promotion source
     * @param array $promotionsCibles Array des IDs des promotions cibles
     * @param int $anneeAcadId ID de l'année académique
     * @param array $fraisIds Array des IDs des frais requis
     * @return bool
     */
    public function saveConfigurationChoixOrientation($promotionSourceId, $promotionsCibles, $anneeAcadId, $fraisIds = []) {
        try {
            $this->db->beginTransaction();
            
            // Désactiver les anciennes configurations pour cette promotion source et année
            $queryDesactive = "UPDATE configuration_choix_orientation SET est_active = 0 
                              WHERE promotion_source_id = :promotionSourceId AND annee_acad_id = :anneeAcadId";
            $stmtDesactive = $this->db->prepare($queryDesactive);
            $stmtDesactive->execute([
                'promotionSourceId' => $promotionSourceId,
                'anneeAcadId' => $anneeAcadId
            ]);
            
            // Créer les nouvelles configurations
            foreach ($promotionsCibles as $promotionCibleId) {
                $queryInsert = "INSERT INTO configuration_choix_orientation 
                               (promotion_source_id, promotion_cible_id, annee_acad_id, est_active) 
                               VALUES (:promotionSourceId, :promotionCibleId, :anneeAcadId, 1)";
                $stmtInsert = $this->db->prepare($queryInsert);
                $stmtInsert->execute([
                    'promotionSourceId' => $promotionSourceId,
                    'promotionCibleId' => $promotionCibleId,
                    'anneeAcadId' => $anneeAcadId
                ]);
                
                $configId = $this->db->lastInsertId();
                
                // Ajouter les frais requis pour chaque configuration
                if (!empty($fraisIds)) {
                    foreach ($fraisIds as $fraisId) {
                        $queryFrais = "INSERT INTO frais_choix_orientation (frais_id, config_choix_orientation_id) 
                                       VALUES (:fraisId, :configId)";
                        $stmtFrais = $this->db->prepare($queryFrais);
                        $stmtFrais->execute([
                            'fraisId' => $fraisId,
                            'configId' => $configId
                        ]);
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur saveConfigurationChoixOrientation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer la configuration du choix d'orientation pour une promotion
     * @param int $promotionId ID de la promotion
     * @param int $anneeAcadId ID de l'année académique
     * @return array
     */
    public function getConfigurationChoixOrientation($promotionId, $anneeAcadId) {
        try {
            $query = "SELECT cco.*, 
                             p_cible.designationPromotion as promotion_cible_designation,
                             p_cible.cycle as cycle_cible,
                             o_cible.designationOrientation as orientation_cible
                      FROM configuration_choix_orientation cco
                      JOIN promotion p_cible ON cco.promotion_cible_id = p_cible.idpromotion
                      LEFT JOIN orientation o_cible ON p_cible.orientation_idorientation = o_cible.idorientation
                      WHERE cco.promotion_source_id = :promotionId 
                      AND cco.annee_acad_id = :anneeAcadId
                      AND cco.est_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'promotionId' => $promotionId,
                'anneeAcadId' => $anneeAcadId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getConfigurationChoixOrientation: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les promotions disponibles pour le choix d'orientation d'un étudiant
     * @param int $etudiantId ID de l'étudiant
     * @return array
     */
    public function getPromotionsDisponiblesChoixOrientation($etudiantId) {
        try {
            // Récupérer la promotion actuelle de l'étudiant
            $queryEtudiant = "SELECT e.promotion_idpromotion, e.annee_acad_idannee_acad 
                             FROM etudiant e WHERE e.idetudiant = :etudiantId";
            $stmtEtudiant = $this->db->prepare($queryEtudiant);
            $stmtEtudiant->execute(['etudiantId' => $etudiantId]);
            $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant) {
                return [];
            }
            
            // Vérifier si l'étudiant a déjà fait un choix cette année
            $queryChoix = "SELECT COUNT(*) as count FROM historique_choix_orientation 
                          WHERE etudiant_id = :etudiantId AND annee_acad_id = :anneeAcadId";
            $stmtChoix = $this->db->prepare($queryChoix);
            $stmtChoix->execute([
                'etudiantId' => $etudiantId,
                'anneeAcadId' => $etudiant['annee_acad_idannee_acad']
            ]);
            $resultChoix = $stmtChoix->fetch(PDO::FETCH_ASSOC);
            
            if ($resultChoix['count'] > 0) {
                return []; // L'étudiant a déjà fait un choix
            }
            
            // Récupérer les promotions cibles disponibles
            $query = "SELECT cco.id as config_id, p.*, 
                             o.\"designationOrientation\", aa.designation as anneeDesignation
                      FROM configuration_choix_orientation cco
                      JOIN promotion p ON cco.promotion_cible_id = p.idpromotion
                      LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                      WHERE cco.promotion_source_id = :promotionId 
                      AND cco.annee_acad_id = :anneeAcadId
                      AND cco.est_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'promotionId' => $etudiant['promotion_idpromotion'],
                'anneeAcadId' => $etudiant['annee_acad_idannee_acad']
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getPromotionsDisponiblesChoixOrientation: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les frais requis pour le choix d'orientation
     * @param int $configId ID de la configuration
     * @return array
     */
    public function getFraisRequisChoixOrientation($configId) {
        try {
            $query = "SELECT f.* 
                      FROM frais_choix_orientation fco
                      JOIN frais f ON fco.frais_id = f.id
                      WHERE fco.config_choix_orientation_id = :configId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['configId' => $configId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getFraisRequisChoixOrientation: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupérer la configuration du choix d'orientation par ID
     * @param int $configId ID de la configuration
     * @return array|null
     */
    public function getConfigChoixOrientationById($configId) {
        try {
            $query = "SELECT * FROM configuration_choix_orientation WHERE id = :configId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['configId' => $configId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getConfigChoixOrientationById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifier si un étudiant a payé les frais requis pour le choix d'orientation
     * @param int $etudiantId ID de l'étudiant
     * @param int $configId ID de la configuration
     * @return bool
     */
    public function hasStudentPaidOrientationChoiceFees($etudiantId, $configId) {
        try {
            // Récupérer les frais requis
            $fraisRequis = $this->getFraisRequisChoixOrientation($configId);
            
            if (empty($fraisRequis)) {
                return true; // Pas de frais requis
            }
            
            // Récupérer le matricule de l'étudiant
            $queryMatricule = "SELECT matricule, promotion_idpromotion FROM etudiant WHERE idetudiant = :etudiantId";
            $stmtMatricule = $this->db->prepare($queryMatricule);
            $stmtMatricule->execute(['etudiantId' => $etudiantId]);
            $etudiantInfo = $stmtMatricule->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiantInfo) {
                return false;
            }
            
            $matricule = $etudiantInfo['matricule'];
            $promotionId = $etudiantInfo['promotion_idpromotion'];
            
            // Pour chaque frais requis, vérifier si l'étudiant a payé
            foreach ($fraisRequis as $frais) {
                $feeId = $frais['id'];
                $montantRequis = floatval($frais['montant']);
                $montantPaye = 0;
                
                // Récupérer la promotion cible depuis la configuration
                $config = $this->getConfigChoixOrientationById($configId);
                $promotionCibleId = $config['promotion_idpromotion'] ?? 0;
                
                // D'abord, vérifier via affectation_frais et paiements_frais (méthode standard)
                $query = "SELECT af.id as affectation_id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'matricule' => $matricule,
                    'promotionId' => $promotionId,
                    'feeId' => $feeId
                ]);
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($affectation) {
                    // Utiliser la méthode standard avec affectation
                    $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                    
                    $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                              FROM paiements_frais
                              WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        'affectationId' => $affectation['affectation_id'],
                        'matricule' => $matricule
                    ]);
                    $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $montantPaye = floatval($paymentInfo['totalPaye'] ?? 0);
                } else {
                    // Fallback: vérifier directement dans la table paiement
                    $query = "SELECT SUM(montant) as total_paye 
                              FROM paiement 
                              WHERE etudiant_id = :etudiantId 
                              AND frais_id = :fraisId";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        'etudiantId' => $etudiantId,
                        'fraisId' => $feeId
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $montantPaye = floatval($result['total_paye'] ?? 0);
                }
                
                // Ajouter les frais transférés de l'ancienne promotion
                if ($promotionCibleId > 0) {
                    $montantTransfere = $this->aFraisTransfere($etudiantId, $feeId, $promotionCibleId);
                    $montantPaye += $montantTransfere;
                }
                
                if ($montantPaye < $montantRequis) {
                    return false;
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur hasStudentPaidOrientationChoiceFees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer le statut des frais pour le choix d'orientation
     * @param int $etudiantId ID de l'étudiant
     * @param int $configId ID de la configuration
     * @return array
     */
    public function getOrientationChoiceFeesStatus($etudiantId, $configId) {
        try {
            // Récupérer les frais requis
            $fraisRequis = $this->getFraisRequisChoixOrientation($configId);
            
            if (empty($fraisRequis)) {
                return [];
            }
            
            // Récupérer le matricule de l'étudiant
            $queryMatricule = "SELECT matricule, promotion_idpromotion FROM etudiant WHERE idetudiant = :etudiantId";
            $stmtMatricule = $this->db->prepare($queryMatricule);
            $stmtMatricule->execute(['etudiantId' => $etudiantId]);
            $etudiantInfo = $stmtMatricule->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiantInfo) {
                return [];
            }
            
            $matricule = $etudiantInfo['matricule'];
            $promotionId = $etudiantInfo['promotion_idpromotion'];
            
            // Récupérer la promotion cible depuis la configuration
            $config = $this->getConfigChoixOrientationById($configId);
            $promotionCibleId = $config['promotion_cible_id'] ?? 0;
            
            $status = [];
            foreach ($fraisRequis as $frais) {
                $feeId = $frais['id'];
                $montantRequis = floatval($frais['montant']);
                $montantPaye = 0;
                
                // D'abord, vérifier via affectation_frais et paiements_frais (méthode standard)
                $query = "SELECT af.id as affectation_id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'matricule' => $matricule,
                    'promotionId' => $promotionId,
                    'feeId' => $feeId
                ]);
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($affectation) {
                    // Utiliser la méthode standard avec affectation
                    $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                    
                    $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                              FROM paiements_frais
                              WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        'affectationId' => $affectation['affectation_id'],
                        'matricule' => $matricule
                    ]);
                    $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $montantPaye = floatval($paymentInfo['totalPaye'] ?? 0);
                    $montantRequis = $montantTotal;
                } else {
                    // Fallback: vérifier directement dans la table paiement
                    $query = "SELECT SUM(montant) as total_paye 
                              FROM paiement 
                              WHERE etudiant_id = :etudiantId 
                              AND frais_id = :fraisId";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        'etudiantId' => $etudiantId,
                        'fraisId' => $feeId
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $montantPaye = floatval($result['total_paye'] ?? 0);
                }
                
                // Ajouter les frais transférés de l'ancienne promotion
                if ($promotionCibleId > 0) {
                    $montantTransfere = $this->aFraisTransfere($etudiantId, $feeId, $promotionCibleId);
                    $montantPaye += $montantTransfere;
                }
                
                $statut = 'non_paye';
                if ($montantPaye >= $montantRequis) {
                    $statut = 'paye';
                } elseif ($montantPaye > 0) {
                    $statut = 'partiel';
                }
                
                $status[] = [
                    'id' => $frais['id'],
                    'designation' => $frais['designation'],
                    'montant' => $montantRequis,
                    'montantPaye' => $montantPaye,
                    'devise' => $frais['devise'],
                    'statut_paiement' => $statut
                ];
            }
            
            return $status;
        } catch (Exception $e) {
            error_log("Erreur getOrientationChoiceFeesStatus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistrer le choix d'orientation d'un étudiant
     * @param int $etudiantId ID de l'étudiant
     * @param int $promotionCibleId ID de la promotion cible
     * @return bool
     */
    public function enregistrerChoixOrientation($etudiantId, $promotionCibleId) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer les infos actuelles de l'étudiant
            $queryEtudiant = "SELECT promotion_idpromotion, annee_acad_idannee_acad, matricule 
                             FROM etudiant WHERE idetudiant = :etudiantId";
            $stmtEtudiant = $this->db->prepare($queryEtudiant);
            $stmtEtudiant->execute(['etudiantId' => $etudiantId]);
            $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant) {
                throw new Exception("Étudiant non trouvé");
            }
            
            $promotionSourceId = $etudiant['promotion_idpromotion'];
            $anneeAcadId = $etudiant['annee_acad_idannee_acad'];
            $matricule = $etudiant['matricule'];
            
            // Transférer les frais déjà payés vers la nouvelle promotion
            $this->transfererFraisPromotion($etudiantId, $matricule, $promotionSourceId, $promotionCibleId, $anneeAcadId);
            
            // Mettre à jour la promotion de l'étudiant (migration vers la nouvelle promotion)
            $queryUpdate = "UPDATE etudiant SET promotion_idpromotion = :promotionCibleId 
                           WHERE idetudiant = :etudiantId";
            $stmtUpdate = $this->db->prepare($queryUpdate);
            $stmtUpdate->execute([
                'promotionCibleId' => $promotionCibleId,
                'etudiantId' => $etudiantId
            ]);
            
            // Enregistrer dans l'historique
            $queryHisto = "INSERT INTO historique_choix_orientation 
                          (etudiant_id, promotion_source_id, promotion_cible_id, annee_acad_id) 
                          VALUES (:etudiantId, :promotionSourceId, :promotionCibleId, :anneeAcadId)";
            $stmtHisto = $this->db->prepare($queryHisto);
            $stmtHisto->execute([
                'etudiantId' => $etudiantId,
                'promotionSourceId' => $promotionSourceId,
                'promotionCibleId' => $promotionCibleId,
                'anneeAcadId' => $anneeAcadId
            ]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur enregistrerChoixOrientation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Transfère les frais déjà payés vers la nouvelle promotion
     */
    private function transfererFraisPromotion($etudiantId, $matricule, $promotionSourceId, $promotionCibleId, $anneeAcadId) {
        // Récupérer tous les paiements effectués par l'étudiant pour les frais de l'ancienne promotion
        $query = "SELECT pf.id as paiement_id, pf.montant, pf.affectation_id, af.frais_id
                  FROM paiements_frais pf
                  INNER JOIN affectation_frais af ON pf.affectation_id = af.id
                  WHERE pf.etudiant_id = :etudiantId 
                  AND af.promotion_id = :promotionSourceId
                  AND af.est_exempte = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'etudiantId' => $etudiantId,
            'promotionSourceId' => $promotionSourceId
        ]);
        $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($paiements as $paiement) {
            // Vérifier si ce frais existe déjà pour la nouvelle promotion
            $queryCheck = "SELECT af.id as affectation_id, f.montant as montant_frais
                          FROM affectation_frais af
                          INNER JOIN frais f ON af.frais_id = f.id
                          WHERE af.promotion_id = :promotionCibleId 
                          AND af.frais_id = :fraisId
                          AND af.est_exempte = 0
                          LIMIT 1";
            $stmtCheck = $this->db->prepare($queryCheck);
            $stmtCheck->execute([
                'promotionCibleId' => $promotionCibleId,
                'fraisId' => $paiement['frais_id']
            ]);
            $nouvelleAffectation = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($nouvelleAffectation) {
                // Vérifier que ce paiement n'a pas déjà été transféré
                $queryExists = "SELECT COUNT(*) as cnt FROM frais_transferred 
                               WHERE etudiant_id = :etudiantId 
                               AND frais_id = :fraisId 
                               AND promotion_cible_id = :promotionCibleId";
                $stmtExists = $this->db->prepare($queryExists);
                $stmtExists->execute([
                    'etudiantId' => $etudiantId,
                    'fraisId' => $paiement['frais_id'],
                    'promotionCibleId' => $promotionCibleId
                ]);
                $exists = $stmtExists->fetch(PDO::FETCH_ASSOC);
                
                if ($exists['cnt'] == 0) {
                    // Créer ou mettre à jour l'affectation pour la nouvelle promotion
                    $montantTotal = $nouvelleAffectation['montant_frais'];
                    $montantPaye = $paiement['montant'];
                    
                    // Mettre à jour ou créer l'enregistrement dans affectation_frais pour la nouvelle promotion
                    $queryAffect = "SELECT id, montant_paye, montant_restant 
                                   FROM affectation_frais 
                                   WHERE promotion_id = :promotionCibleId 
                                   AND frais_id = :fraisId
                                   AND matricule_etudiant = :matricule
                                   LIMIT 1";
                    $stmtAffect = $this->db->prepare($queryAffect);
                    $stmtAffect->execute([
                        'promotionCibleId' => $promotionCibleId,
                        'fraisId' => $paiement['frais_id'],
                        'matricule' => $matricule
                    ]);
                    $affectExistante = $stmtAffect->fetch(PDO::FETCH_ASSOC);
                    
                    if ($affectExistante) {
                        // Mettre à jour l'affectation existante
                        $nouveauMontantPaye = $affectExistante['montant_paye'] + $montantPaye;
                        $nouveauMontantRestant = max(0, $montantTotal - $nouveauMontantPaye);
                        $nouveauStatut = ($nouveauMontantRestant <= 0) ? 'Complet' : 'Partiel';
                        
                        $queryUpdateAffect = "UPDATE affectation_frais 
                                             SET montant_paye = :montant_paye, 
                                                 montant_restant = :montant_restant,
                                                 statut_paiement = :statut
                                             WHERE id = :id";
                        $stmtUpdateAffect = $this->db->prepare($queryUpdateAffect);
                        $stmtUpdateAffect->execute([
                            'montant_paye' => $nouveauMontantPaye,
                            'montant_restant' => $nouveauMontantRestant,
                            'statut' => $nouveauStatut,
                            'id' => $affectExistante['id']
                        ]);
                        
                        $nouvelleAffectationId = $affectExistante['id'];
                    } else {
                        // Créer une nouvelle affectation pour la nouvelle promotion
                        $nouveauMontantRestant = max(0, $montantTotal - $montantPaye);
                        $nouveauStatut = ($nouveauMontantRestant <= 0) ? 'Complet' : 'Partiel';
                        
                        $queryInsertAffect = "INSERT INTO affectation_frais 
                                             (frais_id, promotion_id, matricule_etudiant, etudiant_id, 
                                              montant_specifique, montant_paye, montant_restant, 
                                              statut_paiement, est_exempte, annee_acad_id) 
                                             SELECT 
                                                :frais_id, :promotion_id, :matricule, :etudiant_id,
                                                NULL, :montant_paye, :montant_restant,
                                                :statut, 0, :annee_acad_id
                                             FROM DUAL
                                             WHERE NOT EXISTS (
                                                SELECT 1 FROM affectation_frais 
                                                WHERE promotion_id = :promotion_id2 
                                                AND frais_id = :frais_id2
                                                AND matricule_etudiant = :matricule2
                                             )";
                        $stmtInsertAffect = $this->db->prepare($queryInsertAffect);
                        $stmtInsertAffect->execute([
                            'frais_id' => $paiement['frais_id'],
                            'promotion_id' => $promotionCibleId,
                            'matricule' => $matricule,
                            'etudiant_id' => $etudiantId,
                            'montant_paye' => $montantPaye,
                            'montant_restant' => $nouveauMontantRestant,
                            'statut' => $nouveauStatut,
                            'annee_acad_id' => $anneeAcadId,
                            'promotion_id2' => $promotionCibleId,
                            'frais_id2' => $paiement['frais_id'],
                            'matricule2' => $matricule
                        ]);
                        
                        // Récupérer l'ID de l'affectation créée ou existante
                        $queryNewAffect = "SELECT id FROM affectation_frais 
                                          WHERE promotion_id = :promotionCibleId 
                                          AND frais_id = :fraisId
                                          AND matricule_etudiant = :matricule
                                          LIMIT 1";
                        $stmtNewAffect = $this->db->prepare($queryNewAffect);
                        $stmtNewAffect->execute([
                            'promotionCibleId' => $promotionCibleId,
                            'fraisId' => $paiement['frais_id'],
                            'matricule' => $matricule
                        ]);
                        $newAffect = $stmtNewAffect->fetch(PDO::FETCH_ASSOC);
                        $nouvelleAffectationId = $newAffect ? $newAffect['id'] : null;
                    }
                    
                    // Enregistrer le transfert pour l'historique
                    if ($nouvelleAffectationId) {
                        $queryTransfer = "INSERT INTO frais_transferred 
                                        (etudiant_id, frais_id, montant_transferred, promotion_source_id, promotion_cible_id)
                                        VALUES (:etudiantId, :fraisId, :montant, :promotionSource, :promotionCible)";
                        $stmtTransfer = $this->db->prepare($queryTransfer);
                        $stmtTransfer->execute([
                            'etudiantId' => $etudiantId,
                            'fraisId' => $paiement['frais_id'],
                            'montant' => $montantPaye,
                            'promotionSource' => $promotionSourceId,
                            'promotionCible' => $promotionCibleId
                        ]);
                    }
                }
            }
        }
    }
    
    /**
     * Récupère les frais transférés pour un étudiant et une promotion cible
     */
    public function getFraisTransferes($etudiantId, $promotionCibleId) {
        try {
            $query = "SELECT ft.*, f.designation, f.montant as montant_frais
                      FROM frais_transferred ft
                      INNER JOIN frais f ON ft.frais_id = f.id
                      WHERE ft.etudiant_id = :etudiantId 
                      AND ft.promotion_cible_id = :promotionCibleId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'etudiantId' => $etudiantId,
                'promotionCibleId' => $promotionCibleId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getFraisTransferes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Vérifie si un étudiant a un frais transféré pour un frais spécifique
     */
    public function aFraisTransfere($etudiantId, $fraisId, $promotionCibleId) {
        try {
            $query = "SELECT SUM(montant_transferred) as total_transferred 
                      FROM frais_transferred 
                      WHERE etudiant_id = :etudiantId 
                      AND frais_id = :fraisId 
                      AND promotion_cible_id = :promotionCibleId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'etudiantId' => $etudiantId,
                'fraisId' => $fraisId,
                'promotionCibleId' => $promotionCibleId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($result['total_transferred'] ?? 0);
        } catch (Exception $e) {
            error_log("Erreur aFraisTransfere: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Vérifier si un étudiant a déjà fait un choix d'orientation cette année
     * @param int $etudiantId ID de l'étudiant
     * @return bool
     */
    public function aDejaChoisiOrientation($etudiantId) {
        try {
            // Récupérer l'année académique de l'étudiant
            $queryAnnee = "SELECT annee_acad_idannee_acad FROM etudiant WHERE idetudiant = :etudiantId";
            $stmtAnnee = $this->db->prepare($queryAnnee);
            $stmtAnnee->execute(['etudiantId' => $etudiantId]);
            $resultAnnee = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
            
            if (!$resultAnnee) {
                return false;
            }
            
            $query = "SELECT COUNT(*) as count FROM historique_choix_orientation 
                      WHERE etudiant_id = :etudiantId AND annee_acad_id = :anneeAcadId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'etudiantId' => $etudiantId,
                'anneeAcadId' => $resultAnnee['annee_acad_idannee_acad']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Erreur aDejaChoisiOrientation: " . $e->getMessage());
            return false;
        }
    }

}
?>