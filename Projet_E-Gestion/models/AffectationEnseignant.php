<?php
class AffectationEnseignant {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Ajouter une affectation
    public function addAffectation($idAgent, $idECUE, $poste, $anneeAcad) {
        // Vérifier si l'affectation existe déjà
        if ($this->checkAffectationExists($idAgent, $idECUE, $anneeAcad)) {
            return false;
        }

        $query = "INSERT INTO enseignant_ecue (idAgent, idECUE, poste, anneeAcad) 
                  VALUES (:idAgent, :idECUE, :poste, :anneeAcad)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idAgent' => $idAgent,
            'idECUE' => $idECUE,
            'poste' => $poste,
            'anneeAcad' => $anneeAcad
        ]);
    }

    // Vérifier si une affectation existe déjà
    private function checkAffectationExists($idAgent, $idECUE, $anneeAcad) {
        $query = "SELECT COUNT(*) as count FROM enseignant_ecue 
                  WHERE idAgent = :idAgent AND idECUE = :idECUE AND anneeAcad = :anneeAcad";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'idECUE' => $idECUE,
            'anneeAcad' => $anneeAcad
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    // Récupérer les affectations par enseignant
    public function getAffectationsByEnseignant($idAgent, $anneeAcad) {
        $query = "SELECT e.*, ec.designationECUE, u.designationUE, 
                 s.numeroSemestre, p.designationPromotion, o.designationOrientation
                 FROM enseignant_ecue e
                 JOIN ecue ec ON e.idECUE = ec.idECUE
                 JOIN ue u ON ec.UE_idUE = u.idUE
                 JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                 JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                 JOIN orientation o ON p.orientation_idorientation = o.idorientation
                 WHERE e.idAgent = :idAgent AND e.anneeAcad = :anneeAcad
                 ORDER BY p.designationPromotion, s.numeroSemestre, u.designationUE, ec.designationECUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'anneeAcad' => $anneeAcad
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
