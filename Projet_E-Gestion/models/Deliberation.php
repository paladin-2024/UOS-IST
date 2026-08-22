<?php

class Deliberation
{
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

    // Récupérer les bureaux de jury où un agent est membre
    public function getJuryBureauxByAgent($agentId)
    {
        $query = "SELECT bj.* FROM bureau_jury_deliberation bj
                  LEFT JOIN membre_bureau_jury mbj ON bj.idbureau = mbj.idbureau
                  WHERE (bj.president_id = :agentId OR bj.secretaire_id = :agentId OR mbj.\"idAgent\" = :agentId)
                  AND bj.est_actif = 1
                  ORDER BY bj.date_creation DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier si un agent est président d'un jury
    public function isJuryPresident($agentId)
    {
        $query = "SELECT COUNT(*) as count FROM bureau_jury_deliberation
                  WHERE president_id = :agentId AND est_actif = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    // Récupérer tous les bureaux de jury actifs
    public function getJurys($search = '', $activeOnly = true)
    {
        $query = "SELECT bj.*, 
                  p.noms as president_nom, 
                  s.noms as secretaire_nom,
                  COUNT(DISTINCT bjp.idpromotion) as nb_promotions
                  FROM bureau_jury_deliberation bj
                  LEFT JOIN agent p ON bj.president_id = p.\"idAgent\"
                  LEFT JOIN agent s ON bj.secretaire_id = s.\"idAgent\"
                  LEFT JOIN bureau_jury_promotion bjp ON bj.idbureau = bjp.idbureau";
        
        if ($activeOnly) {
            $query .= " WHERE bj.est_actif = 1";
        }
        
        if (!empty($search)) {
            $query .= $activeOnly ? " AND" : " WHERE";
            $query .= " (bj.designation LIKE :search OR bj.numero_decision LIKE :search)";
        }
        
        $query .= " GROUP BY bj.idbureau ORDER BY bj.date_creation DESC";
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les promotions associées à un bureau de jury
    public function getPromotionsByJury($bureauId)
    {
        $query = "SELECT p.*, o.\"designationOrientation\", s.\"designationSection\"
                  FROM promotion p
                  INNER JOIN bureau_jury_promotion bjp ON p.idpromotion = bjp.idpromotion
                  INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  INNER JOIN section s ON o.section_idsection = s.idsection
                  WHERE bjp.idbureau = :bureauId
                  ORDER BY p.designationPromotion";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les semestres d'une promotion
    public function getSemestresByPromotion($promotionId)
    {
        $query = "SELECT * FROM semestre
                  WHERE promotion_idpromotion = :promotionId
                  ORDER BY numeroSemestre";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les UE d'un semestre
    public function getUEsBySemestre($semestreId)
    {
        $query = "SELECT u.*, 
                  (SELECT SUM(nombre_credits) FROM credit_ue WHERE \"idUE\" = u.\"idUE\") as nombre_credits
                  FROM ue u
                  WHERE u.semestre_idsemestre = :semestreId
                  ORDER BY u.codeUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les étudiants d'une promotion pour une année académique
    public function getEtudiantsByPromotion($promotionId, $anneeId)
    {
        $query = "SELECT e.* FROM etudiant e
                  WHERE e.promotion_idpromotion = :promotionId
                  AND e.annee_acad_idannee_acad = :anneeId
                  ORDER BY e.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les notes d'un étudiant pour un ECUE
    public function getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId)
    {
        $query = "SELECT * FROM cotes_grille
                  WHERE matricule = :matricule
                  AND \"ECUE_idECUE\" = :ecueId
                  AND session_idsession = :sessionId
                  AND annee_acad_id = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Calculer la moyenne d'une UE pour un étudiant
    public function calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId)
    {
        // D'abord, vérifier si une moyenne existe déjà dans moyenne_ue
        $query = "SELECT e.\"idECUE\", e.\"designationECUE\", 
             ((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ") as credits,
             COALESCE(p.coefficient, 1) as coefficient,
             cg.MF as note
             FROM ecue e
             LEFT JOIN ponderation_ecue p ON e.\"idECUE\" = p.\"idECUE\" AND p.annee_acad_idannee_acad = :anneeId
             LEFT JOIN cotes_grille cg ON e.\"idECUE\" = cg.\"ECUE_idECUE\" 
                AND cg.matricule = :matricule 
                AND cg.session_idsession = :sessionId 
                AND cg.annee_acad_id = :anneeId
             WHERE e.\"UE_idUE\" = :ueId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['note'];
        }
        
        
        // Sinon, calculer la moyenne à partir des notes des ECUE
        $query = "SELECT e.\"idECUE\", e.\"designationECUE\", 
                 COALESCE(p.coefficient, 1) as coefficient,
                 cg.MF as note
                 FROM ecue e
                 LEFT JOIN ponderation_ecue p ON e.\"idECUE\" = p.\"idECUE\" AND p.annee_acad_idannee_acad = :anneeId
                 LEFT JOIN cotes_grille cg ON e.\"idECUE\" = cg.\"ECUE_idECUE\" 
                    AND cg.matricule = :matricule 
                    AND cg.session_idsession = :sessionId 
                    AND cg.annee_acad_id = :anneeId
                 WHERE e.\"UE_idUE\" = :ueId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ecues)) {
            return false;
        }
        
        $totalPoints = 0;
        $totalCoefficients = 0;
        $hasValidNotes = false;
        
        foreach ($ecues as $ecue) {
            if ($ecue['note'] !== null) {
                // Utiliser les crédits comme coefficient
                $totalPoints += $ecue['note'] * $ecue['credits'] * $ecue['coefficient'];
                $totalCoefficients += $ecue['credits'] * $ecue['coefficient'];
                $hasValidNotes = true;
            }
        }
        
        if (!$hasValidNotes || $totalCoefficients == 0) {
            return false;
        }
        
        return $totalPoints / $totalCoefficients;
    }

    // Calculer la moyenne d'un semestre pour un étudiant
    public function calculerMoyenneSemestre($matricule, $semestreId, $sessionId, $anneeId)
    {
        // D'abord, vérifier si une moyenne existe déjà dans moyenne_semestre
        $query = "SELECT moyenne_deliberee, moyenne_brute FROM moyenne_semestre
                  WHERE matricule = :matricule
                  AND idsemestre = :semestreId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Utiliser la moyenne délibérée si elle existe, sinon la moyenne brute
            return $result['moyenne_deliberee'] !== null ? $result['moyenne_deliberee'] : $result['moyenne_brute'];
        }
        
        // Sinon, calculer la moyenne à partir des moyennes des UE
        $query = "SELECT u.\"idUE\", u.\"designationUE\", 
                 cu.nombre_credits as credits,
                 mu.moyenne_deliberee, mu.moyenne_brute
                 FROM ue u
                 LEFT JOIN credit_ue cu ON u.\"idUE\" = cu.\"idUE\" AND cu.annee_acad_idannee_acad = :anneeId
                 LEFT JOIN moyenne_ue mu ON u.\"idUE\" = mu.\"idUE\" 
                    AND mu.matricule = :matricule 
                    AND mu.session_idsession = :sessionId 
                    AND mu.annee_acad_idannee_acad = :anneeId
                 WHERE u.semestre_idsemestre = :semestreId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ues)) {
            return false;
        }
        
        $totalPoints = 0;
        $totalCredits = 0;
        $hasValidNotes = false;
        
        foreach ($ues as $ue) {
            $moyenne = $ue['moyenne_deliberee'] !== null ? $ue['moyenne_deliberee'] : $ue['moyenne_brute'];
            $credits = $ue['credits'] !== null ? $ue['credits'] : 1; // Utiliser 1 comme valeur par défaut
            
            if ($moyenne !== null) {
                $totalPoints += $moyenne * $credits;
                $totalCredits += $credits;
                $hasValidNotes = true;
            }
        }
        
        if (!$hasValidNotes || $totalCredits == 0) {
            return false;
        }
        
        return $totalPoints / $totalCredits;
    }

    // Récupérer les crédits validés pour un semestre
    public function getCreditsValidesSemestre($matricule, $semestreId, $sessionId, $anneeId)
{
    // Récupérer les UE du semestre
    $query = "SELECT u.\"idUE\", u.\"designationUE\"
             FROM ue u
             WHERE u.semestre_idsemestre = :semestreId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
    $stmt->execute();
    
    $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalCredits = 0;
    $creditsValides = 0;
    
    foreach ($ues as $ue) {
        $ueId = $ue['idUE'];
        
        // Récupérer les ECUE de l'UE
        $query = "SELECT e.\"idECUE\", ((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ") as credits
                 FROM ecue e
                 WHERE e.\"UE_idUE\" = :ueId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->execute();
        
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ueCredits = 0;
        foreach ($ecues as $ecue) {
            $ueCredits += $ecue['credits'];
        }
        
        $totalCredits += $ueCredits;
        
        // Vérifier si l'UE est validée
        $moyenneUE = $this->calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId);
        if ($moyenneUE !== false && $moyenneUE >= 10) {
            $creditsValides += $ueCredits;
        }
    }
    
    $pourcentage = ($totalCredits > 0) ? ($creditsValides / $totalCredits) * 100 : 0;
    $seuilValidation = 50; // Pourcentage minimum pour valider un semestre
    
    return [
        'credits_valides' => $creditsValides,
        'credits_total' => $totalCredits,
        'pourcentage' => $pourcentage,
        'est_valide' => $pourcentage >= $seuilValidation
    ];
}


    // Récupérer les crédits validés pour une année (deux semestres)
    public function getCreditsValidesAnnee($matricule, $semestres, $sessionId, $anneeId)
    {
        $totalCredits = 0;
        $creditsValides = 0;
        
        foreach ($semestres as $semestre) {
            $semId = $semestre['idsemestre'];
            $creditsSemestre = $this->getCreditsValidesSemestre($matricule, $semId, $sessionId, $anneeId);
            
            $totalCredits += $creditsSemestre['credits_total'];
            $creditsValides += $creditsSemestre['credits_valides'];
        }
        
        $pourcentage = ($totalCredits > 0) ? ($creditsValides / $totalCredits) * 100 : 0;
        $seuilValidation = 50; // Pourcentage minimum pour valider une année
        
        return [
            'credits_valides' => $creditsValides,
            'credits_total' => $totalCredits,
            'pourcentage' => $pourcentage,
            'est_valide' => $pourcentage >= $seuilValidation
        ];
    }

    // Calculer la moyenne annuelle (deux semestres)
    public function calculerMoyenneAnnuelle($matricule, $semestres, $sessionId, $anneeId)
    {
        $totalPoints = 0;
        $totalCredits = 0;
        $hasValidNotes = false;
        
        foreach ($semestres as $semestre) {
            $semId = $semestre['idsemestre'];
            $moyenne = $this->calculerMoyenneSemestre($matricule, $semId, $sessionId, $anneeId);
            $credits = $this->getCreditsValidesSemestre($matricule, $semId, $sessionId, $anneeId);
            
            if ($moyenne !== false) {
                $totalPoints += $moyenne * $credits['credits_total'];
                $totalCredits += $credits['credits_total'];
                $hasValidNotes = true;
            }
        }
        
        if (!$hasValidNotes || $totalCredits == 0) {
            return false;
        }
        
        return $totalPoints / $totalCredits;
    }

    // Récupérer les statistiques de réussite par UE
    public function getStatistiquesReussiteUE($ueId, $sessionId, $anneeId)
    {
        $query = "SELECT 
                  COUNT(DISTINCT matricule) as total_etudiants,
                  SUM(CASE WHEN est_validee = 1 THEN 1 ELSE 0 END) as etudiants_reussis
                  FROM moyenne_ue
                  WHERE \"idUE\" = :ueId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['total_etudiants'] == 0) {
            return [
                'total_etudiants' => 0,
                'etudiants_reussis' => 0,
                'taux_reussite' => 0
            ];
        }
        
        return [
            'total_etudiants' => $result['total_etudiants'],
            'etudiants_reussis' => $result['etudiants_reussis'],
            'taux_reussite' => ($result['etudiants_reussis'] / $result['total_etudiants']) * 100
        ];
    }

    // Récupérer les statistiques globales pour une promotion
    public function getStatistiquesGlobales($promotionId, $sessionId, $anneeId, $semestreId = null)
    {
        if ($semestreId) {
            // Statistiques pour un semestre spécifique
            $query = "SELECT 
                      COUNT(DISTINCT ms.matricule) as total_etudiants,
                      SUM(CASE WHEN ms.est_valide = 1 THEN 1 ELSE 0 END) as etudiants_admis
                      FROM moyenne_semestre ms
                      INNER JOIN etudiant e ON ms.matricule = e.matricule
                      WHERE ms.idsemestre = :semestreId
                      AND ms.session_idsession = :sessionId
                      AND ms.annee_acad_idannee_acad = :anneeId
                      AND e.promotion_idpromotion = :promotionId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        } else {
            // Statistiques pour l'année complète
            $query = "SELECT 
                      COUNT(DISTINCT ma.matricule) as total_etudiants,
                      SUM(CASE WHEN ma.est_admis = 1 THEN 1 ELSE 0 END) as etudiants_admis
                      FROM moyenne_annuelle ma
                      INNER JOIN etudiant e ON ma.matricule = e.matricule
                      WHERE ma.idpromotion = :promotionId
                      AND ma.session_idsession = :sessionId
                      AND ma.annee_acad_idannee_acad = :anneeId";
            
            $stmt = $this->db->prepare($query);
        }
        
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['total_etudiants'] == 0) {
            return [
                'total_etudiants' => 0,
                'etudiants_admis' => 0,
                'etudiants_ajournes' => 0,
                'taux_reussite' => 0
            ];
        }
        
        return [
            'total_etudiants' => $result['total_etudiants'],
            'etudiants_admis' => $result['etudiants_admis'],
            'etudiants_ajournes' => $result['total_etudiants'] - $result['etudiants_admis'],
            'taux_reussite' => ($result['etudiants_admis'] / $result['total_etudiants']) * 100
        ];
    }

    // Exporter la grille de notes en Excel
    public function exportGrilleNotes($bureauId, $promotionId, $sessionId, $anneeId, $semestreId = null, $afficherDeuxSemestres = false)
    {
        // Cette méthode serait implémentée pour générer un fichier Excel
        // Elle utiliserait les mêmes données que celles affichées dans la grille
        // Mais retournerait un chemin vers le fichier Excel généré
    }

    // Récupérer les sessions d'évaluation
    public function getAllSessions()
    {
        $query = "SELECT * FROM session ORDER BY designSession";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les années académiques
    public function getAcademicYears()
    {
        $query = "SELECT * FROM annee_acad ORDER BY designation DESC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les délibérations pour un bureau de jury
    public function getDeliberationsByJury($bureauId)
    {
        $query = "SELECT d.*, p.\"designationPromotion\", s.\"designSession\", a.designation as annee_academique
                  FROM deliberation d
                  INNER JOIN promotion p ON d.idpromotion = p.idpromotion
                  INNER JOIN session s ON d.session_idsession = s.idsession
                  INNER JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
                  WHERE d.idbureau = :bureauId
                  ORDER BY d.date_deliberation DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Créer une nouvelle délibération
    public function createDeliberation($bureauId, $promotionId, $sessionId, $date, $commentaire, $userId)
    {
        $query = "INSERT INTO deliberation (idbureau, idpromotion, session_idsession, date_deliberation, commentaire, statut, \"idUser\")
                  VALUES (:bureauId, :promotionId, :sessionId, :date, :commentaire, 'En préparation', :userId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }

    // Mettre à jour le statut d'une délibération
    public function updateDeliberationStatus($deliberationId, $statut, $userId)
    {
        $query = "UPDATE deliberation 
                  SET statut = :statut, \"idUser\" = :userId
                  WHERE iddeliberation = :deliberationId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Enregistrer une intervention du jury sur une note
    public function saveJuryIntervention($deliberationId, $typeElement, $idElement, $matricule, $noteOriginale, $noteModifiee, $motif, $idAgent, $userId)
    {
        $query = "INSERT INTO intervention_jury (iddeliberation, type_element, id_element, matricule, note_originale, note_modifiee, motif, \"idAgent\", \"idUser\")
                  VALUES (:deliberationId, :typeElement, :idElement, :matricule, :noteOriginale, :noteModifiee, :motif, :idAgent, :userId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->bindParam(':typeElement', $typeElement);
        $stmt->bindParam(':idElement', $idElement, PDO::PARAM_INT);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':noteOriginale', $noteOriginale);
        $stmt->bindParam(':noteModifiee', $noteModifiee);
        $stmt->bindParam(':motif', $motif);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Enregistrer l'historique des modifications de notes
    public function saveNoteHistory($deliberationId, $matricule, $ecueId, $ueId, $sessionId, $noteAvant, $noteApres, $typeModification, $justification, $userId)
    {
        $query = "INSERT INTO historique_notes (iddeliberation, matricule, \"ECUE_idECUE\", \"UE_idUE\", session_idsession, note_avant, note_apres, type_modification, justification, \"idUser\")
                  VALUES (:deliberationId, :matricule, :ecueId, :ueId, :sessionId, :noteAvant, :noteApres, :typeModification, :justification, :userId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':noteAvant', $noteAvant);
        $stmt->bindParam(':noteApres', $noteApres);
        $stmt->bindParam(':typeModification', $typeModification);
        $stmt->bindParam(':justification', $justification);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Enregistrer les résultats finaux de délibération
    public function saveDeliberationResult($deliberationId, $matricule, $promotionId, $semestreId, $moyenneGenerale, $creditsAcquis, $creditsTotal, $decision, $commentaire, $userId, $estFinal = false)
    {
        $query = "INSERT INTO resultat_deliberation (iddeliberation, matricule, idpromotion, idsemestre, moyenne_generale, credits_acquis, credits_total, decision, commentaire, \"idUser\", est_final)
                  VALUES (:deliberationId, :matricule, :promotionId, :semestreId, :moyenneGenerale, :creditsAcquis, :creditsTotal, :decision, :commentaire, :userId, :estFinal)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->bindParam(':moyenneGenerale', $moyenneGenerale);
        $stmt->bindParam(':creditsAcquis', $creditsAcquis, PDO::PARAM_INT);
        $stmt->bindParam(':creditsTotal', $creditsTotal, PDO::PARAM_INT);
        $stmt->bindParam(':decision', $decision);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':estFinal', $estFinal, PDO::PARAM_BOOL);
        
        return $stmt->execute();
    }

    // Mettre à jour les moyennes UE après délibération
    public function updateUEMoyenne($matricule, $ueId, $sessionId, $anneeId, $moyenneDeliberee, $estValidee, $creditsObtenus, $typeValidation, $userId)
    {
        // Vérifier si une entrée existe déjà
        $query = "SELECT idmoyenne_ue FROM moyenne_ue
                  WHERE matricule = :matricule
                  AND \"idUE\" = :ueId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mettre à jour l'entrée existante
            $query = "UPDATE moyenne_ue
                      SET moyenne_deliberee = :moyenneDeliberee,
                          est_validee = :estValidee,
                          credits_obtenus = :creditsObtenus,
                          type_validation = :typeValidation,
                          \"idUser\" = :userId,
                          date_calcul = NOW()
                      WHERE idmoyenne_ue = :idMoyenneUE";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idMoyenneUE', $result['idmoyenne_ue'], PDO::PARAM_INT);
        } else {
            // Calculer d'abord la moyenne brute
            $moyenneBrute = $this->calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId);
            
            // Insérer une nouvelle entrée
            $query = "INSERT INTO moyenne_ue (\"idUE\", matricule, session_idsession, annee_acad_idannee_acad, moyenne_brute, moyenne_deliberee, est_validee, credits_obtenus, type_validation, \"idUser\")
                      VALUES (:ueId, :matricule, :sessionId, :anneeId, :moyenneBrute, :moyenneDeliberee, :estValidee, :creditsObtenus, :typeValidation, :userId)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':moyenneBrute', $moyenneBrute);
        }
        
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':moyenneDeliberee', $moyenneDeliberee);
        $stmt->bindParam(':estValidee', $estValidee, PDO::PARAM_BOOL);
        $stmt->bindParam(':creditsObtenus', $creditsObtenus, PDO::PARAM_INT);
        $stmt->bindParam(':typeValidation', $typeValidation);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Mettre à jour les moyennes de semestre après délibération
    public function updateSemestreMoyenne($matricule, $semestreId, $sessionId, $anneeId, $moyenneDeliberee, $estValide, $creditsObtenus, $creditsTotal, $userId)
    {
        // Vérifier si une entrée existe déjà
        $query = "SELECT idmoyenne_semestre FROM moyenne_semestre
                  WHERE matricule = :matricule
                  AND idsemestre = :semestreId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mettre à jour l'entrée existante
            $query = "UPDATE moyenne_semestre
                      SET moyenne_deliberee = :moyenneDeliberee,
                          est_valide = :estValide,
                          credits_obtenus = :creditsObtenus,
                          credits_total = :creditsTotal,
                          \"idUser\" = :userId,
                          date_calcul = NOW()
                      WHERE idmoyenne_semestre = :idMoyenneSemestre";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idMoyenneSemestre', $result['idmoyenne_semestre'], PDO::PARAM_INT);
        } else {
            // Calculer d'abord la moyenne brute
            $moyenneBrute = $this->calculerMoyenneSemestre($matricule, $semestreId, $sessionId, $anneeId);
            
            // Insérer une nouvelle entrée
            $query = "INSERT INTO moyenne_semestre (idsemestre, matricule, session_idsession, annee_acad_idannee_acad, moyenne_brute, moyenne_deliberee, est_valide, credits_obtenus, credits_total, \"idUser\")
                      VALUES (:semestreId, :matricule, :sessionId, :anneeId, :moyenneBrute, :moyenneDeliberee, :estValide, :creditsObtenus, :creditsTotal, :userId)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':moyenneBrute', $moyenneBrute);
        }
        
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':moyenneDeliberee', $moyenneDeliberee);
        $stmt->bindParam(':estValide', $estValide, PDO::PARAM_BOOL);
        $stmt->bindParam(':creditsObtenus', $creditsObtenus, PDO::PARAM_INT);
        $stmt->bindParam(':creditsTotal', $creditsTotal, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Mettre à jour les moyennes annuelles après délibération
    public function updateAnnuelleMoyenne($matricule, $promotionId, $sessionId, $anneeId, $moyenneDeliberee, $estAdmis, $creditsObtenus, $creditsTotal, $mention, $userId)
    {
        // Vérifier si une entrée existe déjà
        $query = "SELECT idmoyenne_annuelle FROM moyenne_annuelle
                  WHERE matricule = :matricule
                  AND idpromotion = :promotionId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mettre à jour l'entrée existante
            $query = "UPDATE moyenne_annuelle
                      SET moyenne_deliberee = :moyenneDeliberee,
                          est_admis = :estAdmis,
                          credits_obtenus = :creditsObtenus,
                          credits_total = :creditsTotal,
                          mention = :mention,
                          \"idUser\" = :userId,
                          date_calcul = NOW()
                      WHERE idmoyenne_annuelle = :idMoyenneAnnuelle";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idMoyenneAnnuelle', $result['idmoyenne_annuelle'], PDO::PARAM_INT);
        } else {
            // Récupérer les semestres de la promotion
            $semestres = $this->getSemestresByPromotion($promotionId);
            
            // Calculer d'abord la moyenne brute
            $moyenneBrute = $this->calculerMoyenneAnnuelle($matricule, $semestres, $sessionId, $anneeId);
            
            // Insérer une nouvelle entrée
            $query = "INSERT INTO moyenne_annuelle (idpromotion, matricule, session_idsession, annee_acad_idannee_acad, moyenne_brute, moyenne_deliberee, est_admis, credits_obtenus, credits_total, mention, \"idUser\")
                      VALUES (:promotionId, :matricule, :sessionId, :anneeId, :moyenneBrute, :moyenneDeliberee, :estAdmis, :creditsObtenus, :creditsTotal, :mention, :userId)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':moyenneBrute', $moyenneBrute);
        }
        
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':moyenneDeliberee', $moyenneDeliberee);
        $stmt->bindParam(':estAdmis', $estAdmis, PDO::PARAM_BOOL);
        $stmt->bindParam(':creditsObtenus', $creditsObtenus, PDO::PARAM_INT);
        $stmt->bindParam(':creditsTotal', $creditsTotal, PDO::PARAM_INT);
        $stmt->bindParam(':mention', $mention);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Ajouter une mention spéciale (félicitations, encouragements, etc.)
    public function addMentionSpeciale($typeMention, $matricule, $deliberationId, $commentaire, $userId)
    {
        $query = "INSERT INTO mention_speciale (type_mention, matricule, iddeliberation, commentaire, \"idUser\")
                  VALUES (:typeMention, :matricule, :deliberationId, :commentaire, :userId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':typeMention', $typeMention);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':deliberationId', $deliberationId, PDO::PARAM_INT);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    
    // Récupérer la configuration de délibération pour un bureau de jury
    public function getDeliberationConfig($bureauId, $sessionId, $anneeId)
    {
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
        
        if (!$result) {
            // Retourner une configuration par défaut
            return [
                'compensation_intra_ue' => 0,
                'seuil_compensation_intra_ue' => 8.00,
                'compensation_inter_ue' => 0,
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
    }

    // Sauvegarder la configuration de délibération
    public function saveDeliberationConfig($bureauId, $sessionId, $anneeId, $config, $userId)
    {
        // Vérifier si une configuration existe déjà
        $query = "SELECT idconfig FROM configuration_deliberation
                  WHERE idbureau = :bureauId
                  AND session_idsession = :sessionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mettre à jour la configuration existante
            $query = "UPDATE configuration_deliberation
                      SET compensation_intra_ue = :compensationIntraUE,
                          seuil_compensation_intra_ue = :seuilCompensationIntraUE,
                          compensation_inter_ue = :compensationInterUE,
                          seuil_compensation_inter_ue = :seuilCompensationInterUE,
                          exiger_meme_credit_ue = :exigerMemeCreditUE,
                          compensation_inter_semestre = :compensationInterSemestre,
                          seuil_compensation_inter_semestre = :seuilCompensationInterSemestre,
                          limiter_compensation_annee = :limiterCompensationAnnee,
                          note_passage = :notePassage,
                          pourcentage_passage_semestre = :pourcentagePassageSemestre,
                          \"idUser\" = :userId,
                          date_creation = NOW()
                      WHERE idconfig = :idConfig";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idConfig', $result['idconfig'], PDO::PARAM_INT);
        } else {
            // Insérer une nouvelle configuration
            $query = "INSERT INTO configuration_deliberation (
                          idbureau, session_idsession, annee_acad_idannee_acad,
                          compensation_intra_ue, seuil_compensation_intra_ue,
                          compensation_inter_ue, seuil_compensation_inter_ue,
                          exiger_meme_credit_ue, compensation_inter_semestre,
                          seuil_compensation_inter_semestre, limiter_compensation_annee,
                          note_passage, pourcentage_passage_semestre, \"idUser\"
                      ) VALUES (
                          :bureauId, :sessionId, :anneeId,
                          :compensationIntraUE, :seuilCompensationIntraUE,
                          :compensationInterUE, :seuilCompensationInterUE,
                          :exigerMemeCreditUE, :compensationInterSemestre,
                          :seuilCompensationInterSemestre, :limiterCompensationAnnee,
                          :notePassage, :pourcentagePassageSemestre, :userId
                      )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':bureauId', $bureauId, PDO::PARAM_INT);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':compensationIntraUE', $config['compensation_intra_ue'], PDO::PARAM_BOOL);
        $stmt->bindParam(':seuilCompensationIntraUE', $config['seuil_compensation_intra_ue']);
        $stmt->bindParam(':compensationInterUE', $config['compensation_inter_ue'], PDO::PARAM_BOOL);
        $stmt->bindParam(':seuilCompensationInterUE', $config['seuil_compensation_inter_ue']);
        $stmt->bindParam(':exigerMemeCreditUE', $config['exiger_meme_credit_ue'], PDO::PARAM_BOOL);
        $stmt->bindParam(':compensationInterSemestre', $config['compensation_inter_semestre'], PDO::PARAM_BOOL);
        $stmt->bindParam(':seuilCompensationInterSemestre', $config['seuil_compensation_inter_semestre']);
        $stmt->bindParam(':limiterCompensationAnnee', $config['limiter_compensation_annee'], PDO::PARAM_BOOL);
        $stmt->bindParam(':notePassage', $config['note_passage']);
        $stmt->bindParam(':pourcentagePassageSemestre', $config['pourcentage_passage_semestre']);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Récupérer les règles de passage pour une promotion
    public function getReglesPassage($promotionId, $anneeId)
    {
        $query = "SELECT * FROM regle_passage
                  WHERE idpromotion = :promotionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            // Retourner des règles par défaut
            return [
                'credits_min_passage' => 30,
                'nombre_ue_echec_max' => 2,
                'autoriser_dette' => 1,
                'max_dette_credits' => 16
            ];
        }
        
        return $result;
    }

    // Sauvegarder les règles de passage pour une promotion
    public function saveReglesPassage($promotionId, $anneeId, $regles, $userId)
    {
        // Vérifier si des règles existent déjà
        $query = "SELECT idregle FROM regle_passage
                  WHERE idpromotion = :promotionId
                  AND annee_acad_idannee_acad = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mettre à jour les règles existantes
            $query = "UPDATE regle_passage
                      SET credits_min_passage = :creditsMinPassage,
                          nombre_ue_echec_max = :nombreUEEchecMax,
                          autoriser_dette = :autoriserDette,
                          max_dette_credits = :maxDetteCredits,
                          \"idUser\" = :userId,
                          date_creation = NOW()
                      WHERE idregle = :idRegle";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idRegle', $result['idregle'], PDO::PARAM_INT);
        } else {
            // Insérer de nouvelles règles
            $query = "INSERT INTO regle_passage (
                          idpromotion, annee_acad_idannee_acad,
                          credits_min_passage, nombre_ue_echec_max,
                          autoriser_dette, max_dette_credits, \"idUser\"
                      ) VALUES (
                          :promotionId, :anneeId,
                          :creditsMinPassage, :nombreUEEchecMax,
                          :autoriserDette, :maxDetteCredits, :userId
                      )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':creditsMinPassage', $regles['credits_min_passage'], PDO::PARAM_INT);
        $stmt->bindParam(':nombreUEEchecMax', $regles['nombre_ue_echec_max'], PDO::PARAM_INT);
        $stmt->bindParam(':autoriserDette', $regles['autoriser_dette'], PDO::PARAM_BOOL);
        $stmt->bindParam(':maxDetteCredits', $regles['max_dette_credits'], PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }



        // Récupérer un bureau de jury par son ID
        public function getJuryById($idBureau)
        {
            $query = "SELECT b.*, 
                      p.noms as president_nom, 
                      s.noms as secretaire_nom,
                      a.designation as annee_academique
                      FROM bureau_jury_deliberation b
                      LEFT JOIN agent p ON b.president_id = p.\"idAgent\"
                      LEFT JOIN agent s ON b.secretaire_id = s.\"idAgent\"
                      LEFT JOIN annee_acad a ON b.annee_acad_idannee_acad = a.idannee_acad
                      WHERE b.idbureau = :idBureau";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idBureau', $idBureau, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    
        // Récupérer une promotion par son ID
        public function getPromotionById($idPromotion)
        {
            $query = "SELECT p.*, o.\"designationOrientation\", s.\"designationSection\", c.designation as annee_academique
                      FROM promotion p
                      LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      LEFT JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN annee_acad c ON p.annee_acad_idannee_acad = c.idannee_acad
                      WHERE p.idpromotion = :idPromotion";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idPromotion', $idPromotion, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    
        // Récupérer une session par son ID
        public function getSessionById($idSession)
        {
            $query = "SELECT * FROM session WHERE idsession = :idSession";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idSession', $idSession, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    
        // Récupérer une année académique par son ID
        public function getAnneeAcadById($idAnnee)
        {
            $query = "SELECT * FROM annee_acad WHERE idannee_acad = :idAnnee";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idAnnee', $idAnnee, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    
        // Récupérer les statistiques par UE
        public function getStatistiquesParUE($idPromotion, $idSession, $idAnnee, $semestres)
        {
            $ueStats = [];
            
            foreach ($semestres as $semestre) {
                $semId = $semestre['idsemestre'];
                
                // Récupérer les UE du semestre
                $ues = $this->getUEsBySemestre($semId);
                
                // Récupérer les étudiants de la promotion
                $etudiants = $this->getEtudiantsByPromotion($idPromotion, $idAnnee);
                
                foreach ($ues as $ue) {
                    $ueId = $ue['idUE'];
                    $ueLabel = $ue['designationUE'];
                    
                    $totalEtudiants = 0;
                    $etudiantsReussis = 0;
                    
                    foreach ($etudiants as $etudiant) {
                        $matricule = $etudiant['matricule'];
                        $totalEtudiants++;
                        
                        $moyenneUE = $this->calculerMoyenneUE($matricule, $ueId, $idSession, $idAnnee);
                        
                        if ($moyenneUE !== false && $moyenneUE >= 10) {
                            $etudiantsReussis++;
                        }
                    }
                    
                    $tauxReussite = ($totalEtudiants > 0) ? ($etudiantsReussis / $totalEtudiants) * 100 : 0;
                    
                    $ueStats[] = [
                        'label' => $ueLabel,
                        'taux' => $tauxReussite,
                        'reussis' => $etudiantsReussis,
                        'total' => $totalEtudiants
                    ];
                }
            }
            
            return $ueStats;
        }
    
       
/**
 * Récupère les étudiants éligibles pour la deuxième session
 * 
 * @param int $promotionId ID de la promotion
 * @param int $anneeId ID de l'année académique
 * @param array $semestres Liste des semestres à considérer
 * @return array Liste des étudiants éligibles
 */
public function getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestres) {
    try {
        // 1. Récupérer l'ID de la première session
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
        
        // 2. Récupérer les UE des semestres concernés
        $semestreIds = array_map(function($sem) {
            return $sem['idsemestre'];
        }, $semestres);
        
        $placeholders = str_repeat('?,', count($semestreIds) - 1) . '?';
        
        $query = "SELECT \"idUE\" FROM ue WHERE semestre_idsemestre IN ($placeholders)";
        $stmt = $this->db->prepare($query);
        
        foreach ($semestreIds as $index => $semId) {
            $stmt->bindValue($index + 1, $semId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $ues = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ues)) {
            return []; // Aucune UE trouvée
        }
        
        // 3. Récupérer tous les étudiants de la promotion
        $query = "SELECT e.idetudiant, e.matricule, e.noms
                  FROM etudiant e
                  WHERE e.promotion_idpromotion = ?
                  AND e.annee_acad_idannee_acad = ?
                  ORDER BY e.noms";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $promotionId, PDO::PARAM_INT);
        $stmt->bindValue(2, $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $allEtudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Pour chaque étudiant, vérifier s'il a validé toutes les UE
        $eligibleEtudiants = [];
        
        foreach ($allEtudiants as $etudiant) {
            $matricule = $etudiant['matricule'];
            $aValideToutes = true;
            
            foreach ($ues as $ueId) {
                // Récupérer les ECUE de cette UE
                $query = "SELECT \"idECUE\" FROM ecue WHERE \"UE_idUE\" = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(1, $ueId, PDO::PARAM_INT);
                $stmt->execute();
                $ecues = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($ecues)) {
                    continue; // Passer à l'UE suivante si pas d'ECUE
                }
                
                // Vérifier si l'étudiant a validé cette UE
                $placeholdersEcue = str_repeat('?,', count($ecues) - 1) . '?';
                
                // Compter les notes valides (MF non NULL) pour détecter les notes vides
                $query = "SELECT 
                            SUM(CASE WHEN cg.MF IS NOT NULL THEN cg.MF * ROUND((ec.CMI + ec.TD + ec.TP)/ " . $this->heuresParCredit . ", 2) ELSE 0 END) / 
                            NULLIF(SUM(CASE WHEN cg.MF IS NOT NULL THEN ROUND((ec.CMI + ec.TD + ec.TP)/ " . $this->heuresParCredit . ", 2) ELSE 0 END), 0) AS moyenne_ponderee,
                            SUM(CASE WHEN cg.MF IS NOT NULL THEN 1 ELSE 0 END) AS nb_notes_valides,
                            COUNT(DISTINCT ec.\"idECUE\") AS nb_ecues
                          FROM ecue ec
                          LEFT JOIN cotes_grille cg ON ec.\"idECUE\" = cg.\"ECUE_idECUE\" 
                                                    AND cg.matricule = ? 
                                                    AND cg.session_idsession = ? 
                                                    AND cg.annee_acad_id = ?
                          WHERE ec.\"idECUE\" IN ($placeholdersEcue)";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(1, $matricule, PDO::PARAM_STR);
                $stmt->bindValue(2, $session1Id, PDO::PARAM_INT);
                $stmt->bindValue(3, $anneeId, PDO::PARAM_INT);
                
                $paramIndex = 4;
                foreach ($ecues as $ecueId) {
                    $stmt->bindValue($paramIndex++, $ecueId, PDO::PARAM_INT);
                }
                
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Si l'étudiant a une moyenne >= 10 pour cette UE ET toutes les notes sont présentes, il l'a validée
                // Sinon, il ne l'a pas validée (moyenne < 10 ou pas de notes ou notes vides/incomplètes)
                $nbNotesValides = $result ? intval($result['nb_notes_valides']) : 0;
                $nbEcuesTotal = count($ecues);
                
                // RÈGLE PRINCIPALE: Si au moins une note est vide (MF NULL) ou manquante, 
                // l'étudiant est éligible à la 2ème session
                if ($nbNotesValides < $nbEcuesTotal) {
                    // Notes incomplètes ou vides - l'étudiant doit passer en 2ème session
                    $aValideToutes = false;
                    break;
                }
                
                // Si toutes les notes sont présentes mais moyenne < 10
                if ($result['moyenne_ponderee'] === null || $result['moyenne_ponderee'] < 10) {
                    $aValideToutes = false;
                    break;
                }
            }
            
            // Si l'étudiant n'a pas validé toutes les UE, il est éligible pour la 2ème session
            if (!$aValideToutes) {
                $eligibleEtudiants[] = $etudiant;
            }
        }
        
        return $eligibleEtudiants;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des étudiants éligibles pour la 2ème session: " . $e->getMessage());
        return [];
    }
    
}
    
    /**
     * Crée automatiquement les dettes pour un étudiant admis avec rachat
     * @param string $matricule Matricule de l'étudiant
     * @param int $promotionId ID de la promotion
     * @param int $sessionId ID de la session
     * @param int $anneeId ID de l'année académique
     * @param int $userId ID de l'utilisateur qui crée les dettes
     * @return bool Succès ou échec
     */
    public function creerDettesAutomatiques($matricule, $promotionId, $sessionId, $anneeId, $userId) {
        try {
            // Récupérer les UE non validées
            $query = "SELECT DISTINCT u.\"idUE\", u.\"codeUE\", u.\"designationUE\", s.idsemestre, s.\"numeroSemestre\",
                             mu.moyenne_brute, mu.credits_obtenus,
                             SUM((e.CMI + e.TD + e.TP) / :heuresParCredit) as credits_ue
                      FROM ue u
                      INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                      INNER JOIN ecue e ON e.\"UE_idUE\" = u.\"idUE\"
                      LEFT JOIN moyenne_ue mu ON mu.\"idUE\" = u.\"idUE\" 
                          AND mu.matricule = :matricule 
                          AND mu.session_idsession = :sessionId 
                          AND mu.annee_acad_idannee_acad = :anneeId
                      WHERE s.promotion_idpromotion = :promotionId
                      AND (mu.est_validee = 0 OR mu.est_validee IS NULL OR mu.moyenne_brute < 10 OR mu.moyenne_brute IS NULL)
                      GROUP BY u.\"idUE\", u.\"codeUE\", u.\"designationUE\", s.idsemestre, s.\"numeroSemestre\", mu.moyenne_brute, mu.credits_obtenus";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':heuresParCredit', $this->heuresParCredit);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            
            $uesNonValidees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($uesNonValidees as $ue) {
                // Vérifier si une dette existe déjà
                $checkQuery = "SELECT id_dette FROM dette_etudiant 
                               WHERE matricule = :matricule 
                               AND id_ue = :ueId 
                               AND annee_acad_id = :anneeId";
                
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $checkStmt->bindParam(':ueId', $ue['idUE'], PDO::PARAM_INT);
                $checkStmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if (!$checkStmt->fetch()) {
                    // Créer la dette
                    $insertQuery = "INSERT INTO dette_etudiant (
                                        matricule, id_ue, code_ue, designation_ue, credits_ue, 
                                        moyenne_obtenue, id_semestre, numero_semestre, 
                                        annee_acad_id, promotion_id, session_id, 
                                        statut_dette, date_creation, created_by
                                    ) VALUES (
                                        :matricule, :id_ue, :code_ue, :designation_ue, :credits_ue, 
                                        :moyenne_obtenue, :id_semestre, :numero_semestre, 
                                        :annee_acad_id, :promotion_id, :session_id, 
                                        'Non payée', NOW(), :created_by
                                    )";
                    
                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->execute([
                        ':matricule' => $matricule,
                        ':id_ue' => $ue['idUE'],
                        ':code_ue' => $ue['codeUE'],
                        ':designation_ue' => $ue['designationUE'],
                        ':credits_ue' => round($ue['credits_ue'], 2),
                        ':moyenne_obtenue' => $ue['moyenne_brute'] ?? null,
                        ':id_semestre' => $ue['idsemestre'],
                        ':numero_semestre' => $ue['numeroSemestre'],
                        ':annee_acad_id' => $anneeId,
                        ':promotion_id' => $promotionId,
                        ':session_id' => $sessionId,
                        ':created_by' => $userId
                    ]);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la création des dettes automatiques: " . $e->getMessage());
            return false;
        }
    }





/**
 * Récupère la moyenne d'une UE en première session pour un étudiant
 * 
 * @param string $matricule Matricule de l'étudiant
 * @param int $ueId ID de l'UE
 * @param int $anneeId ID de l'année académique
 * @return float|null Moyenne de l'UE ou null si pas de moyenne
 */
public function getMoyenneUEPremiereSession($matricule, $ueId, $anneeId) {
    try {
        // Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstSession) {
            return null;
        }
        
        $session1Id = $firstSession['idsession'];
        
        // Récupérer les ECUE de cette UE
        $query = "SELECT \"idECUE\", CMI, TD, TP FROM ecue WHERE \"UE_idUE\" = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $ueId, PDO::PARAM_INT);
        $stmt->execute();
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ecues)) {
            return null;
        }
        
        // Calculer la moyenne pondérée des notes de ces ECUE
        $totalPoints = 0;
        $totalCoeff = 0;
        
        foreach ($ecues as $ecue) {
            $ecueId = $ecue['idECUE'];
            $coeff = ($ecue['CMI'] + $ecue['TD'] + $ecue['TP'])/ $this->heuresParCredit;
            
            // Récupérer la note de cet ECUE
            $query = "SELECT MF FROM cotes_grille 
                      WHERE \"ECUE_idECUE\" = ? 
                      AND matricule = ? 
                      AND session_idsession = ? 
                      AND annee_acad_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $ecueId, PDO::PARAM_INT);
            $stmt->bindValue(2, $matricule, PDO::PARAM_STR);
            $stmt->bindValue(3, $session1Id, PDO::PARAM_INT);
            $stmt->bindValue(4, $anneeId, PDO::PARAM_INT);
            $stmt->execute();
            $note = $stmt->fetchColumn();
            
            if ($note !== false) {
                $totalPoints += $note * $coeff;
                $totalCoeff += $coeff;
            }
        }
        
        if ($totalCoeff > 0) {
            return $totalPoints / $totalCoeff;
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la moyenne UE en première session: " . $e->getMessage());
        return null;
    }
}
    
    /**
     * Supprime les dettes non validées d'un étudiant pour une année donnée
     * @param string $matricule Matricule de l'étudiant
     * @param int $anneeId ID de l'année académique
     * @param int $promotionId ID de la promotion
     * @return int Nombre de dettes supprimées
     */
    public function supprimerDettesNonValidees($matricule, $anneeId, $promotionId) {
        try {
            $query = "DELETE FROM dette_etudiant 
                      WHERE matricule = :matricule 
                      AND annee_acad_id = :anneeId 
                      AND promotion_id = :promotionId
                      AND (statut_dette = 'Non payée' OR statut_validation IS NULL OR statut_validation != 'Validée')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression des dettes: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Enregistre l'historique des changements de décision
     * @param string $matricule Matricule de l'étudiant
     * @param string $ancienneDecision Ancienne décision
     * @param string $nouvelleDecision Nouvelle décision
     * @param int $promotionId ID de la promotion
     * @param int $sessionId ID de la session
     * @param int $anneeId ID de l'année académique
     * @param int $userId ID de l'utilisateur
     * @return bool Succès ou échec
     */
    public function enregistrerChangementDecision($matricule, $ancienneDecision, $nouvelleDecision, $promotionId, $sessionId, $anneeId, $userId) {
        try {
            // Créer la table si elle n'existe pas
            $createTableQuery = "CREATE TABLE IF NOT EXISTS historique_changement_decision (
                id_historique INT AUTO_INCREMENT PRIMARY KEY,
                matricule VARCHAR(255) NOT NULL,
                ancienne_decision VARCHAR(50),
                nouvelle_decision VARCHAR(50),
                promotion_id INT,
                session_id INT,
                annee_acad_id INT,
                date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INT,
                INDEX idx_matricule (matricule),
                INDEX idx_annee (annee_acad_id)
            )";
            $this->db->exec($createTableQuery);
            
            $query = "INSERT INTO historique_changement_decision (
                        matricule, ancienne_decision, nouvelle_decision, 
                        promotion_id, session_id, annee_acad_id, 
                        date_changement, created_by
                      ) VALUES (
                        :matricule, :ancienneDecision, :nouvelleDecision, 
                        :promotionId, :sessionId, :anneeId, 
                        NOW(), :userId
                      )";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':matricule' => $matricule,
                ':ancienneDecision' => $ancienneDecision,
                ':nouvelleDecision' => $nouvelleDecision,
                ':promotionId' => $promotionId,
                ':sessionId' => $sessionId,
                ':anneeId' => $anneeId,
                ':userId' => $userId
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement du changement de décision: " . $e->getMessage());
            return false;
        }
    }


/**
 * Récupère les notes d'un ECUE en première session pour un étudiant
 * 
 * @param string $matricule Matricule de l'étudiant
 * @param int $ecueId ID de l'ECUE
 * @param int $anneeId ID de l'année académique
 * @return array|null Notes de l'ECUE ou null si pas de notes
 */
public function getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId) {
    try {
        // Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstSession) {
            return null;
        }
        
        $session1Id = $firstSession['idsession'];
        
        // Récupérer les notes
        $query = "SELECT CC, EX, MF FROM cotes_grille 
                  WHERE \"ECUE_idECUE\" = ? 
                  AND matricule = ? 
                  AND session_idsession = ? 
                  AND annee_acad_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $ecueId, PDO::PARAM_INT);
        $stmt->bindValue(2, $matricule, PDO::PARAM_STR);
        $stmt->bindValue(3, $session1Id, PDO::PARAM_INT);
        $stmt->bindValue(4, $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes ECUE en première session: " . $e->getMessage());
        return null;
    }
}


public function initializeProcess($deliberationId, $userId) {
    try {
        // Mettre à jour le statut de la délibération
        $query = "UPDATE deliberation SET statut = 'En préparation' WHERE iddeliberation = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $deliberationId);
        $stmt->execute();
        
        // Créer une entrée dans la table processus_deliberation
        $query = "INSERT INTO processus_deliberation (iddeliberation, etape, statut, message, progression, date_debut, \"idUser\") 
                  VALUES (:iddeliberation, 'Initialisation', 'En cours', 'Initialisation du processus de délibération', 0, NOW(), :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':iddeliberation', $deliberationId);
        $stmt->bindParam(':idUser', $userId);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'initialisation du processus de délibération: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour l'état du processus de délibération
 * @param int $processId ID du processus
 * @param string $etape Étape actuelle
 * @param string $statut Statut de l'étape
 * @param string $message Message d'information
 * @param int $progression Pourcentage de progression
 * @return bool Succès ou échec
 */
public function updateProcessStatus($processId, $etape, $statut, $message, $progression) {
    try {
        $query = "UPDATE processus_deliberation 
                  SET etape = :etape, statut = :statut, message = :message, progression = :progression 
                  WHERE idprocessus = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etape', $etape);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':progression', $progression);
        $stmt->bindParam(':id', $processId);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du processus: " . $e->getMessage());
        return false;
    }
}

/**
 * Finalise une étape du processus
 * @param int $processId ID du processus
 * @param string $etape Étape terminée
 * @return bool Succès ou échec
 */
public function finalizeProcessStep($processId, $etape) {
    try {
        $query = "UPDATE processus_deliberation 
                  SET statut = 'Terminé', date_fin = NOW(), progression = 100 
                  WHERE idprocessus = :id AND etape = :etape";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $processId);
        $stmt->bindParam(':etape', $etape);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la finalisation de l'étape: " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère les informations d'une délibération
 * @param int $deliberationId ID de la délibération
 * @return array|bool Informations ou false en cas d'erreur
 */
public function getDeliberationInfo($deliberationId) {
    try {
        $query = "SELECT d.*, p.\"designationPromotion\", s.\"designSession\", a.designation as annee_acad, 
                  b.designation as bureau_designation, u.\"nomUser\" as nom_createur
                  FROM deliberation d
                  JOIN promotion p ON d.idpromotion = p.idpromotion
                  JOIN session s ON d.session_idsession = s.idsession
                  JOIN annee_acad a ON d.annee_acad_id = a.idannee_acad
                  JOIN bureau_jury_deliberation b ON d.idbureau = b.idbureau
                  JOIN t_users u ON d.\"idUser\" = u.\"idUser\"
                  WHERE d.iddeliberation = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $deliberationId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des informations de délibération: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les UE d'un semestre
 * @param int $semestreId ID du semestre
 * @return array Liste des UE
 */
public function getUEBySemestre($semestreId) {
    try {
        $query = "SELECT u.*, c.nombre_credits 
                  FROM ue u
                  LEFT JOIN credit_ue c ON u.\"idUE\" = c.\"idUE\"
                  WHERE u.semestre_idsemestre = :semestre_id
                  ORDER BY u.codeUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semestre_id', $semestreId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des UE: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les ECUE d'une UE
 * @param int $ueId ID de l'UE
 * @return array Liste des ECUE
 */
public function getECUEByUE($ueId) {
    try {
        $query = "SELECT e.*, p.coefficient 
                  FROM ecue e
                  LEFT JOIN ponderation_ecue p ON e.\"idECUE\" = p.\"idECUE\"
                  WHERE e.\"UE_idUE\" = :ue_id
                  ORDER BY e.designationECUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ue_id', $ueId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des ECUE: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les notes d'un ECUE pour tous les étudiants
 * @param int $ecueId ID de l'ECUE
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return array Liste des notes
 */
public function getNotesByECUE($ecueId, $sessionId, $anneeId) {
    try {
        $query = "SELECT * FROM cotes_grille 
                  WHERE \"ECUE_idECUE\" = :ecue_id 
                  AND session_idsession = :session_id 
                  AND annee_acad_id = :annee_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ecue_id', $ecueId);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->bindParam(':annee_id', $anneeId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes: " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifie si une UE est validée pour un étudiant
 * @param int $ueId ID de l'UE
 * @param string $matricule Matricule de l'étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return bool True si l'UE est validée, false sinon
 */
public function isUEValidated($ueId, $matricule, $sessionId, $anneeId) {
    try {
        $query = "SELECT est_validee FROM moyenne_ue 
                  WHERE \"idUE\" = :ue_id 
                  AND matricule = :matricule 
                  AND session_idsession = :session_id 
                  AND annee_acad_idannee_acad = :annee_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ue_id', $ueId);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->bindParam(':annee_id', $anneeId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['est_validee'] == 1;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de validation de l'UE: " . $e->getMessage());
        return false;
    }
}

/**
 * Exécute l'étape de compensation intra-UE
 * @param int $processId ID du processus
 * @param int $deliberationId ID de la délibération
 * @param int $semestreId ID du semestre (0 pour tous les semestres)
 * @param array $config Configuration de délibération
 * @param int $userId ID de l'utilisateur
 * @return bool Succès ou échec
 */
public function executeIntraUECompensation($processId, $deliberationId, $semestreId, $config, $userId) {
    try {
        // Récupérer les informations de la délibération
        $deliberationInfo = $this->getDeliberationInfo($deliberationId);
        if (!$deliberationInfo) {
            $this->updateProcessStatus($processId, 'Compensation intra-UE', 'Erreur', 'Impossible de récupérer les informations de la délibération', 0);
            return false;
        }
        
        $sessionId = $deliberationInfo['session_idsession'];
        $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
        $promotionId = $deliberationInfo['idpromotion'];
        
        // Mettre à jour le statut du processus
        $this->updateProcessStatus($processId, 'Compensation intra-UE', 'En cours', 'Récupération des UE...', 10);
        
        // Récupérer les semestres à traiter
        $semestres = [];
        if ($semestreId > 0) {
            // Traiter un seul semestre
            $semestres[] = $semestreId;
        } else {
            // Traiter tous les semestres de la promotion
            $query = "SELECT idsemestre FROM semestre WHERE promotion_idpromotion = :promotion_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotion_id', $promotionId);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $semestres[] = $row['idsemestre'];
            }
        }
        
        if (empty($semestres)) {
            $this->updateProcessStatus($processId, 'Compensation intra-UE', 'Erreur', 'Aucun semestre trouvé pour cette promotion', 0);
            return false;
        }
        
        // Compteurs pour le suivi
        $totalUE = 0;
        $processedUE = 0;
        $compensatedECUE = 0;
        
        // Pour chaque semestre
        foreach ($semestres as $currentSemestreId) {
            // Récupérer les UE du semestre
            $ues = $this->getUEBySemestre($currentSemestreId);
            $totalUE += count($ues);
            
            // Pour chaque UE
            foreach ($ues as $ue) {
                $ueId = $ue['idUE'];
                $processedUE++;
                $progression = intval(($processedUE / $totalUE) * 90) + 10; // 10% à 100%
                
                $this->updateProcessStatus(
                    $processId, 
                    'Compensation intra-UE', 
                    'En cours', 
                    "Traitement de l'UE {$ue['designationUE']} ($processedUE/$totalUE)", 
                    $progression
                );
                
                // Récupérer les ECUE de l'UE
                $ecues = $this->getECUEByUE($ueId);
                
                if (empty($ecues)) {
                    continue; // Passer à l'UE suivante si pas d'ECUE
                }
                
                // Récupérer les étudiants ayant des notes pour cette UE
                $query = "SELECT DISTINCT cg.matricule 
                          FROM cotes_grille cg
                          JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                          WHERE e.\"UE_idUE\" = :ue_id
                          AND cg.session_idsession = :session_id
                          AND cg.annee_acad_id = :annee_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':ue_id', $ueId);
                $stmt->bindParam(':session_id', $sessionId);
                $stmt->bindParam(':annee_id', $anneeId);
                $stmt->execute();
                
                $etudiants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Pour chaque étudiant
                foreach ($etudiants as $matricule) {
                    // Vérifier si l'UE est déjà validée
                    if ($this->isUEValidated($ueId, $matricule, $sessionId, $anneeId)) {
                        continue; // Passer à l'étudiant suivant si l'UE est déjà validée
                    }
                    
                    // Récupérer les notes de l'étudiant pour tous les ECUE de l'UE
                    $notesEtudiant = [];
                    $totalCoefficients = 0;
                    $moyenneUE = 0;
                    
                    foreach ($ecues as $ecue) {
                        $ecueId = $ecue['idECUE'];
                        $coefficient = $ecue['coefficient'] ?? 1;
                        $totalCoefficients += $coefficient;
                        
                        $query = "SELECT * FROM cotes_grille 
                                  WHERE \"ECUE_idECUE\" = :ecue_id 
                                  AND matricule = :matricule 
                                  AND session_idsession = :session_id 
                                  AND annee_acad_id = :annee_id";
                        $stmt = $this->db->prepare($query);
                        $stmt->bindParam(':ecue_id', $ecueId);
                        $stmt->bindParam(':matricule', $matricule);
                        $stmt->bindParam(':session_id', $sessionId);
                        $stmt->bindParam(':annee_id', $anneeId);
                        $stmt->execute();
                        
                        $note = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($note) {
                            $notesEtudiant[$ecueId] = [
                                'note' => $note['MF'],
                                'coefficient' => $coefficient,
                                'ecue' => $ecue
                            ];
                            
                            $moyenneUE += $note['MF'] * $coefficient;
                        }
                    }
                }
                        
                // Calculer la moyenne de l'UE
                if ($totalCoefficients > 0) {
                    $moyenneUE = $moyenneUE / $totalCoefficients;
                }
                
                // Vérifier si la compensation intra-UE est possible
                if ($config['compensation_intra_ue'] && $moyenneUE >= $config['note_passage']) {
                    // Identifier les ECUE en échec mais compensables
                    $ecuesEnEchec = [];
                    $ecuesExcedentaires = [];
                    
                    foreach ($notesEtudiant as $ecueId => $infoNote) {
                        $note = $infoNote['note'];
                        
                        if ($note < $config['note_passage'] && $note >= $config['seuil_compensation_intra_ue']) {
                            // ECUE en échec mais compensable
                            $ecuesEnEchec[$ecueId] = [
                                'deficit' => $config['note_passage'] - $note,
                                'info' => $infoNote
                            ];
                        } elseif ($note > $config['note_passage']) {
                            // ECUE avec excédent de points
                            $ecuesExcedentaires[$ecueId] = [
                                'excedent' => $note - $config['note_passage'],
                                'info' => $infoNote
                            ];
                        }
                    }
                    
                    // Si des ECUE sont en échec mais compensables et qu'il y a des ECUE excédentaires
                    if (!empty($ecuesEnEchec) && !empty($ecuesExcedentaires)) {
                        // Calculer le total des déficits et des excédents pondérés
                        $totalDeficitPondere = 0;
                        foreach ($ecuesEnEchec as $ecueId => $info) {
                            $totalDeficitPondere += $info['deficit'] * $info['info']['coefficient'];
                        }
                        
                        $totalExcedentPondere = 0;
                        foreach ($ecuesExcedentaires as $ecueId => $info) {
                            $totalExcedentPondere += $info['excedent'] * $info['info']['coefficient'];
                        }
                        
                        // Vérifier si l'excédent est suffisant pour compenser le déficit
                        if ($totalExcedentPondere >= $totalDeficitPondere) {
                            // Appliquer la compensation
                            foreach ($ecuesEnEchec as $ecueId => $infoEchec) {
                                $noteAvant = $infoEchec['info']['note'];
                                $noteApres = $config['note_passage']; // On monte la note au seuil de passage
                                
                                // Mettre à jour la note dans la base de données
                                $query = "UPDATE cotes_grille 
                                          SET MF = :note_apres 
                                          WHERE \"ECUE_idECUE\" = :ecue_id 
                                          AND matricule = :matricule 
                                          AND session_idsession = :session_id 
                                          AND annee_acad_id = :annee_id";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':note_apres', $noteApres);
                                $stmt->bindParam(':ecue_id', $ecueId);
                                $stmt->bindParam(':matricule', $matricule);
                                $stmt->bindParam(':session_id', $sessionId);
                                $stmt->bindParam(':annee_id', $anneeId);
                                $stmt->execute();
                                
                                // Enregistrer l'historique de la modification
                                $query = "INSERT INTO historique_notes 
                                          (iddeliberation, matricule, \"ECUE_idECUE\", \"UE_idUE\", session_idsession, 
                                           note_avant, note_apres, type_modification, justification, \"idUser\") 
                                          VALUES 
                                          (:iddeliberation, :matricule, :ecue_id, :ue_id, :session_id, 
                                           :note_avant, :note_apres, 'Compensation intra-UE', 
                                           'Compensation automatique intra-UE', :idUser)";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':iddeliberation', $deliberationId);
                                $stmt->bindParam(':matricule', $matricule);
                                $stmt->bindParam(':ecue_id', $ecueId);
                                $stmt->bindParam(':ue_id', $ueId);
                                $stmt->bindParam(':session_id', $sessionId);
                                $stmt->bindParam(':note_avant', $noteAvant);
                                $stmt->bindParam(':note_apres', $noteApres);
                                $stmt->bindParam(':idUser', $userId);
                                $stmt->execute();
                                
                                $compensatedECUE++;
                            }
                            
                            // Réduire les notes des ECUE excédentaires proportionnellement
                            // pour maintenir la même moyenne d'UE
                            $ratioReduction = $totalDeficitPondere / $totalExcedentPondere;
                            
                            foreach ($ecuesExcedentaires as $ecueId => $infoExcedent) {
                                $noteAvant = $infoExcedent['info']['note'];
                                $reduction = $infoExcedent['excedent'] * $ratioReduction;
                                $noteApres = $noteAvant - $reduction;
                                
                                // Mettre à jour la note dans la base de données
                                $query = "UPDATE cotes_grille 
                                          SET MF = :note_apres 
                                          WHERE \"ECUE_idECUE\" = :ecue_id 
                                          AND matricule = :matricule 
                                          AND session_idsession = :session_id 
                                          AND annee_acad_id = :annee_id";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':note_apres', $noteApres);
                                $stmt->bindParam(':ecue_id', $ecueId);
                                $stmt->bindParam(':matricule', $matricule);
                                $stmt->bindParam(':session_id', $sessionId);
                                $stmt->bindParam(':annee_id', $anneeId);
                                $stmt->execute();
                                
                                // Enregistrer l'historique de la modification
                                $query = "INSERT INTO historique_notes 
                                          (iddeliberation, matricule, \"ECUE_idECUE\", \"UE_idUE\", session_idsession, 
                                           note_avant, note_apres, type_modification, justification, \"idUser\") 
                                          VALUES 
                                          (:iddeliberation, :matricule, :ecue_id, :ue_id, :session_id, 
                                           :note_avant, :note_apres, 'Compensation intra-UE', 
                                           'Ajustement pour maintenir la moyenne de l''UE', :idUser)";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':iddeliberation', $deliberationId);
                                $stmt->bindParam(':matricule', $matricule);
                                $stmt->bindParam(':ecue_id', $ecueId);
                                $stmt->bindParam(':ue_id', $ueId);
                                $stmt->bindParam(':session_id', $sessionId);
                                $stmt->bindParam(':note_avant', $noteAvant);
                                $stmt->bindParam(':note_apres', $noteApres);
                                $stmt->bindParam(':idUser', $userId);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
        }
    
    
    // Finaliser l'étape
    $message = "Compensation intra-UE terminée. $compensatedECUE ECUE ont été compensés.";
    $this->updateProcessStatus($processId, 'Compensation intra-UE', 'Terminé', $message, 100);
    $this->finalizeProcessStep($processId, 'Compensation intra-UE');
    
    return true;
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la compensation intra-UE: " . $e->getMessage();
    error_log($errorMessage);
    $this->updateProcessStatus($processId, 'Compensation intra-UE', 'Erreur', $errorMessage, 0);
    return false;
}
}



public function executeDeliberation($processId, $deliberationId, $typeDeliberation, $semestreId, $etapes, $userId) {
    try {
        // Récupérer les informations de la délibération
        $deliberationInfo = $this->getDeliberationInfo($deliberationId);
        if (!$deliberationInfo) {
            $this->updateProcessStatus($processId, 'Initialisation', 'Erreur', 'Impossible de récupérer les informations de la délibération', 0);
            return false;
        }
        
        $bureauId = $deliberationInfo['idbureau'];
        $sessionId = $deliberationInfo['session_idsession'];
        $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
        
        // Récupérer la configuration de délibération
        $config = $this->getDeliberationConfig($bureauId, $sessionId, $anneeId);
        if (!$config) {
            $this->updateProcessStatus($processId, 'Initialisation', 'Erreur', 'Impossible de récupérer la configuration de délibération', 0);
            return false;
        }
        
        // Finaliser l'initialisation
        $this->finalizeProcessStep($processId, 'Initialisation');
        
        // Exécuter les étapes demandées
        if (in_array('intra_ue', $etapes) && $config['compensation_intra_ue']) {
            $this->executeIntraUECompensation($processId, $deliberationId, $semestreId, $config, $userId);
        }
        
        // Autres étapes à implémenter...
        
        // Mettre à jour le statut de la délibération
        $query = "UPDATE deliberation SET statut = 'Effectuée' WHERE iddeliberation = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $deliberationId);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de l'exécution de la délibération: " . $e->getMessage();
        error_log($errorMessage);
        return false;
    }
}




    /**
     * Récupère les informations d'un processus de délibération
     * @param int $processId ID du processus
     * @return array|bool Informations ou false en cas d'erreur
     */
    public function getProcessInfo($processId) {
        try {
            $query = "SELECT * FROM processus_deliberation WHERE idprocessus = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $processId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des informations du processus: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère l'historique des étapes d'un processus de délibération
     * @param int $deliberationId ID de la délibération
     * @return array Historique des étapes
     */
    public function getProcessHistory($deliberationId) {
        try {
            $query = "SELECT * FROM processus_deliberation 
                      WHERE iddeliberation = :id 
                      ORDER BY date_debut DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $deliberationId);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'historique du processus: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Redémarre un processus de délibération en erreur
     * @param int $processId ID du processus
     * @param int $deliberationId ID de la délibération
     * @param int $userId ID de l'utilisateur
     * @return bool Succès ou échec
     */
    public function restartProcess($processId, $deliberationId, $userId) {
        try {
            // Récupérer les informations du processus
            $processInfo = $this->getProcessInfo($processId);
            if (!$processInfo) {
                return false;
            }
            
            // Vérifier si le processus est en erreur
            if ($processInfo['statut'] !== 'Erreur') {
                return false;
            }
            
            // Mettre à jour le statut du processus
            $query = "UPDATE processus_deliberation 
                      SET statut = 'En cours', message = 'Reprise du processus après erreur', 
                          progression = 0, date_debut = NOW(), date_fin = NULL 
                      WHERE idprocessus = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $processId);
            $stmt->execute();
            
            // Récupérer les informations de la délibération
            $deliberationInfo = $this->getDeliberationInfo($deliberationId);
            if (!$deliberationInfo) {
                return false;
            }
            
            // Récupérer la configuration de délibération
            $config = $this->getDeliberationConfig(
                $deliberationInfo['idbureau'], 
                $deliberationInfo['session_idsession'], 
                $deliberationInfo['annee_acad_idannee_acad']
            );
            
            if (!$config) {
                return false;
            }
            
            // Relancer l'étape en erreur
            switch ($processInfo['etape']) {
                case 'Compensation intra-UE':
                    return $this->executeIntraUECompensation($processId, $deliberationId, 0, $config, $userId);
                // Ajouter d'autres étapes au besoin
                default:
                    return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du redémarrage du processus: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcule les moyennes des UE pour tous les étudiants
     * @param int $processId ID du processus
     * @param int $deliberationId ID de la délibération
     * @param int $semestreId ID du semestre (0 pour tous les semestres)
     * @param array $config Configuration de délibération
     * @param int $userId ID de l'utilisateur
     * @return bool Succès ou échec
     */
    public function calculateUEAverages($processId, $deliberationId, $semestreId, $config, $userId) {
        try {
            // Récupérer les informations de la délibération
            $deliberationInfo = $this->getDeliberationInfo($deliberationId);
            if (!$deliberationInfo) {
                $this->updateProcessStatus($processId, 'Calcul UE', 'Erreur', 'Impossible de récupérer les informations de la délibération', 0);
                return false;
            }
            
            $sessionId = $deliberationInfo['session_idsession'];
            $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
            $promotionId = $deliberationInfo['idpromotion'];
            
            // Mettre à jour le statut du processus
            $this->updateProcessStatus($processId, 'Calcul UE', 'En cours', 'Récupération des UE...', 10);
            
            // Récupérer les semestres à traiter
            $semestres = [];
            if ($semestreId > 0) {
                // Traiter un seul semestre
                $semestres[] = $semestreId;
            } else {
                // Traiter tous les semestres de la promotion
                $query = "SELECT idsemestre FROM semestre WHERE promotion_idpromotion = :promotion_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':promotion_id', $promotionId);
                $stmt->execute();
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $semestres[] = $row['idsemestre'];
                }
            }
            
            if (empty($semestres)) {
                $this->updateProcessStatus($processId, 'Calcul UE', 'Erreur', 'Aucun semestre trouvé pour cette promotion', 0);
                return false;
            }
            
            // Compteurs pour le suivi
            $totalUE = 0;
            $processedUE = 0;
            
            // Pour chaque semestre
            foreach ($semestres as $currentSemestreId) {
                // Récupérer les UE du semestre
                $ues = $this->getUEBySemestre($currentSemestreId);
                $totalUE += count($ues);
                
                // Pour chaque UE
                foreach ($ues as $ue) {
                    $ueId = $ue['idUE'];
                    $processedUE++;
                    $progression = intval(($processedUE / $totalUE) * 90) + 10; // 10% à 100%
                    
                    $this->updateProcessStatus(
                        $processId, 
                        'Calcul UE', 
                        'En cours', 
                        "Calcul des moyennes pour l'UE {$ue['designationUE']} ($processedUE/$totalUE)", 
                        $progression
                    );
                    
                    // Récupérer les ECUE de l'UE
                    $ecues = $this->getECUEByUE($ueId);
                    
                    if (empty($ecues)) {
                        continue; // Passer à l'UE suivante si pas d'ECUE
                    }
                    
                    // Récupérer les étudiants ayant des notes pour cette UE
                    $query = "SELECT DISTINCT cg.matricule 
                              FROM cotes_grille cg
                              JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                              WHERE e.\"UE_idUE\" = :ue_id
                              AND cg.session_idsession = :session_id
                              AND cg.annee_acad_id = :annee_id";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':ue_id', $ueId);
                    $stmt->bindParam(':session_id', $sessionId);
                    $stmt->bindParam(':annee_id', $anneeId);
                    $stmt->execute();
                    
                    $etudiants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Pour chaque étudiant
                    foreach ($etudiants as $matricule) {
                        // Calculer la moyenne de l'UE
                        $notesEtudiant = [];
                        $totalCoefficients = 0;
                        $moyenneUE = 0;
                        $tousECUEValides = true;
                        
                        foreach ($ecues as $ecue) {
                            $ecueId = $ecue['idECUE'];
                            $coefficient = $ecue['coefficient'] ?? 1;
                            $totalCoefficients += $coefficient;
                            
                            $query = "SELECT * FROM cotes_grille 
                                      WHERE \"ECUE_idECUE\" = :ecue_id 
                                      AND matricule = :matricule 
                                      AND session_idsession = :session_id 
                                      AND annee_acad_id = :annee_id";
                            $stmt = $this->db->prepare($query);
                            $stmt->bindParam(':ecue_id', $ecueId);
                            $stmt->bindParam(':matricule', $matricule);
                            $stmt->bindParam(':session_id', $sessionId);
                            $stmt->bindParam(':annee_id', $anneeId);
                            $stmt->execute();
                            
                            $note = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($note) {
                                $notesEtudiant[$ecueId] = [
                                    'note' => $note['MF'],
                                    'coefficient' => $coefficient
                                ];
                                
                                $moyenneUE += $note['MF'] * $coefficient;
                                
                                // Vérifier si l'ECUE est validé
                                if ($note['MF'] < $config['note_passage']) {
                                    $tousECUEValides = false;
                                }
                            } else if (!$config['calculer_moyenne_avec_notes_vides']) {
                                // Si une note est manquante et qu'on ne calcule pas avec des notes vides
                                $tousECUEValides = false;
                                break;
                            }
                        }
                        
                        // Calculer la moyenne de l'UE si tous les coefficients sont présents
                        if ($totalCoefficients > 0) {
                            $moyenneUE = $moyenneUE / $totalCoefficients;
                            
                            // Déterminer si l'UE est validée
                            $estValidee = ($moyenneUE >= $config['note_passage']) || $tousECUEValides;
                            
                            // Récupérer le nombre de crédits de l'UE
                            $creditsUE = $ue['nombre_credits'] ?? 0;
                            $creditsObtenus = $estValidee ? $creditsUE : 0;
                            
                            // Vérifier si une moyenne existe déjà pour cette UE
                            $query = "SELECT * FROM moyenne_ue 
                                      WHERE \"idUE\" = :ue_id 
                                      AND matricule = :matricule 
                                      AND session_idsession = :session_id 
                                      AND annee_acad_idannee_acad = :annee_id";
                            $stmt = $this->db->prepare($query);
                            $stmt->bindParam(':ue_id', $ueId);
                            $stmt->bindParam(':matricule', $matricule);
                            $stmt->bindParam(':session_id', $sessionId);
                            $stmt->bindParam(':annee_id', $anneeId);
                            $stmt->execute();
                            
                            $moyenneExistante = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($moyenneExistante) {
                                // Mettre à jour la moyenne existante
                                $query = "UPDATE moyenne_ue 
                                          SET moyenne_brute = :moyenne, 
                                              moyenne_deliberee = :moyenne, 
                                              est_validee = :est_validee, 
                                              credits_obtenus = :credits, 
                                              date_calcul = NOW(), 
                                              \"idUser\" = :idUser 
                                          WHERE idmoyenne_ue = :id";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':moyenne', $moyenneUE);
                                $stmt->bindParam(':est_validee', $estValidee, PDO::PARAM_BOOL);
                                $stmt->bindParam(':credits', $creditsObtenus);
                                $stmt->bindParam(':idUser', $userId);
                                $stmt->bindParam(':id', $moyenneExistante['idmoyenne_ue']);
                                $stmt->execute();
                            } else {
                                // Insérer une nouvelle moyenne
                                $query = "INSERT INTO moyenne_ue 
                                          (\"idUE\", matricule, session_idsession, annee_acad_idannee_acad, 
                                           moyenne_brute, moyenne_deliberee, est_validee, credits_obtenus, 
                                           type_validation, date_calcul, \"idUser\") 
                                          VALUES 
                                          (:ue_id, :matricule, :session_id, :annee_id, 
                                           :moyenne, :moyenne, :est_validee, :credits, 
                                           'Normale', NOW(), :idUser)";
                                $stmt = $this->db->prepare($query);
                                $stmt->bindParam(':ue_id', $ueId);
                                $stmt->bindParam(':matricule', $matricule);
                                $stmt->bindParam(':session_id', $sessionId);
                                $stmt->bindParam(':annee_id', $anneeId);
                                $stmt->bindParam(':moyenne', $moyenneUE);
                                $stmt->bindParam(':est_validee', $estValidee, PDO::PARAM_BOOL);
                                $stmt->bindParam(':credits', $creditsObtenus);
                                $stmt->bindParam(':idUser', $userId);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
            
                        // Finaliser l'étape
                        $this->updateProcessStatus($processId, 'Calcul UE', 'Terminé', "Calcul des moyennes d'UE terminé pour tous les étudiants.", 100);
                        $this->finalizeProcessStep($processId, 'Calcul UE');
                        
                        return true;
                    } catch (PDOException $e) {
                        $errorMessage = "Erreur lors du calcul des moyennes d'UE: " . $e->getMessage();
                        error_log($errorMessage);
                        $this->updateProcessStatus($processId, 'Calcul UE', 'Erreur', $errorMessage, 0);
                        return false;
                    }
                }
                
                /**
                 * Calcule les moyennes des semestres pour tous les étudiants
                 * @param int $processId ID du processus
                 * @param int $deliberationId ID de la délibération
                 * @param int $semestreId ID du semestre (0 pour tous les semestres)
                 * @param array $config Configuration de délibération
                 * @param int $userId ID de l'utilisateur
                 * @return bool Succès ou échec
                 */
                public function calculateSemesterAverages($processId, $deliberationId, $semestreId, $config, $userId) {
                    try {
                        // Récupérer les informations de la délibération
                        $deliberationInfo = $this->getDeliberationInfo($deliberationId);
                        if (!$deliberationInfo) {
                            $this->updateProcessStatus($processId, 'Calcul Semestre', 'Erreur', 'Impossible de récupérer les informations de la délibération', 0);
                            return false;
                        }
                        
                        $sessionId = $deliberationInfo['session_idsession'];
                        $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
                        $promotionId = $deliberationInfo['idpromotion'];
                        
                        // Mettre à jour le statut du processus
                        $this->updateProcessStatus($processId, 'Calcul Semestre', 'En cours', 'Récupération des semestres...', 10);
                        
                        // Récupérer les semestres à traiter
                        $semestres = [];
                        if ($semestreId > 0) {
                            // Traiter un seul semestre
                            $semestres[] = $semestreId;
                        } else {
                            // Traiter tous les semestres de la promotion
                            $query = "SELECT idsemestre, \"numeroSemestre\" FROM semestre WHERE promotion_idpromotion = :promotion_id ORDER BY numeroSemestre";
                            $stmt = $this->db->prepare($query);
                            $stmt->bindParam(':promotion_id', $promotionId);
                            $stmt->execute();
                            
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $semestres[] = $row;
                            }
                        }
                        
                        if (empty($semestres)) {
                            $this->updateProcessStatus($processId, 'Calcul Semestre', 'Erreur', 'Aucun semestre trouvé pour cette promotion', 0);
                            return false;
                        }
                        
                        // Compteurs pour le suivi
                        $totalSemestres = count($semestres);
                        $processedSemestres = 0;
                        
                        // Pour chaque semestre
                        foreach ($semestres as $semestre) {
                            $currentSemestreId = $semestre['idsemestre'];
                            $processedSemestres++;
                            $progression = intval(($processedSemestres / $totalSemestres) * 90) + 10; // 10% à 100%
                            
                            $this->updateProcessStatus(
                                $processId, 
                                'Calcul Semestre', 
                                'En cours', 
                                "Calcul des moyennes pour le semestre {$semestre['numeroSemestre']} ($processedSemestres/$totalSemestres)", 
                                $progression
                            );
                            
                            // Récupérer les UE du semestre
                            $ues = $this->getUEBySemestre($currentSemestreId);
                            
                            if (empty($ues)) {
                                continue; // Passer au semestre suivant si pas d'UE
                            }
                            
                            // Récupérer les étudiants ayant des moyennes d'UE pour ce semestre
                            $query = "SELECT DISTINCT mu.matricule 
                                      FROM moyenne_ue mu
                                      JOIN ue u ON mu.\"idUE\" = u.\"idUE\"
                                      WHERE u.semestre_idsemestre = :semestre_id
                                      AND mu.session_idsession = :session_id
                                      AND mu.annee_acad_idannee_acad = :annee_id";
                            $stmt = $this->db->prepare($query);
                            $stmt->bindParam(':semestre_id', $currentSemestreId);
                            $stmt->bindParam(':session_id', $sessionId);
                            $stmt->bindParam(':annee_id', $anneeId);
                            $stmt->execute();
                            
                            $etudiants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            // Pour chaque étudiant
                            foreach ($etudiants as $matricule) {
                                // Calculer la moyenne du semestre
                                $moyennesUE = [];
                                $totalCredits = 0;
                                $creditsObtenus = 0;
                                $sommePonderee = 0;
                                
                                foreach ($ues as $ue) {
                                    $ueId = $ue['idUE'];
                                    $creditsUE = $ue['nombre_credits'] ?? 0;
                                    $totalCredits += $creditsUE;
                                    
                                    $query = "SELECT * FROM moyenne_ue 
                                              WHERE \"idUE\" = :ue_id 
                                              AND matricule = :matricule 
                                              AND session_idsession = :session_id 
                                              AND annee_acad_idannee_acad = :annee_id";
                                    $stmt = $this->db->prepare($query);
                                    $stmt->bindParam(':ue_id', $ueId);
                                    $stmt->bindParam(':matricule', $matricule);
                                    $stmt->bindParam(':session_id', $sessionId);
                                    $stmt->bindParam(':annee_id', $anneeId);
                                    $stmt->execute();
                                    
                                    $moyenneUE = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($moyenneUE) {
                                        $moyennesUE[$ueId] = $moyenneUE;
                                        $sommePonderee += $moyenneUE['moyenne_deliberee'] * $creditsUE;
                                        
                                        if ($moyenneUE['est_validee']) {
                                            $creditsObtenus += $creditsUE;
                                        }
                                    }
                                }
                                
                                // Calculer la moyenne du semestre si des crédits sont présents
                                if ($totalCredits > 0) {
                                    $moyenneSemestre = $sommePonderee / $totalCredits;
                                    
                                    // Déterminer si le semestre est validé
                                    $pourcentageReussite = ($creditsObtenus / $totalCredits) * 100;
                                    $estValide = $pourcentageReussite >= $config['pourcentage_passage_semestre'];
                                    
                                    // Vérifier si une moyenne existe déjà pour ce semestre
                                    $query = "SELECT * FROM moyenne_semestre 
                                              WHERE idsemestre = :semestre_id 
                                              AND matricule = :matricule 
                                              AND session_idsession = :session_id 
                                              AND annee_acad_idannee_acad = :annee_id";
                                    $stmt = $this->db->prepare($query);
                                    $stmt->bindParam(':semestre_id', $currentSemestreId);
                                    $stmt->bindParam(':matricule', $matricule);
                                    $stmt->bindParam(':session_id', $sessionId);
                                    $stmt->bindParam(':annee_id', $anneeId);
                                    $stmt->execute();
                                    
                                    $moyenneExistante = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($moyenneExistante) {
                                        // Mettre à jour la moyenne existante
                                        $query = "UPDATE moyenne_semestre 
                                                  SET moyenne_brute = :moyenne, 
                                                      moyenne_deliberee = :moyenne, 
                                                      est_valide = :est_valide, 
                                                      credits_obtenus = :credits_obtenus, 
                                                      credits_total = :credits_total, 
                                                      date_calcul = NOW(), 
                                                      \"idUser\" = :idUser 
                                                  WHERE idmoyenne_semestre = :id";
                                        $stmt = $this->db->prepare($query);
                                        $stmt->bindParam(':moyenne', $moyenneSemestre);
                                        $stmt->bindParam(':est_valide', $estValide, PDO::PARAM_BOOL);
                                        $stmt->bindParam(':credits_obtenus', $creditsObtenus);
                                        $stmt->bindParam(':credits_total', $totalCredits);
                                        $stmt->bindParam(':idUser', $userId);
                                        $stmt->bindParam(':id', $moyenneExistante['idmoyenne_semestre']);
                                        $stmt->execute();
                                    } else {
                                        // Insérer une nouvelle moyenne
                                        $query = "INSERT INTO moyenne_semestre 
                                                  (idsemestre, matricule, session_idsession, annee_acad_idannee_acad, 
                                                   moyenne_brute, moyenne_deliberee, est_valide, credits_obtenus, credits_total, 
                                                   date_calcul, \"idUser\") 
                                                  VALUES 
                                                  (:semestre_id, :matricule, :session_id, :annee_id, 
                                                   :moyenne, :moyenne, :est_valide, :credits_obtenus, :credits_total, 
                                                   NOW(), :idUser)";
                                        $stmt = $this->db->prepare($query);
                                        $stmt->bindParam(':semestre_id', $currentSemestreId);
                                        $stmt->bindParam(':matricule', $matricule);
                                        $stmt->bindParam(':session_id', $sessionId);
                                        $stmt->bindParam(':annee_id', $anneeId);
                                        $stmt->bindParam(':moyenne', $moyenneSemestre);
                                        $stmt->bindParam(':est_valide', $estValide, PDO::PARAM_BOOL);
                                        $stmt->bindParam(':credits_obtenus', $creditsObtenus);
                                        $stmt->bindParam(':credits_total', $totalCredits);
                                        $stmt->bindParam(':idUser', $userId);
                                        $stmt->execute();
                                    }
                                }
                            }
                        }
                        
                        // Finaliser l'étape
                        $this->updateProcessStatus($processId, 'Calcul Semestre', 'Terminé', "Calcul des moyennes de semestre terminé pour tous les étudiants.", 100);
                        $this->finalizeProcessStep($processId, 'Calcul Semestre');
                        
                        return true;
                    } catch (PDOException $e) {
                        $errorMessage = "Erreur lors du calcul des moyennes de semestre: " . $e->getMessage();
                        error_log($errorMessage);
                        $this->updateProcessStatus($processId, 'Calcul Semestre', 'Erreur', $errorMessage, 0);
                        return false;
                    }
                }
                
                
          
               
    
    /**
     * Détermine l'étape suivante du processus
     * @param string $etapeActuelle Étape actuelle
     * @return string|null Étape suivante ou null si c'est la dernière étape
     */
    private function getNextStep($etapeActuelle) {
        $etapes = [
            'Initialisation' => 'Calcul ECUE',
            'Calcul ECUE' => 'Calcul UE',
            'Calcul UE' => 'Compensation intra-UE',
            'Compensation intra-UE' => 'Compensation inter-UE',
            'Compensation inter-UE' => 'Calcul Semestre',
            'Calcul Semestre' => 'Compensation inter-semestre',
            'Compensation inter-semestre' => 'Décisions jury',
            'Décisions jury' => 'Finalisation',
            'Finalisation' => null
        ];
        
        return isset($etapes[$etapeActuelle]) ? $etapes[$etapeActuelle] : null;
    }

 
    
 
    
    /**
     * Récupère les résultats de délibération pour une promotion
     * @param int $deliberationId ID de la délibération
     * @return array Résultats de délibération
     */
    public function getDeliberationResults($deliberationId) {
        try {
            // Récupérer les informations de la délibération
            $deliberationInfo = $this->getDeliberationInfo($deliberationId);
            if (!$deliberationInfo) {
                return [];
            }
            
            $promotionId = $deliberationInfo['idpromotion'];
            $sessionId = $deliberationInfo['session_idsession'];
            $anneeId = $deliberationInfo['annee_acad_idannee_acad'];
            
            // Récupérer les étudiants de la promotion
            $query = "SELECT e.idetudiant, e.matricule, e.noms 
                      FROM etudiant e 
                      WHERE e.promotion_idpromotion = :promotion_id 
                      AND e.annee_acad_idannee_acad = :annee_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':promotion_id', $promotionId);
            $stmt->bindParam(':annee_id', $anneeId);
            $stmt->execute();
            
            $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $resultats = [];
            
            // Pour chaque étudiant, récupérer ses résultats
            foreach ($etudiants as $etudiant) {
                $matricule = $etudiant['matricule'];
                
                // Récupérer les moyennes de semestre
                $query = "SELECT ms.*, s.\"numeroSemestre\" 
                          FROM moyenne_semestre ms 
                          JOIN semestre s ON ms.idsemestre = s.idsemestre 
                          WHERE ms.matricule = :matricule 
                          AND ms.session_idsession = :session_id 
                          AND ms.annee_acad_idannee_acad = :annee_id 
                          ORDER BY s.numeroSemestre";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':session_id', $sessionId);
                $stmt->bindParam(':annee_id', $anneeId);
                $stmt->execute();
                
                $moyennesSemestre = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Récupérer la moyenne annuelle
                $query = "SELECT * FROM moyenne_annuelle 
                          WHERE matricule = :matricule 
                          AND idpromotion = :promotion_id 
                          AND session_idsession = :session_id 
                          AND annee_acad_idannee_acad = :annee_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':promotion_id', $promotionId);
                $stmt->bindParam(':session_id', $sessionId);
                $stmt->bindParam(':annee_id', $anneeId);
                $stmt->execute();
                
                $moyenneAnnuelle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer le résultat final
                $query = "SELECT * FROM resultat_deliberation 
                          WHERE matricule = :matricule 
                          AND iddeliberation = :deliberation_id 
                          AND est_final = 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->bindParam(':deliberation_id', $deliberationId);
                $stmt->execute();
                
                $resultatFinal = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Ajouter les résultats à la liste
                $resultats[] = [
                    'etudiant' => $etudiant,
                    'moyennes_semestre' => $moyennesSemestre,
                    'moyenne_annuelle' => $moyenneAnnuelle,
                    'resultat_final' => $resultatFinal
                ];
            }
            
            return $resultats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des résultats de délibération: " . $e->getMessage());
            return [];
        }
    }

    /**
 * Récupère les informations d'un étudiant par son matricule
 * @param string $matricule Matricule de l'étudiant
 * @return array|bool Informations de l'étudiant ou false si non trouvé
 */
public function getEtudiantByMatricule($matricule, $anneeId = null) {
    try {
        // Si l'année académique est spécifiée, filtrer par année
        if ($anneeId) {
            $query = "SELECT e.*, p.\"designationPromotion\" 
                      FROM etudiant e
                      LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      WHERE e.matricule = :matricule 
                      AND e.annee_acad_idannee_acad = :anneeId
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        } else {
            // Garder la requête originale comme fallback
            $query = "SELECT e.*, p.\"designationPromotion\" 
                      FROM etudiant e
                      LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      WHERE e.matricule = :matricule";
                   
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'étudiant par matricule: " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère les informations d'un bureau de jury par son ID
 * @param int $bureauId ID du bureau de jury
 * @return array|bool Informations du bureau ou false si non trouvé
 */
public function getBureauJuryById($bureauId) {
    try {
        $query = "SELECT b.*, 
                  p.noms as president_nom, 
                  s.noms as secretaire_nom,
                  a.designation as annee_academique
                  FROM bureau_jury_deliberation b
                  LEFT JOIN agent p ON b.president_id = p.\"idAgent\"
                  LEFT JOIN agent s ON b.secretaire_id = s.\"idAgent\"
                  LEFT JOIN annee_acad a ON b.annee_acad_idannee_acad = a.idannee_acad
                  WHERE b.idbureau = :idBureau";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idBureau', $bureauId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du bureau de jury: " . $e->getMessage());
        return false;
    }
}




public function getNotesEtudiant($matricule, $sessionId, $anneeId, $semestreId = null) {
    try {
        $result = [];
        
        // Construire la condition du semestre
        $semestresCondition = '';
        if ($semestreId !== null) {
            $semestresCondition = " AND s.idsemestre = :semestreId";
        }
        
        // Récupérer les semestres concernés
        $query = "SELECT s.idsemestre, s.\"numeroSemestre\", p.\"designationPromotion\",
                  o.\"designationOrientation\", sec.\"designationSection\"
                  FROM semestre s
                  INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  INNER JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE p.idpromotion = (
                      SELECT promotion_idpromotion FROM etudiant WHERE matricule = :matricule AND annee_acad_idannee_acad = :anneeId
                  )" . $semestresCondition;
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        
        if ($semestreId !== null) {
            $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque semestre
        foreach ($semestres as $semestre) {
            $semestreData = [
                'info' => $semestre,
                'ues' => []
            ];
            
            // Récupérer les UE du semestre
            $query = "SELECT u.*,
                     (SELECT moyenne_deliberee FROM moyenne_ue WHERE \"idUE\" = u.\"idUE\" AND matricule = :matricule 
                      AND session_idsession = :sessionId AND annee_acad_idannee_acad = :anneeId) as moyenne,
                     (SELECT est_validee FROM moyenne_ue WHERE \"idUE\" = u.\"idUE\" AND matricule = :matricule 
                      AND session_idsession = :sessionId AND annee_acad_idannee_acad = :anneeId) as est_validee
                     FROM ue u
                     WHERE u.semestre_idsemestre = :semestreId
                     ORDER BY u.codeUE";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->bindParam(':semestreId', $semestre['idsemestre'], PDO::PARAM_INT);
            $stmt->execute();
            
            $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pour chaque UE
            foreach ($ues as $ue) {
                $ueData = [
                    'info' => $ue,
                    'ecues' => []
                ];
                
                // Récupérer les ECUE de l'UE
                $query = "SELECT e.*,
                         (SELECT MF FROM cotes_grille WHERE \"ECUE_idECUE\" = e.\"idECUE\" AND matricule = :matricule 
                          AND session_idsession = :sessionId AND annee_acad_id = :anneeId) as note,
                         (SELECT CC FROM cotes_grille WHERE \"ECUE_idECUE\" = e.\"idECUE\" AND matricule = :matricule 
                          AND session_idsession = :sessionId AND annee_acad_id = :anneeId) as cc,
                         (SELECT EX FROM cotes_grille WHERE \"ECUE_idECUE\" = e.\"idECUE\" AND matricule = :matricule 
                          AND session_idsession = :sessionId AND annee_acad_id = :anneeId) as examen,
                         ROUND((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ", 1) as coefficient
                         FROM ecue e
                         WHERE e.\"UE_idUE\" = :ueId
                         ORDER BY e.designationECUE";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
                $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
                $stmt->bindParam(':ueId', $ue['idUE'], PDO::PARAM_INT);
                $stmt->execute();
                
                $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $ueData['ecues'] = $ecues;
                
                // Calculer les crédits et la moyenne de l'UE si nécessaire
                $totalCredits = 0;
                $totalPoints = 0;
                $totalCoefficients = 0;
                
                foreach ($ecues as $ecue) {
                    // Calculer le crédit de l'ECUE: (CMI + TP + TD)/ $this->heuresParCredit
                    $ecueCredit = (floatval($ecue['CMI']) + floatval($ecue['TP']) + floatval($ecue['TD'])) / $this->heuresParCredit;
                    $ecueCredit = round($ecueCredit, 1); // Arrondir à 1 décimale
                    
                    // Ajouter au total des crédits de l'UE
                    $totalCredits += $ecueCredit;
                    
                    // Si l'ECUE a une note, l'ajouter à la moyenne pondérée
                    if (isset($ecue['note']) && $ecue['note'] !== null) {
                        $totalPoints += (floatval($ecue['note']) * $ecueCredit);
                        $totalCoefficients += $ecueCredit;
                    }
                }
                
                // Ajouter le nombre de crédits calculé à l'UE
                $ueData['info']['nombre_credits'] = $totalCredits;
                
                // Calculer la moyenne de l'UE si elle n'est pas disponible dans la base de données
                if (empty($ueData['info']['moyenne']) || $ueData['info']['moyenne'] === null) {
                    if ($totalCoefficients > 0) {
                        $moyenneCalculee = $totalPoints / $totalCoefficients;
                        $ueData['info']['moyenne'] = $moyenneCalculee;
                        $ueData['info']['est_validee'] = ($moyenneCalculee >= 10) ? 1 : 0;
                    } else {
                        $ueData['info']['moyenne'] = 0;
                        $ueData['info']['est_validee'] = 0;
                    }
                }
                
                $semestreData['ues'][] = $ueData;
            }
            
            $result[] = $semestreData;
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notes de l'étudiant: " . $e->getMessage());
        return [];
    }
}



/**
 * Récupère les résultats globaux d'un étudiant pour une session et une année académique
 * @param string $matricule Matricule de l'étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param int|null $semestreId ID du semestre (null pour une vision annuelle)
 * @return array Résultats de l'étudiant
 */
public function getResultatsEtudiant($matricule, $sessionId, $anneeId, $semestreId = null) {
    try {
        $result = [];
        
        // Récupérer les notes de l'étudiant
        $notesEtudiant = $this->getNotesEtudiant($matricule, $sessionId, $anneeId, $semestreId);
        
        // Si aucune note trouvée
        if (empty($notesEtudiant)) {
            return [
                'type' => $semestreId !== null ? 'semestre' : 'annuel',
                'moyenne' => 0,
                'credits_valides' => 0,
                'credits_total' => 0,
                'pourcentage' => 0,
                'est_valide' => false,
                'mention' => ''
            ];
        }
        
        // Calculer les résultats pour chaque semestre
        $semestresCalcules = [];
        foreach ($notesEtudiant as $semestreData) {
            $moyenneSemestre = 0;
            $totalCoefficients = 0;
            $creditsValides = 0;
            $creditsTotal = 0;
            
            // Calculer les résultats pour chaque UE
            foreach ($semestreData['ues'] as $ueData) {
                $ue = $ueData['info'];
                $nombreCredits = isset($ue['nombre_credits']) ? floatval($ue['nombre_credits']) : 0;
                $estValidee = isset($ue['est_validee']) && $ue['est_validee'] == 1;
                
                // Ajouter au total des crédits
                $creditsTotal += $nombreCredits;
                if ($estValidee) {
                    $creditsValides += $nombreCredits;
                }
                
                // Ajouter à la moyenne si disponible
                if (isset($ue['moyenne']) && $ue['moyenne'] !== null) {
                    $moyenneSemestre += ($ue['moyenne'] * $nombreCredits);
                    $totalCoefficients += $nombreCredits;
                }
            }
            
            // Calculer la moyenne finale du semestre
            $moyenneSemestre = $totalCoefficients > 0 ? $moyenneSemestre / $totalCoefficients : 0;
            
            // Stocker les résultats calculés
            $semestresCalcules[] = [
                'info' => $semestreData['info'],
                'moyenne' => $moyenneSemestre,
                'credits_valides' => $creditsValides,
                'credits_total' => $creditsTotal,
                'est_valide' => ($creditsValides == $creditsTotal && $moyenneSemestre >= 10)
            ];
        }
        
        // Construire le résultat selon le type (semestre ou annuel)
        if ($semestreId !== null) {
            // Résultat pour un semestre spécifique
            if (!empty($semestresCalcules[0])) {
                $semestre = $semestresCalcules[0];
                $result = [
                    'type' => 'semestre',
                    'numero_semestre' => $semestre['info']['numeroSemestre'],
                    'moyenne' => $semestre['moyenne'],
                    'credits_valides' => $semestre['credits_valides'],
                    'credits_total' => $semestre['credits_total'],
                    'pourcentage' => $semestre['credits_total'] > 0 ? 
                        ($semestre['credits_valides'] / $semestre['credits_total']) * 100 : 0,
                    'est_valide' => $semestre['est_valide']
                ];
            }
        } else {
            // Résultat annuel (tous les semestres)
            $totalMoyenne = 0;
            $totalCreditsValides = 0;
            $totalCredits = 0;
            $tousValides = true;
            
            foreach ($semestresCalcules as $semestre) {
                $totalMoyenne += $semestre['moyenne'];
                $totalCreditsValides += $semestre['credits_valides'];
                $totalCredits += $semestre['credits_total'];
                $tousValides = $tousValides && $semestre['est_valide'];
            }
            
            $semestreCount = count($semestresCalcules);
            $moyenneCalculee = $semestreCount > 0 ? $totalMoyenne / $semestreCount : 0;
            
            // Déterminer la mention
            $mention = '';
            if ($moyenneCalculee >= 16) {
                $mention = 'Très Bien';
            } elseif ($moyenneCalculee >= 14) {
                $mention = 'Bien';
            } elseif ($moyenneCalculee >= 12) {
                $mention = 'Assez Bien';
            } elseif ($moyenneCalculee >= 10) {
                $mention = 'Satisfaction';
            }
            
            $result = [
                'type' => 'annuel_calcule',
                'moyenne' => $moyenneCalculee,
                'credits_valides' => $totalCreditsValides,
                'credits_total' => $totalCredits,
                'pourcentage' => $totalCredits > 0 ? ($totalCreditsValides / $totalCredits) * 100 : 0,
                'est_valide' => $tousValides && $moyenneCalculee >= 10,
                'mention' => $mention
            ];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des résultats de l'étudiant: " . $e->getMessage());
        return [
            'type' => $semestreId !== null ? 'semestre' : 'annuel',
            'moyenne' => 0,
            'credits_valides' => 0,
            'credits_total' => 0,
            'pourcentage' => 0,
            'est_valide' => false,
            'mention' => ''
        ];
    }
}

/**
 * Récupère les ECUE associés à une UE spécifique
 * 
 * @param int $ueId Identifiant de l'UE
 * @return array Liste des ECUE associés à l'UE
 */
public function getECUEsByUE($ueId) {
    try {
        $query = "SELECT e.*, 
                  (e.CMI + e.TD + e.TP) / 25 as coefficient
                  FROM ecue e 
                  WHERE e.\"idUE\" = :ueId 
                  ORDER BY e.designationECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des ECUE: ' . $e->getMessage());
        return [];
    }
}


/**
 * Vérifie si une UE a été validée en première session pour un étudiant
 * 
 * @param string $matricule Le matricule de l'étudiant
 * @param int $ueId L'identifiant de l'UE
 * @param int $anneeId L'identifiant de l'année académique
 * @return array|null Les informations de l'UE avec son statut de validation ou null si erreur
 */
public function getUEValideePremiereSession($matricule, $ueId, $anneeId) {
    try {
        // Récupérer l'ID de la première session
        $query = "SELECT idsession FROM session
                  WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                  OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $firstSession = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if (!$firstSession) {
            return null;
        }
       
        $session1Id = $firstSession['idsession'];
       
        // Récupérer les ECUEs de cette UE avec les valeurs CMI, TP et TD
        $query = "SELECT \"idECUE\", CMI, TP, TD FROM ecue WHERE \"UE_idUE\" = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $ueId, PDO::PARAM_INT);
        $stmt->execute();
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
        if (empty($ecues)) {
            return null;
        }
       
        // Calculer la moyenne pondérée de l'UE
        $totalPoints = 0;
        $totalCoefficients = 0;
        $allEcuesHaveNotes = true; // Nouveau flag pour vérifier si toutes les ECUEs ont des notes
       
        foreach ($ecues as $ecue) {
            // Calculer le coefficient de l'ECUE: (CMI+TP+TD)/ " . $this->heuresParCredit . "
            $cmi = isset($ecue['CMI']) ? floatval($ecue['CMI']) : 0;
            $tp = isset($ecue['TP']) ? floatval($ecue['TP']) : 0;
            $td = isset($ecue['TD']) ? floatval($ecue['TD']) : 0;
           
            $coefficient = ($cmi + $tp + $td) / 25;
           
            // Si le coefficient est 0, utiliser 1 comme valeur par défaut
            if ($coefficient <= 0) {
                $coefficient = 1;
            }
           
            // Récupérer la note de l'ECUE
            $query = "SELECT MF, CC, EX FROM cotes_grille
                      WHERE \"ECUE_idECUE\" = ?
                      AND matricule = ?
                      AND session_idsession = ?
                      AND annee_acad_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $ecue['idECUE'], PDO::PARAM_INT);
            $stmt->bindValue(2, $matricule, PDO::PARAM_STR);
            $stmt->bindValue(3, $session1Id, PDO::PARAM_INT);
            $stmt->bindValue(4, $anneeId, PDO::PARAM_INT);
            $stmt->execute();
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
           
            // Vérifier si l'ECUE a des notes complètes (CC, EX et MF)
            if ($note && isset($note['MF']) && $note['MF'] !== null && 
                isset($note['CC']) && $note['CC'] !== null && 
                isset($note['EX']) && $note['EX'] !== null) {
                $totalPoints += floatval($note['MF']) * $coefficient;
                $totalCoefficients += $coefficient;
            } else {
                // Si une ECUE n'a pas de notes complètes, marquer le flag
                $allEcuesHaveNotes = false;
                // Nous pouvons soit continuer à calculer avec les notes disponibles,
                // soit sortir immédiatement de la boucle
                // break; // Décommentez cette ligne si vous voulez sortir immédiatement
            }
        }
       
        // Si toutes les ECUEs n'ont pas de notes, considérer l'UE comme non validée
        if (!$allEcuesHaveNotes) {
            // Récupérer les informations de l'UE
            $query = "SELECT * FROM ue WHERE \"idUE\" = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $ueId, PDO::PARAM_INT);
            $stmt->execute();
            $ue = $stmt->fetch(PDO::FETCH_ASSOC);
           
            if (!$ue) {
                return null;
            }
           
            // Marquer l'UE comme non validée si des notes sont manquantes
            $ue['moyenne'] = null;
            $ue['est_validee'] = 0;
            $ue['notes_incompletes'] = true;
           
            return $ue;
        }
       
        // Calculer la moyenne seulement si toutes les ECUEs ont des notes
        $moyenne = ($totalCoefficients > 0) ? ($totalPoints / $totalCoefficients) : 0;
       
        // Récupérer les informations de l'UE
        $query = "SELECT * FROM ue WHERE \"idUE\" = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $ueId, PDO::PARAM_INT);
        $stmt->execute();
        $ue = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if (!$ue) {
            return null;
        }
       
        // Ajouter les informations de validation
        $ue['moyenne'] = $moyenne;
        $ue['est_validee'] = ($moyenne >= 10) ? 1 : 0;
        $ue['notes_incompletes'] = false;
       
        return $ue;
       
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de validation d'UE en première session: " . $e->getMessage());
        return null;
    }
}


/**
 * Récupère les sections dont un agent est responsable
 * 
 * @param int $agentId ID de l'agent
 * @param int|null $anneeId ID de l'année académique (optionnel)
 * @return array Liste des sections
 */
public function getSectionsResponsable($agentId, $anneeId = null) {
    $query = "SELECT s.* 
              FROM section s
              INNER JOIN responsable_section rs ON s.idsection = rs.section_idsection
              WHERE rs.\"idUser\" = :agentId";
    
    $params = [':agentId' => $agentId];
    
    if ($anneeId) {
        $query .= " AND rs.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    $query .= " ORDER BY s.\"designationSection\" ASC";
    
    $stmt = $this->db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les jurys associés à des sections spécifiques
 * 
 * @param array|int $sectionIds ID ou tableau d'IDs des sections
 * @param int|null $anneeId ID de l'année académique (optionnel)
 * @return array Liste des jurys
 */
public function getJurysBySections($sectionIds, $anneeId = null) {
    // Convertir en tableau si un seul ID est fourni
    if (!is_array($sectionIds)) {
        $sectionIds = [$sectionIds];
    }
    
    // Construire la requête avec des placeholders pour chaque ID de section
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    
    $query = "SELECT j.* 
              FROM jury j
              INNER JOIN bureau_jury_promotion bjp ON j.idjury = bjp.idbureau
              INNER JOIN promotion p ON bjp.idpromotion = p.idpromotion
              INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
              INNER JOIN section s ON o.section_idsection = s.idsection
              WHERE s.idsection IN ($placeholders)";
    
    $params = $sectionIds;
    
    if ($anneeId) {
        $query .= " AND j.annee_acad_id = ?";
        $params[] = $anneeId;
    }
    
    $query .= " GROUP BY j.idjury ORDER BY j.designation ASC";
    
    $stmt = $this->db->prepare($query);
    
    // Bind des paramètres
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les promotions associées à un jury et à des sections spécifiques
 * 
 * @param int $juryId ID du jury
 * @param array|int $sectionIds ID ou tableau d'IDs des sections
 * @param int|null $anneeId ID de l'année académique (optionnel)
 * @return array Liste des promotions
 */
public function getPromotionsByJuryAndSections($juryId, $sectionIds, $anneeId = null) {
    // Convertir en tableau si un seul ID est fourni
    if (!is_array($sectionIds)) {
        $sectionIds = [$sectionIds];
    }
    
    // Construire la requête avec des placeholders pour chaque ID de section
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    
    $query = "SELECT p.* 
              FROM promotion p
              INNER JOIN bureau_jury_promotion bjp ON p.idpromotion = bjp.idpromotion
              INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
              INNER JOIN section s ON o.section_idsection = s.idsection
              WHERE bjp.idbureau = ? 
              AND s.idsection IN ($placeholders)";
    
    $params = array_merge([$juryId], $sectionIds);
    
    if ($anneeId) {
        $query .= " AND p.annee_acad_idannee_acad = ?";
        $params[] = $anneeId;
    }
    
    $query .= " ORDER BY p.\"designationPromotion\" ASC";
    
    $stmt = $this->db->prepare($query);
    
    // Bind des paramètres
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le responsable de section (Chef de section ou Doyen) pour une promotion donnée
 * 
 * @param int $promotionId ID de la promotion
 * @param int $anneeId ID de l'année académique
 * @return array|null Informations sur le responsable de section
 */
public function getResponsableSectionByPromotion($promotionId, $anneeId) {
    $query = "SELECT rs.* 
              FROM responsable_section rs
              INNER JOIN section s ON rs.section_idsection = s.idsection
              INNER JOIN orientation o ON s.idsection = o.section_idsection
              INNER JOIN promotion p ON o.idorientation = p.orientation_idorientation
              WHERE p.idpromotion = :promotionId
              AND rs.annee_acad_idannee_acad = :anneeId
              AND (rs.fonction LIKE '%Chef de section%' OR rs.fonction LIKE '%Doyen%')
              AND rs.fonction NOT LIKE '%Vice Doyen%' 
              AND rs.fonction NOT LIKE '%Chef de section adjoint%'
              LIMIT 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Sauvegarde les moyennes calculées pour les semestres et l'année
 * @param array $etudiants Liste des étudiants
 * @param array $moyennesSemestre Moyennes des semestres par étudiant
 * @param array $validationsSemestre Validations des semestres par étudiant
 * @param array $moyennesAnnuelles Moyennes annuelles par étudiant
 * @param array $validationsAnnuelles Validations annuelles par étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param array $semestres Liste des semestres concernés
 * @param int $promotionId ID de la promotion
 * @param int $userId ID de l'utilisateur effectuant la sauvegarde
 * @param bool $afficherDeuxSemestres Indique si on traite deux semestres (année complète)
 * @return bool Succès de l'opération
 */
public function sauvegarderMoyennes($etudiants, $moyennesSemestre, $validationsSemestre, 
                                   $moyennesAnnuelles, $validationsAnnuelles, 
                                   $sessionId, $anneeId, $semestres, $promotionId, 
                                   $userId, $afficherDeuxSemestres) {
    try {
        // Démarrer une transaction
        $this->db->beginTransaction();
        
        // Pour chaque étudiant
        foreach ($etudiants as $etudiant) {
            $matricule = $etudiant['matricule'];
            
            // 1. Sauvegarder les moyennes de semestre
            foreach ($semestres as $semestre) {
                $semestreId = $semestre['idsemestre'];
                
                if (isset($moyennesSemestre[$matricule][$semestreId]) && 
                    isset($validationsSemestre[$matricule][$semestreId])) {
                    
                    $moyenne = $moyennesSemestre[$matricule][$semestreId];
                    $validation = $validationsSemestre[$matricule][$semestreId];
                    
                    // Vérifier si une entrée existe déjà
                    $query = "SELECT idmoyenne_semestre FROM moyenne_semestre 
                             WHERE idsemestre = ? AND matricule = ? 
                             AND session_idsession = ? AND annee_acad_idannee_acad = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([$semestreId, $matricule, $sessionId, $anneeId]);
                    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingRecord) {
                        // Mettre à jour l'enregistrement existant
                        $query = "UPDATE moyenne_semestre SET 
                                 moyenne_brute = ?, 
                                 est_valide = ?, 
                                 credits_obtenus = ?, 
                                 credits_total = ?, 
                                 date_calcul = NOW(), 
                                 \"idUser\" = ? 
                                 WHERE idmoyenne_semestre = ?";
                        
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([
                            $moyenne, 
                            $validation['est_valide'] ? 1 : 0, 
                            $validation['credits_valides'], 
                            $validation['credits_total'], 
                            $userId, 
                            $existingRecord['idmoyenne_semestre']
                        ]);
                    } else {
                        // Créer un nouvel enregistrement
                        $query = "INSERT INTO moyenne_semestre 
                                 (idsemestre, matricule, session_idsession, annee_acad_idannee_acad, 
                                 moyenne_brute, est_valide, credits_obtenus, credits_total, \"idUser\") 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([
                            $semestreId, 
                            $matricule, 
                            $sessionId, 
                            $anneeId, 
                            $moyenne, 
                            $validation['est_valide'] ? 1 : 0, 
                            $validation['credits_valides'], 
                            $validation['credits_total'], 
                            $userId
                        ]);
                    }
                }
            }
            
            // 2. Sauvegarder la moyenne annuelle si on affiche deux semestres
            if ($afficherDeuxSemestres && isset($moyennesAnnuelles[$matricule]) && 
                isset($validationsAnnuelles[$matricule])) {
                
                $moyenneAnnuelle = $moyennesAnnuelles[$matricule];
                $validationAnnuelle = $validationsAnnuelles[$matricule];
                
                // Déterminer la mention
                $mention = null;
                if ($moyenneAnnuelle >= 16) {
                    $mention = 'Très Bien';
                } elseif ($moyenneAnnuelle >= 14) {
                    $mention = 'Bien';
                } elseif ($moyenneAnnuelle >= 12) {
                    $mention = 'Assez Bien';
                } elseif ($moyenneAnnuelle >= 10) {
                    $mention = 'Passable';
                }
                
                // Vérifier si une entrée existe déjà
                $query = "SELECT idmoyenne_annuelle FROM moyenne_annuelle 
                         WHERE idpromotion = ? AND matricule = ? 
                         AND session_idsession = ? AND annee_acad_idannee_acad = ?";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$promotionId, $matricule, $sessionId, $anneeId]);
                $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingRecord) {
                    // Mettre à jour l'enregistrement existant
                    $query = "UPDATE moyenne_annuelle SET 
                             moyenne_brute = ?, 
                             est_admis = ?, 
                             credits_obtenus = ?, 
                             credits_total = ?, 
                             mention = ?,
                             date_calcul = NOW(), 
                             \"idUser\" = ? 
                             WHERE idmoyenne_annuelle = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        $moyenneAnnuelle, 
                        $validationAnnuelle['est_valide'] ? 1 : 0, 
                        $validationAnnuelle['credits_valides'], 
                        $validationAnnuelle['credits_total'], 
                        $mention,
                        $userId, 
                        $existingRecord['idmoyenne_annuelle']
                    ]);
                } else {
                    // Créer un nouvel enregistrement
                    $query = "INSERT INTO moyenne_annuelle 
                             (idpromotion, matricule, session_idsession, annee_acad_idannee_acad, 
                             moyenne_brute, est_admis, credits_obtenus, credits_total, mention, \"idUser\") 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        $promotionId, 
                        $matricule, 
                        $sessionId, 
                        $anneeId, 
                        $moyenneAnnuelle, 
                        $validationAnnuelle['est_valide'] ? 1 : 0, 
                        $validationAnnuelle['credits_valides'], 
                        $validationAnnuelle['credits_total'], 
                        $mention,
                        $userId
                    ]);
                }
            }
        }
        
        // Valider la transaction
        $this->db->commit();
        return true;
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $this->db->rollBack();
        error_log("Erreur dans sauvegarderMoyennes: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la moyenne d'un semestre pour un étudiant
 * @param string $matricule Matricule de l'étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param int $semestreId ID du semestre
 * @return float|null La moyenne du semestre ou null si non disponible
 */
public function getMoyenneSemestre($matricule, $sessionId, $anneeId, $semestreId) {
    try {
        // D'abord vérifier si une moyenne existe déjà dans la table moyenne_semestre
        $query = "SELECT moyenne_brute FROM moyenne_semestre 
                 WHERE matricule = ? AND session_idsession = ? 
                 AND annee_acad_idannee_acad = ? AND idsemestre = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$matricule, $sessionId, $anneeId, $semestreId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['moyenne_brute'] !== null) {
            return floatval($result['moyenne_brute']);
        }
        
        // Sinon, calculer la moyenne à partir des UE
        $totalPoints = 0;
        $totalCredits = 0;
        
        // Récupérer les UE du semestre
        $query = "SELECT \"idUE\" FROM ue WHERE semestre_idsemestre = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$semestreId]);
        $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ues as $ue) {
            $ueId = $ue['idUE'];
            
            // Récupérer la moyenne de l'UE
            $query = "SELECT moyenne_deliberee FROM moyenne_ue 
                     WHERE \"idUE\" = ? AND matricule = ? 
                     AND session_idsession = ? AND annee_acad_idannee_acad = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$ueId, $matricule, $sessionId, $anneeId]);
            $moyenneUE = $stmt->fetchColumn();
            
            if ($moyenneUE !== false && $moyenneUE !== null) {
                // Calculer les crédits de l'UE
                $query = "SELECT SUM(ROUND((CMI + TD + TP)/ " . $this->heuresParCredit . ", 1)) as credits FROM ecue WHERE \"UE_idUE\" = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$ueId]);
                $credits = $stmt->fetchColumn();
                
                if ($credits !== false && $credits > 0) {
                    $totalPoints += (floatval($moyenneUE) * floatval($credits));
                    $totalCredits += floatval($credits);
                }
            }
        }
        
        // Calculer la moyenne si des crédits sont disponibles
        if ($totalCredits > 0) {
            return $totalPoints / $totalCredits;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Erreur dans getMoyenneSemestre: " . $e->getMessage());
        return null;
    }
}


/**
 * Récupère le nombre de crédits validés par un étudiant
 * @param string $matricule Matricule de l'étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param array $semestres Liste des semestres à considérer
 * @return float Le nombre de crédits validés
 */
public function getCreditsValides($matricule, $sessionId, $anneeId, $semestres) {
    try {
        $creditsValides = 0;
        
        // Pour chaque semestre
        foreach ($semestres as $semestre) {
            $semestreId = $semestre['idsemestre'];
            
            // Récupérer les UE du semestre
            $query = "SELECT u.*, 
                     (SELECT moyenne_deliberee FROM moyenne_ue WHERE \"idUE\" = u.\"idUE\" AND matricule = :matricule
                      AND session_idsession = :sessionId AND annee_acad_idannee_acad = :anneeId) as moyenne,
                     (SELECT est_validee FROM moyenne_ue WHERE \"idUE\" = u.\"idUE\" AND matricule = :matricule
                      AND session_idsession = :sessionId AND annee_acad_idannee_acad = :anneeId) as est_validee
                     FROM ue u
                     WHERE u.semestre_idsemestre = :semestreId
                     ORDER BY u.codeUE";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
            $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
            $stmt->execute();
            
            $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pour chaque UE
            foreach ($ues as $ue) {
                // Vérifier si l'UE est validée
                $estValidee = false;
                
                if (isset($ue['est_validee']) && $ue['est_validee'] == 1) {
                    $estValidee = true;
                } else if (isset($ue['moyenne']) && $ue['moyenne'] !== null && floatval($ue['moyenne']) >= 10) {
                    $estValidee = true;
                } else {
                    // Calculer la moyenne de l'UE si elle n'est pas disponible
                    $query = "SELECT e.*,
                             (SELECT MF FROM cotes_grille WHERE \"ECUE_idECUE\" = e.\"idECUE\" AND matricule = :matricule
                              AND session_idsession = :sessionId AND annee_acad_id = :anneeId) as note,
                             ROUND((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ", 1) as coefficient
                             FROM ecue e
                             WHERE e.\"UE_idUE\" = :ueId";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                    $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
                    $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
                    $stmt->bindParam(':ueId', $ue['idUE'], PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculer la moyenne de l'UE
                    $totalPoints = 0;
                    $totalCoefficients = 0;
                    
                    foreach ($ecues as $ecue) {
                        if (isset($ecue['note']) && $ecue['note'] !== null) {
                            $totalPoints += (floatval($ecue['note']) * floatval($ecue['coefficient']));
                            $totalCoefficients += floatval($ecue['coefficient']);
                        }
                    }
                    
                    if ($totalCoefficients > 0) {
                        $moyenneCalculee = $totalPoints / $totalCoefficients;
                        $estValidee = ($moyenneCalculee >= 10);
                    }
                }
                
                // Si l'UE est validée, ajouter ses crédits
                if ($estValidee) {
                    // Récupérer les ECUE pour calculer les crédits
                    $query = "SELECT SUM(ROUND((CMI + TD + TP)/ " . $this->heuresParCredit . ", 1)) as credits FROM ecue WHERE \"UE_idUE\" = :ueId";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':ueId', $ue['idUE'], PDO::PARAM_INT);
                    $stmt->execute();
                    $credits = $stmt->fetchColumn();
                    
                    if ($credits !== false && $credits > 0) {
                        $creditsValides += floatval($credits);
                    }
                }
            }
        }
        
        return $creditsValides;
    } catch (PDOException $e) {
        error_log("Erreur dans getCreditsValides: " . $e->getMessage());
        return 0;
    }
}

/**
 * Récupère le nombre total de crédits pour un ensemble de semestres
 * @param array $semestres Liste des semestres à considérer
 * @return float Le nombre total de crédits
 */
public function getCreditsTotal($semestres) {
    try {
        $creditsTotal = 0;
        
        // Pour chaque semestre
        foreach ($semestres as $semestre) {
            $semestreId = $semestre['idsemestre'];
            
            // Récupérer le total des crédits pour ce semestre
            $query = "SELECT SUM(ROUND((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ", 1)) as total_credits
                     FROM ecue e
                     JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                     WHERE u.semestre_idsemestre = :semestreId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchColumn();
            if ($result !== false) {
                $creditsTotal += floatval($result);
            }
        }
        
        return $creditsTotal;
    } catch (PDOException $e) {
        error_log("Erreur dans getCreditsTotal: " . $e->getMessage());
        return 0;
    }
}

/**
 * Récupère la moyenne annuelle d'un étudiant
 * @param string $matricule Matricule de l'étudiant
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param array $semestres Liste des semestres à considérer
 * @return float|null La moyenne annuelle ou null si non disponible
 */
public function getMoyenneAnnuelle($matricule, $sessionId, $anneeId, $semestres) {
    try {
        // D'abord vérifier si une moyenne annuelle existe déjà
        $query = "SELECT ma.moyenne_brute 
                 FROM moyenne_annuelle ma
                 JOIN promotion p ON ma.idpromotion = p.idpromotion
                 JOIN semestre s ON p.idpromotion = s.promotion_idpromotion
                 WHERE ma.matricule = :matricule 
                 AND ma.session_idsession = :sessionId 
                 AND ma.annee_acad_idannee_acad = :anneeId
                 AND s.idsemestre = :semestreId
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':semestreId', $semestres[0]['idsemestre'], PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['moyenne_brute'] !== null) {
            return floatval($result['moyenne_brute']);
        }
        
        // Sinon, calculer la moyenne à partir des moyennes de semestre
        $totalPoints = 0;
        $totalCredits = 0;
        
        foreach ($semestres as $semestre) {
            $semestreId = $semestre['idsemestre'];
            
            // Récupérer la moyenne du semestre
            $moyenneSemestre = $this->getMoyenneSemestre($matricule, $sessionId, $anneeId, $semestreId);
            
            if ($moyenneSemestre !== null) {
                // Récupérer les crédits du semestre
                $query = "SELECT SUM(ROUND((e.CMI + e.TD + e.TP)/ " . $this->heuresParCredit . ", 1)) as credits
                         FROM ecue e
                         JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                         WHERE u.semestre_idsemestre = :semestreId";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':semestreId', $semestreId, PDO::PARAM_INT);
                $stmt->execute();
                
                $credits = $stmt->fetchColumn();
                if ($credits !== false && $credits > 0) {
                    $totalPoints += ($moyenneSemestre * floatval($credits));
                    $totalCredits += floatval($credits);
                }
            }
        }
        
        // Calculer la moyenne annuelle si des crédits sont disponibles
        if ($totalCredits > 0) {
            return $totalPoints / $totalCredits;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Erreur dans getMoyenneAnnuelle: " . $e->getMessage());
        return null;
    }
}

public function isDeliberationPubliee($promotionId) {
    try {
        $query = "SELECT COUNT(*) AS count FROM deliberation
                  WHERE idpromotion = :promotion
                  AND statut = 'Publiée'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotion', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les promotions
 * @return array La liste de toutes les promotions
 */
public function getAllPromotions() {
    $query = "SELECT p.idpromotion, p.\"designationPromotion\", p.cycle, o.\"designationOrientation\", a.designation as annee_acad 
    FROM promotion p JOIN orientation o ON p.orientation_idorientation = o.idorientation JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad ORDER BY a.designation DESC, o.\"designationOrientation\", p.cycle, p.designationPromotion";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getHeuresParCredit($bureauId, $sessionId, $anneeId) {
    // Initialize the default value
    $heuresParCredit = 25; // New default is 25 hours per credit
    
    try {
        // Get the deliberation configuration
        $sql = "SELECT heures_par_credit FROM configuration_deliberation 
                WHERE idbureau = ? AND session_idsession = ? AND annee_acad_idannee_acad = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bureauId, $sessionId, $anneeId]);
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If the configuration exists and has a value for heures_par_credit, use it
            if (isset($result['heures_par_credit']) && !is_null($result['heures_par_credit'])) {
                $heuresParCredit = intval($result['heures_par_credit']);
            }
        }
    } catch (PDOException $e) {
        // Log the error but continue with the default value
        error_log("Error retrieving hours per credit: " . $e->getMessage());
    }
    
    return $heuresParCredit;
}

    /**
     * Récupérer les étudiants par promotion avec pagination
     * @param int $promotionId ID de la promotion
     * @param int $anneeId ID de l'année académique
     * @param int $offset Position de départ
     * @param int $limit Nombre d'étudiants à récupérer
     * @return array Tableau des étudiants
     */
    public function getEtudiantsByPromotionPaginated($promotionId, $anneeId, $offset = 0, $limit = 25)
    {
        $query = "SELECT e.idetudiant, e.matricule, e.noms, e.sexe, e.\"dateNaissance\", e.telephone, e.adressemail
                 FROM etudiant e
                 WHERE e.promotion_idpromotion = :promotionId 
                 AND e.annee_acad_idannee_acad = :anneeId
                 AND e.est_actif = 1
                 ORDER BY e.noms ASC
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter le nombre total d'étudiants dans une promotion
     * @param int $promotionId ID de la promotion
     * @param int $anneeId ID de l'année académique
     * @return int Nombre total d'étudiants
     */
    public function countEtudiantsByPromotion($promotionId, $anneeId)
    {
        $query = "SELECT COUNT(*) as total
                 FROM etudiant e
                 WHERE e.promotion_idpromotion = :promotionId 
                 AND e.annee_acad_idannee_acad = :anneeId
                 AND e.est_actif = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total']);
    }


/**
 * Vérifie si un étudiant a des dettes (ECUE non validées dont l'UE a une moyenne < 10) des années précédentes du même cycle
 * @param string $matricule Matricule de l'étudiant
 * @param int $anneeId ID de l'année académique actuelle
 * @param int $promotionId ID de la promotion actuelle
 * @return bool Retourne true si l'étudiant a des dettes, false sinon
 */
public function etudiantADesDettes($matricule, $anneeId, $promotionId) {
    try {
        // Obtenir le cycle de la promotion actuelle
        $query = "SELECT cycle FROM promotion WHERE idpromotion = :promotionId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        $cycleActuel = $stmt->fetchColumn();

        if (!$cycleActuel) return false;

        // Vérifier s'il existe des ECUE en échec dont l'UE a une moyenne < 10 dans les années précédentes du même cycle
        $query = "SELECT COUNT(DISTINCT cg.\"ECUE_idECUE\") as nb_dettes
                  FROM cotes_grille cg
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
                  JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
                  JOIN etudiant et ON cg.matricule = et.matricule AND et.promotion_idpromotion = p.idpromotion
                  WHERE cg.matricule = :matricule
                  AND p.cycle = :cycle
                  AND cg.annee_acad_id < :anneeId
                  AND cg.MF < 10  -- ECUE en échec
                  -- Vérifier que l'UE a une moyenne < 10
                  AND EXISTS (
                      SELECT 1
                      FROM (
                          SELECT 
                              e2.\"UE_idUE\",
                              SUM(cg2.MF * ((e2.CMI + e2.TD + e2.TP) / " . $this->heuresParCredit . ")) / 
                              SUM((e2.CMI + e2.TD + e2.TP) / " . $this->heuresParCredit . ") AS moyenne_ue
                          FROM cotes_grille cg2
                          JOIN ecue e2 ON cg2.\"ECUE_idECUE\" = e2.\"idECUE\"
                          WHERE cg2.matricule = cg.matricule
                          AND e2.\"UE_idUE\" = e.\"UE_idUE\"
                          AND cg2.session_idsession = cg.session_idsession
                          AND cg2.annee_acad_id = cg.annee_acad_id
                          GROUP BY e2.\"UE_idUE\"
                          HAVING moyenne_ue < 10
                      ) AS ue_check
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $cycleActuel, PDO::PARAM_STR);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['nb_dettes'] > 0;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification des dettes: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste détaillée des dettes d'un étudiant
 * @param string $matricule Matricule de l'étudiant
 * @param int $anneeId ID de l'année académique actuelle
 * @param int $promotionId ID de la promotion actuelle
 * @return array Liste des dettes avec détails
 */
public function getDetailsDettesEtudiant($matricule, $anneeId, $promotionId) {
    try {
        // Obtenir le cycle de la promotion actuelle
        $query = "SELECT cycle FROM promotion WHERE idpromotion = :promotionId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->execute();
        $cycleActuel = $stmt->fetchColumn();

        if (!$cycleActuel) return [];

        // Récupérer les ECUE en dette avec les détails
        $query = "SELECT DISTINCT 
                    cg.\"ECUE_idECUE\",
                    e.\"designationECUE\",
                    u.\"idUE\",
                    u.\"designationUE\",
                    cg.MF as note_ecue,
                    ROUND((e.CMI + e.TD + e.TP) / " . $this->heuresParCredit . ", 2) as credits_ecue,
                    p.\"designationPromotion\",
                    aa.designation as annee_academique,
                    sess.\"designSession\",
                    -- Calculer la moyenne de l'UE
                    (SELECT 
                        SUM(cg3.MF * ((e3.CMI + e3.TD + e3.TP) / " . $this->heuresParCredit . ")) / 
                        SUM((e3.CMI + e3.TD + e3.TP) / " . $this->heuresParCredit . ")
                     FROM cotes_grille cg3
                     JOIN ecue e3 ON cg3.\"ECUE_idECUE\" = e3.\"idECUE\"
                     WHERE cg3.matricule = cg.matricule
                     AND e3.\"UE_idUE\" = e.\"UE_idUE\"
                     AND cg3.session_idsession = cg.session_idsession
                     AND cg3.annee_acad_id = cg.annee_acad_id
                    ) as moyenne_ue
                  FROM cotes_grille cg
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre sem ON u.semestre_idsemestre = sem.idsemestre
                  JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
                  JOIN etudiant et ON cg.matricule = et.matricule AND et.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad aa ON cg.annee_acad_id = aa.idannee_acad
                  JOIN session sess ON cg.session_idsession = sess.idsession
                  WHERE cg.matricule = :matricule
                  AND p.cycle = :cycle
                  AND cg.annee_acad_id < :anneeId
                  AND cg.MF < 10  -- ECUE en échec
                  HAVING moyenne_ue < 10  -- UE en échec
                  ORDER BY cg.annee_acad_id DESC, u.\"idUE\", e.idECUE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':cycle', $cycleActuel, PDO::PARAM_STR);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des détails des dettes: " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifie si un ECUE spécifique est une dette pour un étudiant
 * @param string $matricule Matricule de l'étudiant
 * @param int $ecueId ID de l'ECUE
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @return bool True si l'ECUE est une dette, false sinon
 */
public function isEcueDette($matricule, $ecueId, $sessionId, $anneeId) {
    try {
        // Récupérer la note de l'ECUE
        $query = "SELECT MF FROM cotes_grille 
                  WHERE matricule = :matricule 
                  AND \"ECUE_idECUE\" = :ecueId 
                  AND session_idsession = :sessionId 
                  AND annee_acad_id = :anneeId";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $noteEcue = $stmt->fetchColumn();
        
        // Si l'ECUE n'est pas en échec, ce n'est pas une dette
        if ($noteEcue === false || $noteEcue >= 10) {
            return false;
        }
        
        // Récupérer l'UE de l'ECUE
        $query = "SELECT \"UE_idUE\" FROM ecue WHERE \"idECUE\" = :ecueId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ecueId', $ecueId, PDO::PARAM_INT);
        $stmt->execute();
        $ueId = $stmt->fetchColumn();
        
        if (!$ueId) return false;
        
        // Calculer la moyenne de l'UE
        $moyenneUE = $this->calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId);
        
        // L'ECUE est une dette seulement si l'UE a une moyenne < 10
        return $moyenneUE !== false && $moyenneUE < 10;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification si ECUE est dette: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre les dettes d'un étudiant lors du passage de classe
 * @param string $matricule Matricule de l'étudiant
 * @param int $promotionId ID de la promotion
 * @param int $sessionId ID de la session
 * @param int $anneeId ID de l'année académique
 * @param int $userId ID de l'utilisateur
 * @return bool True si l'enregistrement s'est bien passé, false sinon
 */
public function enregistrerDettesEtudiant($matricule, $promotionId, $sessionId, $anneeId, $userId) {
    try {
        // Récupérer tous les ECUE en échec dont l'UE a une moyenne < 10
        $query = "SELECT DISTINCT 
                    cg.\"ECUE_idECUE\",
                    e.\"UE_idUE\",
                    u.semestre_idsemestre,
                    cg.MF as note_obtenue,
                    ROUND((e.CMI + e.TD + e.TP) / " . $this->heuresParCredit . ", 2) as credits_ecue
                  FROM cotes_grille cg
                  JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                  WHERE cg.matricule = :matricule
                  AND s.promotion_idpromotion = :promotionId
                  AND cg.session_idsession = :sessionId
                  AND cg.annee_acad_id = :anneeId
                  AND cg.MF < 10  -- ECUE en échec
                  -- Vérifier que l'UE a une moyenne < 10
                  AND EXISTS (
                      SELECT 1
                      FROM (
                          SELECT 
                              e2.\"UE_idUE\",
                              SUM(cg2.MF * ((e2.CMI + e2.TD + e2.TP) / " . $this->heuresParCredit . ")) / 
                              SUM((e2.CMI + e2.TD + e2.TP) / " . $this->heuresParCredit . ") AS moyenne_ue
                          FROM cotes_grille cg2
                          JOIN ecue e2 ON cg2.\"ECUE_idECUE\" = e2.\"idECUE\"
                          WHERE cg2.matricule = cg.matricule
                          AND e2.\"UE_idUE\" = e.\"UE_idUE\"
                          AND cg2.session_idsession = cg.session_idsession
                          AND cg2.annee_acad_id = cg.annee_acad_id
                          GROUP BY e2.\"UE_idUE\"
                          HAVING moyenne_ue < 10
                      ) AS ue_check
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $dettes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Enregistrer chaque dette
        foreach ($dettes as $dette) {
            // Vérifier si la dette n'existe pas déjà
            $checkQuery = "SELECT id_dette FROM dette_etudiant 
                          WHERE matricule = :matricule 
                          AND \"ECUE_idECUE\" = :ecueId 
                          AND statut = 'En cours'";
            
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $checkStmt->bindParam(':ecueId', $dette['ECUE_idECUE'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                // Insérer la nouvelle dette
                $insertQuery = "INSERT INTO dette_etudiant (
                                    matricule, \"ECUE_idECUE\", \"UE_idUE\", semestre_idsemestre,
                                    promotion_idpromotion, session_idsession, annee_acad_idannee_acad,
                                    note_obtenue, credits_ecue, statut, \"idUser\"
                                ) VALUES (
                                    :matricule, :ecueId, :ueId, :semestreId,
                                    :promotionId, :sessionId, :anneeId,
                                    :noteObtenue, :creditsEcue, 'En cours', :userId
                                )";
                
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $insertStmt->bindParam(':ecueId', $dette['ECUE_idECUE'], PDO::PARAM_INT);
                $insertStmt->bindParam(':ueId', $dette['UE_idUE'], PDO::PARAM_INT);
                $insertStmt->bindParam(':semestreId', $dette['semestre_idsemestre'], PDO::PARAM_INT);
                $insertStmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
                $insertStmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
                $insertStmt->bindParam(':anneeId', $anneeId, PDO::PARAM_INT);
                $insertStmt->bindParam(':noteObtenue', $dette['note_obtenue']);
                $insertStmt->bindParam(':creditsEcue', $dette['credits_ecue']);
                $insertStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $insertStmt->execute();
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement des dettes: " . $e->getMessage());
        return false;
    }
}



    












    












}





















        
    
       

    














