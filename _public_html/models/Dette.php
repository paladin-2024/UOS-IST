<?php
require_once dirname(__DIR__) . '/config/Connexion.php';

class Dette {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Récupérer toutes les dettes d'un étudiant
     */
    public function getDettesEtudiant($matricule, $statut = null) {
        // Vérifier si la vue existe
        try {
            $checkView = $this->db->prepare("SELECT 1 FROM v_dettes_etudiants LIMIT 1");
            $checkView->execute();
            $viewExists = true;
        } catch (PDOException $e) {
            $viewExists = false;
        }
        
        if ($viewExists) {
            // Utiliser la vue si elle existe
            $sql = "SELECT * FROM v_dettes_etudiants WHERE matricule = :matricule";
            $params = ['matricule' => $matricule];
            
            if ($statut) {
                $sql .= " AND statut = :statut";
                $params['statut'] = $statut;
            }
            
            $sql .= " ORDER BY annee_academique DESC, \"numeroSemestre\" ASC";
            
        } else {
            // Requête directe si la vue n'existe pas
            $sql = "SELECT 
                        d.id_dette,
                        d.matricule,
                        e.noms as nom_etudiant,
                        d.\"ECUE_idECUE\",
                        ec.\"designationECUE\",
                        d.\"UE_idUE\",
                        ue.\"designationUE\",
                        d.semestre_idsemestre,
                        COALESCE(s.\"numeroSemestre\", d.semestre_idsemestre) as \"numeroSemestre\",
                        d.promotion_idpromotion,
                        COALESCE(p.\"designationPromotion\", 'N/A') as \"designationPromotion\",
                        d.session_idsession,
                        d.annee_acad_idannee_acad,
                        COALESCE(aa.designation, 'N/A') as annee_academique,
                        d.note_obtenue,
                        d.credits_ecue,
                        d.statut,
                        d.date_creation,
                        d.note_rachat,
                        d.session_rachat
                    FROM dette_etudiant d
                    LEFT JOIN etudiant e ON d.matricule = e.matricule
                    LEFT JOIN ecue ec ON d.\"ECUE_idECUE\" = ec.\"idECUE\"
                    LEFT JOIN ue ue ON d.\"UE_idUE\" = ue.\"idUE\"
                    LEFT JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
                    LEFT JOIN promotion p ON d.promotion_idpromotion = p.idpromotion
                    LEFT JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
                    WHERE d.matricule = :matricule";
            
            $params = ['matricule' => $matricule];
            
            if ($statut) {
                $sql .= " AND d.statut = :statut";
                $params['statut'] = $statut;
            }
            
            $sql .= " ORDER BY aa.designation DESC, s.\"numeroSemestre\" ASC";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les dettes d'une promotion
     */
    public function getDettesPromotion($promotionId, $anneeId, $sessionId = null) {
        $sql = "SELECT 
                    d.*,
                    COUNT(DISTINCT d.matricule) as nombre_etudiants,
                    COUNT(d.id_dette) as nombre_dettes,
                    SUM(d.credits_ecue) as total_credits_dettes
                FROM v_dettes_etudiants d
                WHERE d.promotion_idpromotion = :promotion
                AND d.annee_acad_idannee_acad = :annee";
        
        $params = [
            'promotion' => $promotionId,
            'annee' => $anneeId
        ];
        
        if ($sessionId) {
            $sql .= " AND d.session_idsession = :session";
            $params['session'] = $sessionId;
        }
        
        $sql .= " GROUP BY d.matricule ORDER BY d.nom_etudiant";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer une nouvelle dette
     */
    public function enregistrerDette($data) {
        try {
            $sql = "INSERT INTO dette_etudiant (
                        matricule, \"ECUE_idECUE\", \"UE_idUE\", semestre_idsemestre,
                        promotion_idpromotion, session_idsession, annee_acad_idannee_acad,
                        note_obtenue, credits_ecue, statut, \"idUser\"
                    ) VALUES (
                        :matricule, :ecue, :ue, :semestre,
                        :promotion, :session, :annee,
                        :note, :credits, 'En cours', :user
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($data);
            
            if ($result) {
                $detteId = $this->db->lastInsertId();
                $this->ajouterHistorique($detteId, 'Creation', 'Dette créée', $data['user']);
                return $detteId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Erreur enregistrement dette: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistrer les notes de rachat
     */
    public function enregistrerNoteRachat($detteId, $noteCC, $noteEX, $sessionId, $anneeId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Enregistrer les évaluations
            if ($noteCC !== null) {
                $sql = "INSERT INTO dette_evaluation (
                            id_dette, type_evaluation, note, date_evaluation,
                            session_idsession, annee_acad_idannee_acad, \"idUser\"
                        ) VALUES (
                            :dette, 'CC', :note, CURDATE(),
                            :session, :annee, :user
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'dette' => $detteId,
                    'note' => $noteCC,
                    'session' => $sessionId,
                    'annee' => $anneeId,
                    'user' => $userId
                ]);
            }
            
            if ($noteEX !== null) {
                $sql = "INSERT INTO dette_evaluation (
                            id_dette, type_evaluation, note, date_evaluation,
                            session_idsession, annee_acad_idannee_acad, \"idUser\"
                        ) VALUES (
                            :dette, 'EX', :note, CURDATE(),
                            :session, :annee, :user
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'dette' => $detteId,
                    'note' => $noteEX,
                    'session' => $sessionId,
                    'annee' => $anneeId,
                    'user' => $userId
                ]);
            }
            
            // Calculer la moyenne finale
            // Récupérer les pondérations depuis la configuration
$universite = new Universite();
$ponderations = $universite->getPonderationsDefaut();
$moyenneFinale = ($noteCC * $ponderations['ponderation_cc']) + ($noteEX * $ponderations['ponderation_ex']);
            
            // Mettre à jour la dette
            $statut = $moyenneFinale >= 10 ? 'Validée' : 'En cours';
            $dateValidation = $moyenneFinale >= 10 ? 'NOW()' : 'NULL';
            
            $sql = "UPDATE dette_etudiant 
                    SET note_rachat = :note,
                        session_rachat = :session,
                        annee_rachat = :annee,
                        statut = :statut,
                        date_validation = $dateValidation
                    WHERE id_dette = :dette";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'note' => $moyenneFinale,
                'session' => $sessionId,
                'annee' => $anneeId,
                'statut' => $statut,
                'dette' => $detteId
            ]);
            
            // Ajouter à l'historique
            $this->ajouterHistorique($detteId, 'Modification', 
                "Note de rachat enregistrée: $moyenneFinale", $userId);
            
            $this->db->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur enregistrement note rachat: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les statistiques des dettes
     */
    public function getStatistiquesDettes($promotionId, $anneeId) {
        $sql = "SELECT 
                    COUNT(DISTINCT matricule) as nombre_etudiants_avec_dettes,
                    COUNT(id_dette) as nombre_total_dettes,
                    SUM(CASE WHEN statut = 'Validée' THEN 1 ELSE 0 END) as dettes_validees,
                    SUM(CASE WHEN statut = 'En cours' THEN 1 ELSE 0 END) as dettes_en_cours,
                    SUM(credits_ecue) as total_credits_dettes,
                    AVG(note_obtenue) as moyenne_notes_dettes,
                    AVG(note_rachat) as moyenne_notes_rachat
                FROM v_dettes_etudiants
                WHERE promotion_idpromotion = :promotion
                AND annee_acad_idannee_acad = :annee";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'promotion' => $promotionId,
            'annee' => $anneeId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifier si un étudiant peut passer avec des dettes
     */
    public function verifierPassageAvecDettes($matricule, $promotionId, $sessionId, $anneeId) {
        $stmt = $this->db->prepare("CALL verifier_passage_avec_dettes(:matricule, :promotion, :session, :annee, @peut_monter, @message)");
        $stmt->execute([
            'matricule' => $matricule,
            'promotion' => $promotionId,
            'session' => $sessionId,
            'annee' => $anneeId
        ]);
        
        $result = $this->db->query("SELECT @peut_monter as peut_monter, @message as message")->fetch(PDO::FETCH_ASSOC);
        
        return [
            'peut_monter' => (bool)$result['peut_monter'],
            'message' => $result['message']
        ];
    }

    /**
     * Enregistrer automatiquement les dettes lors du passage
     */
    public function enregistrerDettesPassage($matricule, $promotionId, $sessionId, $anneeId, $userId) {
        try {
            $stmt = $this->db->prepare("CALL enregistrer_dettes_passage(:matricule, :promotion, :session, :annee, :user)");
            return $stmt->execute([
                'matricule' => $matricule,
                'promotion' => $promotionId,
                'session' => $sessionId,
                'annee' => $anneeId,
                'user' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Erreur enregistrement dettes passage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer l'historique d'une dette
     */
    public function getHistoriqueDette($detteId) {
        $sql = "SELECT h.*, u.\"nomUser\" 
                FROM dette_historique h
                JOIN t_users u ON h.\"idUser\" = u.\"idUser\"
                WHERE h.id_dette = :dette
                ORDER BY h.date_action DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dette' => $detteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajouter une entrée à l'historique
     */
    private function ajouterHistorique($detteId, $action, $details, $userId) {
        $sql = "INSERT INTO dette_historique (id_dette, action, details, \"idUser\")
                VALUES (:dette, :action, :details, :user)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'dette' => $detteId,
            'action' => $action,
            'details' => $details,
            'user' => $userId
        ]);
    }

    /**
     * Récupérer les dettes par semestre pour un étudiant
     */
    public function getDettesParSemestre($matricule, $anneeId) {
        $sql = "SELECT 
                    s.\"numeroSemestre\",
                    s.idsemestre,
                    COUNT(d.id_dette) as nombre_dettes,
                    SUM(d.credits_ecue) as total_credits,
                    STRING_AGG(d.\"designationECUE\", ', ') as ecues
                FROM v_dettes_etudiants d
                JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
                WHERE d.matricule = :matricule
                AND d.annee_acad_idannee_acad = :annee
                AND d.statut = 'En cours'
                GROUP BY s.idsemestre, s.\"numeroSemestre\"
                ORDER BY s.\"numeroSemestre\"";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'matricule' => $matricule,
            'annee' => $anneeId
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les évaluations d'une dette
     */
    public function getEvaluationsDette($detteId) {
        $sql = "SELECT * FROM dette_evaluation 
                WHERE id_dette = :dette
                ORDER BY date_evaluation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dette' => $detteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}