<?php
class Horaire {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    

    // Récupérer les horaires par promotion
    public function getHorairesByPromotion($idPromotion, $idAnneeAcad) {
        $query = "SELECT h.*, e.\"designationECUE\", u.\"designationUE\", 
                 s.\"numeroSemestre\", p.\"designationPromotion\", a.noms as enseignant_nom
                 FROM horaires_cours h
                 JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
                 JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                 JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                 JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                 LEFT JOIN enseignant_ecue ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = :idAnneeAcad
                 LEFT JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\"
                 WHERE p.idpromotion = :idPromotion AND h.annee_acad_idannee_acad = :idAnneeAcad
                 ORDER BY h.jour, h.heure_debut";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idPromotion' => $idPromotion,
            'idAnneeAcad' => $idAnneeAcad
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtenir les détails d'un horaire spécifique
public function getHoraireById($idHoraire) {
    $query = "SELECT h.*, e.\"designationECUE\", u.\"designationUE\", 
             a.noms as enseignant_nom
             FROM horaires_cours h
             JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
             LEFT JOIN enseignant_ecue ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = h.annee_acad_idannee_acad
             LEFT JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\"
             WHERE h.idhoraire = :idHoraire";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['idHoraire' => $idHoraire]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    /*
    // Mettre à jour un horaire existant
    public function updateHoraire($idHoraire, $jour, $heureDebut, $heureFin, $salle, $idECUE, $typeCours) {
        // Vérifier les chevauchements en excluant l'horaire actuel
        if ($this->verifierChevauchement($jour, $heureDebut, $heureFin, $salle, 
                                         $this->getHoraireById($idHoraire)['annee_acad_idannee_acad'], 
                                         $idHoraire)) {
            return false;
        }
        
        $query = "UPDATE horaires_cours 
                 SET jour = :jour, 
                     heure_debut = :heureDebut, 
                     heure_fin = :heureFin, 
                     salle = :salle, 
                     idECUE = :idECUE,
                     type_cours = :typeCours
                 WHERE idhoraire = :idHoraire";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'jour' => $jour,
            'heureDebut' => $heureDebut,
            'heureFin' => $heureFin,
            'salle' => $salle,
            'idECUE' => $idECUE,
            'typeCours' => $typeCours,
            'idHoraire' => $idHoraire
        ]);
    }
*/




    // Modifier la méthode addHoraire
    public function addHoraire($jour, $heureDebut, $heureFin, $salle, $idECUE, $idAnneeAcad, $idUser, $typeCours = 'CM', $date_cours=null, $skipConflicts = false) {
    // Vérification des conflits (sauf pour les cours en tronc commun)
    if (!$skipConflicts) {
        $conflitSalle = $this->verifierChevauchement($date_cours, $heureDebut, $heureFin, $salle, $idAnneeAcad);
    if ($conflitSalle['conflit']) {
            return ['success' => false, 'message' => $conflitSalle['message']];
        }

        $conflitPromotion = $this->verifierChevauchementPromotion($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad);
    if ($conflitPromotion['conflit']) {
            return ['success' => false, 'message' => $conflitPromotion['message']];
        }

        $conflitEnseignant = $this->verifierChevauchementEnseignant($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad);
    if ($conflitEnseignant['conflit']) {
            return ['success' => false, 'message' => $conflitEnseignant['message']];
        }
    }

    // Si aucun conflit ou cours en tronc commun, ajouter l'horaire
    $query = "INSERT INTO horaires_cours (jour, heure_debut, heure_fin, salle, \"idECUE\",
             annee_acad_idannee_acad, \"idUser\", type_cours, date_cours)
             VALUES (:jour, :heureDebut, :heureFin, :salle, :idECUE, :idAnneeAcad, :idUser, :typeCours, :date_cours)";
    $stmt = $this->db->prepare($query);
    $result = $stmt->execute([
    'jour' => $jour,
    'heureDebut' => $heureDebut,
    'heureFin' => $heureFin,
    'salle' => $salle,
    'idECUE' => $idECUE,
    'idAnneeAcad' => $idAnneeAcad,
    'idUser' => $idUser,
        'typeCours' => $typeCours,
        'date_cours' => $date_cours
    ]);

     return ['success' => $result, 'message' => $result ? 'Horaire ajouté avec succès' : 'Erreur lors de l\'ajout de l\'horaire'];
 }

// Modifier updateHoraire de façon similaire
public function updateHoraire($idHoraire, $jour, $heureDebut, $heureFin, $salle, $idECUE, $typeCours,$date_cours=null, $skipConflicts = false) {
    $horaire = $this->getHoraireById($idHoraire);
    if (!$horaire) {
        return ['success' => false, 'message' => 'Horaire non trouvé'];
    }
    
    $idAnneeAcad = $horaire['annee_acad_idannee_acad'];

    // Vérifications de conflits (sauf pour les cours en tronc commun)
    if (!$skipConflicts) {
        $conflitSalle = $this->verifierChevauchement($date_cours, $heureDebut, $heureFin, $salle, $idAnneeAcad, $idHoraire);
        if ($conflitSalle['conflit']) {
            return ['success' => false, 'message' => $conflitSalle['message']];
        }

        $conflitPromotion = $this->verifierChevauchementPromotion($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire);
        if ($conflitPromotion['conflit']) {
            return ['success' => false, 'message' => $conflitPromotion['message']];
        }

        $conflitEnseignant = $this->verifierChevauchementEnseignant($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire);
        if ($conflitEnseignant['conflit']) {
            return ['success' => false, 'message' => $conflitEnseignant['message']];
        }
    }
    
    // Si aucun conflit ou cours en tronc commun, mettre à jour l'horaire
    $query = "UPDATE horaires_cours 
             SET jour = :jour, 
                 heure_debut = :heureDebut, 
                 heure_fin = :heureFin, 
                 salle = :salle, 
                 \"idECUE\" = :idECUE,
                 type_cours = :typeCours
             WHERE idhoraire = :idHoraire";
    $stmt = $this->db->prepare($query);
    $result = $stmt->execute([
        'jour' => $jour,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'salle' => $salle,
        'idECUE' => $idECUE,
        'typeCours' => $typeCours,
        'idHoraire' => $idHoraire
    ]);
    
    return ['success' => $result, 'message' => $result ? 'Horaire mis à jour avec succès' : 'Erreur lors de la mise à jour de l\'horaire'];
}




    
    // Supprimer un horaire
    public function deleteHoraire($idHoraire) {
        $query = "DELETE FROM horaires_cours WHERE idhoraire = :idHoraire";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idHoraire' => $idHoraire]);
    }





/**
 * Ajouter un horaire de cours avec date spécifique
 * @param string $date_cours Date du cours (Y-m-d)
 * @param string $heureDebut Heure de début
 * @param string $heureFin Heure de fin
 * @param string $salle Salle de cours
 * @param int $idECUE ID de l'ECUE
 * @param int $idAnneeAcad ID de l'année académique 
 * @param int $idUser ID de l'utilisateur
 * @param string $typeCours Type de cours (CM, TD, TP, Evaluation)
 * @return bool Succès de l'opération
 */
public function addHoraireWithDate($date_cours, $heureDebut, $heureFin, $salle, $idECUE, $idAnneeAcad, $idUser, $typeCours = 'CM') {
    // Vérifier les chevauchements
    if ($this->verifierChevauchementWithDate($date_cours, $heureDebut, $heureFin, $salle, $idAnneeAcad)) {
        return false;
    }
    
    $query = "INSERT INTO horaires_cours (date_cours, jour, heure_debut, heure_fin, salle, \"idECUE\", 
             annee_acad_idannee_acad, \"idUser\", type_cours) 
             VALUES (:date_cours, DAYNAME(:date_cours), :heureDebut, :heureFin, :salle, :idECUE, :idAnneeAcad, :idUser, :typeCours)";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'date_cours' => $date_cours,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'salle' => $salle,
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad,
        'idUser' => $idUser,
        'typeCours' => $typeCours
    ]);
}

/**
 * Vérifier les chevauchements d'horaires avec date spécifique
 * @param string $date_cours Date du cours
 * @param string $heureDebut Heure de début
 * @param string $heureFin Heure de fin
 * @param string $salle Salle
 * @param int $idAnneeAcad ID de l'année académique
 * @param int|null $idHoraire ID de l'horaire à exclure (pour modification)
 * @return bool True s'il y a un chevauchement
 */
private function verifierChevauchementWithDate($date_cours, $heureDebut, $heureFin, $salle, $idAnneeAcad, $idHoraire = null) {
    $query = "SELECT COUNT(*) as count FROM horaires_cours 
             WHERE date_cours = :date_cours AND salle = :salle 
             AND annee_acad_idannee_acad = :idAnneeAcad
             AND ((heure_debut <= :heureDebut AND heure_fin > :heureDebut) 
             OR (heure_debut < :heureFin AND heure_fin >= :heureFin) 
             OR (heure_debut >= :heureDebut AND heure_fin <= :heureFin))";
    
    if ($idHoraire) {
        $query .= " AND idhoraire != :idHoraire";
    }
    
    $stmt = $this->db->prepare($query);
    $params = [
        'date_cours' => $date_cours,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'salle' => $salle,
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($idHoraire) {
        $params['idHoraire'] = $idHoraire;
    }
    
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}




/**
 * Mettre à jour un horaire existant avec date spécifique
 * @param int $idHoraire ID de l'horaire
 * @param string $date_cours Date du cours
 * @param string $heureDebut Heure de début
 * @param string $heureFin Heure de fin
 * @param string $salle Salle de cours
 * @param int $idECUE ID de l'ECUE
 * @param string $typeCours Type de cours
 * @return bool Succès de l'opération
 */
public function updateHoraireWithDate($idHoraire, $date_cours, $heureDebut, $heureFin, $salle, $idECUE, $typeCours) {
    // Vérifier les chevauchements en excluant l'horaire actuel
    if ($this->verifierChevauchementWithDate($date_cours, $heureDebut, $heureFin, $salle, 
                                           $this->getHoraireById($idHoraire)['annee_acad_idannee_acad'], 
                                           $idHoraire)) {
        return false;
    }
    
    // Déterminer le jour de la semaine à partir de la date
    $jourSemaine = date('l', strtotime($date_cours));
    $jourMapping = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche'
    ];
    $jour = $jourMapping[$jourSemaine];
    
    $query = "UPDATE horaires_cours 
             SET jour = :jour, 
                 date_cours = :date_cours,
                 heure_debut = :heureDebut, 
                 heure_fin = :heureFin, 
                 salle = :salle, 
                 \"idECUE\" = :idECUE,
                 type_cours = :typeCours
             WHERE idhoraire = :idHoraire";
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'jour' => $jour,
        'date_cours' => $date_cours,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'salle' => $salle,
        'idECUE' => $idECUE,
        'typeCours' => $typeCours,
        'idHoraire' => $idHoraire
    ]);
}

/**
 * Dupliquer un horaire existant à une nouvelle date
 * @param int $idHoraire ID de l'horaire à dupliquer
 * @param string $newDate Nouvelle date pour l'horaire
 * @return bool Succès de l'opération
 */
public function duplicateHoraire($idHoraire, $newDate) {
    // Récupérer les détails de l'horaire à dupliquer
    $horaire = $this->getHoraireById($idHoraire);
    if (!$horaire) {
        return false;
    }
    
    // Calculer le jour de la semaine pour la nouvelle date
    $jourSemaine = date('l', strtotime($newDate));
    $jourMapping = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche'
    ];
    $jour = $jourMapping[$jourSemaine];
    
    // Vérifier s'il y a des chevauchements à la nouvelle date
    if ($this->verifierChevauchementWithDate($newDate, $horaire['heure_debut'], $horaire['heure_fin'], 
                                          $horaire['salle'], $horaire['annee_acad_idannee_acad'])) {
        return false;
    }
    
    // Dupliquer l'horaire
    $query = "INSERT INTO horaires_cours 
              (date_cours, jour, heure_debut, heure_fin, salle, \"idECUE\", annee_acad_idannee_acad, type_cours, \"idUser\")
              VALUES 
              (:date_cours, :jour, :heure_debut, :heure_fin, :salle, :idECUE, :annee_acad_idannee_acad, :type_cours, :idUser)";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'date_cours' => $newDate,
        'jour' => $jour,
        'heure_debut' => $horaire['heure_debut'],
        'heure_fin' => $horaire['heure_fin'],
        'salle' => $horaire['salle'],
        'idECUE' => $horaire['idECUE'],
        'annee_acad_idannee_acad' => $horaire['annee_acad_idannee_acad'],
        'type_cours' => $horaire['type_cours'],
        'idUser' => $_SESSION['id']
    ]);
}


// Modifier la méthode de vérification des chevauchements
public function verifierChevauchement($jour, $heureDebut, $heureFin, $salle, $idAnneeAcad, $idHoraire = null) {
    // 1. Vérification des conflits de salle (existant)
    $querySalle = "SELECT COUNT(*) as count FROM horaires_cours 
             WHERE date_cours = :jour AND salle = :salle 
             AND annee_acad_idannee_acad = :idAnneeAcad
             AND ((heure_debut <= :heureDebut AND heure_fin > :heureDebut) 
             OR (heure_debut < :heureFin AND heure_fin >= :heureFin) 
             OR (heure_debut >= :heureDebut AND heure_fin <= :heureFin))";
    
    if ($idHoraire) {
        $querySalle .= " AND idhoraire != :idHoraire";
    }
    
    $stmtSalle = $this->db->prepare($querySalle);
    $paramsSalle = [
        'jour' => $jour,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'salle' => $salle,
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($idHoraire) {
        $paramsSalle['idHoraire'] = $idHoraire;
    }
    
    $stmtSalle->execute($paramsSalle);
    $resultSalle = $stmtSalle->fetch(PDO::FETCH_ASSOC);
    
    // Si conflit de salle, retourner immédiatement true
    if ($resultSalle['count'] > 0) {
        return ['conflit' => true, 'message' => 'Cette salle est déjà occupée à cet horaire.'];
    }
    
    return ['conflit' => false, 'message' => ''];
}

// Nouvelle méthode pour vérifier les conflits de promotion
public function verifierChevauchementPromotion($jour, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire = null) {
    // Requête pour obtenir la promotion associée à l'ECUE
    $queryPromotion = "SELECT p.idpromotion 
                       FROM ecue e 
                       JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
                       JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
                       JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
                       WHERE e.\"idECUE\" = :idECUE";
    
    $stmtPromotion = $this->db->prepare($queryPromotion);
    $stmtPromotion->execute(['idECUE' => $idECUE]);
    $promotion = $stmtPromotion->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        return ['conflit' => false, 'message' => ''];  // Aucune promotion trouvée
    }
    
    $idPromotion = $promotion['idpromotion'];
    
    // Vérifier si la promotion a déjà un cours à cet horaire
    $queryConflit = "SELECT h.idhoraire, e.\"designationECUE\" 
                     FROM horaires_cours h 
                     JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\" 
                     JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
                     JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
                     JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
                     WHERE p.idpromotion = :idPromotion 
                     AND h.date_cours = :jour 
                     AND h.annee_acad_idannee_acad = :idAnneeAcad
                     AND ((h.heure_debut <= :heureDebut AND h.heure_fin > :heureDebut) 
                     OR (h.heure_debut < :heureFin AND h.heure_fin >= :heureFin) 
                     OR (h.heure_debut >= :heureDebut AND h.heure_fin <= :heureFin))";
    
    if ($idHoraire) {
        $queryConflit .= " AND h.idhoraire != :idHoraire";
    }
    
    $stmtConflit = $this->db->prepare($queryConflit);
    $paramsConflit = [
        'idPromotion' => $idPromotion,
        'jour' => $jour,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($idHoraire) {
        $paramsConflit['idHoraire'] = $idHoraire;
    }
    
    $stmtConflit->execute($paramsConflit);
    $conflit = $stmtConflit->fetch(PDO::FETCH_ASSOC);
    
    if ($conflit) {
        return [
            'conflit' => true, 
            'message' => 'Cette promotion a déjà le cours "' . $conflit['designationECUE'] . '" à cet horaire.'
        ];
    }
    
    return ['conflit' => false, 'message' => ''];
}

// Nouvelle méthode pour vérifier les conflits d'enseignant
public function verifierChevauchementEnseignant($jour, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire = null) {
    // Trouver l'enseignant titulaire associé à cet ECUE
    $queryEnseignant = "SELECT a.\"idAgent\", a.noms 
                        FROM enseignant_ecue ee 
                        JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\" 
                        WHERE ee.\"idECUE\" = :idECUE 
                        AND ee.\"anneeAcad\" = :idAnneeAcad
                        AND ee.poste = 'Titulaire'";  // Ajouter cette condition pour ne récupérer que le titulaire
    
    $stmtEnseignant = $this->db->prepare($queryEnseignant);
    $stmtEnseignant->execute([
        'idECUE' => $idECUE,
        'idAnneeAcad' => $idAnneeAcad
    ]);
    
    $enseignant = $stmtEnseignant->fetch(PDO::FETCH_ASSOC);
    
    if (!$enseignant) {
        return ['conflit' => false, 'message' => '']; // Aucun enseignant titulaire trouvé
    }
    
    $idEnseignant = $enseignant['idAgent'];
    $nomEnseignant = $enseignant['noms'];
    
    // Vérifier si cet enseignant titulaire a déjà un cours à cet horaire
    $queryConflit = "SELECT h.idhoraire, e.\"designationECUE\" 
                     FROM horaires_cours h 
                     JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\" 
                     JOIN enseignant_ecue ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = h.annee_acad_idannee_acad 
                     WHERE ee.\"idAgent\" = :idEnseignant 
                     AND ee.poste = 'Titulaire'
                     AND h.date_cours = :jour 
                     AND h.annee_acad_idannee_acad = :idAnneeAcad
                     AND ((h.heure_debut <= :heureDebut AND h.heure_fin > :heureDebut) 
                     OR (h.heure_debut < :heureFin AND h.heure_fin >= :heureFin) 
                     OR (h.heure_debut >= :heureDebut AND h.heure_fin <= :heureFin))";
    
    if ($idHoraire) {
        $queryConflit .= " AND h.idhoraire != :idHoraire";
    }
    
    $stmtConflit = $this->db->prepare($queryConflit);
    $paramsConflit = [
        'idEnseignant' => $idEnseignant,
        'jour' => $jour,
        'heureDebut' => $heureDebut,
        'heureFin' => $heureFin,
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($idHoraire) {
        $paramsConflit['idHoraire'] = $idHoraire;
    }
    
    $stmtConflit->execute($paramsConflit);
    $conflit = $stmtConflit->fetch(PDO::FETCH_ASSOC);
    
    if ($conflit) {
        return [
            'conflit' => true, 
            'message' => 'L\'enseignant titulaire ' . $nomEnseignant . ' a déjà le cours "' . $conflit['designationECUE'] . '" à cet horaire.'
        ];
    }
    
    return ['conflit' => false, 'message' => ''];
}




// Ajouter cette méthode pour vérifier si l'horaire est trop proche d'un autre cours
public function verifierTempsTransition($jour, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire = null) {
    // Temps minimum entre deux cours (en minutes)
    $tempsTransitionMin = 15;
    
    // Trouver la promotion associée à cet ECUE
    $queryPromotion = "SELECT p.idpromotion 
                       FROM ecue e 
                       JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
                       JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
                       JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
                       WHERE e.\"idECUE\" = :idECUE";
    
    $stmtPromotion = $this->db->prepare($queryPromotion);
    $stmtPromotion->execute(['idECUE' => $idECUE]);
    $promotion = $stmtPromotion->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        return ['warning' => false, 'message' => ''];
    }
    
    $idPromotion = $promotion['idpromotion'];
    
    // Convertir les heures en minutes pour faciliter les calculs
    $heureDebutMinutes = $this->heureEnMinutes($heureDebut);
    $heureFinMinutes = $this->heureEnMinutes($heureFin);
    
    // Vérifier les cours qui se terminent juste avant ce cours
    $queryAvant = "SELECT h.idhoraire, e.\"designationECUE\", h.heure_fin 
                   FROM horaires_cours h 
                   JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\" 
                   JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
                   JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
                   JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
                   WHERE p.idpromotion = :idPromotion 
                   AND h.date_cours = :jour 
                   AND h.annee_acad_idannee_acad = :idAnneeAcad
                   AND ABS(TIMESTAMPDIFF(MINUTE, h.heure_fin, :heureDebut)) < :tempsTransition
                   AND h.heure_fin <= :heureDebut";
    
    if ($idHoraire) {
        $queryAvant .= " AND h.idhoraire != :idHoraire";
    }
    
    $stmtAvant = $this->db->prepare($queryAvant);
    $paramsAvant = [
        'idPromotion' => $idPromotion,
        'jour' => $jour,
        'heureDebut' => $heureDebut,
        'idAnneeAcad' => $idAnneeAcad,
        'tempsTransition' => $tempsTransitionMin
    ];
    
    if ($idHoraire) {
        $paramsAvant['idHoraire'] = $idHoraire;
    }
    
    $stmtAvant->execute($paramsAvant);
    $coursAvant = $stmtAvant->fetch(PDO::FETCH_ASSOC);
    
    if ($coursAvant) {
        $tempsTransition = $heureDebutMinutes - $this->heureEnMinutes($coursAvant['heure_fin']);
        return [
            'warning' => true,
            'message' => 'Le cours précédent "' . $coursAvant['designationECUE'] . '" se termine seulement ' . $tempsTransition . ' minutes avant ce cours. Un minimum de ' . $tempsTransitionMin . ' minutes est recommandé.'
        ];
    }
    
    // Vérifier les cours qui commencent juste après ce cours
    $queryApres = "SELECT h.idhoraire, e.\"designationECUE\", h.heure_debut 
                  FROM horaires_cours h 
                  JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\" 
                  JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
                  JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
                  WHERE p.idpromotion = :idPromotion 
                  AND h.date_cours = :jour 
                  AND h.annee_acad_idannee_acad = :idAnneeAcad
                  AND ABS(TIMESTAMPDIFF(MINUTE, :heureFin, h.heure_debut)) < :tempsTransition
                  AND h.heure_debut >= :heureFin";
    
    if ($idHoraire) {
        $queryApres .= " AND h.idhoraire != :idHoraire";
    }
    
    $stmtApres = $this->db->prepare($queryApres);
    $paramsApres = [
        'idPromotion' => $idPromotion,
        'jour' => $jour,
        'heureFin' => $heureFin,
        'idAnneeAcad' => $idAnneeAcad,
        'tempsTransition' => $tempsTransitionMin
    ];
    
    if ($idHoraire) {
        $paramsApres['idHoraire'] = $idHoraire;
    }
    
    $stmtApres->execute($paramsApres);
    $coursApres = $stmtApres->fetch(PDO::FETCH_ASSOC);
    
    if ($coursApres) {
        $tempsTransition = $this->heureEnMinutes($coursApres['heure_debut']) - $heureFinMinutes;
        return [
            'warning' => true,
            'message' => 'Le cours suivant "' . $coursApres['designationECUE'] . '" commence seulement ' . $tempsTransition . ' minutes après ce cours. Un minimum de ' . $tempsTransitionMin . ' minutes est recommandé.'
        ];
    }
    
    return ['warning' => false, 'message' => ''];
}

// Méthode utilitaire pour convertir une heure (HH:MM:SS) en minutes
public function heureEnMinutes($heure) {
    list($h, $m, $s) = array_pad(explode(':', $heure), 3, 0);
    return $h * 60 + $m;
}


public function getHorairesByPromotionAndDates($idPromotion, $idAnneeAcad, $dateDebut = null, $dateFin = null) {
    // Modifier la requête pour ne sélectionner que l'enseignant titulaire (is_titulaire = 1)
    // ou prendre le premier enseignant si aucun titulaire n'est spécifié
    $query = "SELECT h.*, e.\"designationECUE\", u.\"designationUE\", 
             s.\"numeroSemestre\", p.\"designationPromotion\", a.noms as enseignant_nom,
             DATE_FORMAT(h.date_cours, '%Y-%m-%d') as date_cours,
             DAYNAME(h.date_cours) as jour_semaine
             FROM horaires_cours h
             JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
             JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
             JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
             LEFT JOIN (
                 SELECT ee.\"idECUE\", ee.\"anneeAcad\", ee.\"idAgent\"
                 FROM enseignant_ecue ee
                 LEFT JOIN (
                     SELECT \"idECUE\", \"anneeAcad\", MIN(\"idAgent\") as \"idAgent\"
                     FROM enseignant_ecue
                     WHERE poste = 'Titulaire'
                     GROUP BY \"idECUE\", \"anneeAcad\"
                 ) tit ON ee.\"idECUE\" = tit.\"idECUE\" AND ee.\"anneeAcad\" = tit.\"anneeAcad\"
                 WHERE tit.\"idAgent\" IS NOT NULL OR ee.\"idAgent\" = (
                     SELECT MIN(\"idAgent\") 
                     FROM enseignant_ecue ee2 
                     WHERE ee2.\"idECUE\" = ee.\"idECUE\" AND ee2.\"anneeAcad\" = ee.\"anneeAcad\"
                 )
                 GROUP BY ee.\"idECUE\", ee.\"anneeAcad\"
             ) ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = :idAnneeAcad
             LEFT JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\"
             WHERE p.idpromotion = :idPromotion 
             AND h.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition de date si nécessaire
    if ($dateDebut && $dateFin) {
        $query .= " AND h.date_cours BETWEEN :dateDebut AND :dateFin";
    }
    
    $query .= " ORDER BY h.jour, h.heure_debut";
    
    $stmt = $this->db->prepare($query);
    $params = [
        'idPromotion' => $idPromotion,
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($dateDebut && $dateFin) {
        $params['dateDebut'] = $dateDebut;
        $params['dateFin'] = $dateFin;
    }
    
    $stmt->execute($params);
    $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Détecter les conflits entre les horaires récupérés
    $horairesAvecConflits = $this->detecterConflits($horaires);
    
    return $horairesAvecConflits;
}


/**
 * Détecte les conflits entre les horaires
 * @param array $horaires Liste des horaires à vérifier
 * @return array Liste des horaires avec info de conflit
 */
private function detecterConflits($horaires) {
    $result = [];
    
    // Créer un index pour faciliter la recherche
    $index = [];
    foreach ($horaires as $h) {
        $jour = isset($h['date_cours']) && !empty($h['date_cours']) ? $h['date_cours'] : $h['jour'];
        if (!isset($index[$jour])) {
            $index[$jour] = [];
        }
        $index[$jour][] = $h;
    }
    
    // Vérifier les conflits pour chaque horaire
    foreach ($horaires as $h) {
        $h['has_conflict'] = false;
        $h['conflict_message'] = '';
        $h['has_warning'] = false;
        $h['warning_message'] = '';
        
        $jour = isset($h['date_cours']) && !empty($h['date_cours']) ? $h['date_cours'] : $h['jour'];
        $heureDebut = $h['heure_debut'];
        $heureFin = $h['heure_fin'];
        $salle = $h['salle'];
        
        // Vérifier avec les autres horaires du même jour
        if (isset($index[$jour])) {
            foreach ($index[$jour] as $autre) {
                // Ne pas vérifier avec soi-même
                if ($autre['idhoraire'] == $h['idhoraire']) {
                    continue;
                }
                
                // Vérifier le chevauchement temporel
                $chevauchement = (
                    ($heureDebut <= $autre['heure_debut'] && $heureFin > $autre['heure_debut']) ||
                    ($heureDebut < $autre['heure_fin'] && $heureFin >= $autre['heure_fin']) ||
                    ($heureDebut >= $autre['heure_debut'] && $heureFin <= $autre['heure_fin'])
                );
                
                if ($chevauchement) {
                    // Conflit de salle
                    if ($salle == $autre['salle']) {
                        $h['has_conflict'] = true;
                        $h['conflict_message'] = 'Conflit de salle avec ' . $autre['designationECUE'];
                    }
                    
                    // Conflit de promotion (même ECUE)
                    if ($h['designationPromotion'] == $autre['designationPromotion']) {
                        $h['has_conflict'] = true;
                        $h['conflict_message'] = 'Conflit d\'horaire pour cette promotion avec ' . $autre['designationECUE'];
                    }
                    
                    // Conflit d'enseignant
                    if ($h['enseignant_nom'] && $h['enseignant_nom'] == $autre['enseignant_nom']) {
                        $h['has_conflict'] = true;
                        $h['conflict_message'] = 'L\'enseignant ' . $h['enseignant_nom'] . ' a déjà un cours à cet horaire';
                    }
                }
                
                // Vérifier proximité temporelle (pour les avertissements)
                $heureDebutMinutes = $this->heureEnMinutes($heureDebut);
                $heureFinMinutes = $this->heureEnMinutes($heureFin);
                $autreDebutMinutes = $this->heureEnMinutes($autre['heure_debut']);
                $autreFinMinutes = $this->heureEnMinutes($autre['heure_fin']);
                
                $tempsTransitionMin = 15; // 15 minutes de temps de transition recommandé
                
                // Si le cours actuel finit peu avant l'autre cours commence
                if ($heureFinMinutes < $autreDebutMinutes && 
                    ($autreDebutMinutes - $heureFinMinutes) < $tempsTransitionMin && 
                    $h['designationPromotion'] == $autre['designationPromotion']) {
                    $h['has_warning'] = true;
                    $h['warning_message'] = 'Seulement ' . ($autreDebutMinutes - $heureFinMinutes) . ' min avant le prochain cours';
                }
                
                // Si le cours actuel commence peu après que l'autre cours finit
                if ($heureDebutMinutes > $autreFinMinutes && 
                    ($heureDebutMinutes - $autreFinMinutes) < $tempsTransitionMin && 
                    $h['designationPromotion'] == $autre['designationPromotion']) {
                    $h['has_warning'] = true;
                    $h['warning_message'] = 'Seulement ' . ($heureDebutMinutes - $autreFinMinutes) . ' min après le cours précédent';
                }
            }
        }
        
        $result[] = $h;
    }
    
    return $result;
}


/**
 * Récupère les horaires pour toutes les salles pour une période donnée
 */
public function getOccupationSalles($idAnneeAcad, $dateDebut, $dateFin) {
    $query = "SELECT h.*, e.\"designationECUE\", u.\"designationUE\", 
             s.\"numeroSemestre\", p.\"designationPromotion\", a.noms as enseignant_nom,
             DATE_FORMAT(h.date_cours, '%Y-%m-%d') as date_cours,
             DAYNAME(h.date_cours) as jour_semaine
             FROM horaires_cours h
             JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
             JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
             JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
             LEFT JOIN (
                 SELECT ee.\"idECUE\", ee.\"anneeAcad\", ee.\"idAgent\"
                 FROM enseignant_ecue ee
                 WHERE ee.poste = 'Titulaire'
                 GROUP BY ee.\"idECUE\", ee.\"anneeAcad\"
             ) ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = :idAnneeAcad
             LEFT JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\"
             WHERE h.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition de date si nécessaire
    if ($dateDebut && $dateFin) {
        $query .= " AND h.date_cours BETWEEN :dateDebut AND :dateFin";
    }
    
    $query .= " ORDER BY h.salle, h.jour, h.heure_debut";
    
    $stmt = $this->db->prepare($query);
    $params = [
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($dateDebut && $dateFin) {
        $params['dateDebut'] = $dateDebut;
        $params['dateFin'] = $dateFin;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le taux d'occupation des salles pour une période donnée
 */
public function getSallesOccupationRate($idAnneeAcad, $dateDebut, $dateFin) {
    $query = "SELECT h.salle, 
             COUNT(DISTINCT h.date_cours) as jours_occupes,
             SUM(TIMESTAMPDIFF(MINUTE, h.heure_debut, h.heure_fin)) as minutes_totales,
             COUNT(*) as nombre_cours
             FROM horaires_cours h
             WHERE h.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition de date si nécessaire
    if ($dateDebut && $dateFin) {
        $query .= " AND h.date_cours BETWEEN :dateDebut AND :dateFin";
    }
    
    $query .= " GROUP BY h.salle ORDER BY minutes_totales DESC";
    
    $stmt = $this->db->prepare($query);
    $params = [
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($dateDebut && $dateFin) {
        $params['dateDebut'] = $dateDebut;
        $params['dateFin'] = $dateFin;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les horaires pour toutes les promotions pour une période donnée
 */
public function getOccupationPromotions($idAnneeAcad, $dateDebut, $dateFin) {
    $query = "SELECT h.*, e.\"designationECUE\", u.\"designationUE\", 
             s.\"numeroSemestre\", p.\"designationPromotion\", p.idpromotion, p.cycle,
             a.noms as enseignant_nom,
             DATE_FORMAT(h.date_cours, '%Y-%m-%d') as date_cours,
             DAYNAME(h.date_cours) as jour_semaine
             FROM horaires_cours h
             JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
             JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
             JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
             LEFT JOIN (
                 SELECT ee.\"idECUE\", ee.\"anneeAcad\", ee.\"idAgent\"
                 FROM enseignant_ecue ee
                 WHERE ee.poste = 'Titulaire'
                 GROUP BY ee.\"idECUE\", ee.\"anneeAcad\"
             ) ee ON e.\"idECUE\" = ee.\"idECUE\" AND ee.\"anneeAcad\" = :idAnneeAcad
             LEFT JOIN agent a ON ee.\"idAgent\" = a.\"idAgent\"
             WHERE h.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition de date si nécessaire
    if ($dateDebut && $dateFin) {
        $query .= " AND h.date_cours BETWEEN :dateDebut AND :dateFin";
    }
    
    $query .= " ORDER BY p.cycle, p.\"designationPromotion\", h.jour, h.heure_debut";
    
    $stmt = $this->db->prepare($query);
    $params = [
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($dateDebut && $dateFin) {
        $params['dateDebut'] = $dateDebut;
        $params['dateFin'] = $dateFin;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le taux d'occupation des promotions pour une période donnée
 */
public function getPromotionsOccupationRate($idAnneeAcad, $dateDebut, $dateFin) {
    $query = "SELECT p.idpromotion, p.\"designationPromotion\", p.cycle,
             COUNT(DISTINCT h.date_cours) as jours_occupes,
             SUM(TIMESTAMPDIFF(MINUTE, h.heure_debut, h.heure_fin)) as minutes_totales,
             COUNT(*) as nombre_cours
             FROM horaires_cours h
             JOIN ecue e ON h.\"idECUE\" = e.\"idECUE\"
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
             JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
             JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
             WHERE h.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition de date si nécessaire
    if ($dateDebut && $dateFin) {
        $query .= " AND h.date_cours BETWEEN :dateDebut AND :dateFin";
    }
    
    $query .= " GROUP BY p.idpromotion ORDER BY p.cycle, p.designationPromotion";
    
    $stmt = $this->db->prepare($query);
    $params = [
        'idAnneeAcad' => $idAnneeAcad
    ];
    
    if ($dateDebut && $dateFin) {
        $params['dateDebut'] = $dateDebut;
        $params['dateFin'] = $dateFin;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}










}
