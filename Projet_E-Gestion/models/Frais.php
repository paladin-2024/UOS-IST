<?php

class Frais
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // ==========================================
    // MÉTHODES POUR LES FRAIS ACADÉMIQUES
    // ==========================================

    /**
     * Récupère tous les frais académiques pour une année académique
     * @param int $anneeAcadId - ID de l'année académique
     * @param string $search - Terme de recherche
     * @return array - Liste des frais
     */
    public function getAllFrais($anneeAcadId, $search = '')
    {
        $query = "SELECT 
                f.*, 
                p.designationPromotion, 
                o.designationOrientation,
                s.designationSection 
            FROM frais AS f
            INNER JOIN promotion AS p ON f.promotion_idpromotion = p.idpromotion
            INNER JOIN orientation AS o ON p.orientation_idorientation = o.idorientation
            INNER JOIN section AS s ON o.section_idsection = s.idsection
            WHERE f.annee_acad_idannee_acad = :anneeAcadId";
        
        if (!empty($search)) {
            $query .= " AND (f.designation LIKE :search OR p.designationPromotion LIKE :search OR s.designationSection LIKE :search)";
        }
        
        $query .= " ORDER BY s.designationSection, p.designationPromotion, f.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les frais académiques pour une section et une année académique
     * @param int $sectionId - ID de la section
     * @param int $anneeAcadId - ID de l'année académique
     * @param string $search - Terme de recherche
     * @return array - Liste des frais
     */
    public function getAllFraisBySection($sectionId, $anneeAcadId, $search = '')
    {
        $query = "SELECT 
                f.*, 
                p.designationPromotion, 
                o.designationOrientation,
                s.designationSection 
            FROM frais AS f
            INNER JOIN promotion AS p ON f.promotion_idpromotion = p.idpromotion
            INNER JOIN orientation AS o ON p.orientation_idorientation = o.idorientation
            INNER JOIN section AS s ON o.section_idsection = s.idsection
            WHERE f.annee_acad_idannee_acad = :anneeAcadId 
            AND s.idsection = :sectionId";
        
        if (!empty($search)) {
            $query .= " AND (f.designation LIKE :search OR p.designationPromotion LIKE :search)";
        }
        
        $query .= " ORDER BY p.designationPromotion, f.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les frais académiques pour une promotion
     * @param int $promotionId - ID de la promotion
     * @param int $anneeAcadId - ID de l'année académique
     * @return array - Liste des frais
     */
    public function getFraisByPromotion($promotionId, $anneeAcadId)
    {
        $query = "SELECT * FROM frais 
                WHERE promotion_idpromotion = :promotionId 
                AND annee_acad_idannee_acad = :anneeAcadId 
                ORDER BY designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

    /**
     * Récupère un frais académique par son ID
     * @param int $idFrais - ID du frais
     * @return array|bool - Détails du frais ou false si non trouvé
     */
    public function getFraisById($idFrais)
    {
        $query = "SELECT * FROM frais WHERE idfrais = :idFrais";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idFrais', $idFrais, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouveau frais académique
     * @param string $designation - Désignation du frais
     * @param float $montant - Montant du frais
     * @param string $devise - Devise du frais
     * @param string $description - Description du frais
     * @param bool $estObligatoire - Si le frais est obligatoire
     * @param int $promotionId - ID de la promotion
     * @param int $anneeAcadId - ID de l'année académique
     * @return bool - Succès ou échec
     */
    public function createFrais($designation, $montant, $devise, $description, $estObligatoire, $promotionId, $anneeAcadId)
    {
        $query = "INSERT INTO frais (designation, montant, devise, description, estObligatoire, 
                  dateCreation, promotion_idpromotion, annee_acad_idannee_acad) 
                  VALUES (:designation, :montant, :devise, :description, :estObligatoire, 
                  NOW(), :promotionId, :anneeAcadId)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'montant' => $montant,
            'devise' => $devise,
            'description' => $description,
            'estObligatoire' => $estObligatoire ? 1 : 0,
            'promotionId' => $promotionId,
            'anneeAcadId' => $anneeAcadId
        ]);
    }

    /**
     * Met à jour un frais académique existant
     * @param int $idFrais - ID du frais à mettre à jour
     * @param string $designation - Nouvelle désignation
     * @param float $montant - Nouveau montant
     * @param string $devise - Nouvelle devise
     * @param string $description - Nouvelle description
     * @param bool $estObligatoire - Si le frais est obligatoire
     * @param int $promotionId - ID de la promotion
     * @return bool - Succès ou échec
     */
    public function updateFrais($idFrais, $designation, $montant, $devise, $description, $estObligatoire, $promotionId)
    {
        $query = "UPDATE frais 
                  SET designation = :designation, 
                      montant = :montant, 
                      devise = :devise, 
                      description = :description, 
                      estObligatoire = :estObligatoire, 
                      promotion_idpromotion = :promotionId 
                  WHERE idfrais = :idFrais";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idFrais' => $idFrais,
            'designation' => $designation,
            'montant' => $montant,
            'devise' => $devise,
            'description' => $description,
            'estObligatoire' => $estObligatoire ? 1 : 0,
            'promotionId' => $promotionId
        ]);
    }

    /**
     * Supprime un frais académique
     * @param int $idFrais - ID du frais à supprimer
     * @return bool - Succès ou échec
     */
    public function deleteFrais($idFrais)
    {
        // Vérifier si des paiements existent pour ce frais
        $query = "SELECT COUNT(*) as count FROM paiement WHERE frais_idfrais = :idFrais";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idFrais' => $idFrais]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            // Ne pas supprimer si des paiements sont associés
            return false;
        }
        
        $query = "DELETE FROM frais WHERE idfrais = :idFrais";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idFrais' => $idFrais]);
    }
    
    /**
     * Vérifie si un frais académique existe déjà pour une promotion
     * @param string $designation - Désignation du frais
     * @param int $promotionId - ID de la promotion
     * @param int $anneeAcadId - ID de l'année académique
     * @return bool - True si existe, false sinon
     */
    public function checkDuplicateFrais($designation, $promotionId, $anneeAcadId)
    {
        $query = "SELECT COUNT(*) as count FROM frais 
                  WHERE designation = :designation 
                  AND promotion_idpromotion = :promotionId 
                  AND annee_acad_idannee_acad = :anneeAcadId";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'designation' => $designation,
            'promotionId' => $promotionId,
            'anneeAcadId' => $anneeAcadId
        ]);
        
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function checkDuplicateReference($referencePaiement)
    {
        $query = "SELECT COUNT(*) FROM paiement 
                  WHERE referencePaiement = :reference";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'reference' => $referencePaiement
        ]);
        
        $result = $stmt->fetch();
        return $result;
    }

    // ==========================================
    // MÉTHODES POUR LES FRAIS DE SOUTENANCE
    // ==========================================

    public function getAllFraisSoutenance($anneeAcadId, $search = '')
    {
        $query = "SELECT 
                fs.*,
                s.designationSection,
                u.nomUser as nomUtilisateur
            FROM frais_soutenance AS fs
            LEFT JOIN t_users AS u ON fs.user_id = u.idUser
            LEFT JOIN section AS s ON fs.section_id = s.idsection
            WHERE fs.annee_acad_id = :anneeAcadId";
        
        if (!empty($search)) {
            $query .= " AND (fs.designation LIKE :search OR s.designationSection LIKE :search)";
        }
        
        $query .= " ORDER BY s.designationSection, fs.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllFraisSoutenanceBySection($sectionId, $anneeAcadId, $search = '')
    {
        $query = "SELECT 
                fs.*,
                s.designationSection,
                u.nomUser as nomUtilisateur
            FROM frais_soutenance AS fs
            LEFT JOIN t_users AS u ON fs.user_id = u.idUser
            LEFT JOIN section AS s ON fs.section_id = s.idsection
            WHERE fs.annee_acad_id = :anneeAcadId 
            AND fs.section_id = :sectionId";
        
        if (!empty($search)) {
            $query .= " AND (fs.designation LIKE :search OR s.designationSection LIKE :search)";
        }
        
        $query .= " ORDER BY fs.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getFraisSoutenanceById($idFraisSoutenance)
    {
        $query = "SELECT fs.*, s.designationSection
                  FROM frais_soutenance fs
                  LEFT JOIN section s ON fs.section_id = s.idsection
                  WHERE fs.idfrais_soutenance = :idFraisSoutenance";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idFraisSoutenance', $idFraisSoutenance, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createFraisSoutenance($designation, $montant, $devise, $description, $anneeAcadId, $sectionId, $estObligatoire = true, $idUser = null) 
{
    $query = "INSERT INTO frais_soutenance 
              (designation, montant, devise, description, estObligatoire, dateCreation, section_id, annee_acad_id, user_id) 
              VALUES (:designation, :montant, :devise, :description, :estObligatoire, NOW(), :sectionId, :anneeAcadId, :idUser)";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'designation' => $designation,
        'montant' => $montant,
        'devise' => $devise,
        'description' => $description,
        'estObligatoire' => $estObligatoire ? 1 : 0,
        'sectionId' => $sectionId,
        'anneeAcadId' => $anneeAcadId,
        'idUser' => $idUser
    ]);
}

public function updateFraisSoutenance($idFraisSoutenance, $designation, $montant, $devise, $description, $sectionId, $estObligatoire = true) 
{
    $query = "UPDATE frais_soutenance 
              SET designation = :designation, 
                  montant = :montant, 
                  devise = :devise, 
                  description = :description, 
                  estObligatoire = :estObligatoire,
                  section_id = :sectionId
              WHERE idfrais_soutenance = :idFraisSoutenance";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'designation' => $designation,
        'montant' => $montant,
        'devise' => $devise,
        'description' => $description,
        'estObligatoire' => $estObligatoire ? 1 : 0,
        'sectionId' => $sectionId,
        'idFraisSoutenance' => $idFraisSoutenance
    ]);
}

/**
* Supprime un frais de soutenance
* @param int $idFraisSoutenance - ID du frais de soutenance à supprimer
* @return bool - Succès ou échec
*/
public function deleteFraisSoutenance($idFraisSoutenance)
{
// Vérifier si des paiements existent pour ce frais
$query = "SELECT COUNT(*) as count FROM paiement_soutenance WHERE frais_soutenance_id = :idFraisSoutenance";
$stmt = $this->db->prepare($query);
$stmt->execute(['idFraisSoutenance' => $idFraisSoutenance]);
$result = $stmt->fetch();

if ($result['count'] > 0) {
  // Ne pas supprimer si des paiements sont associés
  return false;
}

$query = "DELETE FROM frais_soutenance WHERE idfrais_soutenance = :idFraisSoutenance";
$stmt = $this->db->prepare($query);
return $stmt->execute(['idFraisSoutenance' => $idFraisSoutenance]);
}

public function checkDuplicateFraisSoutenance($designation, $sectionId, $anneeAcadId) {
    $query = "SELECT COUNT(*) as count FROM frais_soutenance 
              WHERE designation = :designation 
              AND section_id = :sectionId 
              AND annee_acad_idannee_acad = :anneeAcadId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// ==========================================
// MÉTHODES POUR LES PAIEMENTS
// ==========================================

/**
* Récupère tous les paiements de frais académiques
* @param string $search - Terme de recherche
* @param array $filters - Filtres à appliquer (anneeAcadId, promotionId, estComplet)
* @return array - Liste des paiements
*/
public function getPaiements($search = '', $filters = [])
{
$query = "SELECT 
      p.*,
      f.designation as designation_frais,
      f.montant as montant_total,
      f.devise,
      e.noms as nom_etudiant,
      e.matricule,
      pr.designationPromotion,
      s.designationSection,
      u.nomUser as nom_utilisateur
  FROM paiement AS p
  INNER JOIN frais AS f ON p.frais_idfrais = f.idfrais
  INNER JOIN etudiant AS e ON p.etudiant_idetudiant = e.idetudiant
  INNER JOIN promotion AS pr ON e.promotion_idpromotion = pr.idpromotion
  INNER JOIN orientation AS o ON pr.orientation_idorientation = o.idorientation
  INNER JOIN section AS s ON o.section_idsection = s.idsection
  INNER JOIN t_users AS u ON p.idUser = u.idUser
  WHERE 1=1";

// Appliquer les filtres
if (isset($filters['anneeAcadId'])) {
  $query .= " AND p.annee_acad_idannee_acad = :anneeAcadId";
}

if (isset($filters['promotionId'])) {
  $query .= " AND e.promotion_idpromotion = :promotionId";
}

if (isset($filters['estComplet'])) {
  $query .= " AND p.estComplet = :estComplet";
}

if (!empty($search)) {
  $query .= " AND (e.noms LIKE :search OR e.matricule LIKE :search OR f.designation LIKE :search)";
}

$query .= " ORDER BY p.datePaiement DESC";

$stmt = $this->db->prepare($query);

// Bind des paramètres de filtre
if (isset($filters['anneeAcadId'])) {
  $stmt->bindParam(':anneeAcadId', $filters['anneeAcadId'], PDO::PARAM_INT);
}

if (isset($filters['promotionId'])) {
  $stmt->bindParam(':promotionId', $filters['promotionId'], PDO::PARAM_INT);
}

if (isset($filters['estComplet'])) {
  $stmt->bindParam(':estComplet', $filters['estComplet'], PDO::PARAM_BOOL);
}

if (!empty($search)) {
  $searchParam = "%$search%";
  $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Récupère les paiements pour un étudiant
* @param int $etudiantId - ID de l'étudiant
* @param int $anneeAcadId - ID de l'année académique
* @return array - Liste des paiements
*/
public function getPaiementsByEtudiant($etudiantId, $anneeAcadId)
{
$query = "SELECT 
      p.*,
      f.designation as designation_frais,
      f.montant as montant_total,
      f.devise,
      u.nomUser as nom_utilisateur
  FROM paiement AS p
  INNER JOIN frais AS f ON p.frais_idfrais = f.idfrais
  INNER JOIN t_users AS u ON p.idUser = u.idUser
  WHERE p.etudiant_idetudiant = :etudiantId
  AND p.annee_acad_idannee_acad = :anneeAcadId
  ORDER BY p.datePaiement DESC";

$stmt = $this->db->prepare($query);
$stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
$stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
$stmt->execute();

return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Récupère les paiements pour un frais spécifique
* @param int $fraisId - ID du frais
* @param int $anneeAcadId - ID de l'année académique
* @return array - Liste des paiements
*/
public function getPaiementsByFrais($fraisId, $anneeAcadId)
{
$query = "SELECT 
      p.*,
      e.noms as nom_etudiant,
      e.matricule,
      f.montant as montant_total,
      f.devise,
      u.nomUser as nom_utilisateur
  FROM paiement AS p
  INNER JOIN frais AS f ON p.frais_idfrais = f.idfrais
  INNER JOIN etudiant AS e ON p.etudiant_idetudiant = e.idetudiant
  INNER JOIN t_users AS u ON p.idUser = u.idUser
  WHERE p.frais_idfrais = :fraisId
  AND p.annee_acad_idannee_acad = :anneeAcadId
  ORDER BY p.datePaiement DESC";

$stmt = $this->db->prepare($query);
$stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
$stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
$stmt->execute();

return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Enregistre un nouveau paiement de frais académique
* @param int $etudiantId - ID de l'étudiant
* @param int $fraisId - ID du frais
* @param float $montantPaye - Montant payé
* @param string $referencePaiement - Référence du paiement
* @param string $modePaiement - Mode de paiement
* @param string $commentaire - Commentaire sur le paiement
* @param int $anneeAcadId - ID de l'année académique
* @param int $idUser - ID de l'utilisateur qui enregistre le paiement
* @return bool - Succès ou échec
*/
public function enregistrerPaiement($etudiantId, $fraisId, $montantPaye, $referencePaiement, $modePaiement, $commentaire, $anneeAcadId, $idUser)
{
// Récupérer le montant total du frais
$frais = $this->getFraisById($fraisId);
if (!$frais) {
  return false;
}

// Vérifier si le paiement est complet
$estComplet = (float)$montantPaye >= (float)$frais['montant'];

$query = "INSERT INTO paiement (etudiant_idetudiant, frais_idfrais, montantPaye, referencePaiement, 
        datePaiement, estComplet, modePaiement, commentaire, annee_acad_idannee_acad, idUser) 
        VALUES (:etudiantId, :fraisId, :montantPaye, :referencePaiement, 
        NOW(), :estComplet, :modePaiement, :commentaire, :anneeAcadId, :idUser)";

$stmt = $this->db->prepare($query);
return $stmt->execute([
  'etudiantId' => $etudiantId,
  'fraisId' => $fraisId,
  'montantPaye' => $montantPaye,
  'referencePaiement' => $referencePaiement,
  'estComplet' => $estComplet ? 1 : 0,
  'modePaiement' => $modePaiement,
  'commentaire' => $commentaire,
  'anneeAcadId' => $anneeAcadId,
  'idUser' => $idUser
]);
}

/**
* Met à jour un paiement existant
* @param int $idPaiement - ID du paiement à mettre à jour
* @param float $montantPaye - Nouveau montant payé
* @param string $referencePaiement - Nouvelle référence du paiement
* @param string $modePaiement - Nouveau mode de paiement
* @param string $commentaire - Nouveau commentaire
* @return bool - Succès ou échec
*/
public function updatePaiement($idPaiement, $montantPaye, $referencePaiement, $modePaiement, $commentaire)
{
// Récupérer les informations du paiement et du frais
$query = "SELECT p.*, f.montant as montant_total 
        FROM paiement p 
        INNER JOIN frais f ON p.frais_idfrais = f.idfrais 
        WHERE p.idpaiement = :idPaiement";

$stmt = $this->db->prepare($query);
$stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
$stmt->execute();
$paiement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paiement) {
  return false;
}

// Vérifier si le paiement est complet
$estComplet = (float)$montantPaye >= (float)$paiement['montant_total'];

$query = "UPDATE paiement 
        SET montantPaye = :montantPaye, 
            referencePaiement = :referencePaiement, 
            estComplet = :estComplet, 
            modePaiement = :modePaiement, 
            commentaire = :commentaire 
        WHERE idpaiement = :idPaiement";

$stmt = $this->db->prepare($query);
return $stmt->execute([
  'idPaiement' => $idPaiement,
  'montantPaye' => $montantPaye,
  'referencePaiement' => $referencePaiement,
  'estComplet' => $estComplet ? 1 : 0,
  'modePaiement' => $modePaiement,
  'commentaire' => $commentaire
]);
}

/**
* Supprime un paiement
* @param int $idPaiement - ID du paiement à supprimer
* @return bool - Succès ou échec
*/
public function deletePaiement($idPaiement)
{
$query = "DELETE FROM paiement WHERE idpaiement = :idPaiement";
$stmt = $this->db->prepare($query);
return $stmt->execute(['idPaiement' => $idPaiement]);
}

// ==========================================
// MÉTHODES POUR LES PAIEMENTS DE SOUTENANCE
// ==========================================

/**
* Récupère tous les paiements de frais de soutenance
* @param string $search - Terme de recherche
* @param array $filters - Filtres à appliquer (anneeAcadId, sectionId, estComplet)
* @return array - Liste des paiements
*/
public function getPaiementsSoutenance($search = '', $filters = [])
{
$query = "SELECT 
      ps.*,
      fs.designation as designation_frais,
      fs.montant as montant_total,
      fs.devise,
      e.noms as nom_etudiant,
      e.matricule,
      pr.designationPromotion,
      s.designationSection,
      u.nom as nom_utilisateur
  FROM paiement_soutenance AS ps
  INNER JOIN frais_soutenance AS fs ON ps.idfrais_soutenance = fs.idfrais_soutenance
  INNER JOIN etudiant AS e ON ps.idetudiant = e.idetudiant
  INNER JOIN promotion AS pr ON e.promotion_idpromotion = pr.idpromotion
  INNER JOIN orientation AS o ON pr.orientation_idorientation = o.idorientation
  INNER JOIN section AS s ON o.section_idsection = s.idsection
  INNER JOIN t_users AS u ON ps.idUser = u.idUser
  WHERE 1=1";

// Appliquer les filtres
if (isset($filters['anneeAcadId'])) {
  $query .= " AND ps.annee_acad_idannee_acad = :anneeAcadId";
}

if (isset($filters['sectionId'])) {
  $query .= " AND s.idsection = :sectionId";
}

if (isset($filters['estComplet'])) {
  $query .= " AND ps.est_complet = :estComplet";
}

if (!empty($search)) {
  $query .= " AND (e.noms LIKE :search OR e.matricule LIKE :search OR fs.designation LIKE :search)";
}

$query .= " ORDER BY ps.date_paiement DESC";

$stmt = $this->db->prepare($query);

// Bind des paramètres de filtre
if (isset($filters['anneeAcadId'])) {
  $stmt->bindParam(':anneeAcadId', $filters['anneeAcadId'], PDO::PARAM_INT);
}

if (isset($filters['sectionId'])) {
  $stmt->bindParam(':sectionId', $filters['sectionId'], PDO::PARAM_INT);
}

if (isset($filters['estComplet'])) {
  $stmt->bindParam(':estComplet', $filters['estComplet'], PDO::PARAM_BOOL);
}

if (!empty($search)) {
  $searchParam = "%$search%";
  $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Récupère les paiements de soutenance pour un étudiant
* @param int $etudiantId - ID de l'étudiant
* @param int $anneeAcadId - ID de l'année académique
* @return array - Liste des paiements
*/
public function getPaiementsSoutenanceByEtudiant($etudiantId, $anneeAcadId)
{
$query = "SELECT 
      ps.*,
      fs.designation as designation_frais,
      fs.montant as montant_total,
      fs.devise,
      u.nomUser as nom_utilisateur
  FROM paiement_soutenance AS ps
  INNER JOIN frais_soutenance AS fs ON ps.frais_soutenance_id = fs.idfrais_soutenance
  INNER JOIN t_users AS u ON ps.user_id = u.idUser
  WHERE ps.etudiant_id = :etudiantId
  AND ps.annee_acad_id = :anneeAcadId
  ORDER BY ps.datePaiement DESC";

$stmt = $this->db->prepare($query);
$stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
$stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
$stmt->execute();

return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les paiements pour un frais de soutenance spécifique
 * @param int $fraisSoutenanceId - ID du frais de soutenance
 * @param int $anneeAcadId - ID de l'année académique
 * @return array - Liste des paiements
 */
public function getPaiementsByFraisSoutenance($fraisSoutenanceId, $anneeAcadId)
{
    $query = "SELECT 
            ps.*,
            e.noms as nom_etudiant,
            e.matricule,
            u.nom as nom_utilisateur
        FROM paiement_soutenance AS ps
        INNER JOIN etudiant AS e ON ps.idetudiant = e.idetudiant
        INNER JOIN t_users AS u ON ps.idUser = u.idUser
        WHERE ps.idfrais_soutenance = :fraisSoutenanceId
        AND ps.annee_acad_idannee_acad = :anneeAcadId
        ORDER BY ps.date_paiement DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':fraisSoutenanceId', $fraisSoutenanceId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Enregistre un nouveau paiement de frais de soutenance
 * @param int $fraisSoutenanceId ID du frais de soutenance
 * @param int $etudiantId ID de l'étudiant
 * @param float $montantPaye Montant payé
 * @param string $referencePaiement Référence du paiement
 * @param string $modePaiement Mode de paiement
 * @param string $commentaire Commentaire sur le paiement
 * @param int $anneeAcadId ID de l'année académique
 * @param int $userId ID de l'utilisateur qui enregistre le paiement
 * @return bool Succès ou échec
 */
public function enregistrerPaiementSoutenance($fraisSoutenanceId, $etudiantId, $montantPaye, $referencePaiement, $modePaiement, $commentaire, $anneeAcadId, $userId)
{
    // Récupérer le montant total du frais
    $fraisSoutenance = $this->getFraisSoutenanceById($fraisSoutenanceId);
    if (!$fraisSoutenance) {
        return false;
    }
    
    // Vérifier si le paiement est complet
    $estComplet = (float)$montantPaye >= (float)$fraisSoutenance['montant'];
    
    $query = "INSERT INTO paiement_soutenance (
                frais_soutenance_id, 
                etudiant_id, 
                montantPaye, 
                referencePaiement, 
                datePaiement, 
                estComplet, 
                modePaiement, 
                commentaire, 
                annee_acad_id, 
                user_id
              ) VALUES (
                :fraisSoutenanceId, 
                :etudiantId, 
                :montantPaye, 
                :referencePaiement,
                NOW(), 
                :estComplet, 
                :modePaiement, 
                :commentaire, 
                :anneeAcadId, 
                :userId
              )";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'fraisSoutenanceId' => $fraisSoutenanceId,
        'etudiantId' => $etudiantId,
        'montantPaye' => $montantPaye,
        'referencePaiement' => $referencePaiement,
        'estComplet' => $estComplet ? 1 : 0,
        'modePaiement' => $modePaiement,
        'commentaire' => $commentaire,
        'anneeAcadId' => $anneeAcadId,
        'userId' => $userId
    ]);
}

/**
 * Met à jour un paiement de frais de soutenance existant
 * @param int $idPaiement ID du paiement à mettre à jour
 * @param float $montantPaye Nouveau montant payé
 * @param string $referencePaiement Nouvelle référence de paiement
 * @param string $modePaiement Nouveau mode de paiement
 * @param string $commentaire Nouveau commentaire
 * @return bool Succès ou échec
 */
public function updatePaiementSoutenance($idPaiement, $montantPaye, $referencePaiement, $modePaiement, $commentaire = '')
{
    // Récupérer les informations actuelles du paiement
    $query = "SELECT ps.*, fs.montant as montant_total 
              FROM paiement_soutenance ps
              JOIN frais_soutenance fs ON ps.frais_soutenance_id = fs.idfrais_soutenance 
              WHERE ps.idpaiement_soutenance = :idPaiement";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paiement) {
        return false;
    }
    
    // Déterminer si le paiement est complet
    $estComplet = ($montantPaye >= $paiement['montant_total']);
    
    // Mettre à jour le paiement
    $query = "UPDATE paiement_soutenance 
              SET montantPaye = :montantPaye, 
                  referencePaiement = :referencePaiement, 
                  estComplet = :estComplet, 
                  modePaiement = :modePaiement, 
                  commentaire = :commentaire 
              WHERE idpaiement_soutenance = :idPaiement";
    
    $stmt = $this->db->prepare($query);
    return $stmt->execute([
        'montantPaye' => $montantPaye,
        'referencePaiement' => $referencePaiement,
        'estComplet' => $estComplet ? 1 : 0,
        'modePaiement' => $modePaiement,
        'commentaire' => $commentaire,
        'idPaiement' => $idPaiement
    ]);
}

/**
 * Supprime un paiement de soutenance
 * @param int $idPaiementSoutenance - ID du paiement à supprimer
 * @return bool - Succès ou échec
 */
public function deletePaiementSoutenance($idPaiement) {
    $query = "DELETE FROM paiement_soutenance WHERE idpaiement_soutenance = :idPaiement";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
    
    return $stmt->execute();
}

public function getPaiementSoutenanceById($idPaiement) {
    $query = "SELECT ps.*,
                     fs.designation as designation_frais,
                     fs.montant as montant_total,
                     fs.devise,
                     e.noms as nom_etudiant,
                     e.matricule,
                     u.nomUser as nom_utilisateur
              FROM paiement_soutenance ps
              JOIN frais_soutenance fs ON ps.frais_soutenance_id = fs.idfrais_soutenance
              JOIN etudiant e ON ps.etudiant_id = e.idetudiant
              JOIN t_users u ON ps.user_id = u.idUser
              WHERE ps.idpaiement_soutenance = :idPaiement";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==========================================
// MÉTHODES POUR LES STATISTIQUES DE PAIEMENT
// ==========================================

/**
 * Obtient les statistiques de paiement pour les frais académiques
 * @param int $anneeAcadId - ID de l'année académique
 * @param int|null $sectionId - ID de la section (optionnel)
 * @param int|null $promotionId - ID de la promotion (optionnel)
 * @return array - Statistiques de paiement
 */
public function getStatistiquesPaiement($anneeAcadId, $sectionId = null, $promotionId = null)
{
    // Total des frais par promotion
    $query = "SELECT 
            p.promotion_idpromotion,
            p.designationPromotion,
            o.designationOrientation,
            s.designationSection,
            COUNT(f.idfrais) as nb_frais,
            SUM(f.montant) as montant_total,
            f.devise,
            COUNT(DISTINCT e.idetudiant) as nb_etudiants,
            SUM(IF(pay.estComplet, 1, 0)) as frais_payes_complet
        FROM promotion AS p
        INNER JOIN orientation AS o ON p.orientation_idorientation = o.idorientation
        INNER JOIN section AS s ON o.section_idsection = s.idsection
        LEFT JOIN frais AS f ON p.idpromotion = f.promotion_idpromotion AND f.annee_acad_idannee_acad = :anneeAcadId
        LEFT JOIN etudiant AS e ON e.promotion_idpromotion = p.idpromotion AND e.annee_acad_idannee_acad = :anneeAcadId
        LEFT JOIN paiement AS pay ON pay.frais_idfrais = f.idfrais AND pay.etudiant_idetudiant = e.idetudiant
        WHERE p.annee_acad_idannee_acad = :anneeAcadId";
    
    if ($sectionId) {
        $query .= " AND s.idsection = :sectionId";
    }
    
    if ($promotionId) {
        $query .= " AND p.idpromotion = :promotionId";
    }
    
    $query .= " GROUP BY p.idpromotion, f.devise
               ORDER BY s.designationSection, p.designationPromotion";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if ($sectionId) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    
    if ($promotionId) {
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtient les statistiques de paiement pour les frais de soutenance
 * @param int $anneeAcadId - ID de l'année académique
 * @param int|null $sectionId - ID de la section (optionnel)
 * @return array - Statistiques de paiement
 */
public function getStatistiquesPaiementSoutenance($anneeAcadId, $sectionId = null)
{
    $query = "SELECT 
            s.idsection,
            s.designationSection,
            COUNT(fs.idfrais_soutenance) as nb_frais,
            SUM(fs.montant) as montant_total,
            fs.devise,
            COUNT(DISTINCT ps.idetudiant) as nb_etudiants,
            SUM(IF(ps.est_complet, 1, 0)) as frais_payes_complet
        FROM section AS s
        LEFT JOIN frais_soutenance AS fs ON fs.annee_acad_idannee_acad = :anneeAcadId
        LEFT JOIN paiement_soutenance AS ps ON ps.idfrais_soutenance = fs.idfrais_soutenance
        WHERE 1=1";
    
    if ($sectionId) {
        $query .= " AND s.idsection = :sectionId";
    }
    
    $query .= " GROUP BY s.idsection, fs.devise
               ORDER BY s.designationSection";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if ($sectionId) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifie si un étudiant a payé tous les frais obligatoires pour une année académique
 * @param int $etudiantId - ID de l'étudiant
 * @param int $anneeAcadId - ID de l'année académique
 * @return bool - True si tous les frais obligatoires sont payés, false sinon
 */
public function etudiantEnRegle($etudiantId, $anneeAcadId)
{
    // Récupérer la promotion de l'étudiant
    $query = "SELECT promotion_idpromotion FROM etudiant WHERE idetudiant = :etudiantId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        return false;
    }
    
    $promotionId = $etudiant['promotion_idpromotion'];
    
    // Récupérer tous les frais obligatoires pour cette promotion
    $query = "SELECT f.idfrais, f.montant
             FROM frais AS f 
             WHERE f.promotion_idpromotion = :promotionId 
             AND f.annee_acad_idannee_acad = :anneeAcadId 
             AND f.estObligatoire = 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->execute();
    $fraisObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($fraisObligatoires)) {
        return true; // Pas de frais obligatoires
    }
    
    // Vérifier les paiements pour chaque frais obligatoire
    foreach ($fraisObligatoires as $frais) {
        $query = "SELECT COUNT(*) as count 
                 FROM paiement 
                 WHERE etudiant_idetudiant = :etudiantId 
                 AND frais_idfrais = :fraisId 
                 AND estComplet = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':fraisId', $frais['idfrais'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            return false; // Au moins un frais obligatoire n'est pas payé
        }
    }
    
    return true; // Tous les frais obligatoires sont payés
}

/**
 * Marque les étudiants comme étant en ordre de paiement pour un frais spécifique
 * @param array $etudiantIds - IDs des étudiants
 * @param int $fraisId - ID du frais
 * @param int $anneeAcadId - ID de l'année académique
 * @param int $idUser - ID de l'utilisateur qui fait l'enregistrement
 * @param int|null $idImport - ID de l'importation (si importation en masse)
 * @return int - Nombre d'étudiants mis en ordre
 */
public function marquerEtudiantsEnOrdre($etudiantIds, $fraisId, $anneeAcadId, $idUser, $idImport = null)
{
    $count = 0;
    
    foreach ($etudiantIds as $etudiantId) {
        $query = "INSERT INTO etudiant_en_ordre (idetudiant, idfrais, annee_acad_idannee_acad, date_enregistrement, idimport, idUser) 
                  VALUES (:etudiantId, :fraisId, :anneeAcadId, NOW(), :idImport, :idUser)";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'etudiantId' => $etudiantId,
            'fraisId' => $fraisId,
            'anneeAcadId' => $anneeAcadId,
            'idImport' => $idImport,
            'idUser' => $idUser
        ]);
        
        if ($result) {
            $count++;
        }
    }
    
    return $count;
}


public function etudiantEnOrdrePourFrais($etudiantId, $fraisId, $anneeAcadId)
{
   // Vérifier si l'étudiant a un paiement complet
   $query = "SELECT COUNT(*) as count 
            FROM paiement 
            WHERE etudiant_idetudiant = :etudiantId 
            AND frais_idfrais = :fraisId 
            AND annee_acad_idannee_acad = :anneeAcadId
            AND estComplet = 1";
   
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
   $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   if ($result['count'] > 0) {
       return true;
   }
   
   // Vérifier si l'étudiant est marqué comme en ordre
   $query = "SELECT COUNT(*) as count 
            FROM etudiant_en_ordre 
            WHERE idetudiant = :etudiantId 
            AND idfrais = :fraisId 
            AND annee_acad_idannee_acad = :anneeAcadId";
   
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
   $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   return $result['count'] > 0;
}

/**
* Récupère tous les étudiants en ordre pour un frais spécifique
* @param int $fraisId - ID du frais
* @param int $anneeAcadId - ID de l'année académique
* @return array - Liste des étudiants en ordre
*/
public function getEtudiantsEnOrdrePourFrais($fraisId, $anneeAcadId)
{
   $query = "SELECT DISTINCT e.* 
           FROM etudiant e
           WHERE (
               e.idetudiant IN (
                   SELECT etudiant_idetudiant FROM paiement 
                   WHERE frais_idfrais = :fraisId 
                   AND annee_acad_idannee_acad = :anneeAcadId 
                   AND estComplet = 1
               )
               OR
               e.idetudiant IN (
                   SELECT idetudiant FROM etudiant_en_ordre 
                   WHERE idfrais = :fraisId 
                   AND annee_acad_idannee_acad = :anneeAcadId
               )
           )
           ORDER BY e.noms";
   
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Enregistre une importation d'étudiants en ordre
* @param string $fichier - Nom du fichier importé
* @param int $fraisId - ID du frais concerné
* @param int $anneeAcadId - ID de l'année académique
* @param int|null $sectionId - ID de la section concernée
* @param int $idUser - ID de l'utilisateur qui fait l'importation
* @return int - ID de l'importation
*/
public function enregistrerImportEtudiantsOrdre($fichier, $fraisId, $anneeAcadId, $sectionId, $idUser)
{
   $query = "INSERT INTO import_etudiants_ordre (fichier, date_import, idfrais, annee_acad_idannee_acad, idsection, idUser) 
             VALUES (:fichier, NOW(), :fraisId, :anneeAcadId, :sectionId, :idUser)";
   
   $stmt = $this->db->prepare($query);
   $stmt->execute([
       'fichier' => $fichier,
       'fraisId' => $fraisId,
       'anneeAcadId' => $anneeAcadId,
       'sectionId' => $sectionId,
       'idUser' => $idUser
   ]);
   
   return $this->db->lastInsertId();
}

/**
* Récupère l'historique des importations d'étudiants en ordre
* @param int|null $fraisId - ID du frais (optionnel)
* @param int|null $anneeAcadId - ID de l'année académique (optionnel)
* @return array - Liste des importations
*/
public function getImportationsEtudiantsOrdre($fraisId = null, $anneeAcadId = null)
{
   $query = "SELECT i.*, 
           f.designation as designation_frais,
           s.designationSection,
           u.nom as nom_utilisateur,
           COUNT(e.idetudiant) as nb_etudiants
       FROM import_etudiants_ordre i
       LEFT JOIN frais f ON i.idfrais = f.idfrais
       LEFT JOIN section s ON i.idsection = s.idsection
       LEFT JOIN t_users u ON i.idUser = u.idUser
       LEFT JOIN etudiant_en_ordre e ON e.idimport = i.idimport
       WHERE 1=1";
   
   if ($fraisId) {
       $query .= " AND i.idfrais = :fraisId";
   }
   
   if ($anneeAcadId) {
       $query .= " AND i.annee_acad_idannee_acad = :anneeAcadId";
   }
   
   $query .= " GROUP BY i.idimport ORDER BY i.date_import DESC";
   
   $stmt = $this->db->prepare($query);
   
   if ($fraisId) {
       $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
   }
   
   if ($anneeAcadId) {
       $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   }
   
   $stmt->execute();
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Récupère les paiements en attente de validation
* @param int $anneeAcadId - ID de l'année académique
* @param int|null $sectionId - ID de la section (optionnel)
* @return array - Liste des paiements en attente
*/
public function getPaiementsEnAttente($anneeAcadId, $sectionId = null)
{
   $query = "SELECT 
           p.*,
           f.designation as designation_frais,
           f.montant as montant_total,
           f.devise,
           e.noms as nom_etudiant,
           e.matricule,
           pr.designationPromotion,
           s.designationSection
       FROM paiement AS p
       INNER JOIN frais AS f ON p.frais_idfrais = f.idfrais
       INNER JOIN etudiant AS e ON p.etudiant_idetudiant = e.idetudiant
       INNER JOIN promotion AS pr ON e.promotion_idpromotion = pr.idpromotion
       INNER JOIN orientation AS o ON pr.orientation_idorientation = o.idorientation
       INNER JOIN section AS s ON o.section_idsection = s.idsection
       WHERE p.annee_acad_idannee_acad = :anneeAcadId
       AND p.valide = 0";
   
   if ($sectionId) {
       $query .= " AND s.idsection = :sectionId";
   }
   
   $query .= " ORDER BY p.datePaiement DESC";
   
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   
   if ($sectionId) {
       $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
   }
   
   $stmt->execute();
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Valide un paiement
* @param int $idPaiement - ID du paiement à valider
* @param int $idUser - ID de l'utilisateur qui valide
* @return bool - Succès ou échec
*/
public function validerPaiement($idPaiement, $idUser)
{
   $query = "UPDATE paiement 
             SET valide = 1, 
                 idValidateur = :idUser, 
                 dateValidation = NOW() 
             WHERE idpaiement = :idPaiement";
   
   $stmt = $this->db->prepare($query);
   return $stmt->execute([
       'idPaiement' => $idPaiement,
       'idUser' => $idUser
   ]);
}

/**
* Rejette un paiement
* @param int $idPaiement - ID du paiement à rejeter
* @param string $motifRejet - Motif du rejet
* @param int $idUser - ID de l'utilisateur qui rejette
* @return bool - Succès ou échec
*/
public function rejeterPaiement($idPaiement, $motifRejet, $idUser)
{
   $query = "UPDATE paiement 
             SET valide = 2, 
                 idValidateur = :idUser, 
                 dateValidation = NOW(),
                 commentaire = CONCAT(commentaire, '\nRejet: ', :motifRejet)
             WHERE idpaiement = :idPaiement";
   
   $stmt = $this->db->prepare($query);
   return $stmt->execute([
       'idPaiement' => $idPaiement,
       'motifRejet' => $motifRejet,
       'idUser' => $idUser
   ]);
}

/**
* Récupère les statistiques globales de paiement pour un tableau de bord
* @param int $anneeAcadId - ID de l'année académique
* @return array - Statistiques globales
*/
public function getStatistiquesGlobales($anneeAcadId)
{
   $stats = [
       'total_frais' => 0,
       'total_montant' => 0,
       'total_etudiants' => 0,
       'total_paiements' => 0,
       'montant_paye' => 0,
       'pourcentage_paiement' => 0,
       'par_devise' => []
   ];
   
   // Nombre total de frais
   $query = "SELECT COUNT(*) as count FROM frais WHERE annee_acad_idannee_acad = :anneeAcadId";
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   $stats['total_frais'] = $result['count'];
   
   // Montant total des frais par devise
   $query = "SELECT devise, SUM(montant) as total 
            FROM frais 
            WHERE annee_acad_idannee_acad = :anneeAcadId 
            GROUP BY devise";
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
   foreach ($results as $result) {
       $stats['par_devise'][$result['devise']] = [
           'total' => $result['total'],
           'paye' => 0
       ];
   }
   
   // Nombre total d'étudiants
   $query = "SELECT COUNT(*) as count FROM etudiant WHERE annee_acad_idannee_acad = :anneeAcadId";
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   $stats['total_etudiants'] = $result['count'];
   
   // Nombre total de paiements et montant payé
   $query = "SELECT COUNT(*) as count, SUM(p.montantPaye) as total, f.devise
            FROM paiement p
            INNER JOIN frais f ON p.frais_idfrais = f.idfrais
            WHERE p.annee_acad_idannee_acad = :anneeAcadId
            GROUP BY f.devise";
   $stmt = $this->db->prepare($query);
   $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
   $stmt->execute();
   $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
   $stats['total_paiements'] = 0;
   foreach ($results as $result) {
       $stats['total_paiements'] += $result['count'];
       if (isset($stats['par_devise'][$result['devise']])) {
           $stats['par_devise'][$result['devise']]['paye'] = $result['total'];
       }
   }
   
   // Calcul du pourcentage global de paiement
   foreach ($stats['par_devise'] as $devise => $data) {
       if ($data['total'] > 0) {
           $stats['par_devise'][$devise]['pourcentage'] = round(($data['paye'] / $data['total']) * 100, 2);
       } else {
           $stats['par_devise'][$devise]['pourcentage'] = 0;
       }
   }
   
   return $stats;
}

/**
 * Récupère les frais de soutenance disponibles pour un étudiant
 * @param int $etudiantId ID de l'étudiant
 * @param int $anneeAcadId ID de l'année académique
 * @return array Liste des frais de soutenance avec montants payés et restants
 */
public function getFraisSoutenanceForEtudiant($etudiantId, $anneeAcadId) {
    // Récupérer les informations sur l'étudiant et sa promotion
    $query = "SELECT e.*, p.orientation_idorientation 
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              WHERE e.idetudiant = :etudiantId";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        return [];
    }
    
    // Récupérer la section associée à l'orientation de l'étudiant
    $query = "SELECT section_idsection FROM orientation WHERE idorientation = :orientationId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':orientationId', $etudiant['orientation_idorientation'], PDO::PARAM_INT);
    $stmt->execute();
    $orientation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orientation) {
        return [];
    }
    
    $sectionId = $orientation['section_idsection'];
    
    // Récupérer tous les frais de soutenance pour cette section
    $query = "SELECT fs.*, 
                     COALESCE((SELECT SUM(ps.montantPaye) 
                              FROM paiement_soutenance ps 
                              WHERE ps.frais_soutenance_id = fs.idfrais_soutenance 
                              AND ps.etudiant_id = :etudiantId), 0) as montantPaye,
                     fs.montant - COALESCE((SELECT SUM(ps.montantPaye) 
                                          FROM paiement_soutenance ps 
                                          WHERE ps.frais_soutenance_id = fs.idfrais_soutenance 
                                          AND ps.etudiant_id = :etudiantId), 0) as montantRestant
              FROM frais_soutenance fs
              WHERE fs.annee_acad_id = :anneeAcadId
              AND (fs.section_id = :sectionId OR fs.section_id IS NULL)
              ORDER BY fs.designation";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



public function getFraisSoutenanceByPromotion($promotionId, $anneeAcadId = null) {
    // D'abord, récupérer l'ID de la section pour cette promotion
    $sectionQuery = "SELECT s.idsection 
                    FROM promotion p
                    JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    JOIN section s ON o.section_idsection = s.idsection
                    WHERE p.idpromotion = :promotionId";
    
    $sectionStmt = $this->db->prepare($sectionQuery);
    $sectionStmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $sectionStmt->execute();
    $sectionId = $sectionStmt->fetchColumn();
    
    if (!$sectionId) {
        return []; // Retourner un tableau vide si la section n'est pas trouvée
    }
    
    // Maintenant, récupérer les frais de soutenance pour cette section
    $query = "SELECT fs.* 
              FROM frais_soutenance fs
              WHERE fs.section_id = :sectionId";
    
    // Si l'année académique est spécifiée, ajouter la condition
    if ($anneeAcadId) {
        $query .= " AND fs.annee_acad_id = :anneeAcadId";
    }
    
    // Ordonner les résultats par désignation
    $query .= " ORDER BY fs.designation";
    
    // Préparer et exécuter la requête
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    
    if ($anneeAcadId) {
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Récupère l'état de paiement d'un étudiant pour un frais spécifique
 * @param int $etudiantId - ID de l'étudiant
 * @param int $fraisId - ID du frais
 * @param string $typeFrais - Type de frais ('frais' ou 'soutenance')
 * @return array|bool - Informations sur le paiement ou false si non trouvé
 */
public function getEtatPaiementEtudiant($etudiantId, $fraisId, $typeFrais = 'frais') {
    if ($typeFrais == 'frais') {
        // Pour les frais académiques
        $queryFrais = "SELECT f.idfrais, f.designation, f.montant as montant_total, f.devise
                      FROM frais f 
                      WHERE f.idfrais = :fraisId";
        
        $queryPaiement = "SELECT SUM(p.montantPaye) as montant_paye
                         FROM paiement p
                         WHERE p.etudiant_idetudiant = :etudiantId
                         AND p.frais_idfrais = :fraisId";
    } else {
        // Pour les frais de soutenance
        $queryFrais = "SELECT fs.idfrais_soutenance as idfrais, fs.designation, fs.montant as montant_total, fs.devise
                      FROM frais_soutenance fs 
                      WHERE fs.idfrais_soutenance = :fraisId";
        
        $queryPaiement = "SELECT SUM(ps.montant_paye) as montant_paye
                         FROM paiement_soutenance ps
                         WHERE ps.idetudiant = :etudiantId
                         AND ps.idfrais_soutenance = :fraisId";
    }
    
    // Récupérer les informations du frais
    $stmt = $this->db->prepare($queryFrais);
    $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
    $stmt->execute();
    $frais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$frais) {
        return false;
    }
    
    // Récupérer le montant payé
    $stmt = $this->db->prepare($queryPaiement);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $montantPaye = $paiement['montant_paye'] ?? 0;
    
    // Déterminer si le paiement est complet
    $estComplet = (float)$montantPaye >= (float)$frais['montant_total'];
    
    return [
        'frais_id' => $frais['idfrais'],
        'designation' => $frais['designation'],
        'montant_total' => (float)$frais['montant_total'],
        'montant_paye' => (float)$montantPaye,
        'montant_restant' => (float)$frais['montant_total'] - (float)$montantPaye,
        'devise' => $frais['devise'],
        'est_complet' => $estComplet
    ];
}


/**
 * Récupère la liste des étudiants éligibles à la soutenance (en ordre de paiement)
 * @param int|null $sectionId ID de la section (optionnel)
 * @param int|null $promotionId ID de la promotion (optionnel)
 * @param int $anneeAcadId ID de l'année académique
 * @return array Liste des étudiants éligibles
 */
public function getEtudiantsEligiblesSoutenance($sectionId = null, $promotionId = null, $anneeAcadId = null) {
    $query = "SELECT 
                e.idetudiant,
                e.matricule,
                e.noms,
                p.designationPromotion,
                s.designationSection,
                fs.designation as designation_frais,
                COUNT(DISTINCT fs.idfrais_soutenance) as total_frais,
                COUNT(DISTINCT CASE WHEN ps.estComplet = 1 THEN fs.idfrais_soutenance END) as frais_payes,
                SUM(ps.montantPaye) as montant_total_paye,
                MAX(ps.datePaiement) as date_dernier_paiement,
                fs.devise
            FROM etudiant e
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            JOIN frais_soutenance fs ON (fs.section_id = s.idsection OR fs.section_id IS NULL)
                AND fs.annee_acad_id = :anneeAcadId
            LEFT JOIN paiement_soutenance ps ON ps.frais_soutenance_id = fs.idfrais_soutenance
                AND ps.etudiant_id = e.idetudiant
                AND ps.annee_acad_id = :anneeAcadId
            WHERE e.annee_acad_idannee_acad = :anneeAcadId";
    
    // Ajouter les filtres
    if ($sectionId) {
        $query .= " AND s.idsection = :sectionId";
    }
    if ($promotionId) {
        $query .= " AND p.idpromotion = :promotionId";
    }
    
    // Grouper et filtrer pour n'avoir que les étudiants en ordre
    $query .= " GROUP BY e.idetudiant, fs.devise
                HAVING COUNT(DISTINCT fs.idfrais_soutenance) = COUNT(DISTINCT CASE WHEN ps.estComplet = 1 THEN fs.idfrais_soutenance END)
                ORDER BY s.designationSection, p.designationPromotion, e.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if ($sectionId) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    if ($promotionId) {
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la liste des étudiants avec litiges de frais de soutenance
 * @param int|null $sectionId ID de la section (optionnel)
 * @param int|null $promotionId ID de la promotion (optionnel)
 * @param int $anneeAcadId ID de l'année académique
 * @return array Liste des étudiants avec litiges
 */
public function getEtudiantsLitigesSoutenance($sectionId = null, $promotionId = null, $anneeAcadId = null) {
    $query = "SELECT 
                e.idetudiant,
                e.matricule,
                e.noms,
                p.designationPromotion,
                s.designationSection,
                fs.designation as designation_frais,
                COUNT(DISTINCT fs.idfrais_soutenance) as total_frais,
                COUNT(DISTINCT CASE WHEN ps.estComplet = 1 THEN fs.idfrais_soutenance END) as frais_payes,
                COUNT(DISTINCT fs.idfrais_soutenance) - COUNT(DISTINCT CASE WHEN ps.estComplet = 1 THEN fs.idfrais_soutenance END) as frais_manquants,
                GROUP_CONCAT(DISTINCT CASE WHEN (ps.estComplet IS NULL OR ps.estComplet = 0) THEN fs.designation END SEPARATOR ', ') as frais_manquants_liste,
                SUM(CASE WHEN ps.montantPaye IS NULL THEN fs.montant ELSE fs.montant - ps.montantPaye END) as montant_restant,
                fs.devise,
                CASE 
                    WHEN COUNT(DISTINCT CASE WHEN ps.idpaiement_soutenance IS NULL THEN fs.idfrais_soutenance END) > 0 THEN 'Non payé'
                    ELSE 'Paiement partiel'
                END as statut_paiement
            FROM etudiant e
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            JOIN frais_soutenance fs ON (fs.section_id = s.idsection OR fs.section_id IS NULL)
                AND fs.annee_acad_id = :anneeAcadId
            LEFT JOIN paiement_soutenance ps ON ps.frais_soutenance_id = fs.idfrais_soutenance
                AND ps.etudiant_id = e.idetudiant
                AND ps.annee_acad_id = :anneeAcadId
            WHERE e.annee_acad_idannee_acad = :anneeAcadId";
    
    // Ajouter les filtres
    if ($sectionId) {
        $query .= " AND s.idsection = :sectionId";
    }
    if ($promotionId) {
        $query .= " AND p.idpromotion = :promotionId";
    }
    
    // Grouper et filtrer pour n'avoir que les étudiants avec litiges
    $query .= " GROUP BY e.idetudiant, fs.devise
                HAVING COUNT(DISTINCT fs.idfrais_soutenance) > COUNT(DISTINCT CASE WHEN ps.estComplet = 1 THEN fs.idfrais_soutenance END)
                ORDER BY s.designationSection, p.designationPromotion, e.noms";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
    
    if ($sectionId) {
        $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
    }
    if ($promotionId) {
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}






}

