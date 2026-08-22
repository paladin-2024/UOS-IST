<?php

class DependanceServiceFrais
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // =====================================================
    // GESTION DES SERVICES/DOCUMENTS
    // =====================================================

    /**
     * Crée un nouveau service ou document
     */
    public function createService($designation, $type, $description, $scope, $userId)
    {
        $query = "INSERT INTO services_documents 
                  (designation, type, description, scope, created_by, date_creation) 
                  VALUES (:designation, :type, :description, :scope, :userId, NOW())";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'type' => $type,
            'description' => $description,
            'scope' => $scope,
            'userId' => $userId
        ]);
    }

    /**
     * Récupère tous les services/documents
     */
    public function getAllServices($active = true, $type = null)
    {
        $query = "SELECT * FROM services_documents WHERE 1=1";
        
        if ($active) {
            $query .= " AND active = 1";
        }
        
        if ($type) {
            $query .= " AND type = :type";
        }
        
        $query .= " ORDER BY type, designation";
        
        $stmt = $this->db->prepare($query);
        
        if ($type) {
            $stmt->bindParam(':type', $type);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un service par ID
     */
    public function getServiceById($id)
    {
        $query = "SELECT * FROM services_documents WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour un service
     */
    public function updateService($id, $designation, $description, $scope, $active = true)
    {
        $query = "UPDATE services_documents 
                  SET designation = :designation, 
                      description = :description, 
                      scope = :scope,
                      active = :active,
                      date_modification = NOW()
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'designation' => $designation,
            'description' => $description,
            'scope' => $scope,
            'active' => $active ? 1 : 0
        ]);
    }

    /**
     * Supprime un service
     */
    public function deleteService($id)
    {
        $query = "DELETE FROM services_documents WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    // =====================================================
    // GESTION DES DÉPENDANCES FRAIS
    // =====================================================

    /**
     * Ajoute une dépendance frais pour un service
     */
    public function addDependance($serviceId, $fraisId, $scope, $promotionId = null, $cycle = null, $anneeAcadId = null, $ordre = 0, $userId = null)
    {
        try {
            $query = "INSERT INTO dependances_services_frais 
                      (service_id, frais_id, promotion_id, cycle, annee_acad_id, scope, ordre, created_by, active) 
                      VALUES (:serviceId, :fraisId, :promotionId, :cycle, :anneeAcadId, :scope, :ordre, :userId, 1)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'serviceId' => $serviceId,
                'fraisId' => $fraisId,
                'promotionId' => $promotionId,
                'cycle' => $cycle,
                'anneeAcadId' => $anneeAcadId,
                'scope' => $scope,
                'ordre' => $ordre,
                'userId' => $userId
            ]);
        } catch (Exception $e) {
            error_log("Erreur addDependance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les dépendances pour un service
     */
    public function getDependancesByService($serviceId)
    {
        $query = "SELECT d.*, f.designation as frais_designation, f.montant, f.devise,
                         p.designationPromotion, o.designationOrientation, s.designationSection,
                         aa.designation as annee_academique
                  FROM dependances_services_frais d
                  LEFT JOIN frais f ON d.frais_id = f.id
                  LEFT JOIN promotion p ON d.promotion_id = p.idpromotion
                  LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  LEFT JOIN section s ON o.section_idsection = s.idsection
                  LEFT JOIN annee_acad aa ON d.annee_acad_id = aa.idannee_acad
                  WHERE d.service_id = :serviceId AND d.active = 1
                  ORDER BY d.ordre, d.date_creation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une dépendance par ID
     */
    public function getDependanceById($id)
    {
        $query = "SELECT d.*, f.designation as frais_designation
                  FROM dependances_services_frais d
                  LEFT JOIN frais f ON d.frais_id = f.id
                  WHERE d.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les frais affectés à une promotion et disponibles pour une dépendance
     */
    public function getFraisAffectesByPromotion($promotionId, $anneeAcadId = null)
    {
        $query = "SELECT DISTINCT f.id, f.designation, f.montant, f.devise, f.categorie_id,
                         cf.designation as categorie_nom
                  FROM affectation_frais af
                  JOIN frais f ON af.frais_id = f.id
                  LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
                  WHERE af.promotion_id = :promotionId";
        
        if ($anneeAcadId) {
            $query .= " AND f.annee_acad_id = :anneeAcadId";
        }
        
        $query .= " ORDER BY f.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        
        if ($anneeAcadId) {
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les frais affectés à une année académique entière
     */
    public function getFraisAffectesByAnneeAcad($anneeAcadId)
    {
        $query = "SELECT DISTINCT f.id, f.designation, f.montant, f.devise, f.categorie_id,
                         cf.designation as categorie_nom
                  FROM affectation_frais af
                  JOIN frais f ON af.frais_id = f.id
                  LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
                  WHERE f.annee_acad_id = :anneeAcadId
                  ORDER BY f.designation";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime une dépendance
     */
    public function deleteDependance($id)
    {
        $query = "DELETE FROM dependances_services_frais WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Met à jour l'ordre d'une dépendance
     */
    public function updateDependanceOrder($id, $ordre)
    {
        $query = "UPDATE dependances_services_frais 
                  SET ordre = :ordre, date_modification = NOW()
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id, 'ordre' => $ordre]);
    }

    // =====================================================
    // VÉRIFICATION D'ACCÈS
    // =====================================================

    /**
     * Vérifie si un étudiant peut accéder à un service
     * Retourne les frais payés et ceux manquants
     */
    public function verifierAccesService($studentId, $serviceId)
    {
        // Récupérer le service
        $service = $this->getServiceById($serviceId);
        if (!$service) {
            return ['acces' => false, 'raison' => 'Service introuvable'];
        }

        // Récupérer les dépendances du service
        $dependances = $this->getDependancesByService($serviceId);
        if (empty($dependances)) {
            // Pas de dépendances = accès automatique
            return ['acces' => true, 'raison' => 'Aucune dépendance configurée'];
        }

        // Récupérer les infos de l'étudiant
        $etudiantQuery = "SELECT e.*, p.idpromotion, aa.idannee_acad 
                          FROM etudiant e 
                          LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                          LEFT JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                          WHERE e.idetudiant = :studentId";
        
        $stmt = $this->db->prepare($etudiantQuery);
        $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etudiant) {
            return ['acces' => false, 'raison' => 'Étudiant introuvable'];
        }

        $fraisPayes = [];
        $fraisManquants = [];

        foreach ($dependances as $dependance) {
            // Vérifier si le frais correspond au scope et à l'étudiant
            $fraisValide = false;
            
            if ($dependance['scope'] === 'annee_complete') {
                // Frais pour toute l'année académique
                if ($dependance['annee_acad_id'] == $etudiant['idannee_acad']) {
                    $fraisValide = true;
                }
            } elseif ($dependance['scope'] === 'promotion') {
                // Frais pour une promotion spécifique
                if ($dependance['promotion_id'] == $etudiant['idpromotion']) {
                    $fraisValide = true;
                }
            } elseif ($dependance['scope'] === 'cycle') {
                // Frais pour un cycle spécifique
                // À implémenter selon votre système de cycle
                $fraisValide = true;
            }

            if (!$fraisValide) {
                continue;
            }

            // Vérifier si le frais est payé
            $paiementQuery = "SELECT SUM(montant_specifique) as montant_specifique, 
                                     SUM(montant) as montant_frais
                              FROM affectation_frais
                              WHERE frais_id = :fraisId AND matricule_etudiant = :matricule
                              AND statut_paiement = 'Complet'";
            
            $stmtPaiement = $this->db->prepare($paiementQuery);
            $stmtPaiement->bindParam(':fraisId', $dependance['frais_id'], PDO::PARAM_INT);
            $stmtPaiement->bindParam(':matricule', $etudiant['matricule'], PDO::PARAM_STR);
            $stmtPaiement->execute();
            $paiement = $stmtPaiement->fetch(PDO::FETCH_ASSOC);

            if ($paiement && ($paiement['montant_specifique'] || $paiement['montant_frais'])) {
                $fraisPayes[] = [
                    'id' => $dependance['frais_id'],
                    'designation' => $dependance['frais_designation'],
                    'montant' => $dependance['montant'],
                    'devise' => $dependance['devise']
                ];
            } else {
                $fraisManquants[] = [
                    'id' => $dependance['frais_id'],
                    'designation' => $dependance['frais_designation'],
                    'montant' => $dependance['montant'],
                    'devise' => $dependance['devise']
                ];
            }
        }

        $acces = empty($fraisManquants);
        
        return [
            'acces' => $acces,
            'raison' => $acces ? 'Tous les frais ont été payés' : count($fraisManquants) . ' frais manquants',
            'frais_payes' => $fraisPayes,
            'frais_manquants' => $fraisManquants
        ];
    }

    /**
     * Enregistre l'accès d'un étudiant à un service
     */
    public function enregistrerAcces($serviceId, $etudiantId, $matricule, $acces, $raison, $fraisPayes, $fraisManquants)
    {
        $query = "INSERT INTO acces_services_documents 
                  (service_id, etudiant_id, matricule_etudiant, acces_autorise, raison_refus, frais_payes, frais_manquants, date_verification) 
                  VALUES (:serviceId, :etudiantId, :matricule, :acces, :raison, :fraisPayes, :fraisManquants, NOW())";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'serviceId' => $serviceId,
            'etudiantId' => $etudiantId,
            'matricule' => $matricule,
            'acces' => $acces ? 1 : 0,
            'raison' => $acces ? null : $raison,
            'fraisPayes' => json_encode($fraisPayes),
            'fraisManquants' => json_encode($fraisManquants)
        ]);
    }

    /**
     * Récupère l'historique d'accès pour un étudiant et un service
     */
    public function getHistoriqueAcces($serviceId, $etudiantId, $limit = 10)
    {
        $query = "SELECT * FROM acces_services_documents 
                  WHERE service_id = :serviceId AND etudiant_id = :etudiantId
                  ORDER BY date_verification DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
