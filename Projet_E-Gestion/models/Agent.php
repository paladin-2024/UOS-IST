<?php

class Agent
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer tous les agents
    public function getAgents($search = '', $limit = 100)
    {
        $query = "SELECT
            a.*,
            str.designation as designationStructure,
            str.idStructure as idStructure,
            g.designation as gradeDesignation,
            s.designation as serviceDesignation,
            s.idService as idService,
            (SELECT COUNT(*) FROM dossier_famille WHERE Agent_idAgent = a.idAgent) as totalFamilyMembers,
            (SELECT COUNT(*) FROM contrat_agent WHERE Agent_idAgent = a.idAgent) as totalContracts,
            (SELECT COUNT(*) FROM document_agent WHERE Agent_idAgent = a.idAgent) as totalDocuments
        FROM agent AS a
        INNER JOIN structure AS str ON a.idStructure = str.idStructure
        LEFT JOIN grade AS g ON a.grade_id = g.idgrade
        LEFT JOIN service AS s ON a.idService = s.idService";
        
        if (!empty($search)) {
            $query .= " WHERE a.noms LIKE :search";
        }
        
        $query .= " ORDER BY a.noms ASC"; // Adding the ORDER BY clause for sorting
        $query .= " LIMIT :limit"; // Adding the LIMIT clause with a parameter
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT); // Binding the limit parameter
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les agents en fonction des droits d'accès de l'utilisateur
    public function getAgentsByUserAccess($userId)
    {
        $query = "SELECT
            a.*,str.* FROM agent AS a
        INNER JOIN structure AS str ON a.idStructure = str.idStructure
        INNER JOIN user_structure AS us ON us.idStructure = str.idStructure
        WHERE us.idUser = :userId";

        if (!empty($search)) {
            $query .= " AND a.noms LIKE :search";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajouter un agent
    public function addAgent($noms, $lieuNaissance, $dateNaissance, $sexe, $etatCivil, $niveauEtude, $telephone, $email, $codeAgent, $matricule, $type_agent, $grade_id, $idStructure, $idService)
    {
        $query = "INSERT INTO agent (noms, lieuNaissance, dateNaissance, sexe, etatCivil, niveauEtude, telephone, email, codeAgent, matricule, type_agent, grade_id, idStructure, idService) 
                  VALUES (:noms, :lieuNaissance, :dateNaissance, :sexe, :etatCivil, :niveauEtude, :telephone, :email, :codeAgent, :matricule, :type_agent, :grade_id, :idStructure, :idService)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'noms' => $noms,
            'lieuNaissance' => $lieuNaissance,
            'dateNaissance' => $dateNaissance,
            'sexe' => $sexe,
            'etatCivil' => $etatCivil,
            'niveauEtude' => $niveauEtude,
            'telephone' => $telephone,
            'email' => $email,
            'codeAgent' => $codeAgent,
            'matricule' => $matricule,
            'type_agent' => $type_agent,
            'grade_id' => $grade_id,
            'idStructure' => $idStructure,
            'idService' => $idService,
        ]);
    }

    public function addAgent_returnID($noms, $lieuNaissance, $dateNaissance, $sexe, $etatCivil, $niveauEtude, $telephone, $email, $codeAgent, $matricule, $typeAgent, $gradeId, $idStructure, $idService) {
        try {
            $sql = "INSERT INTO agent (noms, lieuNaissance, dateNaissance, sexe, etatCivil, niveauEtude, telephone, email, codeAgent, matricule, type_agent, grade_id, idStructure, idService) 
                    VALUES (:noms, :lieuNaissance, :dateNaissance, :sexe, :etatCivil, :niveauEtude, :telephone, :email, :codeAgent, :matricule, :typeAgent, :gradeId, :idStructure, :idService)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':noms', $noms);
            $stmt->bindParam(':lieuNaissance', $lieuNaissance);
            $stmt->bindParam(':dateNaissance', $dateNaissance);
            $stmt->bindParam(':sexe', $sexe);
            $stmt->bindParam(':etatCivil', $etatCivil);
            $stmt->bindParam(':niveauEtude', $niveauEtude);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':codeAgent', $codeAgent);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->bindParam(':typeAgent', $typeAgent);
            $stmt->bindParam(':gradeId', $gradeId, PDO::PARAM_INT);
            $stmt->bindParam(':idStructure', $idStructure, PDO::PARAM_INT);
            $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
            
            $stmt->execute();
            
            // Récupérer l'ID généré et le retourner
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'ajout d'un agent: " . $e->getMessage());
            return false;
        }
    }
    

    // Vérifier les doublons pour un agent
    public function checkDuplicateAgent($noms, $dateNaissance, $idStructure)
    {
        $query = "SELECT COUNT(*) as count FROM agent 
                  WHERE noms = :noms AND dateNaissance = :dateNaissance AND idStructure = :idStructure";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'noms' => $noms,
            'dateNaissance' => $dateNaissance,
            'idStructure' => $idStructure,
        ]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    // Supprimer un agent
    public function deleteAgent($idAgent)
    {
        $query = "DELETE FROM agent WHERE idAgent = :idAgent";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['idAgent' => $idAgent]);
    }

        // Récupérer un agent par son ID
        public function getAgentById($idAgent)
        {
            $query = "SELECT a.idAgent, a.noms, a.type_agent as type_agent_agent, a.grade_id, a.telephone, a.email,
            g.designation as gradeDesignation
            FROM agent a 
            LEFT JOIN grade g ON a.grade_id = g.idgrade
            WHERE a.idAgent = :idAgent";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['idAgent' => $idAgent]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['type_agent_agent'])) {
                $result['type_agent'] = $result['type_agent_agent'];
            }
            return $result;
        }
    
        // Mettre à jour un agent
        public function updateAgent($idAgent, $noms, $lieuNaissance, $dateNaissance, $sexe, $etatCivil, $niveauEtude, $telephone, $email, $codeAgent, $matricule, $type_agent, $grade_id, $photo, $idStructure, $idService)
        {
            $query = "UPDATE agent 
                      SET noms = :noms, lieuNaissance = :lieuNaissance, dateNaissance = :dateNaissance, 
                          sexe = :sexe, etatCivil = :etatCivil, niveauEtude = :niveauEtude, 
                          telephone = :telephone, email = :email, codeAgent = :codeAgent, 
                          matricule = :matricule, type_agent = :type_agent, grade_id = :grade_id, 
                          photo = :photo, idStructure = :idStructure, idService = :idService 
                      WHERE idAgent = :idAgent";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idAgent' => $idAgent,
                'noms' => $noms,
                'lieuNaissance' => $lieuNaissance,
                'dateNaissance' => $dateNaissance,
                'sexe' => $sexe,
                'etatCivil' => $etatCivil,
                'niveauEtude' => $niveauEtude,
                'telephone' => $telephone,
                'email' => $email,
                'codeAgent' => $codeAgent,
                'matricule' => $matricule,
                'type_agent' => $type_agent,
                'grade_id' => $grade_id,
                'photo' => $photo,
                'idStructure' => $idStructure,
                'idService' => $idService,
            ]);
        }
    
        // Récupérer tous les membres de la famille pour un agent
        public function getFamilyMembersByAgent($agentId, $search = '')
        {
            $query = "SELECT * FROM dossier_famille WHERE Agent_idAgent = :agentId";
            
            // Add search condition if search term is provided
            if (!empty($search)) {
                $query .= " AND (noms LIKE :search OR typeLiaison LIKE :search)";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
            
            if (!empty($search)) {
                $searchParam = "%$search%";
                $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Ajouter un membre de la famille pour un agent
        public function addFamilyMember($agentId, $noms, $sexe, $dateNaissance, $lieuNaissance, $typeLiaison, $idUser)
        {
            $query = "INSERT INTO dossier_famille (noms, sexe, dateNaissance, lieuNaissance, typeLiaison, Agent_idAgent, idUser, dateEnregistrement) 
                      VALUES (:noms, :sexe, :dateNaissance, :lieuNaissance, :typeLiaison, :agentId, :idUser, NOW())";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'noms' => $noms,
                'sexe' => $sexe,
                'dateNaissance' => $dateNaissance,
                'lieuNaissance' => $lieuNaissance,
                'typeLiaison' => $typeLiaison,
                'agentId' => $agentId,
                'idUser' => $idUser,
            ]);
        }
    
        // Mettre à jour un membre de la famille
        public function updateFamilyMember($idDossierFamille, $noms, $sexe, $dateNaissance, $lieuNaissance, $typeLiaison)
        {
            $query = "UPDATE dossier_famille 
                      SET noms = :noms, sexe = :sexe, dateNaissance = :dateNaissance, 
                          lieuNaissance = :lieuNaissance, typeLiaison = :typeLiaison 
                      WHERE idDossier_famille = :idDossierFamille";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idDossierFamille' => $idDossierFamille,
                'noms' => $noms,
                'sexe' => $sexe,
                'dateNaissance' => $dateNaissance,
                'lieuNaissance' => $lieuNaissance,
                'typeLiaison' => $typeLiaison,
            ]);
        }
    
        // Supprimer un membre de la famille
        public function deleteFamilyMember($idDossierFamille)
        {
            $query = "DELETE FROM dossier_famille WHERE idDossier_famille = :idDossierFamille";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['idDossierFamille' => $idDossierFamille]);
        }
    
        // Récupérer tous les contrats pour un agent
        public function getContractsByAgent($agentId, $search = '')
        {
            $query = "SELECT c.*,s.designation as service FROM contrat_agent c INNER JOIN 
            service s ON c.Service_idService=s.idService WHERE c.Agent_idAgent = :agentId";
            
            // Add search condition if search term is provided
            if (!empty($search)) {
                $query .= " AND (designation LIKE :search OR typeContrat LIKE :search)";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
            
            if (!empty($search)) {
                $searchParam = "%$search%";
                $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Ajouter un contrat pour un agent
        public function addContract($agentId, $designation, $typeContrat, $dateDebut, $dateFin, $fonction, $salaireDeBase, $transport, $logement, $anciennete, $serviceId, $userId)
        {
            $query = "INSERT INTO contrat_agent (designation, typeContrat, dateDebut, dateFin, fonction, salaireDeBase, transport, logement, anciennete, dateEnregistrement, Agent_idAgent, Service_idService, idUser) 
                    VALUES (:designation, :typeContrat, :dateDebut, :dateFin, :fonction, :salaireDeBase, :transport, :logement, :anciennete, NOW(), :agentId, :serviceId, :userId)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'designation' => $designation,
                'typeContrat' => $typeContrat,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'fonction' => $fonction,
                'salaireDeBase' => $salaireDeBase,
                'transport' => $transport,
                'logement' => $logement,
                'anciennete' => $anciennete,
                'agentId' => $agentId,
                'serviceId' => $serviceId,
                'userId' => $userId,
            ]);
        }
    
        // Mettre à jour un contrat
        public function updateContract($idContratAgent, $designation, $typeContrat, $dateDebut, $dateFin, $fonction, $salaireDeBase, $transport, $logement, $anciennete)
        {
            $query = "UPDATE contrat_agent 
                    SET designation = :designation, typeContrat = :typeContrat, dateDebut = :dateDebut, dateFin = :dateFin, 
                        fonction = :fonction, salaireDeBase = :salaireDeBase, transport = :transport, logement = :logement, anciennete = :anciennete 
                    WHERE idContrat_agent = :idContratAgent";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idContratAgent' => $idContratAgent,
                'designation' => $designation,
                'typeContrat' => $typeContrat,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'fonction' => $fonction,
                'salaireDeBase' => $salaireDeBase,
                'transport' => $transport,
                'logement' => $logement,
                'anciennete' => $anciennete,
            ]);
        }
    
        // Supprimer un contrat
        public function deleteContract($idContratAgent)
        {
            $query = "DELETE FROM contrat_agent WHERE idContrat_agent = :idContratAgent";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['idContratAgent' => $idContratAgent]);
        }
    
        // Add a document for an agent
        public function addDocument($agentId, $titre, $description, $fichier, $userId)
        {
            $query = "INSERT INTO document_agent (titre, description, fichier, dateEnregistrement, Agent_idAgent, idUser) 
                      VALUES (:titre, :description, :fichier, NOW(), :agentId, :userId)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'titre' => $titre,
                'description' => $description,
                'fichier' => $fichier,
                'agentId' => $agentId,
                'userId' => $userId,
            ]);
        }
    
        // Get documents for an agent
        public function getDocumentsByAgent($agentId)
        {
            $query = "SELECT * FROM document_agent WHERE Agent_idAgent = :agentId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['agentId' => $agentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Update a document
        public function updateDocument($idDocument, $titre, $description, $fichier)
        {
            $query = "UPDATE document_agent 
                      SET titre = :titre, description = :description, fichier = :fichier 
                      WHERE idDocument_agent = :idDocument";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idDocument' => $idDocument,
                'titre' => $titre,
                'description' => $description,
                'fichier' => $fichier,
            ]);
        }
    
        // Delete a document
        public function deleteDocument($idDocument)
        {
            $query = "DELETE FROM document_agent WHERE idDocument_agent = :idDocument";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['idDocument' => $idDocument]);
        }
    
        public function updateDocumentWithoutFile($idDocument, $titre, $description)
        {
            $query = "UPDATE document_agent 
                    SET titre = :titre, description = :description 
                    WHERE idDocument_agent = :idDocument";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idDocument' => $idDocument,
                'titre' => $titre,
                'description' => $description,
            ]);
        }
    
        // Add a presence record for an agent
        public function addPresence($agentId, $annee, $mois, $joursPresence, $joursAbsence, $joursRetard, $userId)
        {
            $query = "INSERT INTO presence_agent (annee, mois, joursPresence, joursAbsence, joursRetard, dateEnregistrement, Agent_idAgent, idUser) 
                    VALUES (:annee, :mois, :joursPresence, :joursAbsence, :joursRetard, NOW(), :agentId, :userId)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'annee' => $annee,
                'mois' => $mois,
                'joursPresence' => $joursPresence,
                'joursAbsence' => $joursAbsence,
                'joursRetard' => $joursRetard,
                'agentId' => $agentId,
                'userId' => $userId,
            ]);
        }
    
        // Get presence records for an agent
        public function getPresenceByAgent($agentId)
        {
            $query = "SELECT * FROM presence_agent WHERE Agent_idAgent = :agentId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['agentId' => $agentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    
        // Update a presence record
        public function updatePresence($idPresence, $annee, $mois, $joursPresence, $joursAbsence, $joursRetard)
        {
            $query = "UPDATE presence_agent 
                    SET annee = :annee, mois = :mois, joursPresence = :joursPresence, joursAbsence = :joursAbsence, joursRetard = :joursRetard 
                    WHERE idPresence_agent = :idPresence";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'idPresence' => $idPresence,
                'annee' => $annee,
                'mois' => $mois,
                'joursPresence' => $joursPresence,
                'joursAbsence' => $joursAbsence,
                'joursRetard' => $joursRetard,
            ]);
        }
    
        // Delete a presence record
        public function deletePresence($idPresence)
        {
            $query = "DELETE FROM presence_agent WHERE idPresence_agent = :idPresence";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['idPresence' => $idPresence]);
        }
    
        public function checkDuplicatePresence($agentId, $annee, $mois)
        {
            $query = "SELECT COUNT(*) as count FROM presence_agent 
                    WHERE Agent_idAgent = :agentId AND annee = :annee AND mois = :mois";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'agentId' => $agentId,
                'annee' => $annee,
                'mois' => $mois,
            ]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        }
    
            // Récupérer le service affecté à un agent
    public function getServiceByAgent($agentId)
    {
        $query = "SELECT s.* FROM service AS s
                  INNER JOIN agent AS a ON s.idService = a.idService
                  WHERE a.idAgent = :agentId";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':agentId', $agentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve presence data for a specific agent, month, and year
    public function getPresenceDataForAgent($agentId, $month, $year)
    {
        $query = "SELECT joursPresence, joursAbsence, joursRetard 
                FROM presence_agent 
                WHERE Agent_idAgent = $agentId AND mois = '$month' AND annee = '$year'";

        $stmt = $this->db->query($query);
        return $stmt;
    }

    // ====== Daily presence management ======
    public function addDailyPresence($agentId, $datePresence, $heureArrivee, $heureDepart, $commentaire, $userId, $ipAddress, $userAgent)
    {
        $query = "INSERT INTO presence_agent_daily
                  (Agent_idAgent, date_presence, heure_arrivee, heure_depart, methode_enregistrement, commentaire, encode_par, ip_address, user_agent)
                  VALUES (:agentId, :date_presence, :heure_arrivee, :heure_depart, 'manuel', :commentaire, :encode_par, :ip_address, :user_agent)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'agentId' => $agentId,
            'date_presence' => $datePresence,
            'heure_arrivee' => !empty($heureArrivee) ? $heureArrivee : null,
            'heure_depart' => !empty($heureDepart) ? $heureDepart : null,
            'commentaire' => $commentaire,
            'encode_par' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function updateDailyPresence($idPresenceDaily, $heureArrivee, $heureDepart, $commentaire, $userId)
    {
        $query = "UPDATE presence_agent_daily
                  SET heure_arrivee = :heure_arrivee, heure_depart = :heure_depart, commentaire = :commentaire, updated_at = NOW()
                  WHERE idpresence_agent_daily = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'id' => $idPresenceDaily,
            'heure_arrivee' => !empty($heureArrivee) ? $heureArrivee : null,
            'heure_depart' => !empty($heureDepart) ? $heureDepart : null,
            'commentaire' => $commentaire,
        ]);
    }

    public function deleteDailyPresence($idPresenceDaily)
    {
        $query = "DELETE FROM presence_agent_daily WHERE idpresence_agent_daily = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $idPresenceDaily]);
    }

    public function getDailyPresencesByAgent($agentId, $startDate = null, $endDate = null, $limit = 30)
    {
        $params = ['agentId' => $agentId];
        $query = "SELECT * FROM presence_agent_daily WHERE Agent_idAgent = :agentId";
        if (!empty($startDate)) { $query .= " AND date_presence >= :startDate"; $params['startDate'] = $startDate; }
        if (!empty($endDate)) { $query .= " AND date_presence <= :endDate"; $params['endDate'] = $endDate; }
        $query .= " ORDER BY date_presence DESC, heure_arrivee DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        foreach ($params as $k => $v) {
            $type = ($k === 'agentId') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(":$k", $v, $type);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsDailyPresence($agentId, $datePresence)
    {
        $query = "SELECT COUNT(*) AS c FROM presence_agent_daily WHERE Agent_idAgent = :agentId AND date_presence = :date_presence";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['agentId' => $agentId, 'date_presence' => $datePresence]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['c'] > 0;
    }

    public function getPresenceSummary($startDate, $endDate, $structureId = null, $serviceId = null)
    {
        $params = [
            'start' => $startDate,
            'end' => $endDate,
        ];
        $query = "SELECT 
                    a.idAgent,
                    a.noms,
                    a.matricule,
                    a.telephone,
                    str.designation AS structure,
                    s.designation AS service,
                    COUNT(DISTINCT pad.date_presence) AS jours_presence
                  FROM agent a
                  INNER JOIN structure str ON a.idStructure = str.idStructure
                  LEFT JOIN service s ON a.idService = s.idService
                  LEFT JOIN presence_agent_daily pad 
                    ON pad.Agent_idAgent = a.idAgent 
                   AND pad.date_presence BETWEEN :start AND :end
                  WHERE a.type_agent = 'Administratif'";

        if (!empty($structureId)) {
            $query .= " AND a.idStructure = :structureId";
            $params['structureId'] = (int)$structureId;
        }
        if (!empty($serviceId)) {
            $query .= " AND a.idService = :serviceId";
            $params['serviceId'] = (int)$serviceId;
        }

        $query .= " GROUP BY a.idAgent, a.noms, a.matricule, a.telephone, str.designation, s.designation
                    ORDER BY str.designation, a.noms";

        $stmt = $this->db->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Presence schedule configuration
    public function getPresenceConfig()
    {
        $stmt = $this->db->query("SELECT * FROM presence_horaire_config ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function savePresenceConfig($joursTravailCsv, $heureDebut, $heureFin, $toleranceMinutes, $pauseDebut = null, $pauseFin = null, $userId = null)
    {
        // Upsert pattern: insert if none, else update latest
        $existing = $this->getPresenceConfig();
        if ($existing) {
            $query = "UPDATE presence_horaire_config
                      SET jours_travail = :jours, heure_debut = :hdeb, heure_fin = :hfin, tolerance_minutes = :tol,
                          pause_debut = :pdeb, pause_fin = :pfin, updated_by = :uid, updated_at = NOW()
                      WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'jours' => $joursTravailCsv,
                'hdeb' => $heureDebut,
                'hfin' => $heureFin,
                'tol' => (int)$toleranceMinutes,
                'pdeb' => $pauseDebut,
                'pfin' => $pauseFin,
                'uid' => $userId,
                'id' => $existing['id'],
            ]);
        } else {
            $query = "INSERT INTO presence_horaire_config (jours_travail, heure_debut, heure_fin, tolerance_minutes, pause_debut, pause_fin, updated_by)
                      VALUES (:jours, :hdeb, :hfin, :tol, :pdeb, :pfin, :uid)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'jours' => $joursTravailCsv,
                'hdeb' => $heureDebut,
                'hfin' => $heureFin,
                'tol' => (int)$toleranceMinutes,
                'pdeb' => $pauseDebut,
                'pfin' => $pauseFin,
                'uid' => $userId,
            ]);
        }
    }

    public function isWorkingDay($dateYmd)
    {
        $cfg = $this->getPresenceConfig();
        if (!$cfg || empty($cfg['jours_travail'])) return true; // default allow
        $map = [1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>6, 7=>7]; // ISO: 1=Mon ..7=Sun
        $dow = (int)date('N', strtotime($dateYmd));
        $allowed = array_map('intval', explode(',', $cfg['jours_travail']));
        return in_array($map[$dow], $allowed, true);
    }

    public function getAgentIdByUserId($userId)
    {
        $query = "SELECT a.idAgent 
                FROM t_users u
                JOIN agent a ON u.idAgent = a.idAgent
                WHERE u.idUser = :userId 
                AND a.type_agent = 'Enseignant'";
                
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['idAgent'] : null;
    }

    // Récupérer les agents par type
    public function getAgentsByType($type, $search = '', $limit = 1000)
    {
        $query = "SELECT
            a.*,
            str.designation as designationStructure,
            str.idStructure as idStructure,
            g.designation as gradeDesignation,
            s.designation as serviceDesignation,
            s.idService as idService
        FROM agent AS a
        INNER JOIN structure AS str ON a.idStructure = str.idStructure
        LEFT JOIN grade AS g ON a.grade_id = g.idgrade
        LEFT JOIN service AS s ON a.idService = s.idService
        WHERE a.type_agent = :type";
        
        if (!empty($search)) {
            $query .= " AND a.noms LIKE :search";
        }
        
        $query .= " ORDER BY a.noms ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Version améliorée pour diagnostiquer les problèmes avec les enseignants
    public function getAgentsByTypeWithDiagnostic($type, $search = '', $limit = 1000)
    {
        // Première requête : récupérer tous les agents du type demandé avec diagnostic
        $query = "SELECT
            a.*,
            str.designation as designationStructure,
            str.idStructure as idStructure,
            g.designation as gradeDesignation,
            s.designation as serviceDesignation,
            CASE 
                WHEN str.idStructure IS NULL THEN 'Structure manquante'
                WHEN g.idgrade IS NULL THEN 'Grade manquant'
                WHEN s.idService IS NULL THEN 'Service manquant'
                ELSE 'OK'
            END as statut_diagnostic
        FROM agent AS a
        LEFT JOIN structure AS str ON a.idStructure = str.idStructure
        LEFT JOIN grade AS g ON a.grade_id = g.idgrade
        LEFT JOIN service AS s ON a.idService = s.idService
        WHERE a.type_agent = :type";
        
        if (!empty($search)) {
            $query .= " AND a.noms LIKE :search";
        }
        
        $query .= " ORDER BY a.noms ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log des problèmes détectés
        foreach ($results as $agent) {
            if ($agent['statut_diagnostic'] !== 'OK') {
                error_log("Agent {$agent['idAgent']} ({$agent['noms']}) - Problème: {$agent['statut_diagnostic']}");
            }
        }
        
        return $results;
    }

    // Méthode pour corriger automatiquement certains problèmes d'enseignants
    public function corrigerProblemeEnseignants()
    {
        $corrections = [];
        
        try {
            // 1. Corriger les agents qui ont un grade d'enseignant mais pas le bon type_agent
            $query1 = "UPDATE agent SET type_agent = 'Enseignant' 
                      WHERE grade_id IN (
                          SELECT idgrade FROM grade 
                          WHERE LOWER(designation) LIKE '%professeur%' 
                          OR LOWER(designation) LIKE '%assistant%' 
                          OR LOWER(designation) LIKE '%chef de travaux%'
                          OR LOWER(designation) LIKE '%docteur%'
                          OR LOWER(designation) LIKE '%enseignant%'
                      ) AND type_agent != 'Enseignant'";
            
            $stmt1 = $this->db->prepare($query1);
            $stmt1->execute();
            $corrections['type_agent_corriges'] = $stmt1->rowCount();
            
            // 2. Identifier les agents sans structure valide
            $query2 = "SELECT COUNT(*) as count FROM agent a 
                      LEFT JOIN structure s ON a.idStructure = s.idStructure 
                      WHERE a.type_agent = 'Enseignant' AND s.idStructure IS NULL";
            $stmt2 = $this->db->prepare($query2);
            $stmt2->execute();
            $result2 = $stmt2->fetch();
            $corrections['enseignants_sans_structure'] = $result2['count'];
            
            // 3. Identifier les agents sans grade valide
            $query3 = "SELECT COUNT(*) as count FROM agent a 
                      LEFT JOIN grade g ON a.grade_id = g.idgrade 
                      WHERE a.type_agent = 'Enseignant' AND g.idgrade IS NULL";
            $stmt3 = $this->db->prepare($query3);
            $stmt3->execute();
            $result3 = $stmt3->fetch();
            $corrections['enseignants_sans_grade'] = $result3['count'];
            
            return $corrections;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la correction des problèmes d'enseignants: " . $e->getMessage());
            return false;
        }
    }
    
    // Récupérer les agents par grade
    public function getAgentsByGrade($gradeId, $search = '', $limit = 100)
    {
        $query = "SELECT
            a.*,
            str.designation as designationStructure,
            str.idStructure as idStructure,
            g.designation as gradeDesignation,
            s.designation as serviceDesignation
        FROM agent AS a
        INNER JOIN structure AS str ON a.idStructure = str.idStructure
        LEFT JOIN grade AS g ON a.grade_id = g.idgrade
        LEFT JOIN service AS s ON a.idService = s.idService
        WHERE a.grade_id = :gradeId";
        
        if (!empty($search)) {
            $query .= " AND a.noms LIKE :search";
        }
        
        $query .= " ORDER BY a.noms ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':gradeId', $gradeId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les agents par service
    public function getAgentsByService($serviceId, $search = '', $limit = 100)
    {
        $query = "SELECT
            a.*,
            str.designation as designationStructure,
            str.idStructure as idStructure,
            g.designation as gradeDesignation,
            s.designation as serviceDesignation
        FROM agent AS a
        INNER JOIN structure AS str ON a.idStructure = str.idStructure
        LEFT JOIN grade AS g ON a.grade_id = g.idgrade
        LEFT JOIN service AS s ON a.idService = s.idService
        WHERE a.idService = :serviceId";
        
        if (!empty($search)) {
            $query .= " AND a.noms LIKE :search";
        }
        
        $query .= " ORDER BY a.noms ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les enseignants avec leurs cours affectés
public function getEnseignantsWithCourses($idAnneeAcad, $idSection = null) {
    $query = "SELECT a.idAgent, a.noms, a.type_agent, g.designation as grade, 
              e.idenseignant_ecue, e.poste, ec.designationECUE, u.designationUE,
              s.numeroSemestre, p.designationPromotion
              FROM agent a 
              LEFT JOIN enseignant_ecue e ON a.idAgent = e.idAgent AND e.anneeAcad = :idAnneeAcad
              LEFT JOIN ecue ec ON e.idECUE = ec.idECUE
              LEFT JOIN ue u ON ec.UE_idUE = u.idUE
              LEFT JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              LEFT JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE a.type_agent = 'Enseignant'";
    
    if ($idSection) {
        $query .= " AND o.section_idsection = :idSection";
    }
    
    $query .= " ORDER BY a.noms, p.designationPromotion, s.numeroSemestre";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAnneeAcad', $idAnneeAcad);
    
    if ($idSection) {
        $stmt->bindParam(':idSection', $idSection);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'ID d'un grade par sa désignation
 * @param string $designation La désignation du grade
 * @return int|null L'ID du grade ou null si non trouvé
 */
public function getGradeIdByDesignation($designation) {
    try {
        $sql = "SELECT idgrade FROM grade WHERE LOWER(designation) = LOWER(:designation)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':designation', $designation);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['idgrade'] : null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du grade par désignation: " . $e->getMessage());
        return null;
    }
}

// Mettre à jour les informations supplémentaires d'un agent
public function updateAgentAdditionalInfo($idAgent, $adresse_avenue, $adresse_quartier, $adresse_commune, 
                                         $conjoint, $contact_urgence, $degre_parente_urgence, 
                                         $telephone_urgence, $annee_engagement, $reference_acte_engagement, 
                                         $prime_locale, $salaire_etat, $prime_institutionnelle, $photo)
{
    try {
        $query = "UPDATE agent 
                  SET adresse_avenue = :adresse_avenue, 
                      adresse_quartier = :adresse_quartier, 
                      adresse_commune = :adresse_commune, 
                      conjoint = :conjoint, 
                      contact_urgence = :contact_urgence, 
                      degre_parente_urgence = :degre_parente_urgence, 
                      telephone_urgence = :telephone_urgence, 
                      annee_engagement = :annee_engagement, 
                      reference_acte_engagement = :reference_acte_engagement, 
                      prime_locale = :prime_locale, 
                      salaire_etat = :salaire_etat, 
                      prime_institutionnelle = :prime_institutionnelle";
        
        // Ajouter la photo si elle est fournie
        if (!empty($photo)) {
            $query .= ", photo = :photo";
        }
        
        $query .= " WHERE idAgent = :idAgent";
        
        $stmt = $this->db->prepare($query);
        $params = [
            'idAgent' => $idAgent,
            'adresse_avenue' => $adresse_avenue,
            'adresse_quartier' => $adresse_quartier,
            'adresse_commune' => $adresse_commune,
            'conjoint' => $conjoint,
            'contact_urgence' => $contact_urgence,
            'degre_parente_urgence' => $degre_parente_urgence,
            'telephone_urgence' => $telephone_urgence,
            'annee_engagement' => $annee_engagement,
            'reference_acte_engagement' => $reference_acte_engagement,
            'prime_locale' => $prime_locale,
            'salaire_etat' => $salaire_etat,
            'prime_institutionnelle' => $prime_institutionnelle
        ];
        
        if (!empty($photo)) {
            $params['photo'] = $photo;
        }
        
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour des informations supplémentaires de l'agent: " . $e->getMessage());
        return false;
    }
}

// Ajouter une formation pour un agent
public function addFormation($idAgent, $niveau, $etablissement, $filiere, $annee_obtention, $diplome_fichier, $idUser)
{
    try {
        $query = "INSERT INTO formation_agent (idAgent, niveau, etablissement, filiere, annee_obtention, diplome_fichier, idUser) 
                  VALUES (:idAgent, :niveau, :etablissement, :filiere, :annee_obtention, :diplome_fichier, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'niveau' => $niveau,
            'etablissement' => $etablissement,
            'filiere' => $filiere,
            'annee_obtention' => $annee_obtention,
            'diplome_fichier' => $diplome_fichier,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'une formation: " . $e->getMessage());
        return false;
    }
}

// Ajouter un historique de grade pour un agent
public function addGradeHistory($idAgent, $idgrade, $date_promotion, $reference_decision, $reference_notification, $idUser)
{
    try {
        $query = "INSERT INTO historique_grade (idAgent, idgrade, date_promotion, reference_decision, reference_notification, idUser) 
                  VALUES (:idAgent, :idgrade, :date_promotion, :reference_decision, :reference_notification, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'idgrade' => $idgrade,
            'date_promotion' => $date_promotion,
            'reference_decision' => $reference_decision,
            'reference_notification' => $reference_notification,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'un historique de grade: " . $e->getMessage());
        return false;
    }
}

// Ajouter une affectation pour un agent
public function addAffectation($idAgent, $idStructure, $idService, $date_affectation, $reference_decision, $est_actuelle, $idUser)
{
    try {
        // Si c'est l'affectation actuelle, mettre à jour les anciennes affectations
        if ($est_actuelle) {
            $updateQuery = "UPDATE affectation_agent SET est_actuelle = 0 WHERE idAgent = :idAgent";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute(['idAgent' => $idAgent]);
        }
        
        $query = "INSERT INTO affectation_agent (idAgent, idStructure, idService, date_affectation, reference_decision, est_actuelle, idUser) 
                  VALUES (:idAgent, :idStructure, :idService, :date_affectation, :reference_decision, :est_actuelle, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'idStructure' => $idStructure,
            'idService' => $idService,
            'date_affectation' => $date_affectation,
            'reference_decision' => $reference_decision,
            'est_actuelle' => $est_actuelle,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'une affectation: " . $e->getMessage());
        return false;
    }
}

// Ajouter des informations spécifiques pour un agent administratif
public function addAdminInfo($idAgent, $direction, $division, $decision_grade, $notification_grade, $idUser)
{
    try {
        $query = "INSERT INTO admin_info (idAgent, direction, division, decision_grade, notification_grade, idUser) 
                  VALUES (:idAgent, :direction, :division, :decision_grade, :notification_grade, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'direction' => $direction,
            'division' => $division,
            'decision_grade' => $decision_grade,
            'notification_grade' => $notification_grade,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'informations administratives: " . $e->getMessage());
        return false;
    }
}

// Ajouter des informations spécifiques pour un enseignant
public function addTeacherInfo($idAgent, $specialisation, $domaine_recherche, $idUser)
{
    try {
        $query = "INSERT INTO teacher_info (idAgent, specialisation, domaine_recherche, idUser) 
                  VALUES (:idAgent, :specialisation, :domaine_recherche, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'specialisation' => $specialisation,
            'domaine_recherche' => $domaine_recherche,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'informations d'enseignant: " . $e->getMessage());
        return false;
    }
}

// Ajouter des informations spécifiques pour un agent de recherche
public function addResearchInfo($idAgent, $unite_recherche, $projet_recherche, $idUser)
{
    try {
        $query = "INSERT INTO research_info (idAgent, unite_recherche, projet_recherche, idUser) 
                  VALUES (:idAgent, :unite_recherche, :projet_recherche, :idUser)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idAgent' => $idAgent,
            'unite_recherche' => $unite_recherche,
            'projet_recherche' => $projet_recherche,
            'idUser' => $idUser
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'informations de recherche: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un code agent existe déjà
 * @param string $code Le code à vérifier
 * @return bool True si le code existe, false sinon
 */
public function checkCodeExists($code) {
    $sql = "SELECT COUNT(*) AS count FROM agent WHERE codeAgent = :code";
    $stmt = $this->db->prepare($sql);
    $stmt->bindParam(':code', $code);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Récupère un agent par son code unique
 * @param string $code Le code unique de l'agent
 * @return array|false Les informations de l'agent ou false si non trouvé
 */
public function getAgentByCode($code) {
    try {
        $query = "SELECT a.*, 
                 str.designation as designationStructure,
                 g.designation as gradeDesignation,
                 s.designation as serviceDesignation,
                 ai.direction, ai.division, ai.decision_grade, ai.notification_grade,
                 ti.specialisation, ti.domaine_recherche,
                 ri.unite_recherche, ri.projet_recherche
                 FROM agent AS a
                 INNER JOIN structure AS str ON a.idStructure = str.idStructure
                 LEFT JOIN grade AS g ON a.grade_id = g.idgrade
                 LEFT JOIN service AS s ON a.idService = s.idService
                 LEFT JOIN admin_info AS ai ON a.idAgent = ai.idAgent
                 LEFT JOIN teacher_info AS ti ON a.idAgent = ti.idAgent
                 LEFT JOIN research_info AS ri ON a.idAgent = ri.idAgent
                 WHERE a.codeAgent = :code";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'agent par code: " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère un agent par son matricule
 * @param string $matricule Le matricule de l'agent
 * @return array|false Les informations de l'agent ou false si non trouvé
 */
public function getAgentByMatricule($matricule) {
    try {
        $query = "SELECT a.*,
                 str.designation as designationStructure,
                 g.designation as gradeDesignation,
                 s.designation as serviceDesignation,
                 ai.direction, ai.division, ai.decision_grade, ai.notification_grade,
                 ti.specialisation, ti.domaine_recherche,
                 ri.unite_recherche, ri.projet_recherche
                 FROM agent AS a
                 INNER JOIN structure AS str ON a.idStructure = str.idStructure
                 LEFT JOIN grade AS g ON a.grade_id = g.idgrade
                 LEFT JOIN service AS s ON a.idService = s.idService
                 LEFT JOIN admin_info AS ai ON a.idAgent = ai.idAgent
                 LEFT JOIN teacher_info AS ti ON a.idAgent = ti.idAgent
                 LEFT JOIN research_info AS ri ON a.idAgent = ri.idAgent
                 WHERE a.matricule = :matricule";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'agent par matricule: " . $e->getMessage());
        return false;
    }
}

/**
 * Recherche des agents par leur nom
 * @param string $searchTerm Le terme de recherche
 * @param int $limit Nombre maximum de résultats à retourner
 * @return array Les agents correspondant au critère de recherche
 */
public function searchAgentsByName($searchTerm, $limit = 100) {
    try {
        $query = "SELECT a.*,
                 str.designation as designationStructure,
                 str.idStructure as idStructure,
                 g.designation as gradeDesignation,
                 s.designation as serviceDesignation,
                 ai.direction, ai.division, ai.decision_grade, ai.notification_grade,
                 ti.specialisation, ti.domaine_recherche,
                 ri.unite_recherche, ri.projet_recherche
                 FROM agent AS a
                 INNER JOIN structure AS str ON a.idStructure = str.idStructure
                 LEFT JOIN grade AS g ON a.grade_id = g.idgrade
                 LEFT JOIN service AS s ON a.idService = s.idService
                 LEFT JOIN admin_info AS ai ON a.idAgent = ai.idAgent
                 LEFT JOIN teacher_info AS ti ON a.idAgent = ti.idAgent
                 LEFT JOIN research_info AS ri ON a.idAgent = ri.idAgent
                 WHERE a.noms LIKE :searchTerm
                 ORDER BY a.noms ASC
                 LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $searchParam = "%" . $searchTerm . "%";
        $stmt->bindParam(':searchTerm', $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche d'agents par nom: " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère toutes les formations d'un agent
 * @param int $idAgent L'identifiant de l'agent
 * @return array Les formations de l'agent
 */
public function getFormationsForAgent($idAgent) {
    try {
        $query = "SELECT * FROM formation_agent 
                 WHERE idAgent = :idAgent 
                 ORDER BY annee_obtention DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des formations de l'agent: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère l'historique des grades d'un agent
 * @param int $idAgent L'identifiant de l'agent
 * @return array L'historique des grades de l'agent
 */
public function getGradeHistoryForAgent($idAgent) {
    try {
        $query = "SELECT hg.*, g.designation as gradeDesignation 
                 FROM historique_grade hg
                 INNER JOIN grade g ON hg.idgrade = g.idgrade
                 WHERE hg.idAgent = :idAgent 
                 ORDER BY hg.date_promotion DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'historique des grades de l'agent: " . $e->getMessage());
        return [];
    }
}

/**
 * Met à jour les informations d'un agent avec un tableau de données
 * @param int $idAgent L'identifiant de l'agent
 * @param array $agentData Les données à mettre à jour
 * @return bool Retourne true si la mise à jour réussit, false sinon
 */
public function editAgent($idAgent, $agentData)
{
    try {
        // Construction dynamique de la requête SQL
        $query = "UPDATE agent SET ";
        $params = [];
        
        foreach ($agentData as $key => $value) {
            // Exclure les champs spécifiques non stockés dans la table agent
            if (!in_array($key, ['direction', 'division', 'decision_grade', 'notification_grade', 
                               'specialisation', 'domaine_recherche', 'unite_recherche', 'projet_recherche'])) {
                $query .= "$key = :$key, ";
                $params[$key] = $value;
            }
        }
        
        // Retirer la virgule finale et ajouter la clause WHERE
        $query = rtrim($query, ", ") . " WHERE idAgent = :idAgent";
        $params['idAgent'] = $idAgent;
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($params);
        
        // Ajouter/mettre à jour les informations spécifiques selon le type d'agent
        if ($result) {
            if (isset($agentData['type_agent'])) {
                $typeAgent = $agentData['type_agent'];
                
                if ($typeAgent === 'Administratif' && 
                    (isset($agentData['direction']) || isset($agentData['division']) || 
                     isset($agentData['decision_grade']) || isset($agentData['notification_grade']))) {
                    
                    // Vérifier si une entrée admin_info existe déjà pour cet agent
                    $checkQuery = "SELECT COUNT(*) FROM admin_info WHERE idAgent = :idAgent";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->execute(['idAgent' => $idAgent]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        // Mettre à jour les informations administratives existantes
                        $adminQuery = "UPDATE admin_info SET 
                            direction = :direction, 
                            division = :division, 
                            decision_grade = :decision_grade, 
                            notification_grade = :notification_grade
                            WHERE idAgent = :idAgent";
                    } else {
                        // Ajouter de nouvelles informations administratives
                        $adminQuery = "INSERT INTO admin_info (idAgent, direction, division, decision_grade, notification_grade, idUser) 
                            VALUES (:idAgent, :direction, :division, :decision_grade, :notification_grade, :idUser)";
                    }
                    
                    $adminStmt = $this->db->prepare($adminQuery);
                    $adminParams = [
                        'idAgent' => $idAgent,
                        'direction' => $agentData['direction'] ?? '',
                        'division' => $agentData['division'] ?? '',
                        'decision_grade' => $agentData['decision_grade'] ?? '',
                        'notification_grade' => $agentData['notification_grade'] ?? ''
                    ];
                    
                    if (strpos($adminQuery, 'INSERT') !== false) {
                        $adminParams['idUser'] = $agentData['modifie_par'] ?? null;
                    }
                    
                    $adminStmt->execute($adminParams);
                } else if ($typeAgent === 'Enseignant' && 
                          (isset($agentData['specialisation']) || isset($agentData['domaine_recherche']))) {
                    
                    // Vérifier si une entrée teacher_info existe déjà pour cet agent
                    $checkQuery = "SELECT COUNT(*) FROM teacher_info WHERE idAgent = :idAgent";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->execute(['idAgent' => $idAgent]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        // Mettre à jour les informations d'enseignant existantes
                        $teacherQuery = "UPDATE teacher_info SET 
                            specialisation = :specialisation, 
                            domaine_recherche = :domaine_recherche
                            WHERE idAgent = :idAgent";
                    } else {
                        // Ajouter de nouvelles informations d'enseignant
                        $teacherQuery = "INSERT INTO teacher_info (idAgent, specialisation, domaine_recherche, idUser) 
                            VALUES (:idAgent, :specialisation, :domaine_recherche, :idUser)";
                    }
                    
                    $teacherStmt = $this->db->prepare($teacherQuery);
                    $teacherParams = [
                        'idAgent' => $idAgent,
                        'specialisation' => $agentData['specialisation'] ?? '',
                        'domaine_recherche' => $agentData['domaine_recherche'] ?? ''
                    ];
                    
                    if (strpos($teacherQuery, 'INSERT') !== false) {
                        $teacherParams['idUser'] = $agentData['modifie_par'] ?? null;
                    }
                    
                    $teacherStmt->execute($teacherParams);
                } else if ($typeAgent === 'Recherche' && 
                          (isset($agentData['unite_recherche']) || isset($agentData['projet_recherche']))) {
                    
                    // Vérifier si une entrée research_info existe déjà pour cet agent
                    $checkQuery = "SELECT COUNT(*) FROM research_info WHERE idAgent = :idAgent";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->execute(['idAgent' => $idAgent]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        // Mettre à jour les informations de recherche existantes
                        $researchQuery = "UPDATE research_info SET 
                            unite_recherche = :unite_recherche, 
                            projet_recherche = :projet_recherche
                            WHERE idAgent = :idAgent";
                    } else {
                        // Ajouter de nouvelles informations de recherche
                        $researchQuery = "INSERT INTO research_info (idAgent, unite_recherche, projet_recherche, idUser) 
                            VALUES (:idAgent, :unite_recherche, :projet_recherche, :idUser)";
                    }
                    
                    $researchStmt = $this->db->prepare($researchQuery);
                    $researchParams = [
                        'idAgent' => $idAgent,
                        'unite_recherche' => $agentData['unite_recherche'] ?? '',
                        'projet_recherche' => $agentData['projet_recherche'] ?? ''
                    ];
                    
                    if (strpos($researchQuery, 'INSERT') !== false) {
                        $researchParams['idUser'] = $agentData['modifie_par'] ?? null;
                    }
                    
                    $researchStmt->execute($researchParams);
                }
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de l'agent: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour une formation existante
 * @param int $formationId L'identifiant de la formation
 * @param array $formation Les données de la formation à mettre à jour
 * @return bool Retourne true si la mise à jour réussit, false sinon
 */
public function updateFormation($formationId, $formation)
{
    try {
        $query = "UPDATE formation_agent SET 
                 niveau = :niveau, 
                 etablissement = :etablissement, 
                 filiere = :filiere, 
                 annee_obtention = :annee_obtention";
        
        // Ajouter le champ diplome_fichier seulement s'il est défini
        if (isset($formation['diplome_fichier'])) {
            $query .= ", diplome_fichier = :diplome_fichier";
        }
        
        $query .= " WHERE idformation = :formationId";
        
        $stmt = $this->db->prepare($query);
        $params = [
            'niveau' => $formation['niveau'],
            'etablissement' => $formation['etablissement'],
            'filiere' => $formation['filiere'],
            'annee_obtention' => $formation['annee_obtention'],
            'formationId' => $formationId
        ];
        
        if (isset($formation['diplome_fichier'])) {
            $params['diplome_fichier'] = $formation['diplome_fichier'];
        }
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de la formation: " . $e->getMessage());
        return false;
    }
}



/**
 * Supprime les formations d'un agent qui ne sont pas dans la liste des IDs fournis
 * @param int $idAgent L'identifiant de l'agent
 * @param array $formationIds Liste des IDs de formations à conserver
 * @return bool Retourne true si la suppression réussit, false sinon
 */
public function deleteFormationsNotIn($idAgent, $formationIds)
{
    try {
        $query = "DELETE FROM formation_agent WHERE idAgent = :idAgent";
        
        if (!empty($formationIds)) {
            $placeholders = implode(',', array_fill(0, count($formationIds), '?'));
            $query .= " AND idformation NOT IN ($placeholders)";
        }
        
        $stmt = $this->db->prepare($query);
        $params = [$idAgent];
        
        if (!empty($formationIds)) {
            foreach ($formationIds as $id) {
                $params[] = $id;
            }
        }
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression des formations: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour un historique de grade existant
 * @param int $gradeHistoryId L'identifiant de l'historique de grade
 * @param array $gradeHistory Les données de l'historique de grade à mettre à jour
 * @return bool Retourne true si la mise à jour réussit, false sinon
 */
public function updateGradeHistory($gradeHistoryId, $gradeHistory)
{
    try {
        $query = "UPDATE historique_grade SET 
                 idgrade = :idgrade, 
                 date_promotion = :date_promotion, 
                 reference_decision = :reference_decision, 
                 reference_notification = :reference_notification
                 WHERE idhistorique = :gradeHistoryId";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idgrade' => $gradeHistory['idgrade'],
            'date_promotion' => $gradeHistory['date_promotion'],
            'reference_decision' => $gradeHistory['reference_decision'],
            'reference_notification' => $gradeHistory['reference_notification'],
            'gradeHistoryId' => $gradeHistoryId
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de l'historique de grade: " . $e->getMessage());
        return false;
    }
}



/**
 * Supprime les historiques de grade d'un agent qui ne sont pas dans la liste des IDs fournis
 * @param int $idAgent L'identifiant de l'agent
 * @param array $gradeHistoryIds Liste des IDs d'historiques de grade à conserver
 * @return bool Retourne true si la suppression réussit, false sinon
 */
public function deleteGradeHistoriesNotIn($idAgent, $gradeHistoryIds)
{
    try {
        $query = "DELETE FROM historique_grade WHERE idAgent = :idAgent";
        
        if (!empty($gradeHistoryIds)) {
            $placeholders = implode(',', array_fill(0, count($gradeHistoryIds), '?'));
            $query .= " AND idhistorique NOT IN ($placeholders)";
        }
        
        $stmt = $this->db->prepare($query);
        $params = [$idAgent];
        
        if (!empty($gradeHistoryIds)) {
            foreach ($gradeHistoryIds as $id) {
                $params[] = $id;
            }
        }
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression des historiques de grade: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère le nombre total d'agents
 * @return int Nombre d'agents
 */
public function getAgentCount() {
    $query = "SELECT COUNT(*) as total FROM agent";
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

/**
 * Récupère le nombre d'agents par type
 * @param string $type Type d'agent (Enseignant, Administratif, Recherche)
 * @return int Nombre d'agents du type spécifié
 */
public function getAgentCountByType($type) {
    $query = "SELECT COUNT(*) as total FROM agent WHERE type_agent = :type";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':type', $type);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

/**
 * Récupère les agents avec filtrage
 * @param string $search Texte de recherche
 * @param string $typeAgent Type d'agent
 * @param int $gradeId ID du grade
 * @param int $structureId ID de la structure
 * @param int $serviceId ID du service
 * @return array Liste des agents filtrés
 */
public function getFilteredAgents($search = '', $typeAgent = '', $gradeId = 0, $structureId = 0, $serviceId = 0) {
    $conditions = [];
    $params = [];
    
    $query = "SELECT a.*, s.designation as designationStructure 
              FROM agent a 
              LEFT JOIN structure s ON a.idStructure = s.idStructure";
    
    // Ajouter les conditions de filtrage
    if (!empty($search)) {
        $conditions[] = "(a.noms LIKE :search OR a.matricule LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($typeAgent)) {
        $conditions[] = "a.type_agent = :typeAgent";
        $params[':typeAgent'] = $typeAgent;
    }
    
    if ($gradeId > 0) {
        $conditions[] = "a.grade_id = :gradeId";
        $params[':gradeId'] = $gradeId;
    }
    
    if ($structureId > 0) {
        $conditions[] = "a.idStructure = :structureId";
        $params[':structureId'] = $structureId;
    }
    
    if ($serviceId > 0) {
        $conditions[] = "a.idService = :serviceId";
        $params[':serviceId'] = $serviceId;
    }
    
    // Ajouter les conditions à la requête
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY a.noms ASC";
    
    $stmt = $this->db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère les statistiques des agents par type
 * @return array Statistiques des agents par type
 */
public function getAgentCountsByType() {
    $query = "SELECT 
                CASE 
                    WHEN type_agent IS NULL OR type_agent = '' THEN 'Non défini'
                    ELSE type_agent 
                END as type, 
                COUNT(*) as count 
              FROM agent 
              GROUP BY type_agent";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques des agents par structure
 * @return array Statistiques des agents par structure
 */
public function getAgentCountsByStructure() {
    $query = "SELECT s.designation as structure, COUNT(a.idAgent) as count
              FROM structure s
              LEFT JOIN agent a ON s.idStructure = a.idStructure
              GROUP BY s.idStructure
              ORDER BY count DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques des agents par grade
 * @return array Statistiques des agents par grade
 */
public function getAgentCountsByGrade() {
    $query = "SELECT g.designation as grade, COUNT(a.idAgent) as count
              FROM grade g
              LEFT JOIN agent a ON g.idgrade = a.grade_id
              GROUP BY g.idgrade
              ORDER BY count DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les agents récemment ajoutés
 * @param int $limit Nombre d'agents à récupérer
 * @return array Liste des agents récemment ajoutés
 */
public function getRecentlyAddedAgents($limit = 5) {
    $query = "SELECT a.*, s.designation as structureName 
              FROM agent a
              LEFT JOIN structure s ON a.idStructure = s.idStructure
              ORDER BY a.dateEnregistrement DESC 
              LIMIT :limit";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les statistiques des enseignants par section
 * @return array Statistiques des enseignants par section
 */
public function getTeacherStatsBySection() {
    $query = "SELECT s.designationSection as section, COUNT(as.idAgent) as count
              FROM section s
              LEFT JOIN agent_section as ON s.idsection = as.idsection
              LEFT JOIN agent a ON as.idAgent = a.idAgent AND a.type_agent = 'Enseignant'
              GROUP BY s.idsection
              ORDER BY count DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les agents qui sont enseignants et leur affectation principale
 * @return array Liste des enseignants avec leur section principale
 */
public function getTeachersWithMainSection() {
    $query = "SELECT a.idAgent, a.noms, a.matricule, a.type_agent, a.grade_id, 
                    g.designation as grade_name, s.designationSection as section_name
              FROM agent a
              JOIN agent_section as ON a.idAgent = as.idAgent AND as.estPrincipal = 1
              JOIN section s ON as.idsection = s.idsection
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE a.type_agent = 'Enseignant'
              ORDER BY a.noms ASC";
    
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les historiques de grades d'un agent
 * @param int $idAgent ID de l'agent
 * @return array Historique des grades
 */
public function getAgentGradeHistory($idAgent) {
    $query = "SELECT hg.*, g.designation as grade_name 
              FROM historique_grade hg
              JOIN grade g ON hg.idgrade = g.idgrade
              WHERE hg.idAgent = :idAgent
              ORDER BY hg.date_promotion DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les formations d'un agent
 * @param int $idAgent ID de l'agent
 * @return array Formations de l'agent
 */
public function getAgentFormations($idAgent) {
    $query = "SELECT * FROM formation_agent 
              WHERE idAgent = :idAgent
              ORDER BY annee_obtention DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'historique des affectations d'un agent
 * @param int $idAgent ID de l'agent
 * @return array Historique des affectations
 */
public function getAgentAffectationHistory($idAgent) {
    $query = "SELECT af.*, str.designation as structure_name, srv.designation as service_name 
              FROM affectation_agent af
              JOIN structure str ON af.idStructure = str.idStructure
              LEFT JOIN service srv ON af.idService = srv.idService
              WHERE af.idAgent = :idAgent
              ORDER BY af.date_affectation DESC";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les agents selon les critères de filtrage spécifiés
 * 
 * @param array $filters Tableau associatif des filtres à appliquer
 * @return array Liste des agents correspondant aux critères
 */
public function getFilteredAgents2($filters) {
    try {
        $query = "SELECT a.*, g.designation as grade_designation 
                  FROM agent a 
                  LEFT JOIN grade g ON a.grade_id = g.idgrade 
                  WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres
        if (!empty($filters['type_agent'])) {
            $query .= " AND a.type_agent = :type_agent";
            $params[':type_agent'] = $filters['type_agent'];
        }
        
        if (!empty($filters['grade_id'])) {
            $query .= " AND a.grade_id = :grade_id";
            $params[':grade_id'] = $filters['grade_id'];
        }
        
        if (!empty($filters['sexe'])) {
            $query .= " AND a.sexe = :sexe";
            $params[':sexe'] = $filters['sexe'];
        }
        
        if (!empty($filters['idStructure'])) {
            $query .= " AND a.idStructure = :idStructure";
            $params[':idStructure'] = $filters['idStructure'];
        }
        
        if (!empty($filters['idService'])) {
            $query .= " AND a.idService = :idService";
            $params[':idService'] = $filters['idService'];
        }
        
        if (!empty($filters['annee_engagement'])) {
            $query .= " AND a.annee_engagement = :annee_engagement";
            $params[':annee_engagement'] = $filters['annee_engagement'];
        }
        
        if ($filters['prime_locale'] !== '') {
            $query .= " AND a.prime_locale = :prime_locale";
            $params[':prime_locale'] = $filters['prime_locale'];
        }
        
        if ($filters['salaire_etat'] !== '') {
            $query .= " AND a.salaire_etat = :salaire_etat";
            $params[':salaire_etat'] = $filters['salaire_etat'];
        }
        
        if ($filters['prime_institutionnelle'] !== '') {
            $query .= " AND a.prime_institutionnelle = :prime_institutionnelle";
            $params[':prime_institutionnelle'] = $filters['prime_institutionnelle'];
        }
        
        if (!empty($filters['niveauEtude'])) {
            $query .= " AND a.niveauEtude = :niveauEtude";
            $params[':niveauEtude'] = $filters['niveauEtude'];
        }
        
        if (!empty($filters['etatCivil'])) {
            $query .= " AND a.etatCivil = :etatCivil";
            $params[':etatCivil'] = $filters['etatCivil'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND a.noms LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Ordonner les résultats
        $query .= " ORDER BY a.noms ASC";
        
        $stmt = $this->db->prepare($query);
        
        // Lier les paramètres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erreur lors de la récupération des agents filtrés: ' . $e->getMessage());
        return [];
    }
}











}

    
