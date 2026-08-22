<?php
class Ecue {
    private $db;
    private $heuresParCredit;
    private const DIVISEUR_CREDITS_DEFAULT = 25;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
        // Initialiser heuresParCredit depuis la configuration de l'université
        $configQuery = $this->db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
        $config = $configQuery->fetch(PDO::FETCH_ASSOC);
        $this->heuresParCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : self::DIVISEUR_CREDITS_DEFAULT;
    }

    // Ajouter un ECUE
    public function addEcue($designationECUE, $CMI, $TD, $TP, $idUE, $idCreateur) {
        $query = "INSERT INTO ecue (\"designationECUE\", CMI, TD, TP, \"UE_idUE\", \"idCreateur\") 
                  VALUES (:designation, :cmi, :td, :tp, :idUE, :idCreateur)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designationECUE,
            'cmi' => $CMI,
            'td' => $TD,
            'tp' => $TP,
            'idUE' => $idUE,
            'idCreateur' => $idCreateur
        ]);
    }
    

    public function getEcueById($idEcue) {
        $query = "SELECT e.*, 
                u.\"designationUE\", u.\"codeUE\",
                s.\"numeroSemestre\", 
                p.\"designationPromotion\", p.cycle,p.annee_acad_idannee_acad,
                o.\"designationOrientation\",p.idpromotion
                FROM ecue e
                JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                WHERE e.\"idECUE\" = :idEcue";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEcuesByUE($ueId, $search = '') {
        $query = "SELECT * FROM ecue WHERE \"UE_idUE\" = :ueId";
        
        if (!empty($search)) {
            $query .= " AND \"designationECUE\" LIKE :search";
        }
        
        $query .= " ORDER BY \"designationECUE\" ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getECUEsByUE2($ueId) {
        $query = "SELECT e.*, ((e.CMI + e.TD + e.TP)/" . $this->heuresParCredit . ") as nombre_credits 
                  FROM ecue e 
                  WHERE e.\"UE_idUE\" = :ueId 
                  ORDER BY e.\"designationECUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    

   
    public function createEcue($designation, $cmi, $td, $tp, $ueId, $idUser) {
        $query = "INSERT INTO ecue (\"designationECUE\", CMI, TD, TP, \"UE_idUE\", \"idCreateur\", \"estVisible\") 
                  VALUES (:designation, :cmi, :td, :tp, :ueId, :idUser, 1)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':cmi', $cmi);
        $stmt->bindParam(':td', $td);
        $stmt->bindParam(':tp', $tp);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function updateEcue($idEcue, $designation, $cmi, $td, $tp) {
        $query = "UPDATE ecue 
                  SET \"designationECUE\" = :designation, 
                      CMI = :cmi, 
                      TD = :td, 
                      TP = :tp 
                  WHERE \"idECUE\" = :idEcue";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':cmi', $cmi);
        $stmt->bindParam(':td', $td);
        $stmt->bindParam(':tp', $tp);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        
        return $stmt->execute();
    }




    public function getEcuesBySection($sectionId, $anneeAcadId) {
        $query = "SELECT e.*, u.\"designationUE\", u.\"codeUE\", s.\"numeroSemestre\", p.\"designationPromotion\"
                  FROM ecue e
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE sec.idsection = :sectionId
                  AND p.annee_acad_idannee_acad = :anneeAcadId
                  AND e.\"estVisible\" = 1
                  ORDER BY p.\"designationPromotion\", s.\"numeroSemestre\", u.\"designationUE\", e.\"designationECUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

       
        public function toggleEcueVisibility($idEcue, $visible) {
            $query = "UPDATE ecue SET \"estVisible\" = :visible WHERE \"idECUE\" = :idEcue";
            
            $stmt = $this->db->prepare($query);
            $visibleInt = $visible ? 1 : 0;
            $stmt->bindParam(':visible', $visibleInt, PDO::PARAM_INT);
            $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
            
            return $stmt->execute();
        }



public function getEnseignantsByEcue($idEcue, $anneeAcadId) {
    try {
        $query = "SELECT e.*, a.noms 
                  FROM enseignant_ecue e 
                  JOIN agent a ON e.\"idAgent\" = a.\"idAgent\" 
                  WHERE e.\"idECUE\" = :idEcue 
                  AND e.\"anneeAcad\" = :anneeAcad";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcad', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
        return [];
    }
}


public function getChaptersByEcue($idEcue) {
    try {
        $query = "SELECT * FROM parties_cours 
                  WHERE \"idECUE\" = :idEcue 
                  ORDER BY ordre ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des chapitres: " . $e->getMessage());
        return [];
    }
}


public function getAssignmentsByEcue($idEcue, $anneeAcadId=null) {
    try {
        $query = "SELECT d.* 
                  FROM devoirs d
                  WHERE d.\"idECUE\" = :idEcue 
                  ORDER BY d.date_limite ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des devoirs: " . $e->getMessage());
        return [];
    }
}


public function getSupportsByEcue($idEcue) {
    try {
        $query = "SELECT * FROM support_cours 
                  WHERE \"idECUE\" = :idEcue 
                  ORDER BY \"dateCreation\" DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des supports de cours: " . $e->getMessage());
        return [];
    }
}


public function getRessourcesByChapter($idPartie) {
    try {
        $query = "SELECT * FROM ressources_cours 
                  WHERE idpartie = :idPartie 
                  ORDER BY \"dateCreation\" DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idPartie', $idPartie, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des ressources: " . $e->getMessage());
        return [];
    }
}


public function getAllTeachers() {
    try {
        $query = "SELECT a.\"idAgent\", a.noms, a.matricule 
                  FROM agent a 
                  WHERE a.type_agent = 'Enseignant' 
                  ORDER BY a.noms ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
        return [];
    }
}


public function checkTeacherAssignment($idEcue, $idAgent, $anneeAcad) {
    try {
        $query = "SELECT COUNT(*) FROM enseignant_ecue 
                  WHERE \"idECUE\" = :idEcue 
                  AND \"idAgent\" = :idAgent 
                  AND \"anneeAcad\" = :anneeAcad";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcad', $anneeAcad, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'affectation de l'enseignant: " . $e->getMessage());
        return false;
    }
}


public function addTeacherToEcue($idEcue, $idAgent, $poste, $anneeAcad) {
    try {
        $query = "INSERT INTO enseignant_ecue (\"idECUE\", \"idAgent\", poste, \"anneeAcad\") 
                  VALUES (:idEcue, :idAgent, :poste, :anneeAcad)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':poste', $poste, PDO::PARAM_STR);
        $stmt->bindParam(':anneeAcad', $anneeAcad, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de l'enseignant: " . $e->getMessage());
        return false;
    }
}


public function removeTeacherFromEcue($idEnseignantEcue) {
    try {
        $query = "DELETE FROM enseignant_ecue WHERE idenseignant_ecue = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idEnseignantEcue, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de l'affectation de l'enseignant: " . $e->getMessage());
        return false;
    }
}

public function addChapterToEcue($idEcue, $titre, $description, $ordre) {
    try {
        $query = "INSERT INTO parties_cours (\"idECUE\", titre, description, ordre, \"estVisible\") 
                  VALUES (:idEcue, :titre, :description, :ordre, 1)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':ordre', $ordre, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du chapitre: " . $e->getMessage());
        return false;
    }
}


public function getChapterById($idPartie) {
    try {
        $query = "SELECT * FROM parties_cours WHERE idpartie = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idPartie, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du chapitre: " . $e->getMessage());
        return false;
    }
}


public function deleteChapter($idPartie) {
    try {
        // Supprimer d'abord les ressources associées
        $queryResources = "DELETE FROM ressources_cours WHERE idpartie = :id";
        $stmtResources = $this->db->prepare($queryResources);
        $stmtResources->bindParam(':id', $idPartie, PDO::PARAM_INT);
        $stmtResources->execute();
        
        // Puis supprimer le chapitre
        $query = "DELETE FROM parties_cours WHERE idpartie = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idPartie, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du chapitre: " . $e->getMessage());
        return false;
    }
}


public function addResourceToChapter($idPartie, $titre, $description, $type_ressource, $fichier, $lien_externe, $est_payant, $idfrais) {
    try {
        $query = "INSERT INTO ressources_cours (idpartie, titre, description, type_ressource, fichier, lien_externe, est_payant, idfrais) 
                  VALUES (:idPartie, :titre, :description, :type_ressource, :fichier, :lien_externe, :est_payant, :idfrais)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idPartie', $idPartie, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':type_ressource', $type_ressource, PDO::PARAM_STR);
        $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
        $stmt->bindParam(':lien_externe', $lien_externe, PDO::PARAM_STR);
        $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_BOOL);
        $stmt->bindParam(':idfrais', $idfrais, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de la ressource: " . $e->getMessage());
        return false;
    }
}


public function getResourceById($idRessource) {
    try {
        $query = "SELECT * FROM ressources_cours WHERE idressource = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idRessource, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la ressource: " . $e->getMessage());
        return false;
    }
}


public function deleteResource($idRessource) {
    try {
        // Récupérer d'abord les informations de la ressource pour supprimer le fichier si nécessaire
        $ressource = $this->getResourceById($idRessource);
        if ($ressource && $ressource['fichier']) {
            $filePath = dirname(__DIR__) . '/uploads/ressources/' . $ressource['fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Puis supprimer la ressource de la base de données
        $query = "DELETE FROM ressources_cours WHERE idressource = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idRessource, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de la ressource: " . $e->getMessage());
        return false;
    }
}


public function addSupportToEcue($idEcue, $titre, $description, $fichier, $est_payant, $idfrais) {
    try {
        $query = "INSERT INTO support_cours (\"idECUE\", titre, description, fichier, est_payant, idfrais) 
                  VALUES (:idEcue, :titre, :description, :fichier, :est_payant, :idfrais)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
        $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_BOOL);
        $stmt->bindParam(':idfrais', $idfrais, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du support: " . $e->getMessage());
        return false;
    }
}


public function getSupportById($idSupport) {
    try {
        $query = "SELECT * FROM support_cours WHERE idsupport = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idSupport, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du support: " . $e->getMessage());
        return false;
    }
}


public function deleteSupport($idSupport) {
    try {
        // Récupérer d'abord les informations du support pour supprimer le fichier
        $support = $this->getSupportById($idSupport);
        if ($support && $support['fichier']) {
            $filePath = dirname(__DIR__) . '/uploads/supports/' . $support['fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Puis supprimer le support de la base de données
        $query = "DELETE FROM support_cours WHERE idsupport = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idSupport, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du support: " . $e->getMessage());
        return false;
    }
}


public function deleteEcue($idEcue) {
    try {
        // Commencer une transaction pour assurer l'intégrité des données
        $this->db->beginTransaction();
        
        // Supprimer les enseignants associés
        $queryEnseignants = "DELETE FROM enseignant_ecue WHERE \"idECUE\" = :idEcue";
        $stmtEnseignants = $this->db->prepare($queryEnseignants);
        $stmtEnseignants->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmtEnseignants->execute();
        
        // Récupérer les chapitres pour supprimer les ressources associées
        $queryChapitres = "SELECT idpartie FROM parties_cours WHERE \"idECUE\" = :idEcue";
        $stmtChapitres = $this->db->prepare($queryChapitres);
        $stmtChapitres->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmtChapitres->execute();
        $chapitres = $stmtChapitres->fetchAll(PDO::FETCH_ASSOC);
        
        // Supprimer les ressources associées à chaque chapitre
        foreach ($chapitres as $chapitre) {
            $queryRessources = "DELETE FROM ressources_cours WHERE idpartie = :idPartie";
            $stmtRessources = $this->db->prepare($queryRessources);
            $stmtRessources->bindParam(':idPartie', $chapitre['idpartie'], PDO::PARAM_INT);
            $stmtRessources->execute();
        }
        
        // Supprimer les chapitres
        $queryDeleteChapitres = "DELETE FROM parties_cours WHERE \"idECUE\" = :idEcue";
        $stmtDeleteChapitres = $this->db->prepare($queryDeleteChapitres);
        $stmtDeleteChapitres->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmtDeleteChapitres->execute();
        
        // Supprimer les supports de cours
        $querySupports = "DELETE FROM support_cours WHERE \"idECUE\" = :idEcue";
        $stmtSupports = $this->db->prepare($querySupports);
        $stmtSupports->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmtSupports->execute();
        
        // Supprimer les devoirs
        $queryDevoirs = "DELETE FROM devoirs WHERE \"idECUE\" = :idEcue";
        $stmtDevoirs = $this->db->prepare($queryDevoirs);
        $stmtDevoirs->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmtDevoirs->execute();

        // Vérifier les points (notes)
        $query = "SELECT COUNT(*) FROM points WHERE \"ECUE_idECUE\" = :idEcue";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        
        // Enfin, supprimer l'ECUE lui-même
        $query = "DELETE FROM ecue WHERE \"idECUE\" = :idEcue";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        // Valider la transaction si tout s'est bien passé
        if ($result) {
            $this->db->commit();
            return true;
        } else {
            $this->db->rollBack();
            return false;
        }
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $this->db->rollBack();
        error_log("Erreur lors de la suppression de l'ECUE: " . $e->getMessage());
        return false;
    }
}


public function addAssignment($idECUE, $titre, $description, $fichier, $date_limite, $est_payant, $idFrais) {
    try {
        $query = "INSERT INTO devoirs (\"idECUE\", titre, description, fichier, date_limite, est_payant, idfrais) 
                  VALUES (:idECUE, :titre, :description, :fichier, :date_limite, :est_payant, :idFrais)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
        $stmt->bindParam(':date_limite', $date_limite, PDO::PARAM_STR);
        $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_BOOL);
        $stmt->bindParam(':idFrais', $idFrais, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du devoir: " . $e->getMessage());
        return false;
    }
}


public function getAssignmentById($idDevoir) {
    try {
        $query = "SELECT * FROM devoirs WHERE iddevoir = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du devoir: " . $e->getMessage());
        return false;
    }
}


public function updateAssignment($idDevoir, $titre, $description, $fichier, $date_limite, $est_payant, $idFrais) {
    try {
        $query = "UPDATE devoirs 
                  SET titre = :titre, 
                      description = :description, 
                      fichier = :fichier, 
                      date_limite = :date_limite, 
                      est_payant = :est_payant, 
                      idfrais = :idFrais 
                  WHERE iddevoir = :idDevoir";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
        $stmt->bindParam(':date_limite', $date_limite, PDO::PARAM_STR);
        $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_BOOL);
        $stmt->bindParam(':idFrais', $idFrais, PDO::PARAM_INT);
        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du devoir: " . $e->getMessage());
        return false;
    }
}


public function deleteAssignment($idDevoir) {
    try {
        // Récupérer d'abord les informations du devoir pour supprimer le fichier
        $devoir = $this->getAssignmentById($idDevoir);
        if ($devoir && $devoir['fichier']) {
            $filePath = dirname(__DIR__) . '/uploads/devoirs/' . $devoir['fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Supprimer les réponses associées
        $queryReponses = "DELETE FROM reponses_devoir WHERE iddevoir = :idDevoir";
        $stmtReponses = $this->db->prepare($queryReponses);
        $stmtReponses->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        $stmtReponses->execute();
        
        // Puis supprimer le devoir de la base de données
        $query = "DELETE FROM devoirs WHERE iddevoir = :idDevoir";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du devoir: " . $e->getMessage());
        return false;
    }
}


public function getAssignmentResponses($idDevoir) {
    try {
        $query = "SELECT r.*, e.noms, e.matricule 
                  FROM reponses_devoir r 
                  JOIN etudiant e ON r.idetudiant = e.idetudiant 
                  WHERE r.iddevoir = :idDevoir 
                  ORDER BY r.date_soumission DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des réponses: " . $e->getMessage());
        return [];
    }
}


public function gradeAssignmentResponse($idReponse, $note, $feedback) {
    try {
        $query = "UPDATE reponses_devoir 
                  SET note = :note, 
                      feedback_enseignant = :feedback 
                  WHERE idreponse = :idReponse";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':note', $note, PDO::PARAM_STR);
        $stmt->bindParam(':feedback', $feedback, PDO::PARAM_STR);
        $stmt->bindParam(':idReponse', $idReponse, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la notation de la réponse: " . $e->getMessage());
        return false;
    }
}


public function deleteAssignmentResponse($idReponse) {
    try {
        // Récupérer d'abord les informations de la réponse pour supprimer le fichier
        $query = "SELECT fichier FROM reponses_devoir WHERE idreponse = :idReponse";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idReponse', $idReponse, PDO::PARAM_INT);
        $stmt->execute();
        $reponse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reponse && $reponse['fichier']) {
            $filePath = dirname(__DIR__) . '/uploads/reponses/' . $reponse['fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Puis supprimer la réponse de la base de données
        $query = "DELETE FROM reponses_devoir WHERE idreponse = :idReponse";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idReponse', $idReponse, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de la réponse: " . $e->getMessage());
        return false;
    }
}


public function updateChapter($idPartie, $titre, $description, $ordre) {
    try {
        $query = "UPDATE parties_cours 
                  SET titre = :titre, 
                      description = :description, 
                      ordre = :ordre 
                  WHERE idpartie = :idPartie";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':ordre', $ordre, PDO::PARAM_INT);
        $stmt->bindParam(':idPartie', $idPartie, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du chapitre: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les types d'évaluation disponibles
 *
 * @return array Liste des types d'évaluation
 */
public function getEvaluationTypes() {
    try {
        $query = "SELECT \"idType\", \"designationT\", categorie FROM typeevaluation ORDER BY categorie, \"designationT\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des types d'évaluation: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les évaluations d'un ECUE pour une année académique donnée
 *
 * @param int $idEcue ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Liste des évaluations
 */
public function getEvaluationsByEcue($idEcue, $idAnneeAcad) {
    try {
        $query = "SELECT e.*, t.\"designationT\", s.\"designSession\",s.description,t.categorie 
                 FROM evaluations e
                 INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
                 INNER JOIN session s ON e.session_idsession = s.idsession
                 WHERE e.\"idECUE\" = ?
                 ORDER BY e.date_evaluation, e.titre";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idEcue]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des évaluations: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère les étudiants inscrits à un ECUE pour une année académique
 * Modifié pour prendre en compte la session
 *
 * @param int $idEcue ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @param int $sessionId ID de la session (optionnel)
 * @return array Liste des étudiants
 */
public function getStudentsByEcue($idEcue, $idAnneeAcad, $sessionId = null) {
    try {
        // Si c'est la deuxième session, appliquer la logique spéciale
        if ($sessionId !== null && $this->isDeuxiemeSession($sessionId)) {
            return $this->getStudentsEligibleForSecondSession($idEcue, $idAnneeAcad);
        }
        
        // Logique originale pour les autres cas (première session ou pas de session spécifiée)
        $query1 = "SELECT p.idpromotion 
                  FROM ecue e
                  INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  WHERE e.\"idECUE\" = :idEcue";
        
        $stmt1 = $this->db->prepare($query1);
        $stmt1->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt1->execute();
        
        $promotion = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if (!$promotion) {
            return [];
        }
        
        $query2 = "SELECT e.idetudiant, e.matricule, e.noms 
                  FROM etudiant e
                  WHERE e.promotion_idpromotion = :promotionId 
                    AND e.annee_acad_idannee_acad = :anneeAcadId
                  ORDER BY e.noms";
        
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bindParam(':promotionId', $promotion['idpromotion'], PDO::PARAM_INT);
        $stmt2->bindParam(':anneeAcadId', $idAnneeAcad, PDO::PARAM_INT);
        $stmt2->execute();
        
        return $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des étudiants: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcule la moyenne pondérée d'un étudiant pour une UE
 *
 * @param string $matricule Matricule de l'étudiant
 * @param int $idUE ID de l'UE
 * @param int $sessionId ID de la session
 * @param int $anneeAcadId ID de l'année académique
 * @return float|null Moyenne pondérée ou null si aucune note
 */
public function calculateUeWeightedAverage($matricule, $idUE, $sessionId, $anneeAcadId) {
    try {
        $query = "SELECT 
                    SUM(cg.MF * ROUND((e.CMI + e.TD + e.TP)/" . $this->heuresParCredit . ", 2)) / 
                    SUM(ROUND((e.CMI + e.TD + e.TP)/" . $this->heuresParCredit . ", 2)) AS moyenne_ponderee
                  FROM cotes_grille cg
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  WHERE cg.matricule = :matricule
                  AND e.\"UE_idUE\" = :idUE
                  AND cg.session_idsession = :sessionId
                  AND cg.annee_acad_id = :anneeAcadId
                  GROUP BY cg.matricule";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['moyenne_ponderee']) : null;
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul de la moyenne pondérée: " . $e->getMessage());
        return null;
    }
}



/**
 * Récupère la configuration de calcul des moyennes pour un ECUE
 *
 * @param int $idEcue ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Configuration des moyennes, indexée par session
 */
/**
 * Récupère les configurations de calcul des moyennes pour un ECUE
 * 
 * @param int $idEcue ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @param int|null $sessionId ID de la session (optionnel)
 * @return array|false Configuration des moyennes (tableau indexé par session ou configuration unique)
 */
public function getConfigurationMoyenne($idEcue, $idAnneeAcad, $sessionId = null) {
    try {
        // Base de la requête
        $query = "SELECT * FROM configuration_moyenne 
                  WHERE \"idECUE\" = ? AND annee_acad_id = ?";
        $params = [$idEcue, $idAnneeAcad];
        
        // Si un ID de session est fourni, filtrer par session
        if ($sessionId !== null) {
            $query .= " AND session_idsession = ?";
            $params[] = $sessionId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        // Si on demande une session spécifique, retourner directement cette configuration
        if ($sessionId !== null) {
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            return $config ?: false; // Retourne false si aucune configuration trouvée
        }
        
        // Sinon, indexer par ID de session comme avant
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['session_idsession']] = $row;
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la configuration des moyennes: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère les notes d'un étudiant pour un ECUE et une année académique
 *
 * @param int $idEtudiant ID de l'étudiant
 * @param int $idEcue ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Notes de l'étudiant pour chaque évaluation
 */
public function getStudentGrades($idEtudiant, $idEcue, $idAnneeAcad) {
    try {
        // Récupérer d'abord le matricule de l'étudiant
        $query1 = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute([$idEtudiant]);
        $etudiant = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return [];
        }
        
        $matricule = $etudiant['matricule'];
        
        // Ensuite, récupérer les notes de cet étudiant
        $query2 = "SELECT p.*, e.idevaluation, e.titre, e.session_idsession, e.\"idType\", t.\"designationT\"
                  FROM points p
                  INNER JOIN evaluations e ON p.typeEvaluation = e.idevaluation 
                                         AND p.\"ECUE_idECUE\" = e.\"idECUE\" 
                                         AND p.session_idsession = e.session_idsession
                  INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
                  WHERE p.matricule = ? 
                    AND p.\"ECUE_idECUE\" = ? 
                    AND p.annee_acad_id = ?";
        
        $stmt2 = $this->db->prepare($query2);
        $stmt2->execute([$matricule, $idEcue, $idAnneeAcad]);
        
        return $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes: " . $e->getMessage());
        return [];
    }
}

/**
 * Ajoute une nouvelle évaluation
 *
 * @param array $data Les données de l'évaluation à ajouter
 * @return bool True si l'opération a réussi, false sinon
 */

public function addEvaluation($titre, $description, $date_evaluation, $idECUE, $idType, $session_idsession, $ponderation, $est_visible, $idUser, $annee_acad_id) {
    try {
        // 1. Vérifier si c'est un contrôle continu en deuxième session
        if ($this->isControleContinu($idType) && $this->isDeuxiemeSession($session_idsession)) {
            throw new Exception("Les contrôles continus ne sont pas autorisés en deuxième session.");
        }
        
        // 2. Vérifier s'il existe déjà un examen pour cette session
        if (!$this->isControleContinu($idType) && $this->examExistsForSession($idECUE, $session_idsession)) {
            throw new Exception("Un examen existe déjà pour cette session dans ce cours.");
        }

        $query = "INSERT INTO evaluations (titre, description, date_evaluation, \"idECUE\", \"idType\", 
                 session_idsession, ponderation, est_visible, \"idUser\", annee_acad_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            $titre, 
            $description, 
            $date_evaluation, 
            $idECUE, 
            $idType, 
            $session_idsession, 
            $ponderation, 
            $est_visible, 
            $idUser,
            $annee_acad_id // Ajoutez ce nouveau paramètre
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'une évaluation: " . $e->getMessage());
        throw $e;
    }
}


/**
 * Récupère une évaluation par son ID
 *
 * @param int $idevaluation L'ID de l'évaluation
 * @return array|false Les détails de l'évaluation ou false si non trouvée
 */
public function getEvaluationById($idevaluation) {
    try {
        $query = "SELECT e.*, t.\"designationT\",t.categorie, s.\"designSession\" 
                 FROM evaluations e
                 INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
                 INNER JOIN session s ON e.session_idsession = s.idsession
                 WHERE e.idevaluation = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idevaluation]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération d'une évaluation: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour une évaluation existante
 *
 * @param array $data Les données mises à jour de l'évaluation
 * @return bool True si l'opération a réussi, false sinon
 */
/**
 * Met à jour une évaluation existante
 */
public function updateEvaluation($idevaluation, $titre, $description, $date_evaluation, $idType, $session_idsession, $ponderation, $est_visible, $annee_acad_id = null) {
    try {
        
        // Si annee_acad_id est fourni, l'inclure dans la mise à jour
        if ($annee_acad_id !== null) {
            $query = "UPDATE evaluations 
                     SET titre = ?, description = ?, date_evaluation = ?, 
                     \"idType\" = ?, session_idsession = ?, ponderation = ?, 
                     est_visible = ?, annee_acad_id = ? 
                     WHERE idevaluation = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $titre, 
                $description, 
                $date_evaluation, 
                $idType, 
                $session_idsession, 
                $ponderation, 
                $est_visible, 
                $annee_acad_id,
                $idevaluation
            ]);
        } else {
            // Sinon, ne pas modifier annee_acad_id
            $query = "UPDATE evaluations 
                     SET titre = ?, description = ?, date_evaluation = ?, 
                     \"idType\" = ?, session_idsession = ?, ponderation = ?, 
                     est_visible = ? 
                     WHERE idevaluation = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $titre, 
                $description, 
                $date_evaluation, 
                $idType, 
                $session_idsession, 
                $ponderation, 
                $est_visible,
                $idevaluation
            ]);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour d'une évaluation: " . $e->getMessage());
        return false;
    }
}


/**
 * Enregistre ou met à jour une note d'étudiant
 *
 * @param array $data Les données de la note
 * @return bool True si l'opération a réussi, false sinon
 */
public function saveGrade($data) {
    try {
        // Vérifier si la note existe déjà
        $query = "SELECT COUNT(*) FROM points 
                 WHERE matricule = ? 
                 AND \"ECUE_idECUE\" = ? 
                 AND typeEvaluation = ? 
                 AND session_idsession = ? 
                 AND annee_acad_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['matricule'],
            $data['idECUE'],
            $data['typeEvaluation'],
            $data['session_idsession'],
            $data['annee_acad_id']
        ]);
        
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Mettre à jour la note existante
            $query = "UPDATE points 
                     SET \"coteObtenu\" = ? 
                     WHERE matricule = ? 
                     AND \"ECUE_idECUE\" = ? 
                     AND typeEvaluation = ? 
                     AND session_idsession = ? 
                     AND annee_acad_id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $data['coteObtenu'],
                $data['matricule'],
                $data['idECUE'],
                $data['typeEvaluation'],
                $data['session_idsession'],
                $data['annee_acad_id']
            ]);
        } else {
            // Créer une nouvelle note
            $query = "INSERT INTO points (\"coteObtenu\", typeEvaluation, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $data['coteObtenu'],
                $data['typeEvaluation'],
                $data['idECUE'],
                $data['session_idsession'],
                $data['matricule'],
                $data['annee_acad_id']
            ]);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement d'une note: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre ou met à jour la configuration de calcul des moyennes
 *
 * @param array $data Les données de configuration
 * @return bool True si l'opération a réussi, false sinon
 */
public function saveConfigurationMoyenne($data) {
    try {
        // Vérifier si une configuration existe déjà
        $query = "SELECT COUNT(*) FROM configuration_moyenne 
                 WHERE \"idECUE\" = ? 
                 AND session_idsession = ? 
                 AND annee_acad_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['idECUE'],
            $data['session_idsession'],
            $data['annee_acad_id']
        ]);
        
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Mettre à jour la configuration existante
            $query = "UPDATE configuration_moyenne 
                     SET formule_cc = ?, formule_ex = ?, ponderation_cc = ?, ponderation_ex = ?, \"idUser\" = ?, \"dateCreation\" = NOW() 
                     WHERE \"idECUE\" = ? AND session_idsession = ? AND annee_acad_id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $data['formule_cc'],
                $data['formule_ex'],
                $data['ponderation_cc'],
                $data['ponderation_ex'],
                $data['idUser'],
                $data['idECUE'],
                $data['session_idsession'],
                $data['annee_acad_id']
            ]);
        } else {
            // Créer une nouvelle configuration
            $query = "INSERT INTO configuration_moyenne 
                     (\"idECUE\", session_idsession, annee_acad_id, formule_cc, formule_ex, 
                      ponderation_cc, ponderation_ex, \"idUser\", \"dateCreation\") 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $data['idECUE'],
                $data['session_idsession'],
                $data['annee_acad_id'],
                $data['formule_cc'],
                $data['formule_ex'],
                $data['ponderation_cc'],
                $data['ponderation_ex'],
                $data['idUser']
            ]);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement de la configuration des moyennes: " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une évaluation et les notes associées
 *
 * @param int $idevaluation L'ID de l'évaluation à supprimer
 * @return bool True si l'opération a réussi, false sinon
 */
public function deleteEvaluation($idevaluation) {
    try {
        $this->db->beginTransaction();
        
        // D'abord, récupérer les informations de l'évaluation
        $eval = $this->getEvaluationById($idevaluation);
        if (!$eval) {
            $this->db->rollBack();
            return false;
        }
        
        // Ensuite, supprimer les notes associées
        $query1 = "DELETE FROM points 
                  WHERE \"ECUE_idECUE\" = ? 
                  AND typeEvaluation = ? 
                  AND session_idsession = ?";
        
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute([
            $eval['idECUE'],
            $eval['idType'],
            $eval['session_idsession']
        ]);
        
        // Enfin, supprimer l'évaluation
        $query2 = "DELETE FROM evaluations WHERE idevaluation = ?";
        $stmt2 = $this->db->prepare($query2);
        $result = $stmt2->execute([$idevaluation]);
        
        if ($result) {
            $this->db->commit();
            return true;
        } else {
            $this->db->rollBack();
            return false;
        }
    } catch (PDOException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        error_log("Erreur lors de la suppression d'une évaluation: " . $e->getMessage());
        return false;
    }
}



public function storeExcelToken($token, $evaluationId, $password) {
    $query = "INSERT INTO excel_tokens (token, evaluation_id, password, created_at, is_valid) 
              VALUES (:token, :evaluationId, :password, NOW(), 1)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    
    return $stmt->execute();
}

/**
 * Vérifie si un token Excel est valide
 *
 * @param string $token Token à vérifier
 * @param int $evaluationId ID de l'évaluation
 * @return bool True si le token est valide
 */
public function isValidExcelToken($token, $evaluationId) {
    $query = "SELECT * FROM excel_tokens 
              WHERE token = :token 
              AND evaluation_id = :evaluationId 
              AND is_valid = 1 
              AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return !empty($result);
}

/**
 * Invalide un token Excel après usage
 *
 * @param string $token Token à invalider
 * @return bool Succès de l'opération
 */
public function invalidateExcelToken($token) {
    $query = "UPDATE excel_tokens SET is_valid = 0 WHERE token = :token";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    
    return $stmt->execute();
}


public function importNotes($evaluationId, $notes) {
    if (empty($notes)) {
        return false;
    }
    
    // Récupérer les informations de l'évaluation
    $evalQuery = "SELECT \"idECUE\", session_idsession FROM evaluations WHERE idevaluation = :evaluationId";
    $evalStmt = $this->db->prepare($evalQuery);
    $evalStmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);
    $evalStmt->execute();
    $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluation) {
        return false;
    }
    
    // Récupérer l'année académique actuelle
    $yearQuery = "SELECT idannee_acad FROM annee_acad WHERE designation = (SELECT MAX(designation) FROM annee_acad)";
    $yearStmt = $this->db->prepare($yearQuery);
    $yearStmt->execute();
    $currentYear = $yearStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentYear) {
        return false;
    }
    
    $idECUE = $evaluation['idECUE'];
    $sessionId = $evaluation['session_idsession'];
    $anneeAcadId = $currentYear['idannee_acad'];
    
    // Démarrer une transaction
    $this->db->beginTransaction();
    
    try {
        foreach ($notes as $etudiantId => $note) {
            // Récupérer le matricule de l'étudiant
            $matriculeQuery = "SELECT matricule FROM etudiant WHERE idetudiant = :etudiantId";
            $matriculeStmt = $this->db->prepare($matriculeQuery);
            $matriculeStmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
            $matriculeStmt->execute();
            $etudiant = $matriculeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant) {
                continue; // Ignorer si l'étudiant n'existe pas
            }
            
            $matricule = $etudiant['matricule'];
            
            // Vérifier si une note existe déjà pour cet étudiant et cette évaluation
            $checkQuery = "SELECT idpoints FROM points 
                          WHERE matricule = :matricule 
                          AND typeEvaluation = :evaluationId  
                          AND \"ECUE_idECUE\" = :idECUE 
                          AND session_idsession = :sessionId";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $checkStmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);
            $checkStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
            $checkStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            $existingNote = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingNote) {
                // Insérer une nouvelle note
                $insertQuery = "INSERT INTO points (\"coteObtenu\", typeEvaluation, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) 
                              VALUES (:note, :evaluationId, :idECUE, :sessionId, :matricule, :anneeAcadId)";
                $stmt = $this->db->prepare($insertQuery);
                $stmt->bindParam(':note', $note, PDO::PARAM_STR);
                $stmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);  // CORRECTION: Utiliser l'ID de l'évaluation
                $stmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            } else {
                // Mettre à jour la note existante
                $updateQuery = "UPDATE points SET \"coteObtenu\" = :note 
                              WHERE matricule = :matricule 
                              AND typeEvaluation = :evaluationId  // CORRECTION: Utiliser l'ID de l'évaluation
                              AND \"ECUE_idECUE\" = :idECUE 
                              AND session_idsession = :sessionId";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bindParam(':note', $note, PDO::PARAM_STR);
                $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $stmt->bindParam(':evaluationId', $evaluationId, PDO::PARAM_INT);
                $stmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
        }
        
        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        error_log("Erreur lors de l'importation des notes: " . $e->getMessage());
        return false;
    }
}



public function getNotesByEvaluation($evaluationId) {
    try {
        // Récupérer d'abord l'évaluation pour obtenir ses informations
        $evalQuery = "SELECT e.*, t.categorie 
                      FROM evaluations e
                      LEFT JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
                      WHERE e.idevaluation = ?";
        $evalStmt = $this->db->prepare($evalQuery);
        $evalStmt->execute([$evaluationId]);
        $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) {
            return [];
        }
        
        // Récupérer les notes en utilisant directement l'ID de l'évaluation
        $query = "SELECT p.*, e.idetudiant, e.noms
                  FROM points p
                  INNER JOIN etudiant e ON p.matricule = e.matricule
                  WHERE p.typeEvaluation = ?
                  AND p.\"ECUE_idECUE\" = ?
                  AND p.session_idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $evaluationId,  // Utilise directement l'ID de l'évaluation, pas le type
            $evaluation['idECUE'],
            $evaluation['session_idsession']
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes: " . $e->getMessage());
        return [];
    }
}



/**
 * Récupère les évaluations par session
 */
public function getEvaluationsBySession($idECUE, $annee_acad_id, $sessionId) {
    $query = "SELECT e.*, t.\"designationT\", t.categorie 
              FROM evaluations e 
              INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
              WHERE e.\"idECUE\" = ? 
              AND e.session_idsession = ?";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idECUE, $sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les notes d'un étudiant pour une session spécifique
 */
public function getStudentGradesBySession($idEtudiant, $idECUE, $annee_acad_id, $sessionId) {
    try {
        // Récupérer d'abord le matricule de l'étudiant
        $query1 = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute([$idEtudiant]);
        $etudiant = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return [];
        }
        
        $matricule = $etudiant['matricule'];
        
        // Requête corrigée
        $query = "SELECT p.*, e.idevaluation, e.titre
                  FROM points p
                  INNER JOIN evaluations e ON p.typeEvaluation = e.idevaluation
                                         AND p.\"ECUE_idECUE\" = e.\"idECUE\"
                                         AND p.session_idsession = e.session_idsession
                  WHERE p.matricule = ?
                  AND p.\"ECUE_idECUE\" = ?
                  AND p.annee_acad_id = ?
                  AND p.session_idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$matricule, $idECUE, $annee_acad_id, $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes par session: " . $e->getMessage());
        return [];
    }
}


/**
 * Enregistre les points compilés d'un étudiant
 */
public function savePoints($coteObtenu, $idECUE, $idSession, $matricule, $annee_acad_id) {
    // Vérifier si une entrée existe déjà
    $query = "SELECT idpoints FROM points 
              WHERE \"ECUE_idECUE\" = ? 
              AND session_idsession = ? 
              AND matricule = ? 
              AND annee_acad_id = ? 
              AND typeEvaluation IS NULL";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idECUE, $idSession, $matricule, $annee_acad_id]);
    $existingPoint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingPoint) {
        // Mettre à jour une entrée existante
        $query = "UPDATE points SET \"coteObtenu\" = ? 
                  WHERE idpoints = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$coteObtenu, $existingPoint['idpoints']]);
    } else {
        // Créer une nouvelle entrée
        $query = "INSERT INTO points (\"coteObtenu\", \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$coteObtenu, $idECUE, $idSession, $matricule, $annee_acad_id]);
    }
}

/**
 * Récupère les notes pour l'onglet "Grille de notes"
 */
public function getCompiledGrades($idECUE, $annee_acad_id) {
    $query = "SELECT e.matricule, e.noms, p.\"coteObtenu\", p.session_idsession, s.\"designSession\"
              FROM etudiant e
              LEFT JOIN points p ON e.matricule = p.matricule 
                                  AND p.\"ECUE_idECUE\" = ? 
                                  AND p.annee_acad_id = ?
                                  AND p.typeEvaluation IS NULL
              LEFT JOIN session s ON p.session_idsession = s.idsession
              WHERE e.annee_acad_idannee_acad = ?
              ORDER BY e.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idECUE, $annee_acad_id, $annee_acad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère la note d'un étudiant pour une évaluation spécifique
 * @param int $evaluationId ID de l'évaluation
 * @param int $idEtudiant ID de l'étudiant
 * @return float|null La note obtenue ou null si aucune note n'existe
 */
public function getNoteByEvaluationAndStudent($evaluationId, $idEtudiant) {
    try {
        // Récupérer d'abord le matricule de l'étudiant
        $query1 = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute([$idEtudiant]);
        $etudiant = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return null;
        }
        
        $matricule = $etudiant['matricule'];
        
        // Récupérer les informations de l'évaluation
        $evalQuery = "SELECT \"idECUE\", session_idsession FROM evaluations WHERE idevaluation = ?";
        $evalStmt = $this->db->prepare($evalQuery);
        $evalStmt->execute([$evaluationId]);
        $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) {
            return null;
        }
        
        // Récupérer la note
        $query = "SELECT \"coteObtenu\" FROM points 
                  WHERE matricule = ? 
                  AND typeEvaluation = ? 
                  AND \"ECUE_idECUE\" = ? 
                  AND session_idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $matricule,
            $evaluationId,  // Utiliser l'ID de l'évaluation
            $evaluation['idECUE'],
            $evaluation['session_idsession']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['coteObtenu']) : null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la note: " . $e->getMessage());
        return null;
    }
}





/**
 * Enregistre un token pour un fichier Excel
 */
public function saveExcelToken($evaluationId, $token, $password) {
    $query = "INSERT INTO excel_tokens (token, evaluation_id, password, created_at) 
              VALUES (?, ?, ?, NOW())";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([$token, $evaluationId, password_hash($password, PASSWORD_DEFAULT)]);
}

/**
 * Vérifie si un token Excel est valide
 */
public function verifyExcelToken($evaluationId, $token) {
    $query = "SELECT id, token, password, created_at 
              FROM excel_tokens 
              WHERE evaluation_id = ? AND token = ? AND is_valid = 1
              ORDER BY created_at DESC LIMIT 1";
    $stmt = $this->db->prepare($query);
    $stmt->execute([$evaluationId, $token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


public function saveNote($evaluationId, $idEtudiant, $coteObtenu) {
    try {
        // Activer le mode de débogage
        error_log("saveNote appelé avec evaluationId=$evaluationId, idEtudiant=$idEtudiant, coteObtenu=$coteObtenu");
        
        // Récupérer le matricule de l'étudiant
        $studentQuery = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
        $studentStmt = $this->db->prepare($studentQuery);
        $studentStmt->execute([$idEtudiant]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            error_log("Étudiant non trouvé avec ID: $idEtudiant");
            return false;
        }
        
        $matricule = $student['matricule'];
        error_log("Matricule trouvé: $matricule");
        
        // Récupérer les informations de l'évaluation
        $evalQuery = "SELECT \"idECUE\", session_idsession, annee_acad_id FROM evaluations WHERE idevaluation = ?";
        $evalStmt = $this->db->prepare($evalQuery);
        $evalStmt->execute([$evaluationId]);
        $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) {
            error_log("Évaluation non trouvée avec ID: $evaluationId");
            return false;
        }
        
        $idECUE = $evaluation['idECUE'];
        $sessionId = $evaluation['session_idsession'];
        $anneeAcadId = $evaluation['annee_acad_id'];
        
        error_log("Info évaluation: ECUE=$idECUE, Session=$sessionId, Année=$anneeAcadId");
        
        // Vérifier si une note existe déjà
        $query = "SELECT COUNT(*) FROM points 
                  WHERE matricule = ? 
                  AND typeEvaluation = ? 
                  AND \"ECUE_idECUE\" = ? 
                  AND session_idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$matricule, $evaluationId, $idECUE, $sessionId]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Mettre à jour la note existante
            $query = "UPDATE points 
                      SET \"coteObtenu\" = ? 
                      WHERE matricule = ? 
                      AND typeEvaluation = ? 
                      AND \"ECUE_idECUE\" = ? 
                      AND session_idsession = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$coteObtenu, $matricule, $evaluationId, $idECUE, $sessionId]);
            
            error_log("Update note: " . ($result ? "Succès" : "Échec"));
            return $result;
        } else {
            // Créer une nouvelle note
            $query = "INSERT INTO points (\"coteObtenu\", typeEvaluation, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $coteObtenu, 
                $evaluationId, 
                $idECUE, 
                $sessionId,
                $matricule,
                $anneeAcadId
            ]);
            
            error_log("Insert note: " . ($result ? "Succès" : "Échec"));
            return $result;
        }
    } catch (PDOException $e) {
        error_log("Erreur SQL dans saveNote: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Exception dans saveNote: " . $e->getMessage());
        return false;
    }
}



/**
 * Compte le nombre d'examens pour une session et un ECUE donnés
 */
public function countExamsBySessionAndEcue($sessionId, $idECUE, $annee_acad_id) {
    $query = "SELECT COUNT(*) as nbExams
              FROM evaluations e
              INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
              WHERE e.\"idECUE\" = ?
              AND e.session_idsession = ? 
              AND t.categorie = 'Examen'";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idECUE, $sessionId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? intval($result['nbExams']) : 0;
}

/**
 * Récupère les informations d'un type d'évaluation
 */
public function getEvaluationType($idType) {
    $query = "SELECT * FROM typeevaluation WHERE \"idType\" = ?";
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idType]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Récupère les informations d'une session par son ID
 */
public function getSessionById($idSession) {
    $query = "SELECT * FROM session WHERE idsession = ?";
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idSession]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Enregistre les cotes compilées d'un étudiant (CC et EX séparément)
 */
public function saveCotesCompilees($coteCC, $coteEX, $moyenneFinale, $idECUE, $idSession, $matricule, $annee_acad_id, $idUser) {
    // Vérifier si une entrée existe déjà
    $query = "SELECT idpoints FROM cotes_grille 
              WHERE \"ECUE_idECUE\" = ? 
              AND session_idsession = ? 
              AND matricule = ? 
              AND annee_acad_id = ?";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute([$idECUE, $idSession, $matricule, $annee_acad_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Mettre à jour une entrée existante
        $query = "UPDATE cotes_grille 
                  SET CC = ?, EX = ?, MF = ?, date_compilation = NOW(), \"idUser\" = ? 
                  WHERE idpoints = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$coteCC, $coteEX, $moyenneFinale, $idUser, $existing['idpoints']]);
    } else {
        // Créer une nouvelle entrée
        $query = "INSERT INTO cotes_grille (CC, EX, MF, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id, \"idUser\") 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$coteCC, $coteEX, $moyenneFinale, $idECUE, $idSession, $matricule, $annee_acad_id, $idUser]);
    }
    
}



/**
 * Récupère les cotes compilées pour l'onglet "Grille de notes"
 */
public function getCotesGrille($idECUE, $annee_acad_id) {
    try {
        $query = "SELECT e.matricule, e.noms, c.CC, c.EX, c.MF, c.session_idsession, s.\"designSession\"
                  FROM etudiant e
                  LEFT JOIN (
                      SELECT * FROM cotes_grille 
                      WHERE \"ECUE_idECUE\" = ? AND annee_acad_id = ?
                  ) c ON e.matricule = c.matricule
                  LEFT JOIN session s ON c.session_idsession = s.idsession
                  INNER JOIN ecue ec ON ec.\"idECUE\" = ?
                  INNER JOIN ue u ON ec.\"UE_idUE\" = u.\"idUE\"
                  INNER JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
                  INNER JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
                  WHERE e.promotion_idpromotion = p.idpromotion
                    AND e.annee_acad_idannee_acad = ?
                  ORDER BY e.noms, s.idsession";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idECUE, $annee_acad_id, $idECUE, $annee_acad_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des cotes de la grille: " . $e->getMessage());
        return [];
    }
}



public function getSessionsByEcue($idEcue, $annee_acad_id) {
    try {
        // D'abord, vérifier s'il y a des évaluations existantes pour cet ECUE et cette année
        // Si oui, récupérer uniquement les sessions utilisées dans ces évaluations
        $query = "SELECT DISTINCT s.idsession, s.\"designSession\" 
                  FROM session s
                  INNER JOIN evaluations e ON s.idsession = e.session_idsession
                  WHERE e.\"idECUE\" = ? 
                  ORDER BY s.idsession";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idEcue]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucune session n'est trouvée (pas encore d'évaluations), récupérer toutes les sessions disponibles
        if (empty($sessions)) {
            $query = "SELECT idsession, \"designSession\" FROM session ORDER BY idsession";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $sessions;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des sessions: " . $e->getMessage());
        return [];
    }
}



public function compileGrades($idEcue, $annee_acad_id) {
    try {
        // Récupérer la configuration des moyennes
        $config = $this->getConfigurationMoyenne($idEcue, $annee_acad_id);
        if (empty($config)) {
            return false;
        }
        
        // Récupérer les étudiants inscrits à ce cours
        $students = $this->getStudentsByEcue($idEcue, $annee_acad_id);
        if (empty($students)) {
            return false;
        }
        
        // Récupérer les sessions disponibles
        $sessions = $this->getSessionsByEcue($idEcue, $annee_acad_id);
        if (empty($sessions)) {
            return false;
        }
        
        // Pour chaque étudiant et chaque session, calculer la moyenne
        foreach ($students as $student) {
            foreach ($sessions as $session) {
                $sessionId = $session['idsession'];
                
                // Récupérer les notes de l'étudiant pour cette session
                $grades = $this->getStudentGradesBySession($student['idetudiant'], $idEcue, $annee_acad_id, $sessionId);
                
                // Calculer les moyennes CC et EX
                $ccGrades = array_filter($grades, function($g) {
                    return isset($g['categorie']) && $g['categorie'] == 'CC';
                });
                
                $exGrades = array_filter($grades, function($g) {
                    return isset($g['categorie']) && $g['categorie'] == 'EX';
                });
                
                // Calculer la moyenne CC
                $ccAvg = $this->calculateAverage($ccGrades, $config[$sessionId]['formule_cc'] ?? '');
                
                // Calculer la moyenne EX
                $exAvg = $this->calculateAverage($exGrades, $config[$sessionId]['formule_ex'] ?? '');
                
                // Calculer la moyenne finale
                $finalAvg = null;
                if ($ccAvg !== null && $exAvg !== null) {
                    // Récupérer les pondérations depuis la configuration ou utiliser les valeurs par défaut
                    $universite = new Universite();
                    $ponderationsDefaut = $universite->getPonderationsDefaut();
                    $pondCC = $config[$sessionId]['ponderation_cc'] ?? $ponderationsDefaut['ponderation_cc'];
                    $pondEX = $config[$sessionId]['ponderation_ex'] ?? $ponderationsDefaut['ponderation_ex'];
                    $finalAvg = ($ccAvg * $pondCC) + ($exAvg * $pondEX);
                }
                
                // Enregistrer les moyennes dans la table cotes_grille
                $this->saveCoteGrille($student['matricule'], $idEcue, $sessionId, $annee_acad_id, $ccAvg, $exAvg, $finalAvg);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la compilation des notes: " . $e->getMessage());
        return false;
    }
}


/**
 * Enregistre les moyennes compilées dans la table cotes_grille
 * @param string $matricule Matricule de l'étudiant
 * @param int $idEcue ID de l'ECUE
 * @param int $sessionId ID de la session
 * @param int $annee_acad_id ID de l'année académique
 * @param float|null $cc Moyenne des contrôles continus
 * @param float|null $ex Moyenne des examens
 * @param float|null $mf Moyenne finale
 * @return bool Succès de l'opération
 */
public function saveCoteGrille($matricule, $idEcue, $sessionId, $annee_acad_id, $cc, $ex, $mf) {
    try {
        // Vérifier si une entrée existe déjà
        $query = "SELECT idpoints FROM cotes_grille 
                  WHERE matricule = ? 
                  AND \"ECUE_idECUE\" = ? 
                  AND session_idsession = ? 
                  AND annee_acad_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$matricule, $idEcue, $sessionId, $annee_acad_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Mettre à jour l'entrée existante
            $query = "UPDATE cotes_grille 
                      SET CC = ?, EX = ?, MF = ?, date_compilation = NOW() 
                      WHERE idpoints = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$cc, $ex, $mf, $existing['idpoints']]);
        } else {
            // Créer une nouvelle entrée
            $query = "INSERT INTO cotes_grille (CC, EX, MF, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$cc, $ex, $mf, $idEcue, $sessionId, $matricule, $annee_acad_id]);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement des moyennes: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule la moyenne des notes selon une formule
 * @param array $grades Liste des notes
 * @param string $formula Formule de calcul (optionnelle)
 * @return float|null Moyenne calculée ou null si pas de notes
 */
private function calculateAverage($grades, $formula = '') {
    if (empty($grades)) {
        return null;
    }
    
    // Si pas de formule spécifique, calculer la moyenne simple
    if (empty($formula)) {
        $sum = 0;
        $count = 0;
        
        foreach ($grades as $grade) {
            if (isset($grade['coteObtenu']) && is_numeric($grade['coteObtenu'])) {
                $sum += floatval($grade['coteObtenu']);
                $count++;
            }
        }
        
        return $count > 0 ? $sum / $count : null;
    }
    
    // TODO: Implémenter le calcul selon une formule spécifique
    // Cette partie nécessiterait un parser de formule mathématique
    
    return null;
}


/**
 * Vérifie si un examen existe déjà pour cette session et cet ECUE
 *
 * @param int $idECUE ID de l'ECUE
 * @param int $sessionId ID de la session
 * @param int|null $evaluationId ID de l'évaluation à exclure (pour les mises à jour)
 * @return bool True si un examen existe déjà
 */
public function examExistsForSession($idECUE, $sessionId, $evaluationId = null) {
    try {
        $query = "SELECT COUNT(*) FROM evaluations e 
                  JOIN typeevaluation t ON e.\"idType\" = t.\"idType\" 
                  WHERE e.\"idECUE\" = ? 
                  AND e.session_idsession = ? 
                  AND t.categorie = 'EX'";
        
        $params = [$idECUE, $sessionId];
        
        if ($evaluationId !== null) {
            $query .= " AND e.idevaluation != ?";
            $params[] = $evaluationId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'existence d'un examen: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si la session est la deuxième session
 *
 * @param int $sessionId ID de la session
 * @return bool True si c'est la deuxième session
 */
public function isDeuxiemeSession($sessionId) {
    try {
        $query = "SELECT \"designSession\" FROM session WHERE idsession = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            return mb_strtolower($session['designSession']) === mb_strtolower('deuxième session') || 
                   mb_strtolower($session['designSession']) === mb_strtolower('deuxieme session');
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de la session: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un type d'évaluation est de catégorie CC
 *
 * @param int $idType ID du type d'évaluation
 * @return bool True si c'est un contrôle continu
 */
public function isControleContinu($idType) {
    try {
        $query = "SELECT categorie FROM typeevaluation WHERE \"idType\" = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idType]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($type) {
            return mb_strtolower($type['categorie']) === mb_strtolower('cc');
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du type d'évaluation: " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère les étudiants éligibles pour la 2ème session d'un ECUE
 * Seuls les étudiants ayant une moyenne pondérée < 10 dans l'UE en première session sont inclus
 * 
 * @param int $idEcue ID de l'ECUE
 * @param int $anneeAcadId ID de l'année académique
 * @return array Liste des étudiants éligibles
 */
public function getStudentsEligibleForSecondSession($idEcue, $anneeAcadId) {
    try {
        // 1. Récupérer l'UE associée à cet ECUE
        $query = "SELECT e.\"UE_idUE\" 
                  FROM ecue e 
                  WHERE e.\"idECUE\" = :idEcue";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        $ecueInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ecueInfo) {
            return [];
        }
        
        $idUE = $ecueInfo['UE_idUE'];
        
        // 2. Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session 
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session' 
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstSession) {
            return []; // Impossible de trouver la première session
        }
        
        $session1Id = $firstSession['idsession'];
        
        // 3. Récupérer la promotion associée à cet ECUE
        $query = "SELECT p.idpromotion 
                  FROM ecue e
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  WHERE e.\"idECUE\" = :idEcue";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$promotion) {
            return [];
        }
        
        $promotionId = $promotion['idpromotion'];
        
        // 4. Requête optimisée pour obtenir les étudiants avec moyenne pondérée < 10 dans cette UE
        $query = "SELECT e.idetudiant, e.matricule, e.noms 
                  FROM etudiant e
                  JOIN (
                      SELECT cg.matricule, 
                             SUM(cg.MF * ROUND((ec.CMI + ec.TD + ec.TP)/" . $this->heuresParCredit . ", 2)) / 
                             SUM(ROUND((ec.CMI + ec.TD + ec.TP)/" . $this->heuresParCredit . ", 2)) AS moyenne_ponderee
                      FROM cotes_grille cg
                      JOIN ecue ec ON cg.\"ECUE_idECUE\" = ec.\"idECUE\"
                      WHERE ec.\"UE_idUE\" = :idUE 
                      AND cg.session_idsession = :sessionId
                      AND cg.annee_acad_id = :anneeAcadId
                      GROUP BY cg.matricule
                      HAVING moyenne_ponderee < 10
                  ) AS moyennes ON e.matricule = moyennes.matricule
                  WHERE e.promotion_idpromotion = :promotionId
                  AND e.annee_acad_idannee_acad = :anneeAcadId
                  ORDER BY e.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucun résultat n'est trouvé, cela peut être parce qu'aucune note n'est encore saisie
        // Dans ce cas, récupérer tous les étudiants de la promotion
        if (empty($result)) {
            $query = "SELECT e.idetudiant, e.matricule, e.noms 
                      FROM etudiant e
                      WHERE e.promotion_idpromotion = :promotionId
                      AND e.annee_acad_idannee_acad = :anneeAcadId
                      ORDER BY e.noms";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des étudiants éligibles pour la 2ème session: " . $e->getMessage());
        return [];
    }
}



public function getStudentsEligibleForSecondSessionCours($idEcue, $anneeAcadId) {
    try {
        // 1. Récupérer l'UE associée à cet ECUE
        $query = "SELECT e.\"UE_idUE\", e.\"designationECUE\"
                  FROM ecue e
                  WHERE e.\"idECUE\" = :idEcue";
       
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        $ecueInfo = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if (!$ecueInfo) {
            return [];
        }
       
        $idUE = $ecueInfo['UE_idUE'];
        $ecueDesignation = $ecueInfo['designationECUE'];
       
        // 2. Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if (!$firstSession) {
            return []; // Impossible de trouver la première session
        }
       
        $session1Id = $firstSession['idsession'];
       
        // 3. Récupérer la promotion associée à cet ECUE
        $query = "SELECT p.idpromotion
                  FROM ecue e
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  WHERE e.\"idECUE\" = :idEcue";
       
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->execute();
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if (!$promotion) {
            return [];
        }
       
        $promotionId = $promotion['idpromotion'];
       
        // 4. Vérifier d'abord si des notes existent pour cet ECUE en première session
        $query = "SELECT COUNT(*) as note_count
                  FROM cotes_grille
                  WHERE \"ECUE_idECUE\" = :idEcue
                  AND session_idsession = :sessionId
                  AND annee_acad_id = :anneeAcadId";
                  
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        $noteCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si des notes existent mais qu'aucun étudiant n'a échoué, retourner un tableau vide
        // au lieu de tous les étudiants de la promotion
        $notesExist = ($noteCount && $noteCount['note_count'] > 0);
       
        // 5. Récupérer tous les étudiants qui ont échoué à cet ECUE spécifique
        // ou qui n'ont pas de note pour cet ECUE
        $query = "SELECT e.idetudiant, e.matricule, e.noms
                  FROM etudiant e
                  LEFT JOIN cotes_grille cg ON e.matricule = cg.matricule
                                           AND cg.\"ECUE_idECUE\" = :idEcue
                                           AND cg.session_idsession = :sessionId
                                           AND cg.annee_acad_id = :anneeAcadId
                  WHERE e.promotion_idpromotion = :promotionId
                  AND e.annee_acad_idannee_acad = :anneeAcadId
                  AND (cg.MF IS NULL OR cg.MF < 10 OR cg.CC IS NULL OR cg.EX IS NULL)
                  ORDER BY e.noms";
       
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
       
        $failedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
        // Si aucun étudiant n'a échoué et qu'aucune note n'existe,
        // retourner tous les étudiants de la promotion (cas où aucune note n'est encore saisie)
        // Sinon, si des notes existent mais qu'aucun étudiant n'a échoué, retourner un tableau vide
        if (empty($failedStudents)) {
            if (!$notesExist) {
                // Aucune note n'existe, retourner tous les étudiants
                $query = "SELECT e.idetudiant, e.matricule, e.noms
                          FROM etudiant e
                          WHERE e.promotion_idpromotion = :promotionId
                          AND e.annee_acad_idannee_acad = :anneeAcadId
                          ORDER BY e.noms";
               
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $stmt->execute();
               
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Des notes existent mais aucun étudiant n'a échoué, retourner un tableau vide
                error_log("ECUE: $ecueDesignation - Tous les étudiants ont réussi en première session, aucun éligible pour la deuxième session");
                return [];
            }
        }
       
        // 6. Filtrer les étudiants qui ont validé l'UE malgré l'échec à cet ECUE
        $eligibleStudents = [];
       
        foreach ($failedStudents as $student) {
            $matricule = $student['matricule'];
           
            // Vérifier si l'UE a été validée en première session
            $query = "SELECT
                        SUM(cg.MF * ROUND((ec.CMI + ec.TD + ec.TP)/" . $this->heuresParCredit . ", 2)) /
                        SUM(ROUND((ec.CMI + ec.TD + ec.TP)/" . $this->heuresParCredit . ", 2)) AS moyenne_ponderee,
                        COUNT(cg.MF) AS notes_count,
                        (SELECT COUNT(*) FROM ecue WHERE \"UE_idUE\" = :idUE) AS total_ecues
                      FROM cotes_grille cg
                      JOIN ecue ec ON cg.\"ECUE_idECUE\" = ec.\"idECUE\"
                      WHERE ec.\"UE_idUE\" = :idUE
                      AND cg.matricule = :matricule
                      AND cg.session_idsession = :sessionId
                      AND cg.annee_acad_id = :anneeAcadId
                      AND cg.MF IS NOT NULL";
           
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
           
            $ueResult = $stmt->fetch(PDO::FETCH_ASSOC);
           
            // L'UE est validée si la moyenne pondérée est >= 10 ET toutes les ECUEs ont des notes
            $ueValidated = false;
            if ($ueResult &&
                $ueResult['moyenne_ponderee'] !== null &&
                $ueResult['moyenne_ponderee'] >= 10 &&
                $ueResult['notes_count'] == $ueResult['total_ecues']) {
                $ueValidated = true;
            }
           
            // Si l'UE n'est pas validée, l'étudiant est éligible pour la deuxième session
            if (!$ueValidated) {
                $eligibleStudents[] = $student;
            }
        }
       
        // Ajouter un message de débogage
        error_log("ECUE: $ecueDesignation - Étudiants éligibles: " . count($eligibleStudents) . " sur " . count($failedStudents) . " ayant échoué");
       
        return $eligibleStudents;
       
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des étudiants éligibles pour la 2ème session: " . $e->getMessage());
        return [];
    }
}


/**
 * Vérifie si un étudiant a validé une UE en première session
 *
 * @param string $matricule Matricule de l'étudiant
 * @param int $idUE ID de l'UE
 * @param int $anneeAcadId ID de l'année académique
 * @return bool True si l'UE est validée (moyenne >= 10)
 */
public function hasValidatedUeInFirstSession($matricule, $idUE, $anneeAcadId) {
    try {
        // Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session 
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session' 
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstSession) {
            return false; // Impossible de trouver la première session
        }
        
        $session1Id = $firstSession['idsession'];
        
        // Calculer la moyenne pondérée
        $average = $this->calculateUeWeightedAverage($matricule, $idUE, $session1Id, $anneeAcadId);
        
        // L'UE est validée si la moyenne est >= 10
        return $average !== null && $average >= 10;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de validation d'UE: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les ECUE d'une UE avec leurs crédits calculés
 *
 * @param int $idUE ID de l'UE
 * @return array Liste des ECUE avec leurs informations et crédits
 */
public function getEcuesWithCreditsByUE($idUE) {
    try {
        $query = "SELECT e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP, 
                  ROUND((e.CMI + e.TD + e.TP)/" . $this->heuresParCredit . ", 2) AS credit 
                  FROM ecue e 
                  WHERE e.\"UE_idUE\" = :idUE
                  ORDER BY e.\"designationECUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des ECUE avec crédits: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcule et enregistre les moyennes pondérées d'UE pour tous les étudiants
 * Cette méthode peut être exécutée périodiquement ou avant l'ouverture de la 2ème session
 *
 * @param int $idUE ID de l'UE
 * @param int $anneeAcadId ID de l'année académique
 * @return bool Succès de l'opération
 */
public function calculateAndStoreUEAverages($idUE, $anneeAcadId) {
    try {
        $this->db->beginTransaction();
        
        // Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session 
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session' 
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstSession) {
            $this->db->rollBack();
            return false; // Impossible de trouver la première session
        }
        
        $session1Id = $firstSession['idsession'];
        
        // Récupérer tous les ECUE de cette UE avec leurs crédits
        $ecues = $this->getEcuesWithCreditsByUE($idUE);
        if (empty($ecues)) {
            $this->db->rollBack();
            return false;
        }
        
        // Récupérer tous les étudiants qui ont des notes pour cette UE
        $query = "SELECT DISTINCT e.idetudiant, e.matricule, e.noms
                  FROM etudiant e
                  JOIN cotes_grille cg ON e.matricule = cg.matricule
                  JOIN ecue ec ON cg.\"ECUE_idECUE\" = ec.\"idECUE\"
                  WHERE ec.\"UE_idUE\" = :idUE
                  AND cg.session_idsession = :sessionId
                  AND cg.annee_acad_id = :anneeAcadId
                  ORDER BY e.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Table temporaire pour stocker les moyennes (peut être une table permanente dans une évolution future)
        $query = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_ue_averages (
                    matricule VARCHAR(50) NOT NULL,
                    id_ue INT NOT NULL,
                    session_id INT NOT NULL,
                    annee_acad_id INT NOT NULL,
                    moyenne_ponderee DECIMAL(5,2),
                    est_valide BOOLEAN,
                    PRIMARY KEY (matricule, id_ue, session_id, annee_acad_id)
                  )";
        $this->db->exec($query);
        
        // Calculer la moyenne pour chaque étudiant
        $insertQuery = "INSERT INTO temp_ue_averages 
                        (matricule, id_ue, session_id, annee_acad_id, moyenne_ponderee, est_valide)
                        VALUES (:matricule, :idUE, :sessionId, :anneeAcadId, :moyenne, :estValide)
                        ON DUPLICATE KEY UPDATE 
                        moyenne_ponderee = VALUES(moyenne_ponderee),
                        est_valide = VALUES(est_valide)";
        
        $insertStmt = $this->db->prepare($insertQuery);
        
        foreach ($students as $student) {
            $average = $this->calculateUeWeightedAverage(
                $student['matricule'], 
                $idUE, 
                $session1Id, 
                $anneeAcadId
            );
            
            if ($average !== null) {
                $isValid = $average >= 10;
                
                $insertStmt->bindParam(':matricule', $student['matricule'], PDO::PARAM_STR);
                $insertStmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
                $insertStmt->bindParam(':sessionId', $session1Id, PDO::PARAM_INT);
                $insertStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $insertStmt->bindParam(':moyenne', $average, PDO::PARAM_STR);
                $insertStmt->bindParam(':estValide', $isValid, PDO::PARAM_BOOL);
                $insertStmt->execute();
            }
        }
        
        $this->db->commit();
        return true;
    } catch (PDOException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        error_log("Erreur lors du calcul et de l'enregistrement des moyennes d'UE: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les notes compilées d'un étudiant pour un ECUE spécifique
 *
 * @param string $matricule Matricule de l'étudiant
 * @param int $ecueId ID de l'ECUE
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array|false Les notes de l'étudiant ou false si aucune note trouvée
 */
public function getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId) {
    try {
        $query = "SELECT cg.*, s.\"designSession\", e.\"designationECUE\", 
                         u.\"designationUE\", u.\"codeUE\", et.noms
                  FROM cotes_grille cg
                  JOIN session s ON cg.session_idsession = s.idsession
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN etudiant et ON cg.matricule = et.matricule
                  WHERE cg.matricule = :matricule
                  AND cg.\"ECUE_idECUE\" = :ecueId
                  AND cg.session_idsession = :sessionId
                  AND cg.annee_acad_id = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes de l'étudiant: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule la moyenne pondérée d'un étudiant pour une UE spécifique
 *
 * @param string $matricule Matricule de l'étudiant
 * @param int $ueId ID de l'UE
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Tableau contenant la moyenne et les détails du calcul
 */
public function calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId) {
    try {
        // Récupérer tous les ECUE de cette UE avec leurs notes et crédits
        $query = "SELECT cg.\"ECUE_idECUE\", cg.MF, e.\"designationECUE\", 
                         ROUND((e.CMI + e.TD + e.TP)/" . $this->heuresParCredit . ", 2) AS credit
                  FROM cotes_grille cg
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  WHERE cg.matricule = :matricule
                  AND e.\"UE_idUE\" = :ueId
                  AND cg.session_idsession = :sessionId
                  AND cg.annee_acad_id = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucun ECUE n'a de note, retourner un résultat vide
        if (empty($ecues)) {
            return [
                'moyenne' => null,
                'est_valide' => false,
                'details' => [],
                'total_credits' => 0,
                'somme_ponderee' => 0
            ];
        }
        
        $totalCredits = 0;
        $sommePonderee = 0;
        
        // Calculer la somme pondérée et le total des crédits
        foreach ($ecues as &$ecue) {
            $credit = floatval($ecue['credit']);
            $note = floatval($ecue['MF']);
            
            $ecue['note_ponderee'] = $note * $credit;
            $totalCredits += $credit;
            $sommePonderee += $ecue['note_ponderee'];
        }
        
        // Calculer la moyenne pondérée
        $moyenne = $totalCredits > 0 ? $sommePonderee / $totalCredits : null;
        
        // Déterminer si l'UE est validée (moyenne >= 10)
        $estValide = $moyenne !== null && $moyenne >= 10;
        
        // Récupérer les informations de l'UE
        $queryUE = "SELECT \"designationUE\", \"codeUE\" FROM ue WHERE \"idUE\" = :ueId";
        $stmtUE = $this->db->prepare($queryUE);
        $stmtUE->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmtUE->execute();
        $ue = $stmtUE->fetch(PDO::FETCH_ASSOC);
        
        return [
            'moyenne' => $moyenne,
            'est_valide' => $estValide,
            'details' => $ecues,
            'total_credits' => $totalCredits,
            'somme_ponderee' => $sommePonderee,
            'ue' => $ue
        ];
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul de la moyenne UE: " . $e->getMessage());
        return [
            'moyenne' => null,
            'est_valide' => false,
            'details' => [],
            'total_credits' => 0,
            'somme_ponderee' => 0,
            'error' => $e->getMessage()
        ];
    }
}


/**
 * Calcule la moyenne pondérée d'un étudiant pour un semestre spécifique
 *
 * @param string $matricule Matricule de l'étudiant
 * @param int $semestreId ID du semestre
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Tableau contenant la moyenne et les détails du calcul
 */
public function calculerMoyenneSemestre($matricule, $semestreId, $sessionId, $anneeId) {
    try {
        // Récupérer toutes les UE de ce semestre
        $query = "SELECT \"idUE\", \"designationUE\", \"codeUE\" 
                  FROM ue 
                  WHERE semestre_idsemestre = :semestreId
                  ORDER BY \"codeUE\"";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->execute();
        
        $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ues)) {
            return [
                'moyenne' => null,
                'est_valide' => false,
                'details' => [],
                'total_credits' => 0,
                'somme_ponderee' => 0,
                'semestre' => null
            ];
        }
        
        // Récupérer les informations du semestre
        $querySemestre = "SELECT s.\"numeroSemestre\", p.\"designationPromotion\", o.\"designationOrientation\"
                          FROM semestre s
                          JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                          JOIN orientation o ON p.orientation_idorientation = o.idorientation
                          WHERE s.idsemestre = :semestreId";
        
        $stmtSemestre = $this->db->prepare($querySemestre);
        $stmtSemestre->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmtSemestre->execute();
        $semestre = $stmtSemestre->fetch(PDO::FETCH_ASSOC);
        
        $totalCredits = 0;
        $sommePonderee = 0;
        $uesDetails = [];
        
        // Calculer la moyenne pour chaque UE
        foreach ($ues as $ue) {
            $ueId = $ue['idUE'];
            $ueResult = $this->calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId);
            
            // Si l'UE a une moyenne calculée
            if ($ueResult['moyenne'] !== null) {
                $ueCredits = $ueResult['total_credits'];
                $ueMoyenne = $ueResult['moyenne'];
                
                $totalCredits += $ueCredits;
                $sommePonderee += ($ueMoyenne * $ueCredits);
                
                // Ajouter les détails de l'UE
                $uesDetails[] = [
                    'ue_id' => $ueId,
                    'designation' => $ue['designationUE'],
                    'code' => $ue['codeUE'],
                    'moyenne' => $ueMoyenne,
                    'credits' => $ueCredits,
                    'est_valide' => $ueResult['est_valide'],
                    'ecues' => $ueResult['details']
                ];
            }
        }
        
        // Calculer la moyenne du semestre
        $moyenne = $totalCredits > 0 ? $sommePonderee / $totalCredits : null;
        
        // Déterminer si le semestre est validé (moyenne >= 10)
        $estValide = $moyenne !== null && $moyenne >= 10;
        
        return [
            'moyenne' => $moyenne,
            'est_valide' => $estValide,
            'details' => $uesDetails,
            'total_credits' => $totalCredits,
            'somme_ponderee' => $sommePonderee,
            'semestre' => $semestre
        ];
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul de la moyenne du semestre: " . $e->getMessage());
        return [
            'moyenne' => null,
            'est_valide' => false,
            'details' => [],
            'total_credits' => 0,
            'somme_ponderee' => 0,
            'error' => $e->getMessage()
        ];
    }
}



public function isNotesVerrouillees($idEcue, $idSession, $idAnneeAcad) {
    $sql = "SELECT * FROM ecue_notes_verrouillage 
            WHERE \"idECUE\" = ? AND idsession = ? AND idannee_acad = ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$idEcue, $idSession, $idAnneeAcad]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


public function verrouillageNotes($idEcue, $idSession, $idAnneeAcad, $idUser) {
    $sql = "INSERT INTO ecue_notes_verrouillage 
            (\"idECUE\", idsession, idannee_acad, date_verrouillage, \"idUser\") 
            VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([$idEcue, $idSession, $idAnneeAcad, $idUser]);
}


public function getSessionsVerrouillees($idEcue, $idAnneeAcad) {
    $sql = "SELECT idsession FROM ecue_notes_verrouillage 
            WHERE \"idECUE\" = ? AND idannee_acad = ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$idEcue, $idAnneeAcad]);
    
    $sessions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sessions[] = $row['idsession'];
    }
    return $sessions;
}


/**
 * Déverrouille les notes d'un ECUE pour une session et une année académique spécifiques
 * 
 * @param int $idEcue ID de l'ECUE
 * @param int $idSession ID de la session
 * @param int $idAnneeAcad ID de l'année académique
 * @return bool Succès de l'opération
 */

// Méthode pour déverrouiller les notes par ID
public function deverrouillerNotes($id, $userId) {
    try {
        // Vérifier si le verrouillage existe
        $query = "SELECT * FROM ecue_notes_verrouillage WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        // Supprimer le verrouillage
        $query = "DELETE FROM ecue_notes_verrouillage WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        // Enregistrer l'action dans les logs si nécessaire
        // ...
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors du déverrouillage des notes: " . $e->getMessage());
        return false;
    }
}



public function getAllVerrouillages($filtres = []) {
    $query = "SELECT v.*, 
                e.\"designationECUE\", 
                u.\"designationUE\",
                s.description,
                s.\"designSession\",
                p.\"designationPromotion\",
                usr.\"nomUser\" AS nom_utilisateur
            FROM ecue_notes_verrouillage v
            JOIN ecue e ON v.\"idECUE\" = e.\"idECUE\"
            JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
            JOIN session s ON v.idsession = s.idsession
            LEFT JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
            LEFT JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
            LEFT JOIN t_users usr ON v.\"idUser\" = usr.\"idUser\"
            WHERE 1=1";
    
    $params = [];
    
    if (isset($filtres['annee_id']) && $filtres['annee_id'] > 0) {
        $query .= " AND v.idannee_acad = :annee_id";
        $params[':annee_id'] = $filtres['annee_id'];
    }
    
    if (isset($filtres['session_id']) && $filtres['session_id'] > 0) {
        $query .= " AND v.idsession = :session_id";
        $params[':session_id'] = $filtres['session_id'];
    }
    
    $query .= " ORDER BY v.date_verrouillage DESC";
    
    $stmt = $this->db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la note d'un étudiant pour une évaluation spécifique
 *
 * @param int $evaluationId ID de l'évaluation
 * @param int $idEtudiant ID de l'étudiant
 * @return float|false La note obtenue ou false si aucune note n'existe
 */
public function getNoteEvaluation($evaluationId, $idEtudiant) {
    try {
        // Récupérer d'abord le matricule de l'étudiant
        $query1 = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->execute([$idEtudiant]);
        $etudiant = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return false;
        }
        
        $matricule = $etudiant['matricule'];
        
        // Récupérer les informations de l'évaluation
        $evalQuery = "SELECT \"idECUE\", session_idsession FROM evaluations WHERE idevaluation = ?";
        $evalStmt = $this->db->prepare($evalQuery);
        $evalStmt->execute([$evaluationId]);
        $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) {
            return false;
        }
        
        // Récupérer la note
        $query = "SELECT \"coteObtenu\" FROM points 
                  WHERE matricule = ? 
                  AND typeEvaluation = ? 
                  AND \"ECUE_idECUE\" = ? 
                  AND session_idsession = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $matricule,
            $evaluationId,
            $evaluation['idECUE'],
            $evaluation['session_idsession']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['coteObtenu']) : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la note d'évaluation: " . $e->getMessage());
        return false;
    }
}



/**
 * Compile les notes pour un ECUE et une session donnés
 * 
 * @param int $idECUE ID de l'ECUE
 * @param int $anneeAcadId ID de l'année académique
 * @param int $sessionId ID de la session (optionnel, toutes les sessions si non spécifié)
 * @return bool Succès de l'opération
 */
public function compileNotes($idECUE, $anneeAcadId, $sessionId = null) {
    try {
        // Récupérer la configuration des moyennes
        $configQuery = "SELECT * FROM configuration_moyenne 
                        WHERE \"idECUE\" = :idECUE AND annee_acad_id = :anneeAcadId";
        
        if ($sessionId !== null) {
            $configQuery .= " AND session_idsession = :sessionId";
        }
        
        $configStmt = $this->db->prepare($configQuery);
        $configStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $configStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        
        if ($sessionId !== null) {
            $configStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        }
        
        $configStmt->execute();
        $configs = $configStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucune configuration n'est trouvée, utiliser les valeurs par défaut depuis la configuration
        $universite = new Universite();
        $ponderationsDefaut = $universite->getPonderationsDefaut();
        $defaultCC = $ponderationsDefaut['ponderation_cc'];
        $defaultEX = $ponderationsDefaut['ponderation_ex'];
        
        // Récupérer les sessions à traiter
        $sessionQuery = "SELECT idsession FROM session";
        if ($sessionId !== null) {
            $sessionQuery .= " WHERE idsession = :sessionId";
        }
        
        $sessionStmt = $this->db->prepare($sessionQuery);
        if ($sessionId !== null) {
            $sessionStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        }
        
        $sessionStmt->execute();
        $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les étudiants
        $studentsQuery = "SELECT DISTINCT matricule FROM points 
                         WHERE \"ECUE_idECUE\" = :idECUE AND annee_acad_id = :anneeAcadId";
        
        $studentsStmt = $this->db->prepare($studentsQuery);
        $studentsStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $studentsStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $studentsStmt->execute();
        
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque session et chaque étudiant, calculer les moyennes
        foreach ($sessions as $session) {
            $currentSessionId = $session['idsession'];
            
            // Trouver la configuration pour cette session
            $sessionConfig = null;
            foreach ($configs as $config) {
                if ($config['session_idsession'] == $currentSessionId) {
                    $sessionConfig = $config;
                    break;
                }
            }
            
            // Utiliser les valeurs par défaut si aucune configuration n'est trouvée
            $ccWeight = $sessionConfig ? floatval($sessionConfig['ponderation_cc']) : $defaultCC;
            $exWeight = $sessionConfig ? floatval($sessionConfig['ponderation_ex']) : $defaultEX;
            
            // Pour chaque étudiant
            foreach ($students as $student) {
                $matricule = $student['matricule'];
                
                // Récupérer les notes de CC
                $ccQuery = "SELECT p.*, t.categorie 
                           FROM points p
                           JOIN typeevaluation t ON p.typeEvaluation = t.\"idType\"
                           WHERE p.\"ECUE_idECUE\" = :idECUE 
                           AND p.session_idsession = :sessionId 
                           AND p.matricule = :matricule 
                           AND p.annee_acad_id = :anneeAcadId
                           AND t.categorie = 'CC'";
                
                $ccStmt = $this->db->prepare($ccQuery);
                $ccStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $ccStmt->bindParam(':sessionId', $currentSessionId, PDO::PARAM_INT);
                $ccStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $ccStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $ccStmt->execute();
                
                $ccNotes = $ccStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Récupérer les notes d'examen
                $exQuery = "SELECT p.*, t.categorie 
                           FROM points p
                           JOIN typeevaluation t ON p.typeEvaluation = t.\"idType\"
                           WHERE p.\"ECUE_idECUE\" = :idECUE 
                           AND p.session_idsession = :sessionId 
                           AND p.matricule = :matricule 
                           AND p.annee_acad_id = :anneeAcadId
                           AND t.categorie = 'Examen'";
                
                $exStmt = $this->db->prepare($exQuery);
                $exStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $exStmt->bindParam(':sessionId', $currentSessionId, PDO::PARAM_INT);
                $exStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $exStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $exStmt->execute();
                
                $exNotes = $exStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Récupérer les ajustements
                $adjustQuery = "SELECT p.*, t.categorie 
                               FROM points p
                               JOIN typeevaluation t ON p.typeEvaluation = t.\"idType\"
                               WHERE p.\"ECUE_idECUE\" = :idECUE 
                               AND p.session_idsession = :sessionId 
                               AND p.matricule = :matricule 
                               AND p.annee_acad_id = :anneeAcadId
                               AND t.categorie = 'Bonus'";
                
                $adjustStmt = $this->db->prepare($adjustQuery);
                $adjustStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $adjustStmt->bindParam(':sessionId', $currentSessionId, PDO::PARAM_INT);
                $adjustStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $adjustStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $adjustStmt->execute();
                
                $adjustNotes = $adjustStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculer la moyenne des CC
                $ccAverage = null;
                if (!empty($ccNotes)) {
                    $ccSum = 0;
                    $ccCount = 0;
                    
                    foreach ($ccNotes as $note) {
                        if ($note['coteObtenu'] !== null) {
                            $ccSum += floatval($note['coteObtenu']);
                            $ccCount++;
                        }
                    }
                    
                    if ($ccCount > 0) {
                        $ccAverage = $ccSum / $ccCount;
                    }
                }
                
                // Récupérer la note d'examen (généralement une seule)
                $exAverage = null;
                if (!empty($exNotes)) {
                    foreach ($exNotes as $note) {
                        if ($note['coteObtenu'] !== null) {
                            $exAverage = floatval($note['coteObtenu']);
                            break; // Prendre la première note d'examen trouvée
                        }
                    }
                }
                
                // Calculer la somme des ajustements
                $adjustmentTotal = 0;
                foreach ($adjustNotes as $note) {
                    if ($note['coteObtenu'] !== null) {
                        $adjustmentTotal += floatval($note['coteObtenu']);
                    }
                }
                
                // Calculer la moyenne finale
                $finalAverage = null;
                
                // Pour la deuxième session, l'examen vaut 100%
                $isDeuxiemeSession = $this->isDeuxiemeSession($currentSessionId);
                
                if ($isDeuxiemeSession) {
                    // En deuxième session, seul l'examen compte
                    if ($exAverage !== null) {
                        $finalAverage = $exAverage;
                    }
                } else {
                    // En première session, appliquer la pondération
                    if ($ccAverage !== null && $exAverage !== null) {
                        // Les deux notes sont disponibles
                        $finalAverage = ($ccAverage * $ccWeight) + ($exAverage * $exWeight);
                    } elseif ($ccAverage !== null) {
                        // Seulement CC disponible
                        $finalAverage = $ccAverage;
                    } elseif ($exAverage !== null) {
                        // Seulement examen disponible
                        $finalAverage = $exAverage;
                    }
                }
                
                // Ajouter les points d'ajustement
                if ($finalAverage !== null && $adjustmentTotal > 0) {
                    $finalAverage = min(20, $finalAverage + $adjustmentTotal); // Ne pas dépasser 20
                }
                
                // Mettre à jour ou insérer dans cotes_grille
                $checkQuery = "SELECT idpoints FROM cotes_grille 
                              WHERE \"ECUE_idECUE\" = :idECUE 
                              AND session_idsession = :sessionId 
                              AND matricule = :matricule 
                              AND annee_acad_id = :anneeAcadId";
                
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $checkStmt->bindParam(':sessionId', $currentSessionId, PDO::PARAM_INT);
                $checkStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $checkStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                $checkStmt->execute();
                
                $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingRecord) {
                    // Mettre à jour
                    $updateQuery = "UPDATE cotes_grille 
                                   SET CC = :cc, EX = :ex, MF = :mf 
                                   WHERE idpoints = :idpoints";
                    
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindParam(':cc', $ccAverage, PDO::PARAM_STR);
                    $updateStmt->bindParam(':ex', $exAverage, PDO::PARAM_STR);
                    $updateStmt->bindParam(':mf', $finalAverage, PDO::PARAM_STR);
                    $updateStmt->bindParam(':idpoints', $existingRecord['idpoints'], PDO::PARAM_INT);
                    $updateStmt->execute();
                } else {
                    // Insérer
                    $insertQuery = "INSERT INTO cotes_grille 
                                   (CC, EX, MF, \"ECUE_idECUE\", session_idsession, matricule, annee_acad_id, \"idUser\") 
                                   VALUES (:cc, :ex, :mf, :idECUE, :sessionId, :matricule, :anneeAcadId, :idUser)";
                    
                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->bindParam(':cc', $ccAverage, PDO::PARAM_STR);
                    $insertStmt->bindParam(':ex', $exAverage, PDO::PARAM_STR);
                    $insertStmt->bindParam(':mf', $finalAverage, PDO::PARAM_STR);
                    $insertStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                    $insertStmt->bindParam(':sessionId', $currentSessionId, PDO::PARAM_INT);
                    $insertStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                    $insertStmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
                    $insertStmt->bindParam(':idUser', $_SESSION['id'], PDO::PARAM_INT);
                    $insertStmt->execute();
                }
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Erreur lors de la compilation des notes: ' . $e->getMessage());
        return false;
    }
}












// Dans la classe Ecue






















}
