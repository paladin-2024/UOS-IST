<?php
class Recours {
    private $conn;

    public function __construct() {
        $this->conn = Connexion::getInstance()->getPDO();
    }

    // Créer un nouveau recours
    public function createRecours($matricule, $idEcue, $idSession, $idAnnee, $motif, $description, $preuve, $idCreateur) {
        $sql = "INSERT INTO recours (matricule, id_ecue, id_session, id_annee_acad, motif, description, preuve, id_createur) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$matricule, $idEcue, $idSession, $idAnnee, $motif, $description, $preuve, $idCreateur]);
    }

    // Récupérer tous les recours pour un étudiant spécifique
    public function getRecoursByEtudiant($matricule) {
        $sql = "SELECT r.*, e.designationECUE, s.description as session_desc, a.designation as annee_desc 
                FROM recours r 
                JOIN ecue e ON r.id_ecue = e.idECUE 
                JOIN session s ON r.id_session = s.idsession 
                JOIN annee_academique a ON r.id_annee_acad = a.idannee_acad 
                WHERE r.matricule = ? 
                ORDER BY r.date_creation DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$matricule]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer tous les recours pour un enseignant spécifique
    public function getRecoursByEnseignant($idEnseignant) {
        $sql = "SELECT r.*, e.designationECUE, et.noms as nom_etudiant, s.description as session_desc, 
                      a.designation as annee_desc, u.attributions 
                FROM recours r 
                JOIN ecue e ON r.id_ecue = e.idECUE 
                JOIN etudiant et ON r.matricule = et.matricule 
                JOIN session s ON r.id_session = s.idsession 
                JOIN annee_academique a ON r.id_annee_acad = a.idannee_acad 
                JOIN ue_enseignant ue ON e.idUE = ue.idUE 
                LEFT JOIN affectation_ecue af ON e.idECUE = af.idECUE AND af.idagent = ? 
                WHERE (ue.idenseignant = ? OR af.idagent = ?) 
                ORDER BY r.date_creation DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idEnseignant, $idEnseignant, $idEnseignant]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer tous les recours pour un bureau de jury spécifique
    public function getRecoursByJury($idBureau) {
        $sql = "SELECT r.*, e.designationECUE, et.noms as nom_etudiant, s.description as session_desc, 
                      a.designation as annee_desc, rr.id_reponse, rr.nouvelle_note, rr.commentaire, 
                      rr.valide_jury, rr.date_reponse 
                FROM recours r 
                JOIN ecue e ON r.id_ecue = e.idECUE 
                JOIN etudiant et ON r.matricule = et.matricule 
                JOIN session s ON r.id_session = s.idsession 
                JOIN annee_academique a ON r.id_annee_acad = a.idannee_acad 
                JOIN promotion_jury pj ON et.idpromotion = pj.idpromotion 
                LEFT JOIN recours_reponse rr ON r.id_recours = rr.id_recours 
                WHERE pj.idbureau = ? 
                ORDER BY r.date_creation DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idBureau]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Créer une réponse à un recours (par l'enseignant)
    public function createReponse($idRecours, $nouvelleNote, $commentaire, $idEnseignant) {
        $sql = "INSERT INTO recours_reponse (id_recours, nouvelle_note, commentaire, id_enseignant) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $success = $stmt->execute([$idRecours, $nouvelleNote, $commentaire, $idEnseignant]);
        
        if ($success) {
            // Mettre à jour le statut du recours
            $this->updateRecoursStatus($idRecours, 'En traitement');
        }
        
        return $success;
    }

    // Valider une réponse (par le jury)
    public function validateReponse($idReponse, $idValidateur) {
        $sql = "UPDATE recours_reponse 
                SET valide_jury = 1, id_validateur = ?, date_validation = NOW() 
                WHERE id_reponse = ?";
        $stmt = $this->conn->prepare($sql);
        $success = $stmt->execute([$idValidateur, $idReponse]);
        
        if ($success) {
            // Récupérer l'ID du recours
            $sql = "SELECT id_recours, nouvelle_note FROM recours_reponse WHERE id_reponse = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idReponse]);
            $reponse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reponse) {
                // Mettre à jour la note dans la table notes
                $this->updateNote($reponse['id_recours'], $reponse['nouvelle_note']);
                
                // Mettre à jour le statut du recours
                $this->updateRecoursStatus($reponse['id_recours'], 'Approuvé');
            }
        }
        
        return $success;
    }

    // Rejeter un recours (par le jury)
    public function rejectRecours($idRecours, $idValidateur) {
        $sql = "UPDATE recours SET statut = 'Rejeté' WHERE id_recours = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$idRecours]);
    }

    // Mettre à jour le statut d'un recours
    private function updateRecoursStatus($idRecours, $statut) {
        $sql = "UPDATE recours SET statut = ? WHERE id_recours = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$statut, $idRecours]);
    }

    // Mettre à jour la note dans la table notes
    private function updateNote($idRecours, $nouvelleNote) {
        // D'abord, récupérer les informations du recours
        $sql = "SELECT matricule, id_ecue, id_session, id_annee_acad FROM recours WHERE id_recours = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idRecours]);
        $recours = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recours) {
            // Vérifier si une note existe déjà
            $sql = "SELECT idnote FROM notes 
                    WHERE matricule = ? AND idECUE = ? AND idsession = ? AND idannee = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $recours['matricule'], 
                $recours['id_ecue'], 
                $recours['id_session'], 
                $recours['id_annee_acad']
            ]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($note) {
                // Mettre à jour la note existante
                $sql = "UPDATE notes SET MF = ? WHERE idnote = ?";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([$nouvelleNote, $note['idnote']]);
            } else {
                // Insérer une nouvelle note
                $sql = "INSERT INTO notes (matricule, idECUE, idsession, idannee, MF) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    $recours['matricule'], 
                    $recours['id_ecue'], 
                    $recours['id_session'], 
                    $recours['id_annee_acad'],
                    $nouvelleNote
                ]);
            }
        }
        
        return false;
    }

    public function getRecoursByMatricule($matricule, $anneeAcadId) {
        try {
            $query = "SELECT r.*, e.designationECUE, s.designSession
                      FROM recours r
                      JOIN ecue e ON r.id_ecue = e.idECUE
                      JOIN session s ON r.id_session = s.idsession
                      WHERE r.matricule = :matricule 
                      AND r.id_annee_acad = :annee_acad
                      ORDER BY r.date_creation DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->bindParam(':annee_acad', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }
    
    public function getRecoursReponse($idRecours) {
        try {
            $query = "SELECT * FROM recours_reponse 
                      WHERE id_recours = :id_recours
                      ORDER BY date_reponse DESC LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_recours', $idRecours, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    /**
 * Récupère un recours par son ID
 * @param int $id ID du recours
 * @return array|false Les données du recours ou false si non trouvé
 */
public function getRecoursById($id) {
    $query = "SELECT r.*, e.designationECUE, ue.designationUE, s.designSession,
                     et.noms, et.matricule,
                     p.designationPromotion as promotion, o.designationOrientation as orientation
              FROM recours r
              LEFT JOIN ecue e ON r.id_ecue = e.idECUE
              LEFT JOIN ue ON e.ue_idUE = ue.idUE
              LEFT JOIN session s ON r.id_session = s.idsession
              LEFT JOIN etudiant et ON r.matricule = et.matricule
              LEFT JOIN promotion p ON et.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              WHERE r.id_recours = :id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


    


    
 
}
