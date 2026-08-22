<?php
class Evaluation {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Ajouter une évaluation
    public function addEvaluation($titre, $description, $dateEvaluation, $idECUE, $idType, 
                                 $ponderation, $idSession, $estVisible, $idUser) {
        $query = "INSERT INTO evaluations (titre, description, date_evaluation, idECUE, 
                 idType, ponderation, session_idsession, est_visible, idUser) 
                 VALUES (:titre, :description, :dateEvaluation, :idECUE, :idType, 
                 :ponderation, :idSession, :estVisible, :idUser)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'titre' => $titre,
            'description' => $description,
            'dateEvaluation' => $dateEvaluation,
            'idECUE' => $idECUE,
            'idType' => $idType,
            'ponderation' => $ponderation,
            'idSession' => $idSession,
            'estVisible' => $estVisible,
            'idUser' => $idUser
        ]);
    }

    // Ajouter un point à un étudiant
    public function addPoint($coteObtenu, $idEvaluation, $idECUE, $idSession, $matricule, $idAnneeAcad) {
        // Vérifier si le point existe déjà
        $query = "SELECT COUNT(*) as count FROM points 
                 WHERE typeEvaluation = :idEvaluation AND ECUE_idECUE = :idECUE 
                 AND session_idsession = :idSession AND matricule = :matricule
                 AND annee_acad_id = :idAnneeAcad";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEvaluation' => $idEvaluation,
            'idECUE' => $idECUE,
            'idSession' => $idSession,
            'matricule' => $matricule,
            'idAnneeAcad' => $idAnneeAcad
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Mettre à jour le point existant
            $query = "UPDATE points SET coteObtenu = :coteObtenu 
                     WHERE typeEvaluation = :idEvaluation AND ECUE_idECUE = :idECUE 
                     AND session_idsession = :idSession AND matricule = :matricule
                     AND annee_acad_id = :idAnneeAcad";
        } else {
            // Ajouter un nouveau point
            $query = "INSERT INTO points (coteObtenu, typeEvaluation, ECUE_idECUE, 
                     session_idsession, matricule, annee_acad_id) 
                     VALUES (:coteObtenu, :idEvaluation, :idECUE, :idSession, :matricule, :idAnneeAcad)";
        }
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'coteObtenu' => $coteObtenu,
            'idEvaluation' => $idEvaluation,
            'idECUE' => $idECUE,
            'idSession' => $idSession,
            'matricule' => $matricule,
            'idAnneeAcad' => $idAnneeAcad
        ]);
    }

    // Configurer les formules de calcul des moyennes
    public function configureFormules($idECUE, $idSession, $idAnneeAcad, $formuleCC, 
                                    $formuleEX, $ponderationCC, $ponderationEX, $idUser) {
        $query = "INSERT INTO configuration_moyenne (idECUE, session_idsession, annee_acad_id, 
                 formule_cc, formule_ex, ponderation_cc, ponderation_ex, idUser) 
                 VALUES (:idECUE, :idSession, :idAnneeAcad, :formuleCC, :formuleEX, 
                 :ponderationCC, :ponderationEX, :idUser) 
                 ON DUPLICATE KEY UPDATE 
                 formule_cc = :formuleCC, formule_ex = :formuleEX, 
                 ponderation_cc = :ponderationCC, ponderation_ex = :ponderationEX, 
                 idUser = :idUser";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idECUE' => $idECUE,
            'idSession' => $idSession,
            'idAnneeAcad' => $idAnneeAcad,
            'formuleCC' => $formuleCC,
            'formuleEX' => $formuleEX,
            'ponderationCC' => $ponderationCC,
            'ponderationEX' => $ponderationEX,
            'idUser' => $idUser
        ]);
    }

    // Calculer la moyenne pour un étudiant dans un ECUE
    public function calculateMoyenne($matricule, $idECUE, $idSession, $idAnneeAcad) {
        // Récupérer la configuration
        $query = "SELECT * FROM configuration_moyenne 
                 WHERE idECUE = :idECUE AND session_idsession = :idSession 
                 AND annee_acad_id = :idAnneeAcad";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idECUE' => $idECUE,
            'idSession' => $idSession,
            'idAnneeAcad' => $idAnneeAcad
        ]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return false;
        }
        
        // Extraire les IDs des évaluations pour le CC et l'EX
        preg_match_all('/\bid(\d+)\b/', $config['formule_cc'], $ccMatches);
        preg_match_all('/\bid(\d+)\b/', $config['formule_ex'], $exMatches);
        
        $ccIds = $ccMatches[1] ?? [];
        $exIds = $exMatches[1] ?? [];
        
        // Récupérer les points pour le CC
        $moyenneCC = $this->calculatePartialMoyenne($matricule, $idECUE, $idSession, $idAnneeAcad, $ccIds, $config['formule_cc']);
        
        // Récupérer les points pour l'EX
        $moyenneEX = $this->calculatePartialMoyenne($matricule, $idECUE, $idSession, $idAnneeAcad, $exIds, $config['formule_ex']);
        
        // Calculer la moyenne finale
        $moyenneFinale = ($moyenneCC * $config['ponderation_cc']) + ($moyenneEX * $config['ponderation_ex']);
        
        return [
            'cc' => $moyenneCC,
            'ex' => $moyenneEX,
            'finale' => $moyenneFinale
        ];
    }

    // Fonction auxiliaire pour calculer une partie de la moyenne (CC ou EX)
    private function calculatePartialMoyenne($matricule, $idECUE, $idSession, $idAnneeAcad, $evalIds, $formule) {
        if (empty($evalIds)) {
            return 0;
        }
        
        // Récupérer les points
        $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
        $query = "SELECT typeEvaluation, coteObtenu FROM points 
                 WHERE matricule = ? AND ECUE_idECUE = ? 
                 AND session_idsession = ? AND annee_acad_id = ? 
                 AND typeEvaluation IN ($placeholders)";
        
        $params = [$matricule, $idECUE, $idSession, $idAnneeAcad];
        $params = array_merge($params, $evalIds);
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Transformer les points en format utilisable dans l'évaluation de la formule
        $values = [];
        foreach ($points as $point) {
            $values['id' . $point['typeEvaluation']] = $point['coteObtenu'];
        }
        
        // Pour les évaluations sans points, mettre 0
        foreach ($evalIds as $id) {
            if (!isset($values['id' . $id])) {
                $values['id' . $id] = 0;
            }
        }
        
        // Évaluer la formule
        $formula = $formule;
        foreach ($values as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }
        
        try {
            $result = eval("return " . $formula . ";");
            return is_numeric($result) ? $result : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
