<?php

class NumeroReleve {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Récupère ou crée un numéro de relevé permanent
     * 
     * @param int $idEtudiant
     * @param int $idPromotion
     * @param int $idSession
     * @param int $idAnneeAcad
     * @return string Le numéro de relevé
     */
    public function getOrCreateNumeroReleve($idEtudiant, $idPromotion, $idSession, $idAnneeAcad) {
        // Vérifier si un numéro existe déjà
        $query = "SELECT numero_releve FROM numero_releves 
                  WHERE id_etudiant = :idEtudiant 
                  AND id_promotion = :idPromotion 
                  AND id_session = :idSession 
                  AND id_annee_acad = :idAnneeAcad";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':idEtudiant' => $idEtudiant,
            ':idPromotion' => $idPromotion,
            ':idSession' => $idSession,
            ':idAnneeAcad' => $idAnneeAcad
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['numero_releve'];
        }
        
        // Créer un nouveau numéro
        $numeroReleve = $this->generateNumeroReleve();
        
        // Stocker le numéro
        $query = "INSERT INTO numero_releves (numero_releve, id_etudiant, id_promotion, id_session, id_annee_acad)
                  VALUES (:numeroReleve, :idEtudiant, :idPromotion, :idSession, :idAnneeAcad)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':numeroReleve' => $numeroReleve,
            ':idEtudiant' => $idEtudiant,
            ':idPromotion' => $idPromotion,
            ':idSession' => $idSession,
            ':idAnneeAcad' => $idAnneeAcad
        ]);
        
        return $numeroReleve;
    }

    /**
     * Génère un numéro de relevé unique
     * Format: RN-YYYYMMDD-XXXXX (ex: RN-20251207-00001)
     * 
     * @return string
     */
    private function generateNumeroReleve() {
        // Récupérer le dernier numéro du jour
        $today = date('Ymd');
        $query = "SELECT MAX(numero_releve) as lastNumero FROM numero_releves 
                  WHERE numero_releve LIKE :pattern";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':pattern' => 'RN-' . $today . '-%']);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Extraire le numéro séquentiel
        $sequence = 1;
        if ($result && $result['lastNumero']) {
            $parts = explode('-', $result['lastNumero']);
            if (isset($parts[2])) {
                $sequence = intval($parts[2]) + 1;
            }
        }
        
        // Formater le numéro avec zéros à gauche
        $numeroReleve = 'RN-' . $today . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
        
        return $numeroReleve;
    }

    /**
     * Récupère le numéro de relevé pour un étudiant
     * 
     * @param int $idEtudiant
     * @param int $idPromotion
     * @param int $idSession
     * @param int $idAnneeAcad
     * @return string|null
     */
    public function getNumeroReleve($idEtudiant, $idPromotion, $idSession, $idAnneeAcad) {
        $query = "SELECT numero_releve FROM numero_releves 
                  WHERE id_etudiant = :idEtudiant 
                  AND id_promotion = :idPromotion 
                  AND id_session = :idSession 
                  AND id_annee_acad = :idAnneeAcad";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':idEtudiant' => $idEtudiant,
            ':idPromotion' => $idPromotion,
            ':idSession' => $idSession,
            ':idAnneeAcad' => $idAnneeAcad
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['numero_releve'] : null;
    }

    /**
     * Récupère ou crée un numéro de relevé pour ancienne grille (sans IDs de promotion/session/année)
     * 
     * @param int $idEtudiant
     * @param int $importId (optionnel pour identifier l'import)
     * @return string Le numéro de relevé
     */
    public function getOrCreateNumeroReleveAncienne($idEtudiant, $importId = 0) {
        // Pour l'ancienne grille, on génère juste un numéro unique
        $numeroReleve = $this->generateNumeroReleve();
        return $numeroReleve;
    }
}
?>
