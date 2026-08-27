<?php

class DepotSoutenance
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Vérifier si un sujet est validé par le directeur
    public function isSujetValide($idSujet)
    {
        $query = "SELECT statut_validation FROM sujets WHERE idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSujet' => $idSujet]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['statut_validation'] === 'Validé';
    }

    // Enregistrer un dépôt de mémoire
    public function enregistrerDepotMemoire($idSujet, $dateDepot, $fichier, $observation)
    {
        // Vérifier si le sujet est validé
        if (!$this->isSujetValide($idSujet)) {
            return [
                'success' => false,
                'message' => 'Le sujet doit être validé par le directeur avant de pouvoir enregistrer le dépôt du mémoire.'
            ];
        }

        $query = "INSERT INTO depot_memoire (\"dateDepot\", fichier, observation, sujets_idsujets) 
                  VALUES (:dateDepot, :fichier, :observation, :idSujet)";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'dateDepot' => $dateDepot,
            'fichier' => $fichier,
            'observation' => $observation,
            'idSujet' => $idSujet
        ]);

        return [
            'success' => $success,
            'message' => $success ? 'Dépôt de mémoire enregistré avec succès.' : 'Erreur lors de l\'enregistrement du dépôt.'
        ];
    }

    // Enregistrer un dépôt de rapport de stage
    public function enregistrerDepotRapport($etudiantId, $dateDepot, $titre, $lieuStage, $dateDebut, $dateFin, $observation, $encadreurId, $fichier = '')
    {
        $query = "INSERT INTO depot_rapport (\"dateDepot\", titre, lieu_stage, date_debut, date_fin, observation, encadreur, etudiant_idetudiant, fichier) 
                VALUES (:dateDepot, :titre, :lieuStage, :dateDebut, :dateFin, :observation, :encadreur, :etudiantId, :fichier)";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'dateDepot' => $dateDepot,
            'titre' => $titre,
            'lieuStage' => $lieuStage,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'observation' => $observation,
            'encadreur' => $encadreurId,
            'etudiantId' => $etudiantId,
            'fichier' => $fichier
        ]);

        return [
            'success' => $success,
            'message' => $success ? 'Dépôt de rapport de stage enregistré avec succès.' : 'Erreur lors de l\'enregistrement du dépôt.'
        ];
    }


    // Vérifier si un étudiant a payé tous les frais de soutenance
    public function isEtudiantEnOrdre($etudiantId, $anneeAcadId, $sectionId)
    {
        // Récupérer tous les frais de soutenance pour la section et l'année académique
        $queryFrais = "SELECT fs.* FROM frais_soutenance fs 
                      WHERE fs.section_id = :sectionId 
                      AND fs.annee_acad_id = :anneeAcadId
                      AND fs.\"estObligatoire\" = 1";
        $stmtFrais = $this->db->prepare($queryFrais);
        $stmtFrais->execute([
            'sectionId' => $sectionId,
            'anneeAcadId' => $anneeAcadId
        ]);
        $frais = $stmtFrais->fetchAll(PDO::FETCH_ASSOC);

        foreach ($frais as $f) {
            // Vérifier si l'étudiant a payé ce frais
            $queryPaiement = "SELECT * FROM paiement_soutenance 
                            WHERE etudiant_id = :etudiantId 
                            AND frais_soutenance_id = :fraisId 
                            AND annee_acad_id = :anneeAcadId
                            AND \"estComplet\" = 1";
            $stmtPaiement = $this->db->prepare($queryPaiement);
            $stmtPaiement->execute([
                'etudiantId' => $etudiantId,
                'fraisId' => $f['idfrais_soutenance'],
                'anneeAcadId' => $anneeAcadId
            ]);
            
            if ($stmtPaiement->rowCount() === 0) {
                return false; // L'étudiant n'a pas payé ce frais
            }
        }
        
        return true; // L'étudiant a payé tous les frais obligatoires
    }

    // Programmer une soutenance
    public function programmerSoutenance($idSujet, $dateSoutenance, $lieu, $userId)
    {
        // Récupérer l'étudiant associé au sujet
        $queryEtudiant = "SELECT s.etudiant_idetudiant, e.promotion_idpromotion, 
                           p.orientation_idorientation, o.section_idsection, 
                           s.annee_acad_idannee_acad
                         FROM sujets s
                         JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                         JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                         JOIN orientation o ON p.orientation_idorientation = o.idorientation
                         WHERE s.idsujets = :idSujet";
        $stmtEtudiant = $this->db->prepare($queryEtudiant);
        $stmtEtudiant->execute(['idSujet' => $idSujet]);
        $etudiantInfo = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiantInfo) {
            return [
                'success' => false,
                'message' => 'Impossible de trouver les informations de l\'étudiant associé à ce sujet.'
            ];
        }
        
        // Vérifier si l'étudiant est en ordre avec ses frais
        if (!$this->isEtudiantEnOrdre(
            $etudiantInfo['etudiant_idetudiant'], 
            $etudiantInfo['annee_acad_idannee_acad'], 
            $etudiantInfo['section_idsection']
        )) {
            return [
                'success' => false,
                'message' => 'L\'étudiant n\'est pas en ordre avec tous les frais de soutenance obligatoires.'
            ];
        }
        
        // Vérifier si le sujet a un dépôt de mémoire
        $queryDepot = "SELECT * FROM depot_memoire WHERE sujets_idsujets = :idSujet";
        $stmtDepot = $this->db->prepare($queryDepot);
        $stmtDepot->execute(['idSujet' => $idSujet]);
        
        if ($stmtDepot->rowCount() === 0) {
            return [
                'success' => false,
                'message' => 'Aucun dépôt de mémoire enregistré pour ce sujet. Veuillez d\'abord enregistrer le dépôt.'
            ];
        }
        
        // Programmer la soutenance
        $query = "INSERT INTO soutenance (date_soutenance, lieu, sujets_idsujets, statut, \"idUser\", \"dateCreation\") 
                 VALUES (:dateSoutenance, :lieu, :idSujet, 'Programmée', :userId, NOW())";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'dateSoutenance' => $dateSoutenance,
            'lieu' => $lieu,
            'idSujet' => $idSujet,
            'userId' => $userId
        ]);
        
        if (!$success) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la programmation de la soutenance.'
            ];
        }
        
        $idSoutenance = $this->db->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Soutenance programmée avec succès.',
            'idSoutenance' => $idSoutenance
        ];
    }

    // Ajouter un membre du jury à une soutenance
    public function ajouterJury($idSoutenance, $idEnseignant, $role)
    {
        $query = "INSERT INTO jury_soutenance (idsoutenance, idenseignant, role) 
                 VALUES (:idSoutenance, :idEnseignant, :role)";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idEnseignant' => $idEnseignant,
            'role' => $role
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Membre du jury ajouté avec succès.' : 'Erreur lors de l\'ajout du membre du jury.'
        ];
    }
    
    
    
    // Récupérer les soutenances par jury
    public function getSoutenancesParJury($idJury, $anneeAcadId)
    {
        $query = "SELECT s.*, sj.intitule, e.noms as nom_etudiant, e.matricule
                 FROM soutenance s
                 JOIN jury_soutenance j ON s.idsoutenance = j.idsoutenance
                 JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                 JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                 WHERE j.idenseignant = :idJury
                 AND sj.annee_acad_idannee_acad = :anneeAcadId
                 ORDER BY s.date_soutenance DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idJury' => $idJury,
            'anneeAcadId' => $anneeAcadId
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Méthode pour récupérer les mémoires avec filtres
public function getMemoiresParSection($idSection, $idAnneeAcad, $filtreEtudiant = '', $filtreSujet = '', $filtreDate = '') {
    $sql = "SELECT dm.*, s.intitule, e.noms as nom_etudiant, 
            d.noms as nom_directeur, en.noms as nom_encadreur
            FROM depot_memoire dm
            JOIN sujets s ON dm.sujets_idsujets = s.idsujets
            JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
            LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
            LEFT JOIN agent en ON s.\"idEncadreur\" = en.\"idAgent\"
            WHERE s.\"idSpecialisation\" IN (
                SELECT sp.\"idSpecialisation\"
                FROM specialisation sp
                JOIN orientation o ON sp.idorientation = o.idorientation
                WHERE o.section_idsection = :idSection
            ) 
            AND s.annee_acad_idannee_acad = :idAnneeAcad";
    
    $params = [
        ':idSection' => $idSection, 
        ':idAnneeAcad' => $idAnneeAcad
    ];
    
    // Ajouter les filtres si présents
    if (!empty($filtreEtudiant)) {
        $sql .= " AND e.noms LIKE :filtreEtudiant";
        $params[':filtreEtudiant'] = "%$filtreEtudiant%";
    }
    
    if (!empty($filtreSujet)) {
        $sql .= " AND s.intitule LIKE :filtreSujet";
        $params[':filtreSujet'] = "%$filtreSujet%";
    }
    
    if (!empty($filtreDate)) {
        $sql .= " AND DATE(dm.\"dateDepot\") = :filtreDate";
        $params[':filtreDate'] = $filtreDate;
    }
    
    $sql .= " ORDER BY dm.\"dateDepot\" DESC";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Méthode pour récupérer les rapports de stage avec filtres
public function getRapportsStageParSection($idSection, $idAnneeAcad, $filtreEtudiant = '', $filtreTitre = '', $filtreLieuStage = '') {
    $sql = "SELECT dr.*, e.noms as nom_etudiant, a.noms as nom_encadreur
            FROM depot_rapport dr
            JOIN etudiant e ON dr.etudiant_idetudiant = e.idetudiant
            LEFT JOIN agent a ON dr.encadreur = a.\"idAgent\"
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            WHERE o.section_idsection = :idSection 
            AND e.annee_acad_idannee_acad = :idAnneeAcad";
    
    $params = [
        ':idSection' => $idSection,
        ':idAnneeAcad' => $idAnneeAcad
    ];
    
    // Ajouter les filtres si présents
    if (!empty($filtreEtudiant)) {
        $sql .= " AND e.noms LIKE :filtreEtudiant";
        $params[':filtreEtudiant'] = "%$filtreEtudiant%";
    }
    
    if (!empty($filtreTitre)) {
        $sql .= " AND dr.titre LIKE :filtreTitre";
        $params[':filtreTitre'] = "%$filtreTitre%";
    }
    
    if (!empty($filtreLieuStage)) {
        $sql .= " AND dr.lieu_stage LIKE :filtreLieuStage";
        $params[':filtreLieuStage'] = "%$filtreLieuStage%";
    }
    
    $sql .= " ORDER BY dr.\"dateDepot\" DESC";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Méthode pour récupérer les soutenances avec filtres
public function getSoutenancesParSection($idSection, $idAnneeAcad, $filtreEtudiant = '', $filtreDate = '', $filtreStatut = '') {
    $query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut, s.note_finale,
             sj.idsujets, sj.intitule, 
             e.noms as nom_etudiant, e.matricule,
             d.noms as nom_directeur,
             enc.noms as nom_encadreur,
             j.designation as jury_designation,
             jp.noms as president_nom,
             js.noms as secretaire_nom,
             (SELECT STRING_AGG(a.noms, '|' ORDER BY ls.est_premier_lecteur DESC) 
              FROM lecteurs_soutenance ls 
              JOIN agent a ON ls.idenseignant = a.\"idAgent\" 
              WHERE ls.idsoutenance = s.idsoutenance) as lecteurs
             FROM soutenance s
             JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
             JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
             LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
             LEFT JOIN agent enc ON sj.\"idEncadreur\" = enc.\"idAgent\"
             LEFT JOIN jury j ON s.jury_id = j.idjury
             LEFT JOIN agent jp ON j.id_president = jp.\"idAgent\"
             LEFT JOIN agent js ON j.id_secretaire = js.\"idAgent\"
             WHERE sj.annee_acad_idannee_acad = :idAnneeAcad";
    
    // Ajouter la condition pour la section via la chaîne étudiant → promotion → orientation → section
    $query .= " AND EXISTS (
                SELECT 1 FROM promotion p 
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                WHERE e.promotion_idpromotion = p.idpromotion 
                AND o.section_idsection = :idSection
            )";
    
    // Filtres supplémentaires
    $params = [
        'idAnneeAcad' => $idAnneeAcad,
        'idSection' => $idSection
    ];
    
    if (!empty($filtreEtudiant)) {
        $query .= " AND e.noms LIKE :filtreEtudiant";
        $params['filtreEtudiant'] = "%$filtreEtudiant%";
    }
    
    if (!empty($filtreDate)) {
        $query .= " AND DATE(s.date_soutenance) >= :filtreDate";
        $params['filtreDate'] = $filtreDate;
    }
    
    if (!empty($filtreStatut)) {
        $query .= " AND s.statut = :filtreStatut";
        $params['filtreStatut'] = $filtreStatut;
    }
    
    $query .= " ORDER BY s.date_soutenance DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère les statistiques de dépôt de mémoires par section
 */
public function getStatistiquesMemoires($idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(dm.iddepot_memoire) as nb_total,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 1 AND 3 THEN 1 END) as t1,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 4 AND 6 THEN 1 END) as t2,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 7 AND 9 THEN 1 END) as t3,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 10 AND 12 THEN 1 END) as t4
            FROM 
                section s
            LEFT JOIN orientation dso_o ON dso_o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = dso_o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN depot_memoire dm ON dm.sujets_idsujets = sj.idsujets";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= " GROUP BY s.idsection, s.\"designationSection\"
              ORDER BY s.\"designationSection\"";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques de dépôt de rapports par section
 */
public function getStatistiquesRapports($idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(dr.iddepot_rapport) as nb_total,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 1 AND 3 THEN 1 END) as t1,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 4 AND 6 THEN 1 END) as t2,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 7 AND 9 THEN 1 END) as t3,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 10 AND 12 THEN 1 END) as t4
            FROM 
                section s
            LEFT JOIN orientation o ON o.section_idsection = s.idsection
            LEFT JOIN promotion p ON p.orientation_idorientation = o.idorientation
            LEFT JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion AND e.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN depot_rapport dr ON dr.etudiant_idetudiant = e.idetudiant";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= " GROUP BY s.idsection, s.\"designationSection\"
              ORDER BY s.\"designationSection\"";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques de soutenances par section
 */
public function getStatistiquesSoutenances($idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(st.idsoutenance) as nb_total,
                COUNT(CASE WHEN st.statut = 'Programmée' THEN 1 END) as programmees,
                COUNT(CASE WHEN st.statut = 'Terminée' THEN 1 END) as terminees,
                COUNT(CASE WHEN st.statut = 'Reportée' THEN 1 END) as reportees,
                COUNT(CASE WHEN st.statut = 'Annulée' THEN 1 END) as annulees
            FROM 
                section s
            LEFT JOIN orientation dso_o ON dso_o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = dso_o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN soutenance st ON st.sujets_idsujets = sj.idsujets";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= " GROUP BY s.idsection, s.\"designationSection\"
              ORDER BY s.\"designationSection\"";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques de validation des sujets par section
 */
/**
 * Récupère les statistiques de validation des sujets par section
 */
public function getStatistiquesSujets($idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(sj.idsujets) as nb_total,
                COUNT(CASE WHEN sj.statut_validation = 'En attente' THEN 1 END) as en_attente,
                COUNT(CASE WHEN sj.statut_validation = 'Validé' THEN 1 END) as valides,
                COUNT(CASE WHEN sj.statut_validation = 'Rejeté' THEN 1 END) as rejetes,
                COUNT(CASE WHEN sj.statut_validation = 'Modifié' THEN 1 END) as sujets_modifies
            FROM 
                section s
            LEFT JOIN orientation dso_o ON dso_o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = dso_o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= " GROUP BY s.idsection, s.\"designationSection\"
              ORDER BY s.\"designationSection\"";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Récupère les statistiques de répartition des directeurs et encadreurs
 */
public function getStatistiquesEncadrement($idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                a.\"idAgent\",
                a.noms,
                COUNT(CASE WHEN sj.\"idDirecteur\" = a.\"idAgent\" THEN 1 END) as nb_sujets_diriges,
                COUNT(CASE WHEN sj.\"idEncadreur\" = a.\"idAgent\" THEN 1 END) as nb_sujets_encadres,
                COUNT(CASE WHEN j.idenseignant = a.\"idAgent\" THEN 1 END) as nb_jury
            FROM 
                agent a
            LEFT JOIN agent_section ag_s ON ag_s.\"idAgent\" = a.\"idAgent\"
            LEFT JOIN sujets sj ON (sj.\"idDirecteur\" = a.\"idAgent\" OR sj.\"idEncadreur\" = a.\"idAgent\") 
                               AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
            LEFT JOIN orientation dso_o2 ON sp.idorientation = dso_o2.idorientation
            LEFT JOIN jury_soutenance j ON j.idenseignant = a.\"idAgent\"
            LEFT JOIN soutenance st ON st.idsoutenance = j.idsoutenance
            WHERE a.type_agent = 'Enseignant'";

    $params = [':anneeAcad' => $idAnneeAcad];

    if ($idSection) {
        $sql .= " AND ag_s.idsection = :idSection AND dso_o2.section_idsection = :idSection";
        $params[':idSection'] = $idSection;
    }

    $sql .= " GROUP BY a.\"idAgent\", a.noms
              HAVING (
                  COUNT(CASE WHEN sj.\"idDirecteur\" = a.\"idAgent\" THEN 1 END) > 0
                  OR COUNT(CASE WHEN sj.\"idEncadreur\" = a.\"idAgent\" THEN 1 END) > 0
                  OR COUNT(CASE WHEN j.idenseignant = a.\"idAgent\" THEN 1 END) > 0
              )
              ORDER BY a.noms";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Récupère les soutenances programmées pour une section donnée
 */
public function getSoutenancesProgrammeesParSection($idSection, $idAnneeAcad, $filtreEtudiant = '', $filtreDate = '') {
    $query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut, s.note_finale,
             sj.idsujets, sj.intitule, 
             e.noms as nom_etudiant, e.matricule,
             d.noms as nom_directeur,
             enc.noms as nom_encadreur,
             j.designation as jury_designation,
             jp.noms as president_nom,
             js.noms as secretaire_nom,
             (SELECT STRING_AGG(a.noms, '|' ORDER BY ls.est_premier_lecteur DESC) 
              FROM lecteurs_soutenance ls 
              JOIN agent a ON ls.idenseignant = a.\"idAgent\" 
              WHERE ls.idsoutenance = s.idsoutenance) as lecteurs
             FROM soutenance s
             JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
             JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
             LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
             LEFT JOIN agent enc ON sj.\"idEncadreur\" = enc.\"idAgent\"
             LEFT JOIN jury j ON s.jury_id = j.idjury
             LEFT JOIN agent jp ON j.id_president = jp.\"idAgent\"
             LEFT JOIN agent js ON j.id_secretaire = js.\"idAgent\"
             WHERE sj.annee_acad_idannee_acad = :idAnneeAcad
             AND s.statut = 'Programmée'";
    
    // Ajouter la condition pour la section via la chaîne étudiant → promotion → orientation → section
    $query .= " AND EXISTS (
                SELECT 1 FROM promotion p 
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                WHERE e.promotion_idpromotion = p.idpromotion 
                AND o.section_idsection = :idSection
            )";
    
    // Filtres supplémentaires
    $params = [
        'idAnneeAcad' => $idAnneeAcad,
        'idSection' => $idSection
    ];
    
    if (!empty($filtreEtudiant)) {
        $query .= " AND e.noms LIKE :filtreEtudiant";
        $params['filtreEtudiant'] = "%$filtreEtudiant%";
    }
    
    if (!empty($filtreDate)) {
        $query .= " AND DATE(s.date_soutenance) >= :filtreDate";
        $params['filtreDate'] = $filtreDate;
    }
    
    $query .= " ORDER BY s.date_soutenance ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    
    




}
