<?php
class Etudiant {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    public function login($matricule, $password) {
        $query = "SELECT e.*, p.cycle, o.idorientation 
                  FROM etudiant e
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  WHERE e.matricule = :matricule AND e.est_actif=1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($etudiant && password_verify($password, $etudiant['pwd'])) {
            return $etudiant;
        }
        return false;
    }

    public function getStudents2($search = '')
    {
        $query = "SELECT e.idetudiant, e.matricule, e.noms, e.adressemail, e.telephone, 
                        p.\"designationPromotion\" as promotion, 
                        o.\"designationOrientation\" as orientation,
                        aa.designation as annee_academique
                FROM etudiant e
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad";
        
        if (!empty($search)) {
            $query .= " WHERE e.noms LIKE :search OR e.matricule LIKE :search";
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
     * Récupère les sujets disponibles pour l'orientation et le cycle de l'étudiant
     * Amélioré pour inclure la recherche par spécialisation
     */
    public function getSujetsDisponibles($orientationId, $cycle)
{
    // Modifier la requête pour utiliser directement idorientation au lieu de idsection
    $query = "SELECT s.*, sp.designation as specialisation, ur.\"designation_UR\" as unite_recherche
            FROM sujets s
            INNER JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
            INNER JOIN unite_recherche ur ON sp.\"idUnite_recherche\" = ur.idunite_recherche
            WHERE s.etudiant_idetudiant IS NULL
            AND s.cycle = :cycle";
            
    if ($orientationId) {
        // Utiliser directement idorientation au lieu de rechercher des sections liées
        $query .= " AND sp.idorientation = :orientationId";
    }
            
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':cycle', $cycle, PDO::PARAM_STR);
            
    if ($orientationId) {
        $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
    }
            
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    /**
     * Récupère les tâches associées à un sujet
     */
    public function getTaches($sujetId) {
        $query = "SELECT t.*, 
                         (SELECT COUNT(*) FROM echanges_taches WHERE taches_idtaches = t.idtaches) as nb_echanges
                  FROM taches t
                  WHERE t.sujets_idsujets = :sujetId
                  ORDER BY t.\"dateTache\" DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sujetId', $sujetId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les échanges pour une tâche spécifique
     */
    public function getEchangesTache($tacheId) {
        $query = "SELECT e.*,
                         CASE e.type_auteur
                             WHEN 'Directeur' THEN d.noms
                             WHEN 'Encadreur' THEN enc.noms
                             WHEN 'Etudiant' THEN et.noms
                         END as nom_auteur
                  FROM echanges_taches e
                  LEFT JOIN agent d ON e.type_auteur = 'Directeur' AND e.\"idAuteur\" = d.\"idAgent\"
                  LEFT JOIN agent enc ON e.type_auteur = 'Encadreur' AND e.\"idAuteur\" = enc.\"idAgent\"
                  LEFT JOIN etudiant et ON e.type_auteur = 'Etudiant' AND e.\"idAuteur\" = et.idetudiant
                  WHERE e.taches_idtaches = :tacheId
                  ORDER BY e.\"dateEchange\" ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tacheId', $tacheId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ajoute un échange à une tâche
     */
    public function ajouterEchange($tacheId, $commentaire, $fichier, $typeAuteur, $idAuteur) {
        $query = "INSERT INTO echanges_taches (\"dateEchange\", commentaire, \"fichierJoint\", 
                                             taches_idtaches, type_auteur, \"idAuteur\")
                 VALUES (NOW(), :commentaire, :fichier, :tacheId, :typeAuteur, :idAuteur)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':fichier', $fichier);
        $stmt->bindParam(':tacheId', $tacheId);
        $stmt->bindParam(':typeAuteur', $typeAuteur);
        $stmt->bindParam(':idAuteur', $idAuteur);
        return $stmt->execute();
    }
    
    /**
     * Met à jour le statut de validation d'une tâche
     */
    public function updateTacheValidation($tacheId, $validation) {
        $query = "UPDATE taches SET validation = :validation, 
                                    date_validation = NOW() 
                  WHERE idtaches = :tacheId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':validation', $validation);
        $stmt->bindParam(':tacheId', $tacheId);
        return $stmt->execute();
    }

    /**
     * Crée une nouvelle tâche pour un sujet
     */
    public function creerTache($sujetId, $description, $fichier, $userId) {
        $query = "INSERT INTO taches (\"dateTache\", description, \"fichierTache\", 
                                    validation, pourcentage_avancement, sujets_idsujets, \"idUser\")
                 VALUES (NOW(), :description, :fichier, 'En attente', 0, 
                         :sujetId, :userId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':fichier', $fichier);
        $stmt->bindParam(':sujetId', $sujetId);
        $stmt->bindParam(':userId', $userId);
        return $stmt->execute();
    }

    /**
     * Récupère les enseignants disponibles pour une orientation
     * Amélioré pour filtrer par grade si nécessaire
     */
    public function getEnseignants($orientationId, $grade = null) {
        $query = "SELECT e.idenseignant, a.noms as \"nomEnseignant\", g.designation as grade
                  FROM agent a
                  JOIN grade g ON a.grade_id = g.idgrade
                  JOIN structure s ON a.\"idStructure\" = s.\"idStructure\"
                  JOIN orientation o ON s.idOrientation = o.idorientation
                  LEFT JOIN enseignant e ON a.\"idAgent\" = e.\"idAgent\"
                  WHERE o.idorientation = :orientationId
                  AND a.type_agent = 'Enseignant'";
        
        if ($grade) {
            $query .= " AND g.designation = :grade";
        }
        $query .= " ORDER BY a.noms ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':orientationId', $orientationId);
        if ($grade) {
            $stmt->bindParam(':grade', $grade);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Permet à un étudiant de choisir un sujet existant
     */
    public function choisirSujet($sujetId, $etudiantId, $directeurId, $encadreurId = null) {
        try {
            $this->db->beginTransaction();
            
            // Vérifier si le sujet est toujours disponible
            $query = "SELECT * FROM sujets WHERE idsujets = :sujetId AND etudiant_idetudiant IS NULL";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sujetId', $sujetId);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new Exception("Ce sujet n'est plus disponible.");
            }
            
            // Mettre à jour le sujet
            $query = "UPDATE sujets 
                     SET etudiant_idetudiant = :etudiantId,
                         \"idDirecteur\" = :directeurId,
                         \"idEncadreur\" = :encadreurId,
                         \"etatSujet\" = 'En attente',
                         statut_validation = 'En attente'
                     WHERE idsujets = :sujetId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':etudiantId', $etudiantId);
            $stmt->bindParam(':directeurId', $directeurId);
            $stmt->bindParam(':encadreurId', $encadreurId);
            $stmt->bindParam(':sujetId', $sujetId);
            
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
     * Récupère le sujet assigné à un étudiant avec les détails complets
     */
    public function getSujetAssigne($etudiantId) {
        $query = "SELECT s.*, 
                         spec.designation as specialisation,
                         ur.\"designation_UR\" as unite_recherche,
                         d.noms as directeur,
                         e.noms as encadreur
                  FROM sujets s
                  JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                  JOIN unite_recherche ur ON spec.\"idUnite_recherche\" = ur.idunite_recherche
                  LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
                  LEFT JOIN agent e ON s.\"idEncadreur\" = e.\"idAgent\"
                  WHERE s.etudiant_idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails d'une tâche spécifique
     */
    public function getTacheDetails($tacheId) {
        $query = "SELECT t.*, s.\"idDirecteur\", s.\"idEncadreur\", 
                         t.pourcentage_avancement, t.date_validation, t.commentaire_validation
                  FROM taches t
                  JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE t.idtaches = :tacheId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tacheId', $tacheId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les informations complètes d'un étudiant
     */
    public function getEtudiantById($etudiantId) {
        $query = "SELECT e.*, p.\"designationPromotion\" as promotion, p.cycle,
                     o.\"designationOrientation\" as departement, aa.designation as annee_academique
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
              WHERE e.idetudiant = :etudiantId";
    
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les compteurs de notifications pour l'étudiant
     */
    public function getNotificationCounters($etudiantId) {
        try {
            // Récupérer le sujet de l'étudiant
            $sujet = $this->getSujetAssigne($etudiantId);
            if (!$sujet) {
                return [
                    'taches_total' => 0,
                    'taches_encours' => 0,
                    'taches_validees' => 0,
                    'taches_attente' => 0,
                    'nouveaux_echanges' => 0
                ];
            }

            // Compteur des tâches
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN validation = 'En cours' THEN 1 ELSE 0 END) as encours,
                        SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as validees,
                        SUM(CASE WHEN validation = 'En attente' THEN 1 ELSE 0 END) as attente
                     FROM taches 
                     WHERE sujets_idsujets = :sujetId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sujetId', $sujet['idsujets']);
            $stmt->execute();
            $tachesCount = $stmt->fetch(PDO::FETCH_ASSOC);

            // Compteur des nouveaux échanges
            $query = "SELECT COUNT(*) as nouveaux
                     FROM echanges_taches e
                     JOIN taches t ON e.taches_idtaches = t.idtaches
                     WHERE t.sujets_idsujets = :sujetId
                     AND e.type_auteur IN ('Directeur', 'Encadreur')
                     AND e.\"dateEchange\" >= COALESCE(
                                                  (SELECT MAX(\"dateEchange\") 
                          FROM echanges_taches e2 
                          JOIN taches t2 ON e2.taches_idtaches = t2.idtaches
                          WHERE t2.sujets_idsujets = :sujetId 
                          AND e2.type_auteur = 'Etudiant'), 
                         '1900-01-01'
                     )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sujetId', $sujet['idsujets']);
            $stmt->execute();
            $echangesCount = $stmt->fetchColumn();

            return [
                'taches_total' => $tachesCount['total'] ?? 0,
                'taches_encours' => $tachesCount['encours'] ?? 0,
                'taches_validees' => $tachesCount['validees'] ?? 0,
                'taches_attente' => $tachesCount['attente'] ?? 0,
                'nouveaux_echanges' => $echangesCount ?? 0
            ];
        } catch (Exception $e) {
            error_log("Erreur lors du comptage des notifications: " . $e->getMessage());
            return [
                'taches_total' => 0,
                'taches_encours' => 0,
                'taches_validees' => 0,
                'taches_attente' => 0,
                'nouveaux_echanges' => 0
            ];
        }
    }

    /**
     * Met à jour la promotion d'un étudiant
     */
    public function updatePromotion($etudiantId, $promotionId) {
        $query = "UPDATE etudiant 
                  SET promotion_idpromotion = :promotionId
                  WHERE idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Récupère toutes les promotions disponibles pour une orientation
     */
    public function getPromotionsByOrientation($orientationId) {
        $query = "SELECT p.idpromotion, p.\"designationPromotion\", p.cycle, aa.designation as annee_academique
                  FROM promotion p
                  JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                  WHERE p.orientation_idorientation = :orientationId
                  ORDER BY aa.designation DESC, p.\"designationPromotion\" ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'orientation associée à une promotion
     */
    public function getOrientationByPromotion($promotionId) {
        $query = "SELECT o.idorientation 
                  FROM orientation o 
                  JOIN promotion p ON o.idorientation = p.orientation_idorientation 
                  WHERE p.idpromotion = :promotionId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['idorientation'] : null;
    }

    /**
     * Met à jour les informations de l'étudiant
     */
    public function updateEtudiant($etudiantId, $data) {
        $query = "UPDATE etudiant 
                  SET telephone = :telephone,
                      adressemail = :email
                  WHERE idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':telephone', $data['telephone']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':etudiantId', $etudiantId);
        return $stmt->execute();
    }

    /**
     * Vérifie l'ancien mot de passe
     */
    public function verifyPassword($etudiantId, $currentPassword) {
        $query = "SELECT pwd FROM etudiant WHERE idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && password_verify($currentPassword, $result['pwd'])) {
            return true;
        }
        return false;
    }

    /**
     * Change le mot de passe de l'étudiant
     */
    public function changePassword($etudiantId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE etudiant 
                  SET pwd = :password
                  WHERE idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Vérifie si un email existe déjà (sauf pour l'étudiant actuel)
     */
    public function isEmailTaken($email, $currentEtudiantId) {
        $query = "SELECT COUNT(*) FROM etudiant 
                  WHERE adressemail = :email 
                  AND idetudiant != :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':etudiantId', $currentEtudiantId);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si un numéro de téléphone existe déjà (sauf pour l'étudiant actuel)
     */
    public function isPhoneTaken($phone, $currentEtudiantId) {
        $query = "SELECT COUNT(*) FROM etudiant 
                  WHERE telephone = :phone 
                  AND idetudiant != :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':etudiantId', $currentEtudiantId);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour la photo de profil de l'étudiant
     */
    public function updateProfilePhoto($etudiantId, $photoPath) {
        $query = "UPDATE etudiant 
                  SET photo = :photo
                  WHERE idetudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':photo', $photoPath);
        $stmt->bindParam(':etudiantId', $etudiantId);
        return $stmt->execute();
    }

    /**
     * Propose un nouveau sujet
     */
    public function proposerSujet($data) {
        $query = "INSERT INTO sujets (intitule, resume, \"etatSujet\", \"idDirecteur\", \"idEncadreur\", 
                                     etudiant_idetudiant, annee_acad_idannee_acad, cycle, 
                                     \"idSpecialisation\", \"idUser\", statut_validation)
                 VALUES (:intitule, :resume, 'En attente', :directeurId, :encadreurId, 
                         :etudiantId, :anneeAcadId, :cycle, :specialisationId, 
                         :userId, 'En attente')";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intitule', $data['intitule']);
        $stmt->bindParam(':resume', $data['resume']);
        $stmt->bindParam(':directeurId', $data['directeur_id']);
        $stmt->bindParam(':encadreurId', $data['encadreur_id']);
        $stmt->bindParam(':etudiantId', $data['etudiant_id']);
        $stmt->bindParam(':anneeAcadId', $data['annee_acad']);
        $stmt->bindParam(':cycle', $data['cycle']);
        $stmt->bindParam(':specialisationId', $data['idSpecialisation']);
        $stmt->bindParam(':userId', $data['idUser']);
        
        return $stmt->execute();
    }

    /**
     * Récupère les statistiques d'avancement pour un sujet
     */
    public function getAvancementStats($sujetId) {
        $query = "SELECT 
                    COUNT(*) as total_taches,
                    SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees,
                    AVG(pourcentage_avancement) as pourcentage_moyen
                  FROM taches
                  WHERE sujets_idsujets = :sujetId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sujetId', $sujetId);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer le pourcentage global d'avancement
        if ($stats['total_taches'] > 0) {
            $stats['pourcentage_global'] = ($stats['taches_validees'] / $stats['total_taches']) * 100;
        } else {
            $stats['pourcentage_global'] = 0;
        }
        
        return $stats;
    }

    /**
     * Récupère l'historique des tâches pour un sujet
     */
    public function getHistoriqueTaches($sujetId) {
        $query = "SELECT t.*, 
                         (SELECT COUNT(*) FROM echanges_taches WHERE taches_idtaches = t.idtaches) as nb_echanges,
                         (SELECT MAX(\"dateEchange\") FROM echanges_taches WHERE taches_idtaches = t.idtaches) as dernier_echange
                  FROM taches t
                  WHERE t.sujets_idsujets = :sujetId
                  ORDER BY t.\"dateTache\" DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sujetId', $sujetId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les spécialisations disponibles pour une orientation donnée
     */
    public function getSpecialisationsByOrientation($orientationId) {
        $query = "SELECT s.\"idSpecialisation\", s.designation, ur.\"designation_UR\" as unite_recherche
                FROM specialisation s
                JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
                JOIN section sec ON urs.idsection = sec.idsection
                JOIN orientation o ON sec.idsection = o.section_idsection
                WHERE o.idorientation = :orientationId
                ORDER BY s.designation ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    /**
     * Récupère les devoirs pour un cours spécifique
     */
    public function getDevoirs($coursId) {
        $query = "SELECT d.*, 
                         (SELECT COUNT(*) FROM soumissions_devoir WHERE idDevoir = d.idDevoir) as nb_soumissions
                  FROM devoirs d
                  WHERE d.idCours = :coursId
                  ORDER BY d.\"dateCreation\" DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':coursId', $coursId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si l'étudiant a déjà soumis un devoir
     */
    public function hasSubmittedDevoir($devoirId, $etudiantId) {
        $query = "SELECT COUNT(*) FROM soumissions_devoir
                  WHERE idDevoir = :devoirId AND idEtudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Soumet un devoir
     */
    public function submitDevoir($devoirId, $etudiantId, $fichier, $commentaire) {
        $query = "INSERT INTO soumissions_devoir (idDevoir, idEtudiant, fichier, commentaire, dateSoumission)
                  VALUES (:devoirId, :etudiantId, :fichier, :commentaire, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':fichier', $fichier);
        $stmt->bindParam(':commentaire', $commentaire);
        return $stmt->execute();
    }

    /**
     * Récupère les notes de l'étudiant
     */
    public function getEtudiantNotes($etudiantId, $promotionId) {
        $query = "SELECT n.*, c.intitule as cours_nom, c.code as cours_code
                                    FROM notes n
                  JOIN cours c ON n.idCours = c.idCours
                  WHERE n.idEtudiant = :etudiantId
                  AND c.idPromotion = :promotionId
                  ORDER BY c.intitule ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCoursEtudiant($etudiantId) {
        $query = "SELECT c.idcours, c.titre, c.description, c.\"dateCreation\",
                         e.\"designationECUE\", e.CMI, e.TD, e.TP,
                         u.\"codeUE\", u.\"designationUE\",
                         ens.\"nomEnseignant\", ens.grade,
                         aa.designation as annee_academique,
                         (SELECT COUNT(*) FROM support_cours WHERE idcours = c.idcours) as nb_supports,
                         (SELECT COUNT(*) FROM devoirs WHERE idcours = c.idcours) as nb_devoirs
                  FROM cours c
                  JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN enseignant ens ON c.idenseignant = ens.idenseignant
                  JOIN annee_acad aa ON c.annee_acad_idannee_acad = aa.idannee_acad
                  JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
                  WHERE et.idetudiant = :etudiantId
                  ORDER BY u.\"codeUE\", e.designationECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails d'un cours spécifique
     */
    public function getCoursDetails($coursId) {
        $query = "SELECT c.*, e.noms as enseignant_nom, 
                         (SELECT COUNT(*) FROM documents_cours WHERE idCours = c.idCours) as nb_documents,
                         (SELECT COUNT(*) FROM devoirs WHERE idCours = c.idCours) as nb_devoirs
                  FROM cours c
                  JOIN agent e ON c.idEnseignant = e.\"idAgent\"
                  WHERE c.idCours = :coursId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':coursId', $coursId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'un cours
     */
    public function getCoursDocuments($coursId) {
        $query = "SELECT * FROM documents_cours
                  WHERE idCours = :coursId
                  ORDER BY dateUpload DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':coursId', $coursId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails d'un devoir
     */
    public function getDevoirDetails($devoirId) {
        $query = "SELECT d.*, c.intitule as cours_nom
                  FROM devoirs d
                  JOIN cours c ON d.idCours = c.idCours
                  WHERE d.idDevoir = :devoirId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la soumission d'un devoir par un étudiant
     */
    public function getEtudiantSoumission($devoirId, $etudiantId) {
        $query = "SELECT s.*, d.titre as devoir_titre, d.dateEcheance
                  FROM soumissions_devoir s
                  JOIN devoirs d ON s.idDevoir = d.idDevoir
                  WHERE s.idDevoir = :devoirId AND s.idEtudiant = :etudiantId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Génère une fiche d'avancement pour un étudiant
     */
    public function generateFicheAvancement($etudiantId) {
        // Récupérer les informations de l'étudiant
        $etudiant = $this->getEtudiantById($etudiantId);
        if (!$etudiant) {
            return false;
        }

        // Récupérer le sujet assigné
        $sujet = $this->getSujetAssigne($etudiantId);
        if (!$sujet || $sujet['etatSujet'] !== 'Validé') {
            return false;
        }

        // Récupérer les statistiques d'avancement
        $stats = $this->getAvancementStats($sujet['idsujets']);
        
        // Récupérer l'historique des tâches
        $taches = $this->getHistoriqueTaches($sujet['idsujets']);
        
        // Construire les données de la fiche
        return [
            'etudiant' => $etudiant,
            'sujet' => $sujet,
            'stats' => $stats,
            'taches' => $taches
        ];
    }

    /**
     * Recherche des sujets par mot-clé
     */
    public function searchSujets($orientationId, $cycle, $keyword) {
        $keyword = '%' . $keyword . '%';
        
        $query = "SELECT s.*, spec.designation as specialisation, 
                         ur.\"designation_UR\" as unite_recherche
                  FROM sujets s
                  JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
                  JOIN unite_recherche ur ON spec.\"idUnite_recherche\" = ur.idunite_recherche
                  JOIN unite_recherche_section urs ON ur.idunite_recherche = urs.idunite_recherche
                  JOIN section sec ON urs.idsection = sec.idsection
                  JOIN orientation o ON sec.idsection = o.section_idsection
                  WHERE o.idorientation = :orientationId
                  AND s.cycle = :cycle
                  AND (s.etudiant_idetudiant IS NULL OR s.\"etatSujet\"='Rejeté')
                  AND (s.intitule LIKE :keyword 
                       OR spec.designation LIKE :keyword 
                       OR ur.\"designation_UR\" LIKE :keyword)
                  ORDER BY s.intitule ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':orientationId', $orientationId);
        $stmt->bindParam(':cycle', $cycle);
        $stmt->bindParam(':keyword', $keyword);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un étudiant a déjà un sujet validé
     */
    public function hasValidatedSujet($etudiantId) {
        $query = "SELECT COUNT(*) FROM sujets
                  WHERE etudiant_idetudiant = :etudiantId
                  AND \"etatSujet\" = 'Validé'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour le pourcentage d'avancement d'une tâche
     */
    public function updateTacheAvancement($tacheId, $pourcentage) {
        $query = "UPDATE taches 
                  SET pourcentage_avancement = :pourcentage
                  WHERE idtaches = :tacheId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':pourcentage', $pourcentage, PDO::PARAM_INT);
        $stmt->bindParam(':tacheId', $tacheId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Récupère les commentaires de la commission sur un sujet
     */
    public function getSujetCommentaires($sujetId) {
        $query = "SELECT s.commentaire_commission, s.date_validation, 
                         a.noms as validateur_nom
                  FROM sujets s
                  LEFT JOIN agent a ON s.\"idValidateur\" = a.\"idAgent\"
                  WHERE s.idsujets = :sujetId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sujetId', $sujetId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les enseignants spécialisés dans un domaine spécifique
     */
    public function getEnseignantsBySpecialisation($specialisationId) {
        $query = "SELECT a.*, g.designation as grade
                  FROM agent a
                  JOIN enseignant_specialisation es ON a.\"idAgent\" = es.\"idAgent\"
                  JOIN grade g ON a.grade_id = g.idgrade
                  WHERE es.\"idSpecialisation\" = :specialisationId
                  AND a.type_agent = 'Enseignant'
                  ORDER BY a.noms ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':specialisationId', $specialisationId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les dernières activités d'un étudiant (tâches et échanges)
     */
    public function getEtudiantActivites($etudiantId, $limit = 10) {
        $query = "SELECT 'tache' as type, t.\"dateTache\" as date, t.description as titre, 
                         t.validation as statut
                  FROM taches t
                  JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE s.etudiant_idetudiant = :etudiantId
                  
                  UNION
                  
                  SELECT 'echange' as type, e.\"dateEchange\" as date, 
                         SUBSTRING(e.commentaire, 1, 50) as titre,
                         t.validation as statut
                  FROM echanges_taches e
                  JOIN taches t ON e.taches_idtaches = t.idtaches
                  JOIN sujets s ON t.sujets_idsujets = s.idsujets
                  WHERE s.etudiant_idetudiant = :etudiantId
                  
                  ORDER BY date DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


/**
 * Récupère les supports de cours disponibles pour un cours spécifique
 * avec vérification des droits d'accès (supports payants)
 */
public function getSupportsCours($coursId, $etudiantId) {
    $query = "SELECT s.*, 
                     CASE 
                         WHEN s.est_payant = 0 THEN 1
                         WHEN s.est_payant = 1 AND EXISTS (
                             SELECT 1 FROM paiement p 
                             WHERE p.etudiant_idetudiant = :etudiantId 
                             AND p.frais_idfrais = s.idfrais
                             AND p.\"estComplet\" = 1
                         ) THEN 1
                         ELSE 0
                     END as a_acces
              FROM support_cours s
              WHERE s.idcours = :coursId
              ORDER BY s.\"dateCreation\" DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':coursId', $coursId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les devoirs associés à un cours avec statut de soumission pour l'étudiant
 */
public function getDevoirsCours($coursId, $etudiantId) {
    $query = "SELECT d.*,
                     CASE 
                         WHEN EXISTS (
                             SELECT 1 FROM reponses_devoir r 
                             WHERE r.iddevoir = d.iddevoir 
                             AND r.idetudiant = :etudiantId
                         ) THEN 1
                         ELSE 0
                     END as est_soumis,
                     (SELECT r.note FROM reponses_devoir r 
                      WHERE r.iddevoir = d.iddevoir AND r.idetudiant = :etudiantId) as note,
                     CASE 
                         WHEN d.date_limite < NOW() THEN 'Expiré'
                         WHEN d.date_limite > NOW() THEN 'En cours'
                     END as statut
              FROM devoirs d
              WHERE d.idcours = :coursId
              ORDER BY d.date_limite ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':coursId', $coursId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Soumet une réponse à un devoir
 */
public function soumettreDevoir($devoirId, $etudiantId, $fichier, $commentaire) {
    // Vérifier si le devoir n'a pas expiré
    $query = "SELECT date_limite FROM devoirs WHERE iddevoir = :devoirId AND date_limite > NOW()";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        throw new Exception("La date limite de soumission est dépassée.");
    }
    
    // Vérifier si l'étudiant a déjà soumis une réponse
    $query = "SELECT idreponse FROM reponses_devoir 
              WHERE iddevoir = :devoirId AND idetudiant = :etudiantId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->fetch()) {
        // Mise à jour d'une soumission existante
        $query = "UPDATE reponses_devoir 
                  SET fichier = :fichier, 
                      commentaire = :commentaire, 
                      date_soumission = NOW()
                  WHERE iddevoir = :devoirId AND idetudiant = :etudiantId";
    } else {
        // Nouvelle soumission
        $query = "INSERT INTO reponses_devoir (fichier, commentaire, iddevoir, idetudiant, date_soumission)
                  VALUES (:fichier, :commentaire, :devoirId, :etudiantId, NOW())";
    }
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':fichier', $fichier);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Récupère les détails d'une soumission de devoir
 */
public function getSoumissionDevoir($devoirId, $etudiantId) {
    $query = "SELECT r.*, d.titre as devoir_titre, d.date_limite,
                     c.titre as cours_titre, ens.\"nomEnseignant\"
              FROM reponses_devoir r
              JOIN devoirs d ON r.iddevoir = d.iddevoir
              JOIN cours c ON d.idcours = c.idcours
              JOIN enseignant ens ON c.idenseignant = ens.idenseignant
              WHERE r.iddevoir = :devoirId AND r.idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':devoirId', $devoirId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un étudiant a accès à un support de cours payant
 */
public function hasAccessToSupport($supportId, $etudiantId) {
    $query = "SELECT s.est_payant, s.idfrais,
                     CASE 
                         WHEN s.est_payant = 0 THEN 1
                         WHEN s.est_payant = 1 AND EXISTS (
                             SELECT 1 FROM paiement p 
                             WHERE p.etudiant_idetudiant = :etudiantId 
                             AND p.frais_idfrais = s.idfrais
                             AND p.\"estComplet\" = 1
                         ) THEN 1
                         ELSE 0
                     END as a_acces
              FROM support_cours s
              WHERE s.idsupport = :supportId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':supportId', $supportId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['a_acces'] == 1;
}

/**
 * Récupère les détails d'un support de cours
 */
public function getSupportDetails($supportId) {
    $query = "SELECT s.*, c.titre as cours_titre, 
                     f.designation as frais_designation, f.montant as frais_montant, f.devise
              FROM support_cours s
              JOIN cours c ON s.idcours = c.idcours
              LEFT JOIN frais f ON s.idfrais = f.idfrais
              WHERE s.idsupport = :supportId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':supportId', $supportId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère le tableau de bord des cours pour un étudiant
 */
public function getCoursTableauBord($etudiantId) {
    $query = "SELECT 
                COUNT(DISTINCT c.idcours) as total_cours,
                COUNT(DISTINCT d.iddevoir) as total_devoirs,
                COUNT(DISTINCT CASE WHEN d.date_limite > NOW() THEN d.iddevoir END) as devoirs_encours,
                COUNT(DISTINCT CASE WHEN d.date_limite < NOW() THEN d.iddevoir END) as devoirs_expires,
                COUNT(DISTINCT CASE WHEN r.idreponse IS NOT NULL THEN d.iddevoir END) as devoirs_soumis,
                COUNT(DISTINCT CASE WHEN r.note IS NOT NULL THEN r.idreponse END) as devoirs_notes,
                AVG(r.note) as moyenne_notes
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN ue u ON u.semestre_idsemestre = p.idpromotion
              JOIN ecue ec ON ec.\"UE_idUE\" = u.\"idUE\"
              JOIN cours c ON c.\"idECUE\" = ec.\"idECUE\"
              LEFT JOIN devoirs d ON d.idcours = c.idcours
              LEFT JOIN reponses_devoir r ON r.iddevoir = d.iddevoir AND r.idetudiant = e.idetudiant
              WHERE e.idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les dernières activités liées aux cours pour un étudiant
 */
public function getCoursActivitesRecentes($etudiantId, $limit = 10) {
    $query = "SELECT 'support' as type, s.\"dateCreation\" as date, 
                     s.titre, c.titre as cours_titre, NULL as note
              FROM support_cours s
              JOIN cours c ON s.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              
              UNION
              
                            SELECT 'devoir' as type, d.\"dateCreation\" as date,
                     d.titre, c.titre as cours_titre, NULL as note
              FROM devoirs d
              JOIN cours c ON d.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              
              UNION
              
              SELECT 'soumission' as type, r.date_soumission as date,
                     d.titre, c.titre as cours_titre, r.note
              FROM reponses_devoir r
              JOIN devoirs d ON r.iddevoir = d.iddevoir
              JOIN cours c ON d.idcours = c.idcours
              WHERE r.idetudiant = :etudiantId
              
              ORDER BY date DESC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les cours par semestre pour un étudiant
 */
public function getCoursBySemestre($etudiantId) {
    $query = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\",
                     COUNT(DISTINCT c.idcours) as nb_cours,
                     COUNT(DISTINCT e.\"idECUE\") as nb_ecues,
                     SUM(e.CMI + e.TD + e.TP) as total_heures
              FROM etudiant et
              JOIN promotion p ON et.promotion_idpromotion = p.idpromotion
              JOIN ue u ON u.semestre_idsemestre = p.idpromotion
              JOIN ecue e ON e.\"UE_idUE\" = u.\"idUE\"
              LEFT JOIN cours c ON c.\"idECUE\" = e.\"idECUE\"
              WHERE et.idetudiant = :etudiantId
              GROUP BY u.\"idUE\", u.\"codeUE\", u.\"designationUE\"
              ORDER BY u.codeUE";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque semestre, récupérer les ECUEs et cours associés
    foreach ($semestres as &$semestre) {
        $query = "SELECT e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP,
                         COUNT(DISTINCT c.idcours) as nb_cours,
                         COUNT(DISTINCT d.iddevoir) as nb_devoirs
                  FROM ue u
                  JOIN ecue e ON e.\"UE_idUE\" = u.\"idUE\"
                  LEFT JOIN cours c ON c.\"idECUE\" = e.\"idECUE\"
                  LEFT JOIN devoirs d ON d.idcours = c.idcours
                  WHERE u.\"idUE\" = :ueId
                  GROUP BY e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP
                  ORDER BY e.designationECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $semestre['idUE'], PDO::PARAM_INT);
        $stmt->execute();
        $semestre['ecues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $semestres;
}

    /**
     * Get student's stages
     */
    public function getStudentStages($studentId, $yearId) {
        try {
            $query = "SELECT s.*, 
                             p.\"designationPromotion\" as promotion, 
                             p.est_terminale,
                             a.noms as encadreur_nom, 
                             al.noms as lecteur_nom,
                             s.date_debut,
                             s.date_fin
                      FROM stage_assignments s
                      JOIN etudiant e ON s.idetudiant = e.idetudiant
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      LEFT JOIN agent a ON s.idencadreur = a.\"idAgent\"
                      LEFT JOIN agent al ON s.idlecteur = al.\"idAgent\"
                      WHERE s.idetudiant = :studentId";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("DEBUG getStudentStages: Student ID = $studentId, Found " . count($stages) . " stages");
            if (count($stages) > 0) {
                error_log("DEBUG getStudentStages: First stage data = " . print_r($stages[0], true));
            }
            
            return $stages;
        } catch (Exception $e) {
            error_log("Erreur getStudentStages: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Récupère les mémoires (sujets) de l'étudiant avec informations de dépôt et soutenance
     */
    public function getStudentMemoires($studentId) {
        try {
            $query = "SELECT 
                        s.idsujets,
                        s.intitule,
                        s.cycle,
                        s.\"idDirecteur\",
                        s.\"idEncadreur\",
                        s.\"idSpecialisation\",
                        s.statut_validation,
                        s.\"etatSujet\",
                        sp.designation as specialisation_nom,
                        d.noms as directeur_nom,
                        e.noms as encadreur_nom,
                        dm.fichier as memoire_path,
                        dm.\"dateDepot\" as date_depot,
                        so.idsoutenance,
                        so.date_soutenance,
                        so.lieu as lieu_soutenance,
                        so.statut,
                        so.note_finale
                      FROM sujets s
                      LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
                      LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
                      LEFT JOIN agent e ON s.\"idEncadreur\" = e.\"idAgent\"
                      LEFT JOIN depot_memoire dm ON s.idsujets = dm.sujets_idsujets
                      LEFT JOIN soutenance so ON s.idsujets = so.sujets_idsujets
                      WHERE s.etudiant_idetudiant = :studentId
                      ORDER BY s.idsujets DESC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $memoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("DEBUG getStudentMemoires: Student ID = $studentId, Found " . count($memoires) . " memoires");
            
            return $memoires;
        } catch (Exception $e) {
            error_log("Erreur getStudentMemoires: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if student has paid stage fee
     */
    public function hasStudentPaidStageFee($studentId, $stageId) {
        // Get the required fee for the stage's promotion
        $query = "SELECT f.idfrais FROM frais f
                  JOIN stage_required_fees srf ON f.idfrais = srf.idfrais
                  JOIN stage_assignments s ON srf.idpromotion = (SELECT promotion_idpromotion FROM etudiant WHERE idetudiant = s.idetudiant)
                  WHERE s.idstage = :stageId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':stageId', $stageId, PDO::PARAM_INT);
        $stmt->execute();
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fee) return true; // No fee required

        $query = "SELECT COUNT(*) FROM paiement_etudiant
                  WHERE idetudiant = :studentId AND idfrais = :feeId AND statut = 'paye'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':feeId', $fee['idfrais'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si l'étudiant a payé tous les frais requis pour le dépôt de mémoire
     */
    public function hasStudentPaidMemoireFees($studentId) {
        try {
            // Récupérer la promotion et le matricule de l'étudiant
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("hasStudentPaidMemoireFees: Student $studentId not found");
                return false;
            }
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            error_log("hasStudentPaidMemoireFees: Student $studentId ($matricule), Promotion $promotionId");
            
            // Récupérer tous les frais requis pour le mémoire de cette promotion
            $query = "SELECT f.id, f.designation, f.montant
                      FROM frais f
                      JOIN frais_memoire fm ON f.id = fm.frais_id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'memoire'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("hasStudentPaidMemoireFees: Required fees count: " . count($requiredFees));
            
            // Si aucun frais requis, l'étudiant peut uploader
            if (empty($requiredFees)) {
                error_log("hasStudentPaidMemoireFees: No required fees, returning true");
                return true;
            }
            
            // Vérifier que l'étudiant a payé tous les frais requis
            foreach ($requiredFees as $fee) {
                // Chercher l'affectation du frais à l'étudiant (individuelle ou de promotion)
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    error_log("hasStudentPaidMemoireFees: Fee {$fee['id']} ({$fee['designation']}) - No affectation found - returning false");
                    return false;
                }
                
                // Récupérer le montant total à payer
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                // Récupérer les paiements pour cette affectation
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                error_log("hasStudentPaidMemoireFees: Fee {$fee['id']} ({$fee['designation']}) - Paid: $montantPaye / Total: $montantTotal");
                
                if ($montantPaye < $montantTotal) {
                    // Au moins un frais n'est pas complètement payé
                    error_log("hasStudentPaidMemoireFees: Fee {$fee['id']} not fully paid - returning false");
                    return false;
                }
            }
            
            error_log("hasStudentPaidMemoireFees: All fees paid - returning true");
            return true;
        } catch (Exception $e) {
            error_log("Erreur hasStudentPaidMemoireFees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si l'étudiant a payé tous les frais requis pour le dépôt de sujet
     */
    public function hasStudentPaidSujetFees($studentId) {
        try {
            // Récupérer la promotion et le matricule de l'étudiant
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("hasStudentPaidSujetFees: Student $studentId not found");
                return false;
            }
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            error_log("hasStudentPaidSujetFees: Student $studentId ($matricule), Promotion $promotionId");
            
            // Récupérer tous les frais requis pour le sujet de cette promotion
            $query = "SELECT f.id, f.designation, f.montant
                      FROM frais f
                      JOIN frais_memoire fm ON f.id = fm.frais_id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'sujet'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("hasStudentPaidSujetFees: Required fees count: " . count($requiredFees));
            
            // Si aucun frais requis, l'étudiant peut proposer un sujet
            if (empty($requiredFees)) {
                error_log("hasStudentPaidSujetFees: No required fees, returning true");
                return true;
            }
            
            // Vérifier que l'étudiant a payé tous les frais requis
            foreach ($requiredFees as $fee) {
                // Chercher l'affectation du frais à l'étudiant (individuelle ou de promotion)
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    error_log("hasStudentPaidSujetFees: Fee {$fee['id']} ({$fee['designation']}) - No affectation found - returning false");
                    return false;
                }
                
                // Récupérer le montant total à payer
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                // Récupérer les paiements pour cette affectation
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                error_log("hasStudentPaidSujetFees: Fee {$fee['id']} ({$fee['designation']}) - Paid: $montantPaye / Total: $montantTotal");
                
                if ($montantPaye < $montantTotal) {
                    // Au moins un frais n'est pas complètement payé
                    error_log("hasStudentPaidSujetFees: Fee {$fee['id']} not fully paid - returning false");
                    return false;
                }
            }
            
            error_log("hasStudentPaidSujetFees: All fees paid - returning true");
            return true;
        } catch (Exception $e) {
            error_log("Erreur hasStudentPaidSujetFees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste des frais requis pour le mémoire et leur statut de paiement
     */
    public function getMemoireFeesStatus($studentId) {
        try {
            // Récupérer la promotion et le matricule de l'étudiant
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("getMemoireFeesStatus: Student $studentId not found");
                return [];
            }
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            
            // Récupérer les frais requis pour le mémoire de cette promotion
            $query = "SELECT f.id, f.designation, f.montant, f.devise
                      FROM frais f
                      JOIN frais_memoire fm ON f.id = fm.frais_id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'memoire'
                      ORDER BY f.designation";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("getMemoireFeesStatus: Required fees count: " . count($requiredFees));
            
            // Pour chaque frais requis, récupérer le statut de paiement
            $result = [];
            foreach ($requiredFees as $fee) {
                // Chercher l'affectation du frais à l'étudiant (individuelle ou de promotion)
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    // Pas d'affectation trouvée - le frais n'est pas assigné à l'étudiant
                    error_log("getMemoireFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - No affectation found");
                    continue;
                }
                
                // Récupérer le montant total à payer
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                // Récupérer les paiements pour cette affectation
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                // Déterminer le statut
                if ($montantPaye >= $montantTotal) {
                    $statut_paiement = 'paye';
                } elseif ($montantPaye > 0) {
                    $statut_paiement = 'partiel';
                } else {
                    $statut_paiement = 'non_paye';
                }
                
                $result[] = [
                    'id' => $fee['id'],
                    'designation' => $fee['designation'],
                    'montant' => $montantTotal,
                    'devise' => $fee['devise'],
                    'montantPaye' => $montantPaye,
                    'statut_paiement' => $statut_paiement
                ];
                
                error_log("getMemoireFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - Paid: $montantPaye / Total: $montantTotal - Status: $statut_paiement");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur getMemoireFeesStatus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère la liste des frais requis pour le sujet et leur statut de paiement
     */
    public function getSujetFeesStatus($studentId) {
        try {
            // Récupérer la promotion et le matricule de l'étudiant
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("getSujetFeesStatus: Student $studentId not found");
                return [];
            }
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            
            // Récupérer les frais requis pour le sujet de cette promotion
            $query = "SELECT f.id, f.designation, f.montant, f.devise
                      FROM frais f
                      JOIN frais_memoire fm ON f.id = fm.frais_id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'sujet'
                      ORDER BY f.designation";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("getSujetFeesStatus: Required fees count: " . count($requiredFees));
            
            // Pour chaque frais requis, récupérer le statut de paiement
            $result = [];
            foreach ($requiredFees as $fee) {
                // Chercher l'affectation du frais à l'étudiant (individuelle ou de promotion)
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    // Pas d'affectation trouvée - le frais n'est pas assigné à l'étudiant
                    error_log("getSujetFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - No affectation found");
                    continue;
                }
                
                // Récupérer le montant total à payer
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                // Récupérer les paiements pour cette affectation
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                // Déterminer le statut
                if ($montantPaye >= $montantTotal) {
                    $statut_paiement = 'paye';
                } elseif ($montantPaye > 0) {
                    $statut_paiement = 'partiel';
                } else {
                    $statut_paiement = 'non_paye';
                }
                
                $result[] = [
                    'id' => $fee['id'],
                    'designation' => $fee['designation'],
                    'montant' => $montantTotal,
                    'devise' => $fee['devise'],
                    'montantPaye' => $montantPaye,
                    'statut_paiement' => $statut_paiement
                ];
                
                error_log("getSujetFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - Paid: $montantPaye / Total: $montantTotal - Status: $statut_paiement");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur getSujetFeesStatus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si l'étudiant a payé tous les frais requis pour la fiche de validation
     */
    public function hasStudentPaidFicheValidationFees($studentId) {
        try {
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) return false;
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            
            // Use frais_fiche_validation table instead of frais_memoire
            $query = "SELECT f.id, f.designation, f.montant
                      FROM frais f
                      JOIN frais_fiche_validation fv ON f.id = fv.frais_id
                      WHERE fv.promotion_idpromotion = :promotionId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($requiredFees)) return true;
            
            foreach ($requiredFees as $fee) {
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) return false;
                
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                if ($montantPaye < $montantTotal) return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur hasStudentPaidFicheValidationFees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste des frais requis pour la fiche de validation et leur statut de paiement
     */
    public function getFicheValidationFeesStatus($studentId) {
        try {
            // Récupérer la promotion et le matricule de l'étudiant
            $query = "SELECT promotion_idpromotion, matricule FROM etudiant WHERE idetudiant = :studentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("getFicheValidationFeesStatus: Student $studentId not found");
                return [];
            }
            
            $promotionId = $result['promotion_idpromotion'];
            $matricule = $result['matricule'];
            
            // Récupérer les frais requis pour la fiche de validation de cette promotion
            $query = "SELECT f.id, f.designation, f.montant, f.devise
                      FROM frais f
                      JOIN frais_fiche_validation fv ON f.id = fv.frais_id
                      WHERE fv.promotion_idpromotion = :promotionId
                      ORDER BY f.designation";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            $requiredFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("getFicheValidationFeesStatus: Required fees count: " . count($requiredFees));
            
            // Pour chaque frais requis, récupérer le statut de paiement
            $result = [];
            foreach ($requiredFees as $fee) {
                // Chercher l'affectation du frais à l'étudiant (individuelle ou de promotion)
                $query = "SELECT af.id, af.montant_specifique, f.montant as montant_frais
                          FROM affectation_frais af
                          JOIN frais f ON af.frais_id = f.id
                          WHERE (af.matricule_etudiant = :matricule OR af.promotion_id = :promotionId)
                          AND af.frais_id = :feeId
                          AND af.est_exempte = 0
                          LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $stmt->bindParam(':feeId', $fee['id'], PDO::PARAM_INT);
                $stmt->execute();
                $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    // Pas d'affectation trouvée - le frais n'est pas assigné à l'étudiant
                    error_log("getFicheValidationFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - No affectation found");
                    continue;
                }
                
                // Récupérer le montant total à payer
                $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
                
                // Récupérer les paiements pour cette affectation
                $query = "SELECT COALESCE(SUM(montant), 0) as totalPaye 
                          FROM paiements_frais
                          WHERE affectation_id = :affectationId AND matricule_etudiant = :matricule";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':affectationId', $affectation['id'], PDO::PARAM_INT);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montantPaye = $paymentInfo['totalPaye'] ?? 0;
                
                // Déterminer le statut
                if ($montantPaye >= $montantTotal) {
                    $statut_paiement = 'paye';
                } elseif ($montantPaye > 0) {
                    $statut_paiement = 'partiel';
                } else {
                    $statut_paiement = 'non_paye';
                }
                
                $result[] = [
                    'id' => $fee['id'],
                    'designation' => $fee['designation'],
                    'montant' => $montantTotal,
                    'devise' => $fee['devise'],
                    'montantPaye' => $montantPaye,
                    'statut_paiement' => $statut_paiement
                ];
                
                error_log("getFicheValidationFeesStatus: Fee {$fee['id']} ({$fee['designation']}) - Paid: $montantPaye / Total: $montantTotal - Status: $statut_paiement");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur getFicheValidationFeesStatus: " . $e->getMessage());
            return [];
        }
    }

/**
 * Récupère les cours par ECUE
 */
public function getCoursByECUE($ecueId) {
    $query = "SELECT c.idcours, c.titre, c.description, c.\"dateCreation\",
                     ens.\"nomEnseignant\", ens.grade,
                     (SELECT COUNT(*) FROM support_cours WHERE idcours = c.idcours) as nb_supports,
                     (SELECT COUNT(*) FROM devoirs WHERE idcours = c.idcours) as nb_devoirs
              FROM cours c
              JOIN enseignant ens ON c.idenseignant = ens.idenseignant
              WHERE c.\"idECUE\" = :ecueId
              ORDER BY c.\"dateCreation\" DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un étudiant a payé des frais spécifiques
 */
public function hasPaidFrais($etudiantId, $fraisId) {
    $query = "SELECT SUM(\"montantPaye\") as total_paye, 
                     (SELECT montant FROM frais WHERE idfrais = :fraisId) as montant_total
              FROM paiement
              WHERE etudiant_idetudiant = :etudiantId
              AND frais_idfrais = :fraisId
              AND \"estComplet\" = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['total_paye'] >= $result['montant_total'];
}

/**
 * Récupère les frais liés aux supports de cours pour un étudiant
 */
public function getFraisSupportsCours($etudiantId) {
    $query = "SELECT DISTINCT f.idfrais, f.designation, f.montant, f.devise, f.description,
                     COUNT(DISTINCT s.idsupport) as nb_supports,
                     CASE 
                         WHEN EXISTS (
                             SELECT 1 FROM paiement p 
                             WHERE p.etudiant_idetudiant = :etudiantId 
                             AND p.frais_idfrais = f.idfrais
                             AND p.\"estComplet\" = 1
                         ) THEN 1
                         ELSE 0
                     END as est_paye
              FROM frais f
              JOIN support_cours s ON s.idfrais = f.idfrais
              JOIN cours c ON s.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              AND s.est_payant = 1
              GROUP BY f.idfrais, f.designation, f.montant, f.devise, f.description
              ORDER BY f.designation";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques de performance d'un étudiant dans ses cours
 */
public function getStatistiquesPerformance($etudiantId) {
    $query = "SELECT 
                COUNT(DISTINCT d.iddevoir) as total_devoirs,
                COUNT(DISTINCT CASE WHEN r.idreponse IS NOT NULL THEN d.iddevoir END) as devoirs_soumis,
                COUNT(DISTINCT CASE WHEN r.note IS NOT NULL THEN r.idreponse END) as devoirs_notes,
                AVG(r.note) as moyenne_generale,
                MAX(r.note) as meilleure_note,
                MIN(CASE WHEN r.note IS NOT NULL THEN r.note END) as moins_bonne_note,
                COUNT(DISTINCT CASE WHEN r.note >= 10 THEN r.idreponse END) as devoirs_reussis,
                COUNT(DISTINCT CASE WHEN r.note < 10 AND r.note IS NOT NULL THEN r.idreponse END) as devoirs_echoues
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN ue u ON u.semestre_idsemestre = p.idpromotion
              JOIN ecue ec ON ec.\"UE_idUE\" = u.\"idUE\"
              JOIN cours c ON c.\"idECUE\" = ec.\"idECUE\"
              LEFT JOIN devoirs d ON d.idcours = c.idcours
              LEFT JOIN reponses_devoir r ON r.iddevoir = d.iddevoir AND r.idetudiant = e.idetudiant
              WHERE e.idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer le taux de réussite
    if ($stats['devoirs_notes'] > 0) {
        $stats['taux_reussite'] = ($stats['devoirs_reussis'] / $stats['devoirs_notes']) * 100;
    } else {
        $stats['taux_reussite'] = 0;
    }
    
    // Calculer le taux de participation
    if ($stats['total_devoirs'] > 0) {
        $stats['taux_participation'] = ($stats['devoirs_soumis'] / $stats['total_devoirs']) * 100;
    } else {
        $stats['taux_participation'] = 0;
    }
    
    return $stats;
}

/**
 * Récupère les notes par UE pour un étudiant
 */
public function getNotesParUE($etudiantId) {
    $query = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\",
                     COUNT(DISTINCT r.idreponse) as nb_notes,
                     AVG(r.note) as moyenne_ue
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN ue u ON u.semestre_idsemestre = p.idpromotion
              JOIN ecue ec ON ec.\"UE_idUE\" = u.\"idUE\"
              JOIN cours c ON c.\"idECUE\" = ec.\"idECUE\"
              JOIN devoirs d ON d.idcours = c.idcours
              LEFT JOIN reponses_devoir r ON r.iddevoir = d.iddevoir AND r.idetudiant = e.idetudiant
              WHERE e.idetudiant = :etudiantId
              AND r.note IS NOT NULL
              GROUP BY u.\"idUE\", u.\"codeUE\", u.\"designationUE\"
              ORDER BY u.codeUE";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les devoirs à venir pour un étudiant
 */
public function getDevoirsAVenir($etudiantId, $limit = 5) {
    $query = "SELECT d.iddevoir, d.titre, d.date_limite, 
                     c.titre as cours_titre, 
                     (d.date_limite::date - NOW()::date) as jours_restants,
                     CASE 
                         WHEN EXISTS (
                             SELECT 1 FROM reponses_devoir r 
                             WHERE r.iddevoir = d.iddevoir 
                             AND r.idetudiant = :etudiantId
                         ) THEN 1
                         ELSE 0
                     END as est_soumis
              FROM devoirs d
              JOIN cours c ON d.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              AND d.date_limite > NOW()
              ORDER BY d.date_limite ASC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les supports de cours récemment ajoutés
 */
public function getSupportsRecents($etudiantId, $limit = 5) {
    $query = "SELECT s.idsupport, s.titre, s.\"dateCreation\", 
                     c.titre as cours_titre, s.est_payant,
                     CASE 
                         WHEN s.est_payant = 0 THEN 1
                         WHEN s.est_payant = 1 AND EXISTS (
                             SELECT 1 FROM paiement p 
                             WHERE p.etudiant_idetudiant = :etudiantId 
                             AND p.frais_idfrais = s.idfrais
                             AND p.\"estComplet\" = 1
                         ) THEN 1
                         ELSE 0
                     END as a_acces
              FROM support_cours s
              JOIN cours c ON s.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              ORDER BY s.\"dateCreation\" DESC
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recherche des cours par mot-clé
 */
public function searchCours($etudiantId, $keyword) {
    $keyword = '%' . $keyword . '%';
    
    $query = "SELECT c.idcours, c.titre, c.description,
                     e.\"designationECUE\", u.\"designationUE\",
                     ens.\"nomEnseignant\"
              FROM cours c
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN enseignant ens ON c.idenseignant = ens.idenseignant
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              AND (c.titre LIKE :keyword 
                   OR c.description LIKE :keyword
                   OR e.\"designationECUE\" LIKE :keyword
                   OR u.\"designationUE\" LIKE :keyword
                   OR ens.\"nomEnseignant\" LIKE :keyword)
              ORDER BY c.titre ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':keyword', $keyword);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque un support de cours comme consulté
 */
public function marquerSupportConsulte($supportId, $etudiantId) {
    // Vérifier d'abord si l'étudiant a accès au support
    if (!$this->hasAccessToSupport($supportId, $etudiantId)) {
        return false;
    }
    
    // Vérifier si une entrée existe déjà
    $query = "SELECT COUNT(*) FROM consultation_support 
              WHERE idsupport = :supportId AND idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':supportId', $supportId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        // Mettre à jour la date de dernière consultation
        $query = "UPDATE consultation_support 
                  SET date_consultation = NOW(), 
                      nb_consultations = nb_consultations + 1
                  WHERE idsupport = :supportId AND idetudiant = :etudiantId";
    } else {
        // Créer une nouvelle entrée
        $query = "INSERT INTO consultation_support 
                  (idsupport, idetudiant, date_consultation, nb_consultations)
                  VALUES (:supportId, :etudiantId, NOW(), 1)";
    }
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':supportId', $supportId, PDO::PARAM_INT);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Récupère les enseignants des cours suivis par l'étudiant
 */
public function getEnseignantsCours($etudiantId) {
    $query = "SELECT DISTINCT ens.idenseignant, ens.\"nomEnseignant\", ens.grade,
                     COUNT(DISTINCT c.idcours) as nb_cours,
                     a.telephone, a.email
              FROM enseignant ens
              JOIN cours c ON c.idenseignant = ens.idenseignant
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              LEFT JOIN agent a ON ens.\"idAgent\" = a.\"idAgent\"
              WHERE et.idetudiant = :etudiantId
              GROUP BY ens.idenseignant, ens.\"nomEnseignant\", ens.grade, a.telephone, a.email
              ORDER BY ens.nomEnseignant";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Génère un rapport de progression pour les cours d'un étudiant
 */
public function genererRapportProgression($etudiantId) {
    // Récupérer les informations de l'étudiant
    $etudiant = $this->getEtudiantById($etudiantId);
    if (!$etudiant) {
        return false;
    }

    // Récupérer les statistiques générales
    $stats = $this->getStatistiquesPerformance($etudiantId);
    
    // Récupérer les notes par UE
    $notesUE = $this->getNotesParUE($etudiantId);
    
    // Récupérer les cours et leurs devoirs
    $cours = $this->getCoursEtudiant($etudiantId);
    foreach ($cours as &$c) {
        $c['devoirs'] = $this->getDevoirsCours($c['idcours'], $etudiantId);
    }
    
    // Construire les données du rapport
    return [
        'etudiant' => $etudiant,
        'stats' => $stats,
        'notes_ue' => $notesUE,
        'cours' => $cours,
        'date_generation' => date('Y-m-d H:i:s')
    ];
}

/**
 * Vérifie si un étudiant est à jour dans ses paiements pour accéder aux supports de cours
 */
public function estAJourPaiementsCours($etudiantId) {
    $query = "SELECT COUNT(*) as total_frais,
                     SUM(CASE 
                         WHEN EXISTS (
                             SELECT 1 FROM paiement p 
                             WHERE p.etudiant_idetudiant = :etudiantId 
                             AND p.frais_idfrais = f.idfrais
                             AND p.\"estComplet\" = 1
                         ) THEN 1
                         ELSE 0
                     END) as frais_payes
              FROM frais f
              JOIN support_cours s ON s.idfrais = f.idfrais
              JOIN cours c ON s.idcours = c.idcours
              JOIN ecue e ON c.\"idECUE\" = e.\"idECUE\"
              JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              JOIN etudiant et ON et.promotion_idpromotion = u.semestre_idsemestre
              WHERE et.idetudiant = :etudiantId
              AND s.est_payant = 1
              AND f.\"estObligatoire\" = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['total_frais'] > 0 && $result['total_frais'] == $result['frais_payes'];
}

/**
 * Vérifie si un étudiant a accès à une ressource payante
 * 
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param string $type Le type de ressource ('ressource', 'support', 'devoir')
 * @param int $idRessource L'identifiant de la ressource
 * @return bool True si l'étudiant a accès, false sinon
 */
public function checkResourceAccess($idEtudiant, $idRessource) {
    try {
        $query = "SELECT COUNT(*) FROM paiement
                  INNER JOIN devoirs d ON d.idfrais=paiement.frais_idfrais  
                  WHERE paiement.etudiant_idetudiant = :idEtudiant
                  AND d.iddevoir = :idRessource
                  AND \"estComplet\" = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->bindParam(':idRessource', $idRessource, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'accès à la ressource: " . $e->getMessage());
        return false;
    }
}

public function checkResourceAccess2($idEtudiant, $idRessource) {
    try {
        $query = "SELECT COUNT(*) FROM paiement
                  INNER JOIN support_cours d ON d.idfrais=paiement.frais_idfrais  
                  WHERE paiement.etudiant_idetudiant = :idEtudiant
                  AND d.idsupport = :idRessource";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->bindParam(':idRessource', $idRessource, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'accès à la ressource: " . $e->getMessage());
        return false;
    }
}

public function checkResourceAccess3($idEtudiant, $idRessource) {
    try {
        $query = "SELECT COUNT(*) FROM paiement
                  INNER JOIN ressources_cours d ON d.idfrais=paiement.frais_idfrais  
                  WHERE paiement.etudiant_idetudiant = :idEtudiant
                  AND d.idressource = :idRessource";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->bindParam(':idRessource', $idRessource, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'accès à la ressource: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la réponse d'un étudiant à un devoir
 * 
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param int $idDevoir L'identifiant du devoir
 * @return array|false Les données de la réponse ou false si aucune réponse
 */
public function getStudentAssignmentResponse($idEtudiant, $idDevoir) {
    try {
        $query = "SELECT * FROM reponses_devoir 
                  WHERE idetudiant = :idEtudiant 
                  AND iddevoir = :idDevoir";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la réponse: " . $e->getMessage());
        return false;
    }
}

/**
 * Soumet une réponse à un devoir
 * 
 * @param int $idDevoir L'identifiant du devoir
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param string $fichier Le nom du fichier
 * @param string $commentaire Le commentaire de l'étudiant
 * @return bool True si la soumission a réussi, false sinon
 */
public function submitAssignmentResponse($idDevoir, $idEtudiant, $fichier, $commentaire) {
    try {
        $query = "INSERT INTO reponses_devoir (iddevoir, idetudiant, fichier, commentaire, date_soumission) 
                  VALUES (:idDevoir, :idEtudiant, :fichier, :commentaire, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
        $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
        $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
        $stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la soumission de la réponse: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les informations d'un étudiant par son matricule
 * @param string $matricule Matricule de l'étudiant
 * @return array|false Les données de l'étudiant ou false si non trouvé
 */
public function getEtudiantByMatricule($matricule) {
    $query = "SELECT e.*, p.\"designationPromotion\" as promotion, o.\"designationOrientation\" as orientation, 
                     s.\"designationSection\" as section, a.designation as annee_academique
              FROM etudiant e
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
              WHERE e.matricule = :matricule";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}







/**
 * Rechercher des étudiants par matricule ou nom dans une promotion/année
 */
public function searchStudentsByNameOrMatricule($search, $promotionId, $anneeId) {
    try {
        $query = "SELECT e.matricule, e.noms, p.\"designationPromotion\" as promotion,
                         p.idpromotion, a.designation as annee_academique
                  FROM etudiant e
                  INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  INNER JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                  WHERE (e.matricule LIKE :search OR e.noms LIKE :search)
                  AND e.promotion_idpromotion = :promotion_id
                  AND e.annee_acad_idannee_acad = :annee_id
                  AND e.est_actif = 1
                  ORDER BY e.noms ASC
                  LIMIT 10";
        
        $stmt = $this->db->prepare($query);
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erreur searchStudentsByNameOrMatricule: " . $e->getMessage());
        return [];
    }
}
    
}
