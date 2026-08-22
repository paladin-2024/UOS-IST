<?php

class Enseignant
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer les informations d'un enseignant par son ID utilisateur
    public function getEnseignantByUserId($userId)
    {
        $query = "SELECT a.*, g.designation as grade 
                  FROM agent a 
                  LEFT JOIN grade g ON a.grade_id = g.idgrade 
                  INNER JOIN t_users u ON a.idAgent = u.idAgent 
                  WHERE u.idUser = :userId AND a.type_agent = 'Enseignant'";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEnseignants($orientationId = null)
    {
        $query = "SELECT a.idAgent as idenseignant, a.noms as nomEnseignant, g.designation as grade 
                  FROM agent a 
                  INNER JOIN grade g ON a.grade_id = g.idgrade
                  LEFT JOIN agent_section as ON a.idAgent = as.idAgent";
                  
        if ($orientationId) {
            $query .= " LEFT JOIN section s ON as.idsection = s.idsection
                        LEFT JOIN orientation o ON o.section_idsection = s.idsection
                        WHERE a.type_agent = 'Enseignant' AND o.idorientation = :orientationId";
        } else {
            $query .= " WHERE a.type_agent = 'Enseignant'";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($orientationId) {
            $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les sujets supervisés par un enseignant (comme directeur ou encadreur)
    public function getSujetsSupervisesParEnseignant($idEnseignant, $search = '', $statutValidation = 'Validé')
    {
        $query = "SELECT s.*, 
                    sp.designation as specialisation, 
                    aa.designation as annee_academique,
                    COALESCE(e.noms, 'Non assigné') as nom_etudiant,
                    COALESCE(e.noms, 'Non assigné') as etudiant,
                    dir.noms as nom_directeur,
                    enc.noms as nom_encadreur
                  FROM sujets s
                  INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                  WHERE (s.idDirecteur = :idEnseignant OR s.idEncadreur = :idEnseignant)
                  AND s.statut_validation = :statutValidation";
        
        if (!empty($search)) {
            $query .= " AND (s.intitule LIKE :search OR e.noms LIKE :search)";
        }
        
        $query .= " ORDER BY s.idsujets DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEnseignant', $idEnseignant, PDO::PARAM_INT);
        $stmt->bindParam(':statutValidation', $statutValidation, PDO::PARAM_STR);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les statistiques des sujets par année académique
    public function getStatistiquesSujetsParAnnee($idEnseignant, $idAnneeAcad)
    {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN s.idDirecteur = :idEnseignant THEN 1 ELSE 0 END) as directeur,
                    SUM(CASE WHEN s.idEncadreur = :idEnseignant THEN 1 ELSE 0 END) as encadreur,
                    SUM(CASE WHEN s.etatSujet = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN s.etatSujet = 'Validé' THEN 1 ELSE 0 END) as valide,
                    SUM(CASE WHEN s.etatSujet = 'Rejeté' THEN 1 ELSE 0 END) as rejete
                  FROM sujets s
                  WHERE (s.idDirecteur = :idEnseignant OR s.idEncadreur = :idEnseignant)
                  AND s.annee_acad_idannee_acad = :idAnneeAcad
                  AND s.statut_validation = 'Validé'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEnseignant', $idEnseignant, PDO::PARAM_INT);
        $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour l'état d'un sujet
    public function updateEtatSujet($idSujet, $etatSujet, $idEnseignant)
    {
        // Vérifier si l'enseignant est le directeur du sujet
        $query = "SELECT idDirecteur FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSujet' => $idSujet]);
        $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sujet['idDirecteur'] != $idEnseignant) {
            return false; // Seul le directeur peut changer l'état
        }
        
        $query = "UPDATE sujets SET etatSujet = :etatSujet WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'etatSujet' => $etatSujet,
            'idSujet' => $idSujet
        ]);
        

    }

    // Mettre à jour un sujet (intitulé et spécialisation)
    public function updateSujet($idSujet, $intitule, $idSpecialisation, $idEncadreur, $idEnseignant)
    {
        // Vérifier si l'enseignant est le directeur du sujet
        $query = "SELECT idDirecteur FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSujet' => $idSujet]);
        $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sujet['idDirecteur'] != $idEnseignant) {
            return false; // Seul le directeur peut modifier le sujet
        }
        
        $query = "UPDATE sujets 
                  SET intitule = :intitule, 
                      idSpecialisation = :idSpecialisation,
                      idEncadreur = :idEncadreur
                  WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'intitule' => $intitule,
            'idSpecialisation' => $idSpecialisation,
            'idEncadreur' => $idEncadreur,
            'idSujet' => $idSujet
        ]);
    }

    /**
 * Récupérer les soutenances programmées pour les sujets supervisés
 */
public function getSoutenancesBySujets($idEnseignant)
{
    $query = "SELECT DISTINCT so.*, s.intitule, s.cycle, sp.designation as specialisation,
                CONCAT(e.noms) as etudiant,
                CASE
                    WHEN s.idDirecteur = :idEnseignant THEN 'Directeur'
                    WHEN s.idEncadreur = :idEnseignant THEN 'Encadreur'
                    ELSE 'Membre du jury'
                END as role
              FROM soutenance so
              INNER JOIN sujets s ON so.sujets_idsujets = s.idsujets
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN jury_soutenance js ON so.idsoutenance = js.idsoutenance AND js.idenseignant = :idEnseignant
              WHERE s.idDirecteur = :idEnseignant
                 OR s.idEncadreur = :idEnseignant
                 OR js.idenseignant = :idEnseignant
              ORDER BY so.date_soutenance DESC";
   
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idEnseignant', $idEnseignant, PDO::PARAM_INT);
    $stmt->execute();
   
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    // Récupérer les enseignants pour le choix d'encadreur
    public function getAllEnseignants()
    {
        $query = "SELECT a.idAgent, a.noms, g.designation as grade
                  FROM agent a
                  LEFT JOIN grade g ON a.grade_id = g.idgrade
                  WHERE a.type_agent = 'Enseignant'
                  ORDER BY a.noms ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un utilisateur est un enseignant
     * @param int $userId ID de l'utilisateur
     * @return bool True si l'utilisateur est un enseignant, false sinon
     */
    public function isUserEnseignant($userId)
    {
        // Récupérer d'abord l'idAgent à partir de l'ID utilisateur
        $query = "SELECT idAgent FROM t_users WHERE idUser = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['idAgent']) {
            return false;
        }
        
        // Vérifier si l'agent est de type Enseignant
        $query = "SELECT type_agent FROM agent WHERE idAgent = :idAgent";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idAgent' => $user['idAgent']]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($agent && $agent['type_agent'] === 'Enseignant');
    }

    /**
     * Récupère l'ID de l'agent (enseignant) à partir de l'ID utilisateur
     * @param int $userId ID de l'utilisateur
     * @return int|null ID de l'agent ou null si non trouvé
     */
    public function getAgentIdByUserId($userId)
    {
        $query = "SELECT a.idAgent 
                FROM agent a 
                INNER JOIN t_users u ON a.idAgent = u.idAgent 
                WHERE u.idUser = :userId AND a.type_agent = 'Enseignant'";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['idAgent'] : null;
    }

        /**
     * Récupère les sujets à valider par la commission
     * @param string $search Terme de recherche optionnel
     * @return array Liste des sujets
     */
    public function getSujetsForCommissionValidation($search = '', $filters = []) {
        $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                         CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                         CONCAT(d.nomEnseignant, ' (', d.grade, ')') as directeur,
                         CONCAT(enc.nomEnseignant, ' (', enc.grade, ')') as encadreur
                  FROM sujets s
                  LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
                  LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN enseignant d ON s.idDirecteur = d.idenseignant
                  LEFT JOIN enseignant enc ON s.idEncadreur = enc.idenseignant
                  WHERE 1=1";
    
        $params = [];
        
        // Appliquer les filtres
        if (!empty($filters['status'])) {
            $query .= " AND s.statut_validation = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['cycle'])) {
            $query .= " AND s.cycle = ?";
            $params[] = $filters['cycle'];
        }
        
        if (!empty($filters['specialisation'])) {
            $query .= " AND s.idSpecialisation = ?";
            $params[] = $filters['specialisation'];
        }
        
        if (!empty($filters['annee'])) {
            $query .= " AND s.annee_acad_idannee_acad = ?";
            $params[] = $filters['annee'];
        }
        
        if (isset($filters['has_student'])) {
            if ($filters['has_student'] == '1') {
                $query .= " AND s.etudiant_idetudiant IS NOT NULL";
            } else {
                $query .= " AND s.etudiant_idetudiant IS NULL";
            }
        }
    
        if (!empty($search)) {
            $query .= " AND (s.intitule LIKE ? 
                       OR spec.designation LIKE ?
                       OR e.noms LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $query .= " ORDER BY s.statut_validation, s.idsujets DESC";
        
        $conn = Connexion::getInstance()->getPDO();
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compte le nombre de sujets par statut de validation
     * @param string $status Statut de validation
     * @return int Nombre de sujets pour ce statut
     */
    public function countSujetsByValidationStatus($status)
    {
        $query = "SELECT COUNT(*) as total FROM sujets WHERE statut_validation = :status";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Met à jour le statut de validation d'un sujet par la commission
     * @param int $idSujet ID du sujet
     * @param string $statutValidation Nouveau statut de validation
     * @param int $idValidateur ID de l'enseignant/agent qui valide
     * @param string $commentaire Commentaire optionnel de la commission
     * @return bool Succès de l'opération
     */
    public function updateSujetValidation($idSujet, $statutValidation, $idValidateur, $commentaire = null)
    {
        try {
            $this->db->beginTransaction();
            
            $query = "UPDATE sujets 
                      SET statut_validation = :statutValidation,
                          commentaire_commission = :commentaire,
                          date_validation = NOW(),
                          idValidateur = :idValidateur
                      WHERE idsujets = :idSujet";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':statutValidation', $statutValidation, PDO::PARAM_STR);
            $stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
            $stmt->bindParam(':idValidateur', $idValidateur, PDO::PARAM_INT);
            $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);

            $result = $stmt->execute();

            $ip = $_SERVER['REMOTE_ADDR'];
            $commentaire="Modifié avec l'adresse IP Suivant : ".$ip;

            // Ajouter après la mise à jour du statut du sujet:
            $now = date('Y-m-d H:i:s');
            $query = "INSERT INTO sujet_validation_history (idsujets, status, commentaire, idUser, date_action)
                    VALUES (:idsujets, :status, :commentaire, :idUser, :date_action)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'idsujets' => $idSujet,
                'status' => $statutValidation,
                'commentaire' => $commentaire,
                'idUser' => $idValidateur,
                'date_action' => $now
            ]);
            
            if ($result) {
                $this->db->commit();
                return true;
            }
            
            $this->db->rollBack();
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour complètement un sujet par la commission (validation + réassignation)
     * @param int $idSujet ID du sujet
     * @param array $data Données du sujet à mettre à jour
     * @param int $idValidateur ID de l'enseignant/agent qui effectue la validation
     * @return bool Succès de l'opération
     */
    public function updateSujetByCommission($idSujet, $data, $idValidateur)
{
    try {
        $this->db->beginTransaction();
        
        // Variables pour suivre les champs optionnels
        $hasEtudiant = isset($data['etudiant']) && !empty($data['etudiant']);
        $hasDirecteur = isset($data['directeur']) && !empty($data['directeur']);
        $hasEncadreur = isset($data['encadreur']) && !empty($data['encadreur']);
        
        // Construction de la requête de mise à jour
        $query = "UPDATE sujets SET
                  intitule = :intitule,
                  cycle = :cycle,
                  idSpecialisation = :idSpecialisation,
                  annee_acad_idannee_acad = :anneeAcad,
                  statut_validation = :statutValidation,
                  commentaire_commission = :commentaire,
                  date_validation = NOW(),
                  idValidateur = :idValidateur";
        
        // Ajouter les champs optionnels s'ils sont présents
        if ($hasEtudiant) {
            $query .= ", etudiant_idetudiant = :etudiantId";
        }
        
        if ($hasDirecteur) {
            $query .= ", idDirecteur = :directeurId";
        }
        
        if ($hasEncadreur) {
            $query .= ", idEncadreur = :encadreurId";
        }
        
        $query .= " WHERE idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        
        // Paramètres obligatoires
        $stmt->bindParam(':intitule', $data['intitule'], PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $data['cycle'], PDO::PARAM_STR);
        $stmt->bindParam(':idSpecialisation', $data['idSpecialisation'], PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcad', $data['annee_acad'], PDO::PARAM_INT);
        $stmt->bindParam(':statutValidation', $data['statut_validation'], PDO::PARAM_STR);
        $stmt->bindParam(':commentaire', $data['commentaire_commission'], PDO::PARAM_STR);
        $stmt->bindParam(':idValidateur', $idValidateur, PDO::PARAM_INT);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        
        // Paramètres optionnels - n'ajouter que si présents dans la requête
        if ($hasEtudiant) {
            $stmt->bindParam(':etudiantId', $data['etudiant'], PDO::PARAM_INT);
        }
        
        if ($hasDirecteur) {
            $stmt->bindParam(':directeurId', $data['directeur'], PDO::PARAM_INT);
        }
        
        if ($hasEncadreur) {
            $stmt->bindParam(':encadreurId', $data['encadreur'], PDO::PARAM_INT);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            $this->db->commit();
            return true;
        }
        
        $this->db->rollBack();
        return false;
    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
}


    /**
 * Vérifie si un sujet a un directeur et un étudiant assignés
 * @param int $idSujet ID du sujet à vérifier
 * @return bool True si le sujet a un directeur et un étudiant, false sinon
 */
public function sujetHasDirectorAndStudent($idSujet)
{
    $query = "SELECT idDirecteur, etudiant_idetudiant 
              FROM sujets 
              WHERE idsujets = :idSujet";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier si le sujet a à la fois un directeur et un étudiant assignés
    if ($result && !empty($result['idDirecteur']) && !empty($result['etudiant_idetudiant'])) {
        return true;
    }
    
    return false;
}

/**
 * Récupère tous les enseignants avec leurs grades pour les listes déroulantes
 * @return array Liste des enseignants
 */
public function getTeachers()
{
    $query = "SELECT 
                a.idAgent as idenseignant, 
                a.noms as nomEnseignant, 
                g.designation as grade
              FROM agent a
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE a.type_agent = 'Enseignant'
              ORDER BY a.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les sujets pour une année académique spécifique
 * @param int $anneeId ID de l'année académique
 * @return array Liste des sujets pour l'année spécifiée
 */
public function getSujetsByAnneeForCommission($anneeId)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                dir.noms as directeur,
                enc.noms as encadreur,
                g_dir.designation as grade_directeur,
                g_enc.designation as grade_encadreur
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.annee_acad_idannee_acad = :anneeId
              ORDER BY sp.designation, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère les statistiques des sujets pour une année académique
 * @param int $anneeId ID de l'année académique
 * @return array Statistiques des sujets (total, validés, en attente, rejetés)
 */
public function getStatistiquesSujetsByAnnee($anneeId)
{
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN s.etatSujet = 'Validé' THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN s.etatSujet = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                SUM(CASE WHEN s.etatSujet = 'Rejeté' THEN 1 ELSE 0 END) as rejetes,
                SUM(CASE WHEN s.statut_validation = 'Validé' THEN 1 ELSE 0 END) as commission_valides,
                SUM(CASE WHEN s.statut_validation = 'En attente' THEN 1 ELSE 0 END) as commission_en_attente,
                SUM(CASE WHEN s.statut_validation = 'Rejeté' THEN 1 ELSE 0 END) as commission_rejetes
              FROM sujets s
              WHERE s.annee_acad_idannee_acad = :anneeId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets groupés par spécialisation pour une année académique
 * @param int $anneeId ID de l'année académique
 * @return array Sujets groupés par spécialisation
 */
public function getSujetsBySpecialisationForCommission($anneeId)
{
    $sujets = $this->getSujetsByAnneeForCommission($anneeId);
    
    $sujetsBySpecialisation = [];
    foreach ($sujets as $sujet) {
        $specialisation = $sujet['specialisation'] ?? 'Non spécifié';
        if (!isset($sujetsBySpecialisation[$specialisation])) {
            $sujetsBySpecialisation[$specialisation] = [];
        }
        $sujetsBySpecialisation[$specialisation][] = $sujet;
    }
    
    return $sujetsBySpecialisation;
}

/**
 * Récupère tous les sujets validés par la commission avec leurs détails
 * @param string $search Terme de recherche optionnel
 * @return array Liste des sujets validés
 */
public function getSujetsValidesParCommission($search = '')
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                p.designation as promotion,
                d.designation as departement
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN departement d ON p.departement_iddepartement = d.iddepartement
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'";
    
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                    OR e.noms LIKE :search 
                    OR e.matricule LIKE :search
                    OR dir.noms LIKE :search 
                    OR enc.noms LIKE :search
                    OR sp.designation LIKE :search
                    OR p.designation LIKE :search)";
    }
    
    $query .= " ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails d'un sujet spécifique avec toutes les informations associées
 * @param int $idSujet ID du sujet
 * @return array|false Détails du sujet ou false si non trouvé
 */
public function getDetailsSujet($idSujet)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                e.idetudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                p.designation as promotion,
                d.designation as departement,
                val.noms as validateur,
                g_val.designation as grade_validateur
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN departement d ON p.departement_iddepartement = d.iddepartement
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN agent val ON s.idValidateur = val.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              LEFT JOIN grade g_val ON val.grade_id = g_val.idgrade
              WHERE s.idsujets = :idSujet";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les tâches associées à un sujet avec leur état d'avancement
 * @param int $idSujet ID du sujet
 * @return array Liste des tâches
 */
public function getTachesBySujet($idSujet)
{
    $query = "SELECT t.*, 
                u.nomUser as createur,
                (SELECT COUNT(*) FROM echanges_taches WHERE taches_idtaches = t.idtaches) as nombre_echanges
              FROM taches t
              LEFT JOIN t_users u ON t.idUser = u.idUser
              WHERE t.sujets_idsujets = :idSujet
              ORDER BY t.dateTache DESC, t.idtaches DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les échanges associés à une tâche
 * @param int $idTache ID de la tâche
 * @return array Liste des échanges
 */
public function getEchangesByTache($idTache)
{
    $query = "SELECT e.*,
                CASE 
                    WHEN e.type_auteur = 'Etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = e.idAuteur)
                    ELSE (SELECT noms FROM agent WHERE idAgent = e.idAuteur)
                END as nom_auteur
              FROM echanges_taches e
              WHERE e.taches_idtaches = :idTache
              ORDER BY e.dateEchange ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idTache', $idTache, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcule la progression globale d'un sujet basée sur les tâches
 * @param int $idSujet ID du sujet
 * @return array Statistiques de progression
 */
public function calculerProgressionSujet($idSujet)
{
    $query = "SELECT 
                COUNT(*) as total_taches,
                SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees,
                SUM(CASE WHEN validation = 'En cours' THEN 1 ELSE 0 END) as taches_en_cours,
                SUM(CASE WHEN validation = 'En attente' THEN 1 ELSE 0 END) as taches_en_attente,
                SUM(CASE WHEN validation = 'Rejeté' THEN 1 ELSE 0 END) as taches_rejetees,
                AVG(pourcentage_avancement) as moyenne_avancement
              FROM taches
              WHERE sujets_idsujets = :idSujet";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer le pourcentage global
    $pourcentageGlobal = 0;
    if ($result['total_taches'] > 0) {
        $pourcentageGlobal = round(($result['taches_validees'] / $result['total_taches']) * 100);
    }
    
    $result['pourcentage_global'] = $pourcentageGlobal;
    
    return $result;
}

/**
 * Récupère les sujets par étudiant avec leur progression
 * @param int $etudiantId ID de l'étudiant
 * @return array Liste des sujets avec leur progression
 */
public function getSujetsAvecProgressionParEtudiant($etudiantId)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.etudiant_idetudiant = :etudiantId
              AND s.statut_validation = 'Validé'
              ORDER BY aa.designation DESC, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter les informations de progression pour chaque sujet
    foreach ($sujets as &$sujet) {
        $progression = $this->calculerProgressionSujet($sujet['idsujets']);
        $sujet['progression'] = $progression;
    }
    
    return $sujets;
}

/**
 * Récupère les statistiques globales des sujets validés par la commission
 * @return array Statistiques globales
 */
public function getStatistiquesGlobalesSujetsValides()
{
    $query = "SELECT 
                COUNT(*) as total_sujets,
                SUM(CASE WHEN s.etatSujet = 'Validé' THEN 1 ELSE 0 END) as sujets_valides,
                SUM(CASE WHEN s.etatSujet = 'En attente' THEN 1 ELSE 0 END) as sujets_en_attente,
                SUM(CASE WHEN s.etatSujet = 'Rejeté' THEN 1 ELSE 0 END) as sujets_rejetes,
                COUNT(DISTINCT s.etudiant_idetudiant) as total_etudiants,
                COUNT(DISTINCT s.idDirecteur) as total_directeurs,
                COUNT(DISTINCT s.idEncadreur) as total_encadreurs
              FROM sujets s
              WHERE s.statut_validation = 'Validé'";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les dernières activités (tâches et échanges) pour un sujet
 * @param int $idSujet ID du sujet
 * @param int $limit Nombre d'activités à récupérer
 * @return array Liste des dernières activités
 */
public function getDernieresActivitesSujet($idSujet, $limit = 10)
{
    $query = "SELECT 'tache' as type, 
                t.idtaches as id,
                t.dateTache as date,
                t.description as contenu,
                t.validation as statut,
                NULL as auteur,
                NULL as type_auteur
              FROM taches t
              WHERE t.sujets_idsujets = :idSujet
              
              UNION ALL
              
              SELECT 'echange' as type,
                e.idechange as id,
                e.dateEchange as date,
                e.commentaire as contenu,
                NULL as statut,
                CASE 
                    WHEN e.type_auteur = 'Etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = e.idAuteur)
                    ELSE (SELECT noms FROM agent WHERE idAgent = e.idAuteur)
                END as auteur,
                e.type_auteur
              FROM echanges_taches e
              JOIN taches t ON e.taches_idtaches = t.idtaches
              WHERE t.sujets_idsujets = :idSujet
              
              ORDER BY date DESC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets validés par année académique
 * @param int $anneeId ID de l'année académique
 * @return array Liste des sujets validés pour cette année
 */
public function getSujetsValidesByAnnee($anneeId)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                p.designation as promotion
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                            LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'
              AND s.annee_acad_idannee_acad = :anneeId
              ORDER BY sp.designation, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets validés par département
 * @param int $departementId ID du département
 * @return array Liste des sujets validés pour ce département
 */
public function getSujetsValidesByDepartement($departementId)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                p.designation as promotion
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN departement d ON p.departement_iddepartement = d.iddepartement
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'
              AND d.iddepartement = :departementId
              ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':departementId', $departementId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets validés par promotion
 * @param int $promotionId ID de la promotion
 * @return array Liste des sujets validés pour cette promotion
 */
public function getSujetsValidesByPromotion($promotionId)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'
              AND p.idpromotion = :promotionId
              ORDER BY aa.designation DESC, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les étudiants avec leurs sujets validés et leur progression
 * @param string $search Terme de recherche optionnel
 * @return array Liste des étudiants avec leurs sujets
 */
public function getEtudiantsAvecSujetsValides($search = '')
{
    $query = "SELECT DISTINCT e.idetudiant, e.noms, e.matricule, 
                p.designationPromotion as promotion,
                d.designationOrientation as departement,
                aa.designation as annee_academique
              FROM etudiant e
              INNER JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
              INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              INNER JOIN orientation d ON p.orientation_idorientation = d.idorientation
              INNER JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
              WHERE s.statut_validation = 'Validé'";
    
    if (!empty($search)) {
        $query .= " AND (e.noms LIKE :search 
                    OR e.matricule LIKE :search 
                    OR p.designationPromotion LIKE :search
                    OR d.designationOrientation LIKE :search)";
    }
    
    $query .= " ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails d'un étudiant avec ses sujets validés
 * @param int $etudiantId ID de l'étudiant
 * @return array Détails de l'étudiant et ses sujets
 */
public function getDetailsEtudiantAvecSujets($etudiantId)
{
    // Récupérer les informations de l'étudiant
    $query = "SELECT e.*, 
                p.designationPromotion as promotion,
                d.designationOrientation as departement,
                aa.designation as annee_academique
              FROM etudiant e
              INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              INNER JOIN orientation d ON p.orientation_idorientation = d.idorientation
              INNER JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
              WHERE e.idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        return null;
    }
    
    // Récupérer les sujets validés de l'étudiant avec leur progression
    $etudiant['sujets'] = $this->getSujetsAvecProgressionParEtudiant($etudiantId);
    
    return $etudiant;
}

/**
 * Récupère les statistiques de progression pour tous les étudiants
 * @return array Statistiques de progression par étudiant
 */
public function getStatistiquesProgressionEtudiants()
{
    $query = "SELECT e.idetudiant, e.noms, e.matricule, 
                p.designationPromotion as promotion,
                COUNT(DISTINCT s.idsujets) as nombre_sujets,
                (SELECT COUNT(*) FROM taches t WHERE t.sujets_idsujets IN 
                    (SELECT idsujets FROM sujets WHERE etudiant_idetudiant = e.idetudiant)
                ) as nombre_taches,
                (SELECT COUNT(*) FROM taches t WHERE t.validation = 'Validé' AND t.sujets_idsujets IN 
                    (SELECT idsujets FROM sujets WHERE etudiant_idetudiant = e.idetudiant)
                ) as taches_validees
              FROM etudiant e
              INNER JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
              INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE s.statut_validation = 'Validé'
              GROUP BY e.idetudiant, e.noms, e.matricule, p.designation
              ORDER BY e.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le pourcentage de progression pour chaque étudiant
    foreach ($resultats as &$resultat) {
        $pourcentage = 0;
        if ($resultat['nombre_taches'] > 0) {
            $pourcentage = round(($resultat['taches_validees'] / $resultat['nombre_taches']) * 100);
        }
        $resultat['pourcentage_progression'] = $pourcentage;
    }
    
    return $resultats;
}

/**
 * Récupère les détails d'une tâche spécifique
 * @param int $idTache ID de la tâche
 * @return array|false Détails de la tâche ou false si non trouvée
 */
public function getDetailsTache($idTache)
{
    $query = "SELECT t.*, 
                s.intitule as sujet_intitule,
                s.idsujets,
                u.username as createur_username
              FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              LEFT JOIN t_users u ON t.idUser = u.idUser
              WHERE t.idtaches = :idTache";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idTache', $idTache, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les tâches récentes pour tous les sujets validés
 * @param int $limit Nombre de tâches à récupérer
 * @return array Liste des tâches récentes
 */
public function getTachesRecentes($limit = 10)
{
    $query = "SELECT t.*, 
                s.intitule as sujet_intitule,
                s.idsujets,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                u.username as createur_username
              FROM taches t
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN t_users u ON t.idUser = u.idUser
              WHERE s.statut_validation = 'Validé'
              ORDER BY t.dateTache DESC, t.idtaches DESC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les échanges récents pour tous les sujets validés
 * @param int $limit Nombre d'échanges à récupérer
 * @return array Liste des échanges récents
 */
public function getEchangesRecents($limit = 10)
{
    $query = "SELECT e.*,
                t.description as tache_description,
                t.idtaches,
                s.intitule as sujet_intitule,
                s.idsujets,
                CASE 
                    WHEN e.type_auteur = 'Etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = e.idAuteur)
                    ELSE (SELECT noms FROM agent WHERE idAgent = e.idAuteur)
                END as nom_auteur
              FROM echanges_taches e
              INNER JOIN taches t ON e.taches_idtaches = t.idtaches
              INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
              WHERE s.statut_validation = 'Validé'
              ORDER BY e.dateEchange DESC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Génère un rapport de progression pour un étudiant spécifique
 * @param int $etudiantId ID de l'étudiant
 * @return array Données du rapport
 */
public function genererRapportProgressionEtudiant($etudiantId)
{
    // Récupérer les détails de l'étudiant
    $etudiant = $this->getDetailsEtudiantAvecSujets($etudiantId);
    
    if (!$etudiant) {
        return null;
    }
    
    // Récupérer toutes les tâches pour tous les sujets de l'étudiant
    $tachesParSujet = [];
    $statistiquesGlobales = [
        'total_taches' => 0,
        'taches_validees' => 0,
        'taches_en_cours' => 0,
        'taches_en_attente' => 0,
        'taches_rejetees' => 0
    ];
    
    foreach ($etudiant['sujets'] as $sujet) {
        $taches = $this->getTachesBySujet($sujet['idsujets']);
        $tachesParSujet[$sujet['idsujets']] = $taches;
        
        // Mettre à jour les statistiques globales
        $statistiquesGlobales['total_taches'] += count($taches);
        foreach ($taches as $tache) {
            switch ($tache['validation']) {
                case 'Validé':
                    $statistiquesGlobales['taches_validees']++;
                    break;
                case 'En cours':
                    $statistiquesGlobales['taches_en_cours']++;
                    break;
                    case 'En attente':
                        $statistiquesGlobales['taches_en_attente']++;
                        break;
                    case 'Rejeté':
                        $statistiquesGlobales['taches_rejetees']++;
                        break;
                }
            }
        }
        
        // Calculer le pourcentage global de progression
        $pourcentageGlobal = 0;
        if ($statistiquesGlobales['total_taches'] > 0) {
            $pourcentageGlobal = round(($statistiquesGlobales['taches_validees'] / $statistiquesGlobales['total_taches']) * 100);
        }
        
        return [
            'etudiant' => $etudiant,
            'taches_par_sujet' => $tachesParSujet,
            'statistiques' => $statistiquesGlobales,
            'pourcentage_global' => $pourcentageGlobal
        ];
    }
    
    /**
     * Récupère les sujets validés par cycle d'études
     * @param string $cycle Cycle d'études (Premier, Deuxieme, Troisieme)
     * @return array Liste des sujets pour ce cycle
     */
    public function getSujetsValidesByCycle($cycle)
    {
        $query = "SELECT s.*, 
                    sp.designation as specialisation, 
                    aa.designation as annee,
                    e.noms as etudiant,
                    e.matricule as matricule_etudiant,
                    dir.noms as directeur,
                    g_dir.designation as grade_directeur,
                    enc.noms as encadreur,
                    g_enc.designation as grade_encadreur,
                    p.designation as promotion
                  FROM sujets s
                  INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                  LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
                  LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
                  WHERE s.statut_validation = 'Validé'
                  AND s.cycle = :cycle
                  ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les sujets validés par enseignant (directeur ou encadreur)
     * @param int $enseignantId ID de l'enseignant
     * @return array Liste des sujets supervisés par cet enseignant
     */
    public function getSujetsValidesByEnseignant($enseignantId)
    {
        $query = "SELECT s.*, 
                    sp.designation as specialisation, 
                    aa.designation as annee,
                    e.noms as etudiant,
                    e.matricule as matricule_etudiant,
                    dir.noms as directeur,
                    g_dir.designation as grade_directeur,
                    enc.noms as encadreur,
                    g_enc.designation as grade_encadreur,
                    p.designation as promotion,
                    CASE 
                        WHEN s.idDirecteur = :enseignantId THEN 'Directeur'
                        WHEN s.idEncadreur = :enseignantId THEN 'Encadreur'
                        ELSE 'Autre'
                    END as role
                  FROM sujets s
                  INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                  LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
                  LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
                  WHERE s.statut_validation = 'Validé'
                  AND (s.idDirecteur = :enseignantId OR s.idEncadreur = :enseignantId)
                  ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':enseignantId', $enseignantId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les statistiques des tâches pour un sujet spécifique
     * @param int $idSujet ID du sujet
     * @return array Statistiques des tâches
     */
    public function getStatistiquesTachesBySujet($idSujet)
    {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as validees,
                    SUM(CASE WHEN validation = 'En cours' THEN 1 ELSE 0 END) as en_cours,
                    SUM(CASE WHEN validation = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN validation = 'Rejeté' THEN 1 ELSE 0 END) as rejetees,
                    AVG(pourcentage_avancement) as moyenne_avancement,
                    MIN(dateTache) as premiere_tache,
                    MAX(dateTache) as derniere_tache,
                    DATEDIFF(MAX(dateTache), MIN(dateTache)) as duree_jours
                  FROM taches
                  WHERE sujets_idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer le pourcentage de progression
        if ($stats['total'] > 0) {
            $stats['pourcentage_progression'] = round(($stats['validees'] / $stats['total']) * 100);
        } else {
            $stats['pourcentage_progression'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Récupère les statistiques des échanges pour un sujet spécifique
     * @param int $idSujet ID du sujet
     * @return array Statistiques des échanges
     */
    public function getStatistiquesEchangesBySujet($idSujet)
    {
        $query = "SELECT 
                    COUNT(*) as total_echanges,
                    SUM(CASE WHEN e.type_auteur = 'Etudiant' THEN 1 ELSE 0 END) as echanges_etudiant,
                    SUM(CASE WHEN e.type_auteur = 'Directeur' THEN 1 ELSE 0 END) as echanges_directeur,
                    SUM(CASE WHEN e.type_auteur = 'Encadreur' THEN 1 ELSE 0 END) as echanges_encadreur,
                    COUNT(DISTINCT e.taches_idtaches) as taches_avec_echanges,
                    MIN(e.dateEchange) as premier_echange,
                    MAX(e.dateEchange) as dernier_echange
                  FROM echanges_taches e
                  JOIN taches t ON e.taches_idtaches = t.idtaches
                  WHERE t.sujets_idsujets = :idSujet";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère le nombre de tâches par mois pour un sujet
     * @param int $idSujet ID du sujet
     * @return array Nombre de tâches par mois
     */
    public function getTachesParMoisBySujet($idSujet)
    {
        $query = "SELECT 
                    DATE_FORMAT(dateTache, '%Y-%m') as mois,
                    COUNT(*) as nombre_taches,
                    SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees
                  FROM taches
                  WHERE sujets_idsujets = :idSujet
                  GROUP BY DATE_FORMAT(dateTache, '%Y-%m')
                  ORDER BY mois ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère le nombre d'échanges par mois pour un sujet
     * @param int $idSujet ID du sujet
     * @return array Nombre d'échanges par mois
     */
    public function getEchangesParMoisBySujet($idSujet)
    {
        $query = "SELECT 
                    DATE_FORMAT(e.dateEchange, '%Y-%m') as mois,
                    COUNT(*) as nombre_echanges,
                    SUM(CASE WHEN e.type_auteur = 'Etudiant' THEN 1 ELSE 0 END) as echanges_etudiant,
                    SUM(CASE WHEN e.type_auteur IN ('Directeur', 'Encadreur') THEN 1 ELSE 0 END) as echanges_enseignants
                  FROM echanges_taches e
                  JOIN taches t ON e.taches_idtaches = t.idtaches
                  WHERE t.sujets_idsujets = :idSujet
                  GROUP BY DATE_FORMAT(e.dateEchange, '%Y-%m')
                  ORDER BY mois ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSujet', $idSujet, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les sujets validés avec leur progression pour le tableau de bord
     * @param int $limit Nombre de sujets à récupérer
     * @return array Liste des sujets avec leur progression
     */
    public function getSujetsValidesAvecProgressionPourDashboard($limit = 10)
    {
        $query = "SELECT s.idsujets, s.intitule, 
                    e.noms as etudiant,
                    e.matricule as matricule_etudiant,
                    dir.noms as directeur,
                    enc.noms as encadreur,
                    p.designation as promotion,
                    aa.designation as annee,
                    (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets) as total_taches,
                    (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets AND validation = 'Validé') as taches_validees
                  FROM sujets s
                  INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                  WHERE s.statut_validation = 'Validé'
                  ORDER BY aa.designation DESC, s.idsujets DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le pourcentage de progression pour chaque sujet
        foreach ($sujets as &$sujet) {
            $pourcentage = 0;
            if ($sujet['total_taches'] > 0) {
                $pourcentage = round(($sujet['taches_validees'] / $sujet['total_taches']) * 100);
            }
            $sujet['pourcentage_progression'] = $pourcentage;
        }
        
        return $sujets;
    }
    
    /**
     * Vérifie si un utilisateur a le droit de voir les détails d'un sujet
     * @param int $userId ID de l'utilisateur
     * @param int $sujetId ID du sujet
     * @return bool True si l'utilisateur a le droit, false sinon
     */
    public function userCanViewSujet($userId, $sujetId)
    {
        // Récupérer l'ID de l'agent associé à cet utilisateur
        $query = "SELECT idAgent FROM t_users WHERE idUser = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        $idAgent = $user['idAgent'];
        
        // Vérifier si l'utilisateur est un administrateur
        $query = "SELECT role FROM t_users WHERE idUser = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userRole = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userRole && $userRole['role'] === 'admin') {
            return true; // Les administrateurs peuvent voir tous les sujets
        }
        
        // Vérifier si l'utilisateur est le directeur ou l'encadreur du sujet
        $query = "SELECT * FROM sujets WHERE idsujets = :sujetId AND (idDirecteur = :idAgent OR idEncadreur = :idAgent)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return true; // L'utilisateur est le directeur ou l'encadreur du sujet
        }
        
        // Vérifier si l'utilisateur est l'étudiant associé au sujet
        $query = "SELECT e.idetudiant 
                  FROM etudiant e 
                  INNER JOIN t_users u ON e.idUser = u.idUser
                  INNER JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
                  WHERE u.idUser = :userId AND s.idsujets = :sujetId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return true; // L'utilisateur est l'étudiant associé au sujet
        }
        
        // Vérifier si l'utilisateur fait partie de la commission
        $query = "SELECT * FROM commission_membres WHERE idAgent = :idAgent";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return true; // L'utilisateur est membre de la commission
        }
        
        return false; // L'utilisateur n'a pas le droit de voir ce sujet
    }
    
    /**
     * Récupère les sujets validés par spécialisation
     * @param int $specialisationId ID de la spécialisation
     * @return array Liste des sujets pour cette spécialisation
     */
    public function getSujetsValidesBySpecialisation($specialisationId)
    {
        $query = "SELECT s.*, 
                    sp.designation as specialisation, 
                    aa.designation as annee,
                    e.noms as etudiant,
                    e.matricule as matricule_etudiant,
                    dir.noms as directeur,
                    g_dir.designation as grade_directeur,
                    enc.noms as encadreur,
                    g_enc.designation as grade_encadreur,
                    p.designation as promotion
                  FROM sujets s
                  INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                  INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                  LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
                  LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
                  WHERE s.statut_validation = 'Validé'
                  AND s.idSpecialisation = :specialisationId
                  ORDER BY aa.designation DESC, s.idsujets DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':specialisationId', $specialisationId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les statistiques globales des tâches pour tous les sujets validés
     * @return array Statistiques globales
     */
    public function getStatistiquesGlobalesTaches()
    {
        $query = "SELECT 
                    COUNT(*) as total_taches,
                    SUM(CASE WHEN t.validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees,
                    SUM(CASE WHEN t.validation = 'En cours' THEN 1 ELSE 0 END) as taches_en_cours,
                    SUM(CASE WHEN t.validation = 'En attente' THEN 1 ELSE 0 END) as taches_en_attente,
                    SUM(CASE WHEN t.validation = 'Rejeté' THEN 1 ELSE 0 END) as taches_rejetees,
                    AVG(t.pourcentage_avancement) as moyenne_avancement,
                    COUNT(DISTINCT t.sujets_idsujets) as nombre_sujets_avec_taches
                  FROM taches t
                  INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE s.statut_validation = 'Validé'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer le pourcentage global de progression
        if ($stats['total_taches'] > 0) {
            $stats['pourcentage_global'] = round(($stats['taches_validees'] / $stats['total_taches']) * 100);
        } else {
            $stats['pourcentage_global'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Récupère les statistiques globales des échanges pour tous les sujets validés
     * @return array Statistiques globales des échanges
     */
    public function getStatistiquesGlobalesEchanges()
    {
        $query = "SELECT 
                    COUNT(*) as total_echanges,
                    SUM(CASE WHEN e.type_auteur = 'Etudiant' THEN 1 ELSE 0 END) as echanges_etudiant,
                    SUM(CASE WHEN e.type_auteur = 'Directeur' THEN 1 ELSE 0 END) as echanges_directeur,
                    SUM(CASE WHEN e.type_auteur = 'Encadreur' THEN 1 ELSE 0 END) as echanges_encadreur,
                    COUNT(DISTINCT e.taches_idtaches) as taches_avec_echanges,
                    COUNT(DISTINCT t.sujets_idsujets) as sujets_avec_echanges
                  FROM echanges_taches e
                  INNER JOIN taches t ON e.taches_idtaches = t.idtaches
                  INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE s.statut_validation = 'Validé'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les tâches et échanges pour un étudiant spécifique
     * @param int $etudiantId ID de l'étudiant
     * @return array Tâches et échanges de l'étudiant
     */
    public function getTachesEtEchangesParEtudiant($etudiantId)
    {
        // Récupérer tous les sujets de l'étudiant
        $query = "SELECT idsujets FROM sujets WHERE etudiant_idetudiant = :etudiantId AND statut_validation = 'Validé'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->execute();
        $sujets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($sujets)) {
            return [
                'taches' => [],
                'echanges' => []
            ];
        }
        
        // Récupérer toutes les tâches pour ces sujets
        $placeholders = implode(',', array_fill(0, count($sujets), '?'));
        $query = "SELECT t.*, 
                    s.intitule as sujet_intitule,
                    s.idsujets
                  FROM taches t
                  INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE t.sujets_idsujets IN ($placeholders)
                  ORDER BY t.dateTache DESC, t.idtaches DESC";
        
        $stmt = $this->db->prepare($query);
        foreach ($sujets as $index => $sujetId) {
            $stmt->bindValue($index + 1, $sujetId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer tous les échanges pour ces tâches
        $echanges = [];
        if (!empty($taches)) {
            $tacheIds = array_column($taches, 'idtaches');
            $placeholders = implode(',', array_fill(0, count($tacheIds), '?'));
            $query = "SELECT e.*,
                        t.description as tache_description,
                        t.idtaches,
                        s.intitule as sujet_intitule,
                        s.idsujets,
                        CASE 
                            WHEN e.type_auteur = 'Etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = e.idAuteur)
                            ELSE (SELECT noms FROM agent WHERE idAgent = e.idAuteur)
                        END as nom_auteur
                      FROM echanges_taches e
                      INNER JOIN taches t ON e.taches_idtaches = t.idtaches
                      INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                      WHERE e.taches_idtaches IN ($placeholders)
                      ORDER BY e.dateEchange DESC";
            
            $stmt = $this->db->prepare($query);
            foreach ($tacheIds as $index => $tacheId) {
                $stmt->bindValue($index + 1, $tacheId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $echanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'taches' => $taches,
            'echanges' => $echanges
        ];
    }
    
    /**
     * Récupère les tâches et échanges pour un enseignant spécifique (directeur ou encadreur)
     * @param int $enseignantId ID de l'enseignant
     * @return array Tâches et échanges liés à l'enseignant
     */
    public function getTachesEtEchangesParEnseignant($enseignantId)
    {
        // Récupérer tous les sujets où l'enseignant est directeur ou encadreur
        $query = "SELECT idsujets FROM sujets 
                  WHERE (idDirecteur = :enseignantId OR idEncadreur = :enseignantId) 
                  AND statut_validation = 'Validé'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':enseignantId', $enseignantId, PDO::PARAM_INT);
        $stmt->execute();
        $sujets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($sujets)) {
            return [
                'taches' => [],
                'echanges' => []
            ];
        }
        
        // Récupérer toutes les tâches pour ces sujets
        $placeholders = implode(',', array_fill(0, count($sujets), '?'));
        $query = "SELECT t.*, 
                    s.intitule as sujet_intitule,
                    s.idsujets,
                    e.noms as etudiant,
                    e.matricule as matricule_etudiant
                  FROM taches t
                  INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                  WHERE t.sujets_idsujets IN ($placeholders)
                  ORDER BY t.dateTache DESC, t.idtaches DESC";
        
        $stmt = $this->db->prepare($query);
        foreach ($sujets as $index => $sujetId) {
            $stmt->bindValue($index + 1, $sujetId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer tous les échanges pour ces tâches
        $echanges = [];
        if (!empty($taches)) {
            $tacheIds = array_column($taches, 'idtaches');
            $placeholders = implode(',', array_fill(0, count($tacheIds), '?'));
            $query = "SELECT e.*,
                        t.description as tache_description,
                        t.idtaches,
                        s.intitule as sujet_intitule,
                        s.idsujets,
                        CASE 
                            WHEN e.type_auteur = 'Etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = e.idAuteur)
                            ELSE (SELECT noms FROM agent WHERE idAgent = e.idAuteur)
                        END as nom_auteur
                      FROM echanges_taches e
                      INNER JOIN taches t ON e.taches_idtaches = t.idtaches
                      INNER JOIN sujets s ON t.sujets_idsujets = s.idsujets
                      WHERE e.taches_idtaches IN ($placeholders)
                      ORDER BY e.dateEchange DESC";
            
            $stmt = $this->db->prepare($query);
            foreach ($tacheIds as $index => $tacheId) {
                $stmt->bindValue($index + 1, $tacheId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $echanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'taches' => $taches,
            'echanges' => $echanges
        ];
    }
    
    /**
 * Récupère les sujets validés avec leur progression pour l'exportation
 * @param int $anneeId ID de l'année académique (optionnel)
 * @return array Liste des sujets avec leur progression pour exportation
 */
public function getSujetsValidesAvecProgressionPourExport($anneeId = null)
{
    $query = "SELECT s.idsujets, s.intitule, s.cycle,
                sp.designation as specialisation,
                aa.designation as annee_academique,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                p.designation as promotion,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets) as total_taches,
                (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets AND validation = 'Validé') as taches_validees,
                (SELECT COUNT(*) FROM echanges_taches et JOIN taches t ON et.taches_idtaches = t.idtaches 
                 WHERE t.sujets_idsujets = s.idsujets) as total_echanges
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'";
    
    if ($anneeId) {
        $query .= " AND s.annee_acad_idannee_acad = :anneeId";
    }
    
    $query .= " ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC";
    
    $stmt = $this->db->prepare($query);
    
    if ($anneeId) {
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le pourcentage de progression pour chaque sujet
    foreach ($sujets as &$sujet) {
        $pourcentage = 0;
        if ($sujet['total_taches'] > 0) {
            $pourcentage = round(($sujet['taches_validees'] / $sujet['total_taches']) * 100);
        }
        $sujet['pourcentage_progression'] = $pourcentage;
    }
    
    return $sujets;
}

/**
 * Récupère les détails complets d'un sujet pour la fiche d'avancement
 * @param int $idSujet ID du sujet
 * @return array Détails complets du sujet
 */
public function getDetailsSujetPourFicheAvancement($idSujet)
{
    // Récupérer les informations de base du sujet
    $sujet = $this->getDetailsSujet($idSujet);
    
    if (!$sujet) {
        return null;
    }
    
    // Récupérer toutes les tâches avec leurs échanges
    $taches = $this->getTachesBySujet($idSujet);
    
    foreach ($taches as &$tache) {
        $tache['echanges'] = $this->getEchangesByTache($tache['idtaches']);
    }
    
    // Récupérer les statistiques de progression
    $progression = $this->calculerProgressionSujet($idSujet);
    
    // Récupérer les statistiques des échanges
    $statistiquesEchanges = $this->getStatistiquesEchangesBySujet($idSujet);
    
    return [
        'sujet' => $sujet,
        'taches' => $taches,
        'progression' => $progression,
        'statistiques_echanges' => $statistiquesEchanges
    ];
}

/**
 * Récupère les sujets validés pour un tableau de bord avec filtres
 * @param array $filtres Tableau de filtres (annee, departement, promotion, cycle, specialisation)
 * @param string $search Terme de recherche
 * @param int $limit Limite de résultats
 * @param int $offset Décalage pour la pagination
 * @return array Liste des sujets filtrés
 */
public function getSujetsValidesAvecFiltres($filtres = [], $search = '', $limit = 50, $offset = 0)
{
    $query = "SELECT s.*, 
                sp.designation as specialisation, 
                aa.designation as annee,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                dir.noms as directeur,
                g_dir.designation as grade_directeur,
                enc.noms as encadreur,
                g_enc.designation as grade_encadreur,
                p.designation as promotion,
                d.designation as departement,
                (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets) as total_taches,
                (SELECT COUNT(*) FROM taches WHERE sujets_idsujets = s.idsujets AND validation = 'Validé') as taches_validees
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN departement d ON p.departement_iddepartement = d.iddepartement
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              LEFT JOIN grade g_dir ON dir.grade_id = g_dir.idgrade
              LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
              WHERE s.statut_validation = 'Validé'";
    
    $params = [];
    
    // Appliquer les filtres
    if (!empty($filtres['annee'])) {
        $query .= " AND s.annee_acad_idannee_acad = :annee";
        $params[':annee'] = $filtres['annee'];
    }
    
    if (!empty($filtres['departement'])) {
        $query .= " AND d.iddepartement = :departement";
        $params[':departement'] = $filtres['departement'];
    }
    
    if (!empty($filtres['promotion'])) {
        $query .= " AND p.idpromotion = :promotion";
        $params[':promotion'] = $filtres['promotion'];
    }
    
    if (!empty($filtres['cycle'])) {
        $query .= " AND s.cycle = :cycle";
        $params[':cycle'] = $filtres['cycle'];
    }
    
    if (!empty($filtres['specialisation'])) {
        $query .= " AND s.idSpecialisation = :specialisation";
        $params[':specialisation'] = $filtres['specialisation'];
    }
    
    // Appliquer la recherche
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                    OR e.noms LIKE :search 
                    OR e.matricule LIKE :search
                    OR dir.noms LIKE :search 
                    OR enc.noms LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Ajouter l'ordre et la pagination
    $query .= " ORDER BY aa.designation DESC, sp.designation, s.idsujets DESC
                LIMIT :limit OFFSET :offset";
    
    $stmt = $this->db->prepare($query);
    
    // Lier les paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le pourcentage de progression pour chaque sujet
    foreach ($sujets as &$sujet) {
        $pourcentage = 0;
        if ($sujet['total_taches'] > 0) {
            $pourcentage = round(($sujet['taches_validees'] / $sujet['total_taches']) * 100);
        }
        $sujet['pourcentage_progression'] = $pourcentage;
    }
    
    return $sujets;
}

/**
 * Compte le nombre total de sujets validés avec filtres (pour pagination)
 * @param array $filtres Tableau de filtres
 * @param string $search Terme de recherche
 * @return int Nombre total de sujets
 */
public function countSujetsValidesAvecFiltres($filtres = [], $search = '')
{
    $query = "SELECT COUNT(*) as total
              FROM sujets s
              INNER JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
              INNER JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN departement d ON p.departement_iddepartement = d.iddepartement
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              WHERE s.statut_validation = 'Validé'";
    
    $params = [];
    
    // Appliquer les filtres
    if (!empty($filtres['annee'])) {
        $query .= " AND s.annee_acad_idannee_acad = :annee";
        $params[':annee'] = $filtres['annee'];
    }
    
    if (!empty($filtres['departement'])) {
        $query .= " AND d.iddepartement = :departement";
        $params[':departement'] = $filtres['departement'];
    }
    
    if (!empty($filtres['promotion'])) {
        $query .= " AND p.idpromotion = :promotion";
        $params[':promotion'] = $filtres['promotion'];
    }
    
    if (!empty($filtres['cycle'])) {
        $query .= " AND s.cycle = :cycle";
        $params[':cycle'] = $filtres['cycle'];
    }
    
    if (!empty($filtres['specialisation'])) {
        $query .= " AND s.idSpecialisation = :specialisation";
        $params[':specialisation'] = $filtres['specialisation'];
    }
    
    // Appliquer la recherche
    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE :search 
                    OR e.noms LIKE :search 
                    OR e.matricule LIKE :search
                    OR dir.noms LIKE :search 
                    OR enc.noms LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $stmt = $this->db->prepare($query);
    
    // Lier les paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

/**
 * Récupère les sujets à valider par la commission, filtré par sections
 */
public function getSujetsForCommissionValidationBySections($search = '', $sections = [], $filters = []) {
    if (empty($sections)) {
        return []; // Aucune section, aucun résultat
    }
    
    $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                     CONCAT(e.noms, ' (', e.matricule, ')') as etudiant,
                     CONCAT(d.noms, ' (', d.grade, ')') as directeur,
                     CONCAT(enc.noms, ' (', enc.grade, ')') as encadreur
              FROM sujets s
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON s.idDirecteur = d.idagent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idagent
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")";

    $params = $sections;
    
    // Appliquer les filtres
    if (!empty($filters['status'])) {
        $query .= " AND s.statut_validation = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['cycle'])) {
        $query .= " AND s.cycle = ?";
        $params[] = $filters['cycle'];
    }
    
    if (!empty($filters['specialisation'])) {
        $query .= " AND s.idSpecialisation = ?";
        $params[] = $filters['specialisation'];
    }
    
    if (!empty($filters['annee'])) {
        $query .= " AND s.annee_acad_idannee_acad = ?";
        $params[] = $filters['annee'];
    }
    
    if (isset($filters['has_student'])) {
        if ($filters['has_student'] == '1') {
            $query .= " AND s.etudiant_idetudiant IS NOT NULL";
        } else {
            $query .= " AND s.etudiant_idetudiant IS NULL";
        }
    }

    if (!empty($search)) {
        $query .= " AND (s.intitule LIKE ? 
                   OR spec.designation LIKE ?
                   OR e.noms LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $query .= " ORDER BY s.statut_validation, s.idsujets DESC";
    
    $conn = Connexion::getInstance()->getPDO();
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Compte les sujets par statut de validation et sections
 */
public function countSujetsByValidationStatusAndSections($status, $sections = []) {
    if (empty($sections)) {
        return 0; // Aucune section, aucun résultat
    }
    
    $placeholders = implode(',', array_fill(0, count($sections), '?'));
    
    $query = "SELECT COUNT(*) as total 
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.statut_validation = ? 
              AND s.etudiant_idetudiant IS NOT NULL
              AND sec.idsection IN ($placeholders)";
    
    $conn = Connexion::getInstance()->getPDO();
    $stmt = $conn->prepare($query);
    
    $params = [$status];
    $params = array_merge($params, $sections);
    
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['total'] : 0;
}


public function getSujetValidationHistory($sujetId) {
    $query = "SELECT 
                h.id,
                h.idsujets,
                h.status, 
                h.date_action, 
                h.commentaire, 
                h.idUser
              FROM sujet_validation_history h
              WHERE h.idsujets = ?
              ORDER BY h.date_action DESC";
    
    $conn = Connexion::getInstance()->getPDO();
    $stmt = $conn->prepare($query);
    $stmt->execute([$sujetId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets par année académique et sections pour la commission
 */
public function getSujetsByAnneeAndSectionsForCommission($anneeId, $sections, $statusFilter = null) {
    if (empty($sections)) {
        return []; // Aucune section autorisée, aucun résultat
    }
    
    $query = "SELECT s.*, spec.designation as specialisation, aa.designation as annee,
                     e.noms as etudiant, e.matricule,
                     d.nomEnseignant as directeur, d.grade as grade_directeur,
                     enc.nomEnseignant as encadreur, enc.grade as grade_encadreur
              FROM sujets s
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN annee_acad aa ON s.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN enseignant d ON s.idDirecteur = d.idenseignant
              LEFT JOIN enseignant enc ON s.idEncadreur = enc.idenseignant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    if ($statusFilter !== null) {
        $query .= " AND s.statut_validation = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " ORDER BY spec.designation ASC, s.intitule ASC";
    
    $conn = Connexion::getInstance()->getPDO();
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les sujets groupés par spécialisation, filtrés par sections et statut
 */
public function getSujetsBySpecialisationAndSectionsForCommission($anneeId, $sections, $statusFilter = null) {
    $sujets = $this->getSujetsByAnneeAndSectionsForCommission($anneeId, $sections, $statusFilter);
    
    // Organiser les sujets par spécialisation
    $sujetsBySpecialisation = [];
    
    foreach ($sujets as $sujet) {
        $specialisation = $sujet['specialisation'] ?? 'Non spécifié';
        
        if (!isset($sujetsBySpecialisation[$specialisation])) {
            $sujetsBySpecialisation[$specialisation] = [];
        }
        
        $sujetsBySpecialisation[$specialisation][] = $sujet;
    }
    
    return $sujetsBySpecialisation;
}

/**
 * Récupère les statistiques des sujets par année et sections
 */
public function getStatistiquesSujetsByAnneeAndSections($anneeId, $sections) {
    if (empty($sections)) {
        return [
            'total' => 0,
            'commission_valides' => 0,
            'commission_en_attente' => 0,
            'commission_rejetes' => 0,
            'commission_modifies' => 0
        ];
    }
    
    $conn = Connexion::getInstance()->getPDO();
    
    // Calculer le total des sujets
    $query = "SELECT COUNT(*) as total
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result ? $result['total'] : 0;
    
    // Calculer le nombre de sujets validés
    $query = "SELECT COUNT(*) as count
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")
              AND s.statut_validation = 'Validé'";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $valides = $result ? $result['count'] : 0;
    
    // Calculer le nombre de sujets en attente
    $query = "SELECT COUNT(*) as count
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")
              AND s.statut_validation = 'En attente'";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $enAttente = $result ? $result['count'] : 0;
    
    // Calculer le nombre de sujets rejetés
    $query = "SELECT COUNT(*) as count
              FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")
              AND s.statut_validation = 'Rejeté'";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $rejetes = $result ? $result['count'] : 0;
    
    // Calculer le nombre de sujets modifiés
    $query = "SELECT COUNT(*) as count
                            FROM sujets s
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?
              AND sec.idsection IN (" . implode(',', array_fill(0, count($sections), '?')) . ")
              AND s.statut_validation = 'Modifié'";
    
    $params = [$anneeId];
    $params = array_merge($params, $sections);
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $modifies = $result ? $result['count'] : 0;
    
    // Retourner les statistiques
    return [
        'total' => $total,
        'commission_valides' => $valides,
        'commission_en_attente' => $enAttente,
        'commission_rejetes' => $rejetes,
        'commission_modifies' => $modifies
    ];
}




public function checkAffectationExists($idAgent, $idEcue, $idAnneeAcad) {
    $query = "SELECT COUNT(*) FROM enseignant_ecue 
              WHERE idAgent = :idAgent 
              AND idECUE = :idEcue 
              AND anneeAcad = :anneeAcad";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcad', $idAnneeAcad, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetchColumn() > 0;
}


public function updateAffectation($idAgent, $idEcue, $poste, $idAnneeAcad) {
    $query = "UPDATE enseignant_ecue 
              SET poste = :poste 
              WHERE idAgent = :idAgent 
              AND idECUE = :idEcue 
              AND anneeAcad = :anneeAcad";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':poste', $poste, PDO::PARAM_STR);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcad', $idAnneeAcad, PDO::PARAM_STR);
    
    return $stmt->execute();
}


public function affecterEnseignant($idAgent, $idEcue, $poste, $idAnneeAcad) {
    $query = "INSERT INTO enseignant_ecue (poste, idAgent, idECUE, anneeAcad) 
              VALUES (:poste, :idAgent, :idEcue, :anneeAcad)";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':poste', $poste, PDO::PARAM_STR);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcad', $idAnneeAcad, PDO::PARAM_STR);
    
    return $stmt->execute();
}


public function getEnseignantsAffectesByCours($idEcue, $idAnneeAcad) {
    $query = "SELECT ee.*, a.noms, a.photo, g.designation as gradeDesignation
              FROM enseignant_ecue ee
              JOIN agent a ON ee.idAgent = a.idAgent
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE ee.idECUE = :idEcue 
              AND ee.anneeAcad = :anneeAcad
              ORDER BY ee.poste ASC, a.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idEcue', $idEcue, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcad', $idAnneeAcad, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function supprimerAffectation($idAffectation) {
    $query = "DELETE FROM enseignant_ecue WHERE idenseignant_ecue = :idAffectation";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAffectation', $idAffectation, PDO::PARAM_INT);
    
    return $stmt->execute();
}


public function getCoursAffectesEnseignant($idEnseignant, $idAnneeAcad) {
    try {
        $query = "SELECT e.idECUE, e.designationECUE, e.CMI, e.TD, e.TP, 
                         u.idUE, u.designationUE, u.codeUE, s.numeroSemestre,
                         p.designationPromotion, ee.poste
                  FROM ecue e
                  INNER JOIN enseignant_ecue ee ON e.idECUE = ee.idECUE
                  INNER JOIN ue u ON e.UE_idUE = u.idUE
                  INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  WHERE ee.idAgent = ?
                    AND ee.anneeAcad = ?
                    AND e.estVisible = 1
                  ORDER BY s.numeroSemestre, u.designationUE, e.designationECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idEnseignant, $idAnneeAcad]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des cours affectés: " . $e->getMessage());
        return [];
    }
}


public function isEnseignantAssignedToEcue($idEnseignant, $idEcue, $idAnneeAcad) {
    try {
        $query = "SELECT COUNT(*) FROM enseignant_ecue 
                 WHERE idAgent = ? AND idECUE = ? AND anneeAcad = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idEnseignant, $idEcue, $idAnneeAcad]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'affectation de l'enseignant: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un enseignant est titulaire d'un ECUE
 *
 * @param int $idEnseignant ID de l'enseignant
 * @param int $idEcue ID de l'ECUE
 * @param int $anneeAcadId ID de l'année académique
 * @return bool True si l'enseignant est titulaire, false sinon
 */
public function isEnseignantTitulaire($idEnseignant, $idEcue, $anneeAcadId) {
    try {
        $query = "SELECT COUNT(*) FROM enseignant_ecue 
                  WHERE idAgent = ? AND idECUE = ? AND anneeAcad = ? AND poste = 'Titulaire'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idEnseignant, $idEcue, $anneeAcadId]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du statut de titulaire: " . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute un enseignant à un ECUE
 *
 * @param int $idEnseignant ID de l'enseignant
 * @param int $idEcue ID de l'ECUE
 * @param string $poste Poste de l'enseignant (Assistant, Suppléant, etc.)
 * @param int $anneeAcadId ID de l'année académique
 * @return bool True si l'ajout a réussi, false sinon
 */
public function addEnseignantToEcue($idEnseignant, $idEcue, $poste, $anneeAcadId) {
    try {
        $query = "INSERT INTO enseignant_ecue (idAgent, idECUE, poste, anneeAcad) 
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([$idEnseignant, $idEcue, $poste, $anneeAcadId]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de l'enseignant à l'ECUE: " . $e->getMessage());
        return false;
    }
}

/**
 * Retire un enseignant d'un ECUE
 *
 * @param int $idEnseignant ID de l'enseignant
 * @param int $idEcue ID de l'ECUE
 * @param int $anneeAcadId ID de l'année académique
 * @return bool True si la suppression a réussi, false sinon
 */
public function removeEnseignantFromEcue($idEnseignant, $idEcue, $anneeAcadId) {
    try {
        $query = "DELETE FROM enseignant_ecue 
                  WHERE idAgent = ? AND idECUE = ? AND anneeAcad = ?";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([$idEnseignant, $idEcue, $anneeAcadId]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors du retrait de l'enseignant de l'ECUE: " . $e->getMessage());
        return false;
    }
}
















    




}
