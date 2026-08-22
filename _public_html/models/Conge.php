<?php
class Conge {
    private $db;
    
    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }
    
    /**
     * Récupère toutes les demandes de congé
     * @param array $filters Filtres à appliquer (statut, agent, type, etc.)
     * @return array Liste des demandes de congé
     */
    public function getAllDemandesConge($filters = []) {
        $sql = "SELECT dc.*, a.noms as nom_agent, tc.designation as type_conge_nom 
                FROM demande_conge dc
                JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres
        if (isset($filters['statut']) && !empty($filters['statut'])) {
            $sql .= " AND dc.statut = :statut";
            $params[':statut'] = $filters['statut'];
        }
        
        if (isset($filters['idAgent']) && $filters['idAgent'] > 0) {
            $sql .= " AND dc.\"idAgent\" = :idAgent";
            $params[':idAgent'] = $filters['idAgent'];
        }
        
        if (isset($filters['idtype_conge']) && $filters['idtype_conge'] > 0) {
            $sql .= " AND dc.idtype_conge = :idtype_conge";
            $params[':idtype_conge'] = $filters['idtype_conge'];
        }
        
        if (isset($filters['dateDebut']) && !empty($filters['dateDebut'])) {
            $sql .= " AND dc.date_debut >= :dateDebut";
            $params[':dateDebut'] = $filters['dateDebut'];
        }
        
        if (isset($filters['dateFin']) && !empty($filters['dateFin'])) {
            $sql .= " AND dc.date_fin <= :dateFin";
            $params[':dateFin'] = $filters['dateFin'];
        }
        
        $sql .= " ORDER BY dc.date_creation DESC";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère une demande de congé par son ID
     * @param int $idDemande ID de la demande
     * @return array|false Détails de la demande ou false si non trouvée
     */
    public function getDemandeCongeById($idDemande) {
        $sql = "SELECT dc.*, a.noms as nom_agent, tc.designation as type_conge_nom 
                FROM demande_conge dc
                JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                WHERE dc.iddemande_conge = :idDemande";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idDemande', $idDemande, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les demandes de congé d'un agent
     * @param int $idAgent ID de l'agent
     * @return array Liste des demandes de congé de l'agent
     */
    public function getDemandesCongeByAgent($idAgent) {
        $sql = "SELECT dc.*, tc.designation as type_conge_nom 
                FROM demande_conge dc
                JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                WHERE dc.\"idAgent\" = :idAgent
                ORDER BY dc.date_creation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDemandesCongeEnAttente($idService = null) {
        $query = "SELECT dc.*, tc.designation as type_conge_nom, a.noms as nom_agent, 
                         a.\"codeAgent\", a.matricule, s.designation as service_nom
                  FROM demande_conge dc
                  JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                  JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                  LEFT JOIN service s ON a.\"idService\" = s.\"idService\"
                  WHERE dc.statut = 'En attente'";
        
        if ($idService) {
            $query .= " AND a.\"idService\" = :idService";
        }
        
        $query .= " ORDER BY dc.date_demande ASC";
        
        $stmt = $this->db->prepare($query);
        
        if ($idService) {
            $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistoriqueDemandesConge($idService = null, $limit = 50) {
        $query = "SELECT dc.*, 
                 a.noms as nom_agent, 
                 a.matricule,
                 tc.designation as type_conge_nom,
                 s.designation as service_nom,
                 decideur_agent.noms as decideur_nom
              FROM demande_conge dc
              JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
              JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
              LEFT JOIN service s ON a.\"idService\" = s.\"idService\"
              LEFT JOIN t_users u ON dc.\"idDecideur\" = u.\"idUser\"
              LEFT JOIN agent decideur_agent ON u.\"idAgent\" = decideur_agent.\"idAgent\"
              WHERE dc.statut IN ('Approuvé', 'Refusé', 'Annulé')";
        
        if ($idService) {
            $query .= " AND a.\"idService\" = :idService";
        }
        
        $query .= " ORDER BY dc.date_decision DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        
        if ($idService) {
            $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDashboardConges($idService = null) {
        $annee = date('Y');
        
        // Requête pour obtenir les statistiques des congés
        $query = "SELECT 
                    COUNT(CASE WHEN dc.statut = 'En attente' THEN 1 END) as nb_en_attente,
                    COUNT(CASE WHEN dc.statut = 'Approuvé' THEN 1 END) as nb_approuve,
                    COUNT(CASE WHEN dc.statut = 'Refusé' THEN 1 END) as nb_refuse,
                    COUNT(CASE WHEN dc.statut = 'Annulé' THEN 1 END) as nb_annule,
                    COUNT(*) as total
                  FROM demande_conge dc
                  JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                  WHERE YEAR(dc.date_demande) = :annee";
        
        if ($idService) {
            $query .= " AND a.\"idService\" = :idService";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
        
        if ($idService) {
            $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Requête pour obtenir les agents actuellement en congé
        $query = "SELECT a.\"idAgent\", a.noms, a.\"codeAgent\", a.matricule, 
                         tc.designation as type_conge_nom, 
                         dc.date_debut, dc.date_fin
                  FROM demande_conge dc
                  JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                  JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                  WHERE dc.statut = 'Approuvé'
                  AND CURRENT_DATE BETWEEN dc.date_debut AND dc.date_fin";
        
        if ($idService) {
            $query .= " AND a.\"idService\" = :idService";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($idService) {
            $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $agentsEnConge = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'stats' => $stats,
            'agents_en_conge' => $agentsEnConge
        ];
    }
    
    /**
     * Vérifie si un agent a déjà une demande de congé en cours pour une période donnée
     * @param int $idAgent ID de l'agent
     * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
     * @return bool True si une demande existe, false sinon
     */
    public function hasDemandeCongeEnCours($idAgent, $dateDebut, $dateFin) {
        $sql = "SELECT COUNT(*) FROM demande_conge 
                WHERE \"idAgent\" = :idAgent 
                AND statut = 'En attente'
                AND ((date_debut BETWEEN :dateDebut AND :dateFin) 
                    OR (date_fin BETWEEN :dateDebut AND :dateFin)
                    OR (:dateDebut BETWEEN date_debut AND date_fin))";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindValue(':dateDebut', $dateDebut);
        $stmt->bindValue(':dateFin', $dateFin);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Crée une nouvelle demande de congé
     * @param int $idAgent ID de l'agent
     * @param int $idtype_conge ID du type de congé
     * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
          * @param string $motif Motif du congé
     * @param string|null $documentJustificatif Chemin du document justificatif
     * @return int|false ID de la demande créée ou false en cas d'erreur
     */
    public function createDemandeConge($idAgent, $idtype_conge, $dateDebut, $dateFin, $motif, $documentJustificatif = null) {
        $sql = "INSERT INTO demande_conge (\"idAgent\", idtype_conge, date_debut, date_fin, motif, document_justificatif, statut, date_demande, \"idUser\") 
                VALUES (:idAgent, :idtype_conge, :dateDebut, :dateFin, :motif, :documentJustificatif, 'En attente', NOW(), :idUser)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
            $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
            $stmt->bindValue(':dateDebut', $dateDebut);
            $stmt->bindValue(':dateFin', $dateFin);
            $stmt->bindValue(':motif', $motif);
            $stmt->bindValue(':documentJustificatif', $documentJustificatif);
            $stmt->bindValue(':idUser', $_SESSION['id'], PDO::PARAM_INT); // Ajout de l'ID utilisateur
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de la demande de congé: " . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Approuve une demande de congé
     * @param int $idDemande ID de la demande
     * @param int $idUser ID de l'utilisateur qui approuve
     * @param string $commentaire Commentaire d'approbation
     * @return bool True si succès, false sinon
     */
    public function approuverDemandeConge($idDemande, $idUser, $commentaire = '') {
        try {
            $this->db->beginTransaction();
            
            // Mettre à jour le statut de la demande
            $sql = "UPDATE demande_conge 
                    SET statut = 'Approuvé', 
                        date_decision = NOW(), 
                        idUser_decision = :idUser, 
                        commentaire_decision = :commentaire 
                    WHERE iddemande_conge = :idDemande";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idDemande', $idDemande, PDO::PARAM_INT);
            $stmt->bindValue(':idUser', $idUser, PDO::PARAM_INT);
            $stmt->bindValue(':commentaire', $commentaire);
            $stmt->execute();
            
            // Récupérer les détails de la demande
            $demande = $this->getDemandeCongeById($idDemande);
            
            // Mettre à jour le solde de congé si le type est cumulable
            $typeConge = $this->getTypeCongeById($demande['idtype_conge']);
            if ($typeConge['est_cumulable']) {
                $joursOuvrables = $this->calculerJoursOuvrables($demande['date_debut'], $demande['date_fin']);
                
                // Vérifier si un solde existe déjà pour cet agent et ce type de congé
                $solde = $this->getSoldeCongeByAgentAndType($demande['idAgent'], $demande['idtype_conge']);
                
                if ($solde) {
                    // Mettre à jour le solde existant
                    $sql = "UPDATE solde_conge 
                            SET solde_disponible = solde_disponible - :jours, 
                                date_mise_a_jour = NOW() 
                            WHERE idsolde_conge = :idSolde";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindValue(':jours', $joursOuvrables, PDO::PARAM_INT);
                    $stmt->bindValue(':idSolde', $solde['idsolde_conge'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'approbation de la demande de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refuse une demande de congé
     * @param int $idDemande ID de la demande
     * @param int $idUser ID de l'utilisateur qui refuse
     * @param string $commentaire Motif du refus
     * @return bool True si succès, false sinon
     */
    public function refuserDemandeConge($idDemande, $idUser, $commentaire) {
        $sql = "UPDATE demande_conge 
                SET statut = 'Refusé', 
                    date_decision = NOW(), 
                    idUser_decision = :idUser, 
                    commentaire_decision = :commentaire 
                WHERE iddemande_conge = :idDemande";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idDemande', $idDemande, PDO::PARAM_INT);
            $stmt->bindValue(':idUser', $idUser, PDO::PARAM_INT);
            $stmt->bindValue(':commentaire', $commentaire);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors du refus de la demande de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Annule une demande de congé
     * @param int $idDemande ID de la demande
     * @param int $idUser ID de l'utilisateur qui annule
     * @return bool True si succès, false sinon
     */
    public function annulerDemandeConge($idDemande, $idUser) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer les informations de la demande
            $demande = $this->getDemandeCongeById($idDemande);
            if (!$demande) {
                throw new Exception("Demande de congé introuvable.");
            }
            
            // Si la demande est déjà traitée et n'est pas en attente, vérifier si elle est en cours
            if ($demande['statut'] == 'Approuvé') {
                $dateDebut = new DateTime($demande['date_debut']);
                $dateFin = new DateTime($demande['date_fin']);
                $aujourdhui = new DateTime();
                
                // Si le congé est déjà commencé, on ne peut pas l'annuler
                if ($aujourdhui >= $dateDebut) {
                    throw new Exception("Ce congé a déjà commencé et ne peut pas être annulé.");
                }
                
                // Si le congé est approuvé mais pas encore commencé, on peut l'annuler
                // et il faut mettre à jour le solde de congés
                $joursOuvrables = $this->calculerJoursOuvrables($demande['date_debut'], $demande['date_fin']);
                $this->restituerSoldeConge($demande['idAgent'], $demande['idtype_conge'], $joursOuvrables);
            } else if ($demande['statut'] != 'En attente') {
                throw new Exception("Cette demande ne peut pas être annulée car elle est déjà " . strtolower($demande['statut']) . ".");
            }
            
            // Mettre à jour le statut de la demande
            $query = "UPDATE demande_conge 
                      SET statut = 'Annulé', commentaire_decision = 'Annulé par l\'agent', 
                          date_decision = NOW(), \"idDecideur\" = :idUser 
                      WHERE iddemande_conge = :idDemande";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
            $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function restituerSoldeConge($idAgent, $idtype_conge, $joursOuvrables) {
        $annee = date('Y');
        $solde = $this->getSoldeConge($idAgent, $idtype_conge, $annee);
        
        $joursPris = max(0, $solde['jours_pris'] - $joursOuvrables);
        
        $query = "UPDATE solde_conge SET jours_pris = :joursPris, date_mise_a_jour = NOW() 
                  WHERE idsolde_conge = :idSolde";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':joursPris', $joursPris, PDO::PARAM_INT);
        $stmt->bindParam(':idSolde', $solde['idsolde_conge'], PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAllSoldesCongeByAgent($idAgent) {
        $annee = date('Y');
        
        // Récupérer tous les types de congés
        $typesConges = $this->getAllTypeConges();
        $result = [];
        
        foreach ($typesConges as $typeConge) {
            $solde = $this->getSoldeConge($idAgent, $typeConge['idtype_conge'], $annee);
            
            $soldeDisponible = $solde['jours_acquis'] + $solde['jours_reportes'] - $solde['jours_pris'];
            
            
            // Si c'est un congé cumulable, vérifier aussi l'année précédente
            if ($typeConge['est_cumulable']) {
                $soldeAnneePrec = $this->getSoldeConge($idAgent, $typeConge['idtype_conge'], $annee - 1);
                if ($soldeAnneePrec) {
                    $soldeDisponible += $soldeAnneePrec['jours_acquis'] + $soldeAnneePrec['jours_reportes'] - $soldeAnneePrec['jours_pris'];
                }
            }
            
            $result[] = [
                'type_conge' => $typeConge,
                'solde' => $solde,
                'solde_disponible' => $soldeDisponible
            ];
        }
        
        return $result;
    }

    public function effectuerReportConges($annee) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer tous les soldes de congés pour l'année spécifiée
            $query = "SELECT * FROM solde_conge WHERE annee = :annee";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->execute();
            $soldes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($soldes as $solde) {
                // Vérifier si le type de congé est cumulable
                $typeConge = $this->getTypeCongeById($solde['idtype_conge']);
                if (!$typeConge['est_cumulable']) {
                    continue;
                }
                
                // Calculer le solde non utilisé
                $soldeNonUtilise = $solde['jours_acquis'] + $solde['jours_reportes'] - $solde['jours_pris'];
                
                if ($soldeNonUtilise <= 0) {
                    continue;
                }
                
                // Vérifier si un solde existe déjà pour l'année suivante
                $anneeSuivante = $annee + 1;
                $soldeSuivant = $this->getSoldeConge($solde['idAgent'], $solde['idtype_conge'], $anneeSuivante);
                
                // Mettre à jour le solde de l'année suivante
                $query = "UPDATE solde_conge 
                          SET jours_reportes = :joursReportes, date_mise_a_jour = NOW() 
                          WHERE idsolde_conge = :idSolde";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':joursReportes', $soldeNonUtilise, PDO::PARAM_INT);
                $stmt->bindParam(':idSolde', $soldeSuivant['idsolde_conge'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    public function isAgentEnConge($idAgent) {
        $query = "SELECT COUNT(*) as nb FROM demande_conge 
                  WHERE \"idAgent\" = :idAgent 
                  AND statut = 'Approuvé' 
                  AND CURRENT_DATE BETWEEN date_debut AND date_fin";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['nb'] > 0;
    }

    public function getAgentsEnCongeByService($idService) {
        $query = "SELECT a.\"idAgent\", a.noms, a.\"codeAgent\", a.matricule, 
                         tc.designation as type_conge_nom, 
                         dc.date_debut, dc.date_fin
                  FROM demande_conge dc
                  JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                  JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                  WHERE dc.statut = 'Approuvé'
                  AND CURRENT_DATE BETWEEN dc.date_debut AND dc.date_fin
                  AND a.\"idService\" = :idService";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère tous les types de congé
     * @return array Liste des types de congé
     */
    public function getAllTypeConges() {
        $sql = "SELECT * FROM type_conge ORDER BY designation";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function verifierSoldeConge($idAgent, $idtype_conge, $dateDebut, $dateFin) {
        // Récupérer le type de congé
        $typeConge = $this->getTypeCongeById($idtype_conge);
        
        // Si c'est un congé de maladie ou de circonstance, pas besoin de vérifier le solde
        if ($typeConge['designation'] == 'Congé de maladie' || 
            strpos($typeConge['designation'], 'Congé de circonstance') === 0) {
            return true;
        }
        
        // Calculer le nombre de jours demandés (jours ouvrables)
        $joursOuvrables = $this->calculerJoursOuvrables($dateDebut, $dateFin);
        
        // Récupérer le solde de congés pour l'année en cours
        $annee = date('Y');
        $solde = $this->getSoldeConge($idAgent, $idtype_conge, $annee);
        
        // Si c'est un congé cumulable, vérifier aussi l'année précédente
        $soldeDisponible = $solde['jours_acquis'] + $solde['jours_reportes'] - $solde['jours_pris'];
        if (!$solde || $soldeDisponible < $joursOuvrables) {
            throw new Exception("Solde de congé insuffisant...");
        }
        
        if ($typeConge['est_cumulable']) {
            $soldeAnneePrec = $this->getSoldeConge($idAgent, $idtype_conge, $annee - 1);
            if ($soldeAnneePrec) {
                $soldeDisponible += $soldeAnneePrec['jours_acquis'] + $soldeAnneePrec['jours_reportes'] - $soldeAnneePrec['jours_pris'];
            }
        }
        
        return $joursOuvrables <= $soldeDisponible;
    }




    
    /**
     * Récupère un type de congé par son ID
     * @param int $idtype_conge ID du type de congé
     * @return array|false Détails du type de congé ou false si non trouvé
     */
    public function getTypeCongeById($idtype_conge) {
        $sql = "SELECT * FROM type_conge WHERE idtype_conge = :idtype_conge";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Vérifie si un type de congé avec le même nom existe déjà
     * @param string $designation Nom du type de congé
     * @return bool True si existe, false sinon
     */
    public function typeCongeExistsByName($designation) {
        $sql = "SELECT COUNT(*) FROM type_conge WHERE designation = :designation";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':designation', $designation);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Vérifie si un type de congé avec le même nom existe déjà (sauf celui avec l'ID spécifié)
     * @param string $designation Nom du type de congé
     * @param int $idtype_conge ID du type de congé à exclure
     * @return bool True si existe, false sinon
     */
    public function typeCongeExistsByNameExcept($designation, $idtype_conge) {
        $sql = "SELECT COUNT(*) FROM type_conge WHERE designation = :designation AND idtype_conge != :idtype_conge";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':designation', $designation);
        $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Crée un nouveau type de congé
     * @param string $designation Nom du type de congé
     * @param int|null $dureeStandard Durée standard en jours
     * @param bool $estCumulable Si le congé est cumulable d'une année à l'autre
     * @param string $description Description du type de congé
     * @return int|false ID du type créé ou false en cas d'erreur
     */
    public function createTypeConge($designation, $dureeStandard, $estCumulable, $description) {
        $sql = "INSERT INTO type_conge (designation, duree_standard, est_cumulable, description) 
                VALUES (:designation, :dureeStandard, :estCumulable, :description)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':designation', $designation);
            $stmt->bindValue(':dureeStandard', $dureeStandard, $dureeStandard === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':estCumulable', $estCumulable, PDO::PARAM_INT);
            $stmt->bindValue(':description', $description);
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du type de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour un type de congé
     * @param int $idtype_conge ID du type de congé
     * @param string $designation Nom du type de congé
     * @param int|null $dureeStandard Durée standard en jours
     * @param bool $estCumulable Si le congé est cumulable d'une année à l'autre
     * @param string $description Description du type de congé
     * @return bool True si succès, false sinon
     */
    public function updateTypeConge($idtype_conge, $designation, $dureeStandard, $estCumulable, $description) {
        $sql = "UPDATE type_conge 
                SET designation = :designation, 
                    duree_standard = :dureeStandard, 
                    est_cumulable = :estCumulable, 
                    description = :description 
                WHERE idtype_conge = :idtype_conge";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
            $stmt->bindValue(':designation', $designation);
            $stmt->bindValue(':dureeStandard', $dureeStandard, $dureeStandard === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':estCumulable', $estCumulable, PDO::PARAM_INT);
            $stmt->bindValue(':description', $description);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du type de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprime un type de congé
     * @param int $idtype_conge ID du type de congé
     * @return bool True si succès, false sinon
     */
    public function deleteTypeConge($idtype_conge) {
        $sql = "DELETE FROM type_conge WHERE idtype_conge = :idtype_conge";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du type de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si un type de congé est utilisé dans des demandes
     * @param int $idtype_conge ID du type de congé
     * @return bool True si utilisé, false sinon
     */
    public function typeCongeIsUsed($idtype_conge) {
        $sql = "SELECT COUNT(*) FROM demande_conge WHERE idtype_conge = :idtype_conge";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Récupère le solde de congé d'un agent pour un type de congé
     * @param int $idAgent ID de l'agent
     * @param int $idtype_conge ID du type de congé
     * @return array|false Détails du solde ou false si non trouvé
     */
    public function getSoldeCongeByAgentAndType($idAgent, $idtype_conge) {
        $sql = "SELECT * FROM solde_conge 
                WHERE \"idAgent\" = :idAgent AND idtype_conge = :idtype_conge";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcule le nombre de jours ouvrables entre deux dates
     * @param string $dateDebut Date de début
          * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
     * @return int Nombre de jours ouvrables
     */
    public function calculerJoursOuvrables($dateDebut, $dateFin) {
        $debut = new DateTime($dateDebut);
        $fin = new DateTime($dateFin);
        $fin->modify('+1 day'); // Inclure le dernier jour
        
        $joursOuvrables = 0;
        $interval = new DateInterval('P1D');
        $periode = new DatePeriod($debut, $interval, $fin);
        
        foreach ($periode as $jour) {
            $jourSemaine = $jour->format('N');
            // 1 (lundi) à 5 (vendredi) sont des jours ouvrables
            if ($jourSemaine <= 5) {
                $joursOuvrables++;
            }
        }
        
        return $joursOuvrables;
    }

    public function getSoldeConge($idAgent, $idtype_conge, $annee) {
        $query = "SELECT * FROM solde_conge 
                  WHERE \"idAgent\" = :idAgent AND idtype_conge = :idtype_conge AND annee = :annee";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
        $stmt->execute();
        
        $solde = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si aucun solde n'existe, créer un solde par défaut
        if (!$solde) {
            $solde = $this->initialiserSoldeConge($idAgent, $idtype_conge, $annee);
        }
        
        return $solde;
    }

    private function initialiserSoldeConge($idAgent, $idtype_conge, $annee) {
        $typeConge = $this->getTypeCongeById($idtype_conge);
        $joursAcquis = $typeConge['duree_standard'] ?? 0;
        
        $query = "INSERT INTO solde_conge (\"idAgent\", idtype_conge, annee, jours_acquis, jours_pris, jours_reportes, \"idUser\") 
                  VALUES (:idAgent, :idtype_conge, :annee, :joursAcquis, 0, 0, 1)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
        $stmt->bindParam(':joursAcquis', $joursAcquis, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'idsolde_conge' => $this->db->lastInsertId(),
            'idAgent' => $idAgent,
            'idtype_conge' => $idtype_conge,
            'annee' => $annee,
            'jours_acquis' => $joursAcquis,
            'jours_pris' => 0,
            'jours_reportes' => 0
        ];
    }

    private function mettreAJourSoldeConge($idAgent, $idtype_conge, $joursOuvrables) {
        $annee = date('Y');
        $solde = $this->getSoldeConge($idAgent, $idtype_conge, $annee);
        
        $joursPris = $solde['jours_pris'] + $joursOuvrables;
        
        $query = "UPDATE solde_conge SET jours_pris = :joursPris, date_mise_a_jour = NOW() 
                  WHERE idsolde_conge = :idSolde";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':joursPris', $joursPris, PDO::PARAM_INT);
        $stmt->bindParam(':idSolde', $solde['idsolde_conge'], PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function traiterDemandeConge($idDemande, $statut, $commentaire, $idDecideur) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer les informations de la demande
            $demande = $this->getDemandeCongeById($idDemande);
            if (!$demande) {
                throw new Exception("Demande de congé introuvable.");
            }
            
            // Si la demande est déjà traitée, ne rien faire
            if ($demande['statut'] != 'En attente') {
                throw new Exception("Cette demande a déjà été traitée.");
            }
            
            // Mettre à jour le statut de la demande
            $query = "UPDATE demande_conge 
                      SET statut = :statut, commentaire_decision = :commentaire, 
                          date_decision = NOW(), \"idDecideur\" = :idDecideur 
                      WHERE iddemande_conge = :idDemande";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':idDecideur', $idDecideur, PDO::PARAM_INT);
            $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
            $stmt->execute();
            
            // Si la demande est approuvée, mettre à jour le solde de congés
            if ($statut == 'Approuvé') {
                $joursOuvrables = $this->calculerJoursOuvrables($demande['date_debut'], $demande['date_fin']);
                $this->mettreAJourSoldeConge($demande['idAgent'], $demande['idtype_conge'], $joursOuvrables);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }



    
    /**
     * Initialise ou met à jour le solde de congé d'un agent
     * @param int $idAgent ID de l'agent
     * @param int $idtype_conge ID du type de congé
     * @param int $soldeInitial Solde initial en jours
     * @param int $annee Année du solde
     * @return bool True si succès, false sinon
     */
    public function initSoldeConge($idAgent, $idtype_conge, $soldeInitial, $annee) {
        // Vérifier si un solde existe déjà pour cet agent, ce type de congé et cette année
        $sql = "SELECT idsolde_conge FROM solde_conge 
                WHERE \"idAgent\" = :idAgent AND idtype_conge = :idtype_conge AND annee = :annee";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
        $stmt->bindValue(':annee', $annee, PDO::PARAM_INT);
        $stmt->execute();
        
        $soldeExistant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            if ($soldeExistant) {
                // Mettre à jour le solde existant
                $sql = "UPDATE solde_conge 
                        SET solde_initial = :soldeInitial, 
                            solde_disponible = :soldeInitial, 
                            date_mise_a_jour = NOW() 
                        WHERE idsolde_conge = :idSolde";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':soldeInitial', $soldeInitial, PDO::PARAM_INT);
                $stmt->bindValue(':idSolde', $soldeExistant['idsolde_conge'], PDO::PARAM_INT);
            } else {
                // Créer un nouveau solde
                $sql = "INSERT INTO solde_conge (\"idAgent\", idtype_conge, solde_initial, solde_disponible, annee, date_creation) 
                        VALUES (:idAgent, :idtype_conge, :soldeInitial, :soldeInitial, :annee, NOW())";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':idAgent', $idAgent, PDO::PARAM_INT);
                $stmt->bindValue(':idtype_conge', $idtype_conge, PDO::PARAM_INT);
                $stmt->bindValue(':soldeInitial', $soldeInitial, PDO::PARAM_INT);
                $stmt->bindValue(':annee', $annee, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de l'initialisation du solde de congé: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère tous les soldes de congé d'un agent
     * @param int $idAgent ID de l'agent
     * @param int|null $annee Année spécifique (optionnel)
     * @return array Liste des soldes de congé
     */
    public function getSoldesCongeByAgent($idAgent, $annee = null) {
        $sql = "SELECT sc.*, tc.designation as type_conge_nom 
                FROM solde_conge sc
                JOIN type_conge tc ON sc.idtype_conge = tc.idtype_conge
                WHERE sc.\"idAgent\" = :idAgent";
        
        $params = [':idAgent' => $idAgent];
        
        if ($annee !== null) {
            $sql .= " AND sc.annee = :annee";
            $params[':annee'] = $annee;
        }
        
        $sql .= " ORDER BY sc.annee DESC, tc.designation";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les statistiques de congé pour un agent
     * @param int $idAgent ID de l'agent
     * @param int|null $annee Année spécifique (optionnel)
     * @return array Statistiques de congé
     */
    public function getStatistiquesCongeByAgent($idAgent, $annee = null) {
        $stats = [
            'total_demandes' => 0,
            'approuvees' => 0,
            'refusees' => 0,
            'en_attente' => 0,
            'annulees' => 0,
            'jours_pris' => 0,
            'par_type' => []
        ];
        
        // Requête pour compter les demandes par statut
        $sql = "SELECT statut, COUNT(*) as nombre 
                FROM demande_conge 
                WHERE \"idAgent\" = :idAgent";
        
        $params = [':idAgent' => $idAgent];
        
        if ($annee !== null) {
            $sql .= " AND YEAR(date_debut) = :annee";
            $params[':annee'] = $annee;
        }
        
        $sql .= " GROUP BY statut";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultats as $resultat) {
            $stats['total_demandes'] += $resultat['nombre'];
            
            switch ($resultat['statut']) {
                case 'Approuvé':
                    $stats['approuvees'] = $resultat['nombre'];
                    break;
                case 'Refusé':
                    $stats['refusees'] = $resultat['nombre'];
                    break;
                case 'En attente':
                    $stats['en_attente'] = $resultat['nombre'];
                    break;
                case 'Annulé':
                    $stats['annulees'] = $resultat['nombre'];
                    break;
            }
        }
        
        // Requête pour calculer le nombre de jours pris
        $sql = "SELECT dc.idtype_conge, tc.designation, 
                SUM(DATEDIFF(dc.date_fin, dc.date_debut) + 1) as jours_totaux 
                FROM demande_conge dc
                JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                WHERE dc.\"idAgent\" = :idAgent AND dc.statut = 'Approuvé'";
        
        $params = [':idAgent' => $idAgent];
        
        if ($annee !== null) {
            $sql .= " AND YEAR(dc.date_debut) = :annee";
            $params[':annee'] = $annee;
        }
        
        $sql .= " GROUP BY dc.idtype_conge, tc.designation";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultats as $resultat) {
            $stats['jours_pris'] += $resultat['jours_totaux'];
            $stats['par_type'][] = [
                'type' => $resultat['designation'],
                'jours' => $resultat['jours_totaux']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Récupère le calendrier des congés pour une période donnée
     * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
     * @param int|null $idStructure ID de la structure (optionnel)
     * @return array Calendrier des congés
     */
    public function getCalendrierConges($dateDebut, $dateFin, $idStructure = null) {
        $sql = "SELECT dc.*, a.noms as nom_agent, tc.designation as type_conge_nom, 
                s.designation as service_nom, str.designation as structure_nom
                FROM demande_conge dc
                JOIN agent a ON dc.\"idAgent\" = a.\"idAgent\"
                JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                LEFT JOIN service s ON a.\"idService\" = s.\"idService\"
                LEFT JOIN structure str ON a.\"idStructure\" = str.\"idStructure\"
                WHERE dc.statut = 'Approuvé'
                AND ((dc.date_debut BETWEEN :dateDebut AND :dateFin) 
                    OR (dc.date_fin BETWEEN :dateDebut AND :dateFin)
                    OR (:dateDebut BETWEEN dc.date_debut AND dc.date_fin))";
        
        $params = [
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin
        ];
        
        if ($idStructure !== null) {
            $sql .= " AND a.\"idStructure\" = :idStructure";
            $params[':idStructure'] = $idStructure;
        }
        
        $sql .= " ORDER BY dc.date_debut, a.noms";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
