<?php
class Cours {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Ajouter un cours
    public function addCours($titre, $description, $idECUE, $idEnseignant, $idAnneeAcad) {
        $query = "INSERT INTO cours (titre, description, idECUE, idenseignant, annee_acad_idannee_acad) 
                  VALUES (:titre, :description, :idECUE, :idEnseignant, :idAnneeAcad)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'titre' => $titre,
            'description' => $description,
            'idECUE' => $idECUE,
            'idEnseignant' => $idEnseignant,
            'idAnneeAcad' => $idAnneeAcad
        ]);
    }

    public function addPartieCours($titre, $description, $ordre, $idCours) {
        $query = "INSERT INTO parties_cours (titre, description, ordre, idcours) 
                  VALUES (:titre, :description, :ordre, :idCours)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'titre' => $titre,
            'description' => $description,
            'ordre' => $ordre,
            'idCours' => $idCours
        ]);
    }

    // Récupérer un cours par son ID
    public function getCoursById($idCours) {
        $query = "SELECT c.*, e.designationECUE, a.noms as enseignant_nom, 
                 g.designation as grade_enseignant
                 FROM cours c
                 JOIN ecue e ON c.idECUE = e.idECUE
                 JOIN agent a ON c.idenseignant = a.idAgent
                 LEFT JOIN grade g ON a.grade_id = g.idgrade
                 WHERE c.idcours = :idCours";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idCours' => $idCours]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Récupérer les cours par enseignant
    public function getCoursByEnseignant($idEnseignant, $idAnneeAcad) {
        $query = "SELECT c.*, e.designationECUE, u.designationUE, 
                 s.numeroSemestre, p.designationPromotion, o.designationOrientation,
                 (SELECT COUNT(*) FROM parties_cours WHERE idcours = c.idcours) as nb_parties,
                 (SELECT COUNT(*) FROM devoirs WHERE idcours = c.idcours) as nb_devoirs
                 FROM cours c
                 JOIN ecue e ON c.idECUE = e.idECUE
                 JOIN ue u ON e.UE_idUE = u.idUE
                 JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                 JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                 JOIN orientation o ON p.orientation_idorientation = o.idorientation
                 WHERE c.idenseignant = :idEnseignant AND c.annee_acad_idannee_acad = :idAnneeAcad
                 ORDER BY p.designationPromotion, s.numeroSemestre, u.designationUE, e.designationECUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEnseignant' => $idEnseignant,
            'idAnneeAcad' => $idAnneeAcad
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }


    

    // Ajouter une ressource à un chapitre
    public function addRessource($titre, $description, $typeRessource, $fichier, $lienExterne, 
                               $estPayant, $idFrais, $idPartie) {
        $query = "INSERT INTO ressources_cours (titre, description, type_ressource, fichier, 
                 lien_externe, est_payant, idfrais, idpartie) 
                 VALUES (:titre, :description, :typeRessource, :fichier, :lienExterne, 
                 :estPayant, :idFrais, :idPartie)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'titre' => $titre,
            'description' => $description,
            'typeRessource' => $typeRessource,
            'fichier' => $fichier,
            'lienExterne' => $lienExterne,
            'estPayant' => $estPayant,
            'idFrais' => $estPayant ? $idFrais : null,
            'idPartie' => $idPartie
        ]);
    }

    // Vérifier si un étudiant a accès à une ressource payante
    public function hasAccessToResource($idEtudiant, $idRessource) {
        // Si la ressource n'est pas payante, l'accès est autorisé
        $query = "SELECT est_payant, idfrais FROM ressources_cours WHERE idressource = :idRessource";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idRessource' => $idRessource]);
        $ressource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ressource['est_payant']) {
            return true;
        }
        
        // Vérifier si l'étudiant a payé le frais requis
        $query = "SELECT COUNT(*) as count FROM etudiant_en_ordre 
                 WHERE idetudiant = :idEtudiant AND idfrais = :idFrais";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEtudiant' => $idEtudiant,
            'idFrais' => $ressource['idfrais']
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    public function getCoursByPromotion($idPromotion, $idAnneeAcad) {
        try {
            $query = "SELECT e.*,u.*,s.* 
                      FROM ecue e
                      JOIN ue u ON e.UE_idUE = u.idUE
                      JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                      JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                      WHERE p.idpromotion = :idPromotion 
                      AND p.annee_acad_idannee_acad = :idAnneeAcad
                      ORDER BY s.numeroSemestre ASC, u.designationUE ASC, e.designationECUE ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idPromotion', $idPromotion, PDO::PARAM_INT);
            $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des cours par promotion: " . $e->getMessage());
            return [];
        }
    }

    /**
 * Récupère les chapitres (parties) associés à un cours spécifique
 * @param int $idCours ID du cours
 * @return array Liste des chapitres avec leurs ressources
 */
public function getChaptersByCourse($idCours) {
    // Récupérer d'abord les chapitres du cours
    $query = "SELECT * FROM parties_cours 
             WHERE idcours = :idCours 
             ORDER BY ordre ASC";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idCours' => $idCours]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque chapitre, récupérer les ressources associées
    foreach ($chapters as &$chapter) {
        $query = "SELECT * FROM ressources_cours 
                 WHERE idpartie = :idPartie
                 ORDER BY dateCreation ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idPartie' => $chapter['idpartie']]);
        $chapter['ressources'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $chapters;
}

/**
 * Récupère les devoirs associés à un cours spécifique
 * @param int $idCours ID du cours
 * @return array Liste des devoirs
 */
public function getAssignmentsByCourse($idCours) {
    $query = "SELECT d.*, 
             (SELECT COUNT(*) FROM reponses_devoir WHERE iddevoir = d.iddevoir) as reponses_count
             FROM devoirs d
             WHERE d.idcours = :idCours
             ORDER BY d.date_limite ASC";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idCours' => $idCours]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un étudiant a accès à un devoir spécifique
 * @param int $etudiantId ID de l'étudiant
 * @param int $idDevoir ID du devoir
 * @return bool true si l'étudiant a accès, false sinon
 */
public function hasAccessToAssignment($etudiantId, $idDevoir) {
    // Vérifier si le devoir est payant
    $query = "SELECT est_payant, idfrais FROM devoirs WHERE iddevoir = :idDevoir";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idDevoir' => $idDevoir]);
    $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$devoir || !$devoir['est_payant']) {
        return true; // Accès libre si non payant
    }
    
    // Vérifier si l'étudiant a payé le frais requis
    $query = "SELECT COUNT(*) as count FROM etudiant_en_ordre 
             WHERE idetudiant = :idEtudiant AND idfrais = :idFrais";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idEtudiant' => $etudiantId,
        'idFrais' => $devoir['idfrais']
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Récupère la réponse d'un étudiant à un devoir spécifique
 * @param int $etudiantId ID de l'étudiant
 * @param int $idDevoir ID du devoir
 * @return array|false Informations sur la réponse ou false si aucune réponse
 */
public function getStudentResponse($etudiantId, $idDevoir) {
    $query = "SELECT * FROM reponses_devoir
             WHERE iddevoir = :idDevoir AND idetudiant = :idEtudiant
             ORDER BY date_soumission DESC
             LIMIT 1";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idDevoir' => $idDevoir,
        'idEtudiant' => $etudiantId
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les chapitres d'un ECUE
 * @param int $idECUE ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Liste des chapitres pour tous les cours de cet ECUE
 */
public function getChaptersByEcue($idECUE, $idAnneeAcad) {
    // D'abord récupérer tous les cours pour cet ECUE
    $query = "SELECT idcours FROM cours 
             WHERE idECUE = :idECUE AND annee_acad_idannee_acad = :idAnneeAcad";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    $cours = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupérer tous les chapitres pour ces cours
    $chapters = [];
    foreach ($cours as $idCours) {
        $coursChapters = $this->getChaptersByCourse($idCours);
        $chapters = array_merge($chapters, $coursChapters);
    }
    
    return $chapters;
}

/**
 * Récupère tous les devoirs d'un ECUE
 * @param int $idECUE ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Liste des devoirs pour tous les cours de cet ECUE
 */
public function getAssignmentsByEcue($idECUE, $idAnneeAcad) {
    // D'abord récupérer tous les cours pour cet ECUE
    $query = "SELECT idcours FROM cours 
             WHERE idECUE = :idECUE AND annee_acad_idannee_acad = :idAnneeAcad";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    $cours = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupérer tous les devoirs pour ces cours
    // Inclure aussi les devoirs créés directement via idECUE (sans idcours)
    if (!empty($cours)) {
        $placeholders = implode(',', array_fill(0, count($cours), '?'));
        $query = "SELECT d.*, c.titre as cours_titre
                 FROM devoirs d
                 LEFT JOIN cours c ON d.idcours = c.idcours
                 WHERE (d.idcours IN ($placeholders) OR (d.idECUE = :idECUE2 AND (d.idcours IS NULL OR d.idcours = 0)))
                 ORDER BY d.date_limite ASC";
        
        $stmt = $this->db->prepare($query);
        $params = array_merge($cours, [$idECUE]);
        $stmt->execute($params);
    } else {
        // Si aucun cours, chercher seulement par idECUE
        $query = "SELECT d.*, c.titre as cours_titre
                 FROM devoirs d
                 LEFT JOIN cours c ON d.idcours = c.idcours
                 WHERE d.idECUE = :idECUE3 AND (d.idcours IS NULL OR d.idcours = 0)
                 ORDER BY d.date_limite ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idECUE3' => $idECUE
        ]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les enseignants associés à un ECUE
 * @param int $idECUE ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Liste des enseignants
 */
public function getEnseignantsByEcue($idECUE, $idAnneeAcad) {
    $query = "SELECT e.*, a.noms, a.email, a.telephone, g.designation as grade, ee.poste
             FROM enseignant_ecue ee
             JOIN agent a ON ee.idAgent = a.idAgent
             LEFT JOIN grade g ON a.grade_id = g.idgrade
             WHERE ee.idECUE = :idECUE AND ee.anneeAcad = :idAnneeAcad";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les cours associés à un ECUE
 * @param int $idECUE ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique
 * @return array Liste des cours
 */
public function getCoursByEcue($idECUE, $idAnneeAcad) {
    $query = "SELECT c.*, a.noms as enseignant_nom, g.designation as grade_enseignant
             FROM cours c
             JOIN agent a ON c.idenseignant = a.idAgent
             LEFT JOIN grade g ON a.grade_id = g.idgrade
             WHERE c.idECUE = :idECUE AND c.annee_acad_idannee_acad = :idAnneeAcad
             ORDER BY c.dateCreation ASC";
    $stmt = $this->db->prepare($query);
    $stmt->execute([
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Récupère les informations d'un devoir par son ID
 *
 * @param int $idDevoir L'identifiant du devoir
 * @return array|false Les informations du devoir ou false si non trouvé
 */
public function getDevoirById($idDevoir) {
    try {
        $query = "SELECT d.*, c.designationECUE, c.idECUE 
                 FROM devoirs d
                 INNER JOIN ecue c ON d.idECUE = c.idECUE
                 WHERE d.iddevoir = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idDevoir]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du devoir: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un étudiant a déjà soumis une réponse à un devoir
 *
 * @param int $idDevoir L'identifiant du devoir
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @return bool True si une soumission existe, false sinon
 */
public function checkExistingSubmission($idDevoir, $idEtudiant) {
    try {
        $query = "SELECT COUNT(*) FROM reponses_devoir 
                 WHERE iddevoir = ? AND idetudiant = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idDevoir, $idEtudiant]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification d'une soumission existante: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre une soumission de devoir par un étudiant
 *
 * @param int $idDevoir L'identifiant du devoir
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param string $commentaire Commentaire de l'étudiant
 * @param string $fichier Nom du fichier téléchargé
 * @return bool True si l'opération a réussi, false sinon
 */
public function submitAssignment($idDevoir, $idEtudiant, $commentaire, $fichier) {
    try {
        $this->db->beginTransaction();
        
        // Vérifier si une soumission existe déjà
        $existingSubmission = $this->checkExistingSubmission($idDevoir, $idEtudiant);
        
        if ($existingSubmission) {
            // Mettre à jour la soumission existante
            $query = "UPDATE reponses_devoir 
                     SET commentaire = ?, fichier = ?, date_soumission = NOW(), 
                         note = NULL, feedback_enseignant = NULL 
                     WHERE iddevoir = ? AND idetudiant = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$commentaire, $fichier, $idDevoir, $idEtudiant]);
        } else {
            // Créer une nouvelle soumission
            $query = "INSERT INTO reponses_devoir 
                     (iddevoir, idetudiant, commentaire, fichier, date_soumission) 
                     VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$idDevoir, $idEtudiant, $commentaire, $fichier]);
        }
        
        if ($result) {
            // Mettre à jour le statut du devoir pour l'étudiant
            $this->updateAssignmentStatus($idDevoir, $idEtudiant, 'Soumis');
            
            // Enregistrer l'activité
            $this->logActivity($idEtudiant, 'devoir', $idDevoir, 'Soumission de devoir');
            
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
        error_log("Erreur lors de la soumission du devoir: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour le statut d'un devoir pour un étudiant
 *
 * @param int $idDevoir L'identifiant du devoir
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param string $statut Le nouveau statut
 * @return bool True si l'opération a réussi, false sinon
 */
private function updateAssignmentStatus($idDevoir, $idEtudiant, $statut) {
    try {
        // Vérifier si un statut existe déjà
        $query = "SELECT COUNT(*) FROM statut_devoir_etudiant 
                 WHERE iddevoir = ? AND idetudiant = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idDevoir, $idEtudiant]);
        
        if ($stmt->fetchColumn() > 0) {
            // Mettre à jour le statut existant
            $query = "UPDATE statut_devoir_etudiant 
                     SET statut = ?, date_modification = NOW() 
                     WHERE iddevoir = ? AND idetudiant = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$statut, $idDevoir, $idEtudiant]);
        } else {
            // Créer un nouveau statut
            $query = "INSERT INTO statut_devoir_etudiant 
                     (iddevoir, idetudiant, statut, date_modification) 
                     VALUES (?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$idDevoir, $idEtudiant, $statut]);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du statut du devoir: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre une activité dans le journal
 *
 * @param int $idEtudiant L'identifiant de l'étudiant
 * @param string $type Le type d'activité
 * @param int $idElement L'identifiant de l'élément concerné
 * @param string $description Description de l'activité
 * @return bool True si l'opération a réussi, false sinon
 */
private function logActivity($idEtudiant, $type, $idElement, $description) {
    try {
        $query = "INSERT INTO journal_activites 
                 (user_type, user_id, type_activite, id_element, description, date_activite) 
                 VALUES ('etudiant', ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$idEtudiant, $type, $idElement, $description]);
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement de l'activité: " . $e->getMessage());
        return false;
    }
}






    
}

