<?php
class BibliothequeNumerique {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Ajouter un travail scientifique
    public function addTravail($titre, $typeDocument, $nomAuteur, $typeAuteur, $departementId, 
                             $specialisationId, $anneeAcademiqueId, $directeurId, $motsCles, 
                             $resume, $fichierPath, $estPublic, $estPayant, $idFrais) {
        $query = "INSERT INTO travaux_scientifiques (titre, type_document, nom_auteur, 
                 type_auteur, departement_id, specialisation_id, annee_academique_id, 
                 directeur_id, mots_cles, resume, fichier_path, statut, est_public, 
                 est_payant, idfrais) 
                 VALUES (:titre, :typeDocument, :nomAuteur, :typeAuteur, :departementId, 
                 :specialisationId, :anneeAcademiqueId, :directeurId, :motsCles, 
                 :resume, :fichierPath, 'En attente', :estPublic, :estPayant, :idFrais)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'titre' => $titre,
            'typeDocument' => $typeDocument,
            'nomAuteur' => $nomAuteur,
            'typeAuteur' => $typeAuteur,
            'departementId' => $departementId,
            'specialisationId' => $specialisationId,
            'anneeAcademiqueId' => $anneeAcademiqueId,
            'directeurId' => $directeurId,
            'motsCles' => $motsCles,
            'resume' => $resume,
            'fichierPath' => $fichierPath,
            'estPublic' => $estPublic,
            'estPayant' => $estPayant ? 1 : 0,
            'idFrais' => $estPayant ? $idFrais : null
        ]);
    }

    // Récupérer les travaux avec filtres
    public function getTravaux($search = '', $filters = []) {
        $query = "SELECT t.*, d.designationDepartement, s.designation as designationSpecialisation,
                 (SELECT COUNT(*) FROM consultations WHERE travail_id = t.id) as nb_consultations
                 FROM travaux_scientifiques t
                 LEFT JOIN section d ON t.departement_id = d.idsection
                 LEFT JOIN specialisation s ON t.specialisation_id = s.idSpecialisation
                 WHERE t.statut = 'Validé'";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (t.titre LIKE :search OR t.nom_auteur LIKE :search OR t.mots_cles LIKE :search OR t.resume LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        if (!empty($filters['type_document'])) {
            $query .= " AND t.type_document = :type_document";
            $params['type_document'] = $filters['type_document'];
        }
        
        if (!empty($filters['type_auteur'])) {
            $query .= " AND t.type_auteur = :type_auteur";
            $params['type_auteur'] = $filters['type_auteur'];
        }
        
        if (!empty($filters['departement_id'])) {
            $query .= " AND t.departement_id = :departement_id";
            $params['departement_id'] = $filters['departement_id'];
        }
        
        if (!empty($filters['annee_academique_id'])) {
            $query .= " AND t.annee_academique_id = :annee_academique_id";
            $params['annee_academique_id'] = $filters['annee_academique_id'];
        }
        
        if (!empty($filters['est_public'])) {
            $query .= " AND t.est_public = :est_public";
            $params['est_public'] = $filters['est_public'];
        }
        
        $query .= " ORDER BY t.date_depot DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT :limit";
            $params['limit'] = (int) $filters['limit'];
        }
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            if ($key === 'limit') {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier l'accès à un travail payant
    public function checkAccess($idTravail, $idEtudiant) {
        // Vérifier si le travail est payant
        $query = "SELECT est_payant, idfrais FROM travaux_scientifiques WHERE id = :idTravail";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idTravail' => $idTravail]);
        $travail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$travail || !$travail['est_payant']) {
            return true; // Accès libre si non payant
        }
        
        // Vérifier si l'étudiant a payé le frais requis
        $query = "SELECT COUNT(*) as count FROM etudiant_en_ordre 
                 WHERE idetudiant = :idEtudiant AND idfrais = :idFrais";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEtudiant' => $idEtudiant,
            'idFrais' => $travail['idfrais']
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    // Enregistrer une consultation
    public function logConsultation($idTravail, $ipAddress) {
        $query = "INSERT INTO consultations (travail_id, ip_address) VALUES (:idTravail, :ipAddress)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idTravail' => $idTravail,
            'ipAddress' => $ipAddress
        ]);
    }
}

