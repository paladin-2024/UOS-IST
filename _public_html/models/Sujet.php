<?php

class Sujet {
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Récupère tous les sujets avec filtrage optionnel
     * @param string $search Terme de recherche
     * @return array Liste des sujets
     */
    public function getAllSujets($search = '') {
        $query = "SELECT s.*, sp.designation as specialisation, aa.designation as annee 
                  FROM sujets s
                  JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad";
        
        if (!empty($search)) {
            $query .= " WHERE s.intitule LIKE :search OR sp.designation LIKE :search OR aa.designation LIKE :search";
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

    /**
     * Récupère les sujets disponibles pour les étudiants
     * @param int $idSpecialisation ID de la spécialisation
     * @param string $cycle Cycle d'études
     * @return array Liste des sujets disponibles
     */
    public function getAvailableSujets($idSpecialisation, $cycle) {
        $query = "SELECT s.*, sp.designation as specialisation, aa.designation as annee,
                  d.noms as directeur_nom, e.noms as encadreur_nom
                  FROM sujets s
                  JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN agent d ON s.idDirecteur = d.idAgent
                  LEFT JOIN agent e ON s.idEncadreur = e.idAgent
                  WHERE s.idSpecialisation = :idSpecialisation 
                  AND s.cycle = :cycle
                  AND s.etudiant_idetudiant IS NULL
                  AND s.etatSujet = 'En attente'
                  ORDER BY s.idsujets DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation, PDO::PARAM_INT);
        $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSujet($intitule, $cycle, $idSpecialisation, $anneeAcadId, $idUser, 
                               $etatSujet = 'En attente', $etudiantId = null, $directeurId = null, $encadreurId = null) {
        $query = "INSERT INTO sujets (intitule, cycle, idSpecialisation, annee_acad_idannee_acad, 
                                     idUser, etatSujet, etudiant_idetudiant, idDirecteur, idEncadreur) 
                  VALUES (:intitule, :cycle, :idSpecialisation, :anneeAcadId, 
                         :idUser, :etatSujet, :etudiantId, :directeurId, :encadreurId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intitule', $intitule, PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':etatSujet', $etatSujet, PDO::PARAM_STR);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':directeurId', $directeurId, PDO::PARAM_INT);
        $stmt->bindParam(':encadreurId', $encadreurId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    
    public function updateSujet($idSujet, $intitule, $cycle, $idSpecialisation, $anneeAcadId) {
        $query = "UPDATE sujets SET 
                  intitule = :intitule, 
                  cycle = :cycle, 
                  idSpecialisation = :idSpecialisation, 
                  annee_acad_idannee_acad = :anneeAcadId 
                  WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intitule', $intitule, PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Supprime un sujet
     * @param int $idSujet ID du sujet
     * @return bool Succès de l'opération
     */
    public function deleteSujet($idSujet) {
        $query = "DELETE FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Vérifie si un sujet est déjà attribué à un étudiant
     * @param int $idSujet ID du sujet
     * @return bool True si le sujet est attribué, false sinon
     */
    public function isSujetAttribue($idSujet) {
        $query = "SELECT etudiant_idetudiant FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['etudiant_idetudiant'] !== null);
    }

    /**
     * Récupère les enseignants pour les listes déroulantes
     * @return array Liste des enseignants
     */
    public function getEnseignants() {
        $query = "SELECT idAgent, noms FROM agent WHERE type_agent = 'Enseignant' ORDER BY noms ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un sujet par son ID
     * @param int $idSujet ID du sujet
     * @return array|false Données du sujet ou false si non trouvé
     */
    public function getSujetById($idSujet) {
        $query = "SELECT * FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le sujet assigné à un étudiant
     * @param int $idEtudiant ID de l'étudiant
     * @return array|false Données du sujet avec informations du directeur et encadreur
     */
    public function getSujetByEtudiant($idEtudiant) {
        $query = "SELECT s.*, sp.designation as specialisation, aa.designation as annee,
                  d.noms as directeur_nom,
                  e.noms as encadreur_nom,
                  ur.designation_UR as unite_recherche
                  FROM sujets s
                  LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN agent d ON s.idDirecteur = d.idAgent
                  LEFT JOIN agent e ON s.idEncadreur = e.idAgent
                  LEFT JOIN unite_recherche ur ON sp.idUnite_recherche = ur.idunite_recherche
                  WHERE s.etudiant_idetudiant = :idEtudiant
                  ORDER BY s.idsujets DESC
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Valide un sujet de recherche
     * @param int $idSujet ID du sujet
     * @param string $statut Statut de validation
     * @param string $commentaire Commentaire de la commission
     * @param int $idValidateur ID du validateur
     * @return bool Succès de l'opération
     */
    public function validerSujet($idSujet, $statut, $commentaire, $idValidateur) {
        $query = "UPDATE sujets SET 
                  statut_validation = :statut, 
                  commentaire_commission = :commentaire, 
                  date_validation = NOW(), 
                  idValidateur = :idValidateur 
                  WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':statut', $statut, PDO::PARAM_STR);
        $stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        $stmt->bindParam(':idValidateur', $idValidateur, PDO::PARAM_INT);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function updateSujetComplet($idSujet, $intitule, $cycle, $idSpecialisation, $anneeAcadId, $directeurId = null, $encadreurId = null) {
        $query = "UPDATE sujets SET 
                  intitule = :intitule, 
                  cycle = :cycle, 
                  idSpecialisation = :idSpecialisation, 
                  annee_acad_idannee_acad = :anneeAcadId,
                  idDirecteur = :directeurId,
                  idEncadreur = :encadreurId
                  WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intitule', $intitule, PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
        $stmt->bindParam(':idSpecialisation', $idSpecialisation, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':directeurId', $directeurId, PDO::PARAM_INT);
        $stmt->bindParam(':encadreurId', $encadreurId, PDO::PARAM_INT);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Vérifie si le directeur et l'encadreur sont différents
     * @param int|null $directeurId ID du directeur
     * @param int|null $encadreurId ID de l'encadreur
     * @return bool True si les IDs sont différents ou si l'un des deux est null
     */
    public function validateDifferentDirecteurEncadreur($directeurId, $encadreurId) {
        if ($directeurId !== null && $encadreurId !== null) {
            return $directeurId !== $encadreurId;
        }
        return true;
    }
}
