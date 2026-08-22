<?php
class PlanTravail {
    private $connexion;

    public function __construct() {
        $this->connexion = Connexion::getInstance()->getPDO();
    }

    /**
     * Créer un nouveau plan de travail
     */
    public function creerPlan($donnees) {
        try {
            $this->connexion->beginTransaction();
            
            // Insérer le plan principal
            $sql = "INSERT INTO plan_travail (idsujets, titre_plan, introduction, problematique, objectifs, methodologie, idUser) 
                    VALUES (:idsujets, :titre_plan, :introduction, :problematique, :objectifs, :methodologie, :idUser)";
            
            $stmt = $this->connexion->prepare($sql);
            $stmt->execute([
                ':idsujets' => $donnees['sujet_id'],
                ':titre_plan' => $donnees['titre_plan'],
                ':introduction' => $donnees['introduction'] ?? null,
                ':problematique' => $donnees['problematique'] ?? null,
                ':objectifs' => $donnees['objectifs'] ?? null,
                ':methodologie' => $donnees['methodologie'] ?? null,
                ':idUser' => $donnees['user_id']
            ]);
            
            $planId = $this->connexion->lastInsertId();
            
            // Insérer les chapitres s'ils existent
            if (isset($donnees['chapitres']) && is_array($donnees['chapitres'])) {
                foreach ($donnees['chapitres'] as $ordre => $chapitre) {
                    $this->ajouterChapitre($planId, $chapitre, $ordre);
                }
            }
            
            // Enregistrer dans l'historique
            $this->ajouterHistorique($planId, 'En attente', 'Plan de travail créé', $donnees['user_id']);
            
            $this->connexion->commit();
            return $planId;
            
        } catch (Exception $e) {
            $this->connexion->rollBack();
            throw new Exception("Erreur lors de la création du plan: " . $e->getMessage());
        }
    }

    /**
     * Modifier un plan existant
     */
    public function modifierPlan($planId, $donnees) {
        try {
            $this->connexion->beginTransaction();
            
            // Incrémenter la version
            $this->connexion->prepare("UPDATE plan_travail SET version = version + 1 WHERE idplan_travail = ?")->execute([$planId]);
            
            // Mettre à jour le plan
            $sql = "UPDATE plan_travail SET 
                    titre_plan = :titre_plan,
                    introduction = :introduction,
                    problematique = :problematique,
                    objectifs = :objectifs,
                    methodologie = :methodologie,
                    statut_validation = 'En attente',
                    commentaire_directeur = NULL,
                    date_validation = NULL,
                    idValidateur = NULL
                    WHERE idplan_travail = :plan_id";
            
            $stmt = $this->connexion->prepare($sql);
            $stmt->execute([
                ':plan_id' => $planId,
                ':titre_plan' => $donnees['titre_plan'],
                ':introduction' => $donnees['introduction'] ?? null,
                ':problematique' => $donnees['problematique'] ?? null,
                ':objectifs' => $donnees['objectifs'] ?? null,
                ':methodologie' => $donnees['methodologie'] ?? null
            ]);
            
            // Obtenir la nouvelle version
            $version = $this->connexion->query("SELECT version FROM plan_travail WHERE idplan_travail = $planId")->fetchColumn();
            
            // Enregistrer dans l'historique
            $this->ajouterHistorique($planId, 'Modifié', 'Plan de travail modifié', $donnees['user_id'], $version);
            
            $this->connexion->commit();
            return true;
            
        } catch (Exception $e) {
            $this->connexion->rollBack();
            throw new Exception("Erreur lors de la modification du plan: " . $e->getMessage());
        }
    }

    /**
     * Valider ou rejeter un plan par le directeur
     */
    public function validerPlan($planId, $statut, $commentaire = null, $directeurId = null) {
        try {
            $sql = "UPDATE plan_travail SET 
                    statut_validation = :statut,
                    commentaire_directeur = :commentaire,
                    date_validation = NOW(),
                    idValidateur = :directeur_id
                    WHERE idplan_travail = :plan_id";
            
            $stmt = $this->connexion->prepare($sql);
            $stmt->execute([
                ':plan_id' => $planId,
                ':statut' => $statut,
                ':commentaire' => $commentaire,
                ':directeur_id' => $directeurId
            ]);
            
            // Obtenir la version actuelle
            $version = $this->connexion->query("SELECT version FROM plan_travail WHERE idplan_travail = $planId")->fetchColumn();
            
            // Enregistrer dans l'historique
            $this->ajouterHistorique($planId, $statut, $commentaire, $directeurId, $version);
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la validation: " . $e->getMessage());
        }
    }

    /**
     * Récupérer un plan par ID de sujet
     */
    public function getPlanBySujet($sujetId) {
        $sql = "SELECT pt.*, 
                       a.noms as validateur_nom
                FROM plan_travail pt
                LEFT JOIN agent a ON pt.idValidateur = a.idAgent
                WHERE pt.idsujets = :sujet_id
                ORDER BY pt.version DESC
                LIMIT 1";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':sujet_id' => $sujetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un plan par ID
     */
    public function getPlanById($planId) {
        $sql = "SELECT pt.*, 
                       s.intitule as sujet_intitule,
                       s.idDirecteur,
                       s.idEncadreur,
                       s.etudiant_idetudiant,
                       e.noms as etudiant_nom,
                       e.matricule,
                       dir.noms as directeur_nom,
                       enc.noms as encadreur_nom,
                       sp.designation as specialisation
                FROM plan_travail pt
                JOIN sujets s ON pt.idsujets = s.idsujets
                JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                WHERE pt.idplan_travail = :plan_id";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les chapitres d'un plan
     */
    public function getChapitresByPlan($planId) {
        $sql = "SELECT cp.*, da.deadline, da.description_deadline, da.priorite
                FROM chapitre_plan cp
                LEFT JOIN deadline_assignment da ON cp.idchapitre_plan = da.idchapitre_plan 
                    AND da.statut_deadline = 'Active'
                WHERE cp.idplan_travail = :plan_id
                ORDER BY cp.ordre_affichage, cp.numero_chapitre";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajouter un chapitre au plan
     */
    public function ajouterChapitre($planId, $chapitre, $ordre = 1) {
        $sql = "INSERT INTO chapitre_plan (idplan_travail, numero_chapitre, titre_chapitre, description, ordre_affichage)
                VALUES (:plan_id, :numero, :titre, :description, :ordre)";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([
            ':plan_id' => $planId,
            ':numero' => $chapitre['numero'],
            ':titre' => $chapitre['titre'],
            ':description' => $chapitre['description'] ?? null,
            ':ordre' => $ordre
        ]);
        
        return $this->connexion->lastInsertId();
    }

    /**
     * Assigner une deadline à un chapitre
     */
    public function assignerDeadline($chapitreId, $deadline, $description = null, $priorite = 'Moyenne', $directeurId = null) {
        try {
            // Désactiver les anciennes deadlines
            $this->connexion->prepare("UPDATE deadline_assignment SET statut_deadline = 'Annulée' WHERE idchapitre_plan = ?")->execute([$chapitreId]);
            
            // Créer la nouvelle deadline
            $sql = "INSERT INTO deadline_assignment (idchapitre_plan, type_element, deadline, description_deadline, priorite, idDirecteur)
                    VALUES (:chapitre_id, 'chapitre', :deadline, :description, :priorite, :directeur_id)";
            
            $stmt = $this->connexion->prepare($sql);
            $stmt->execute([
                ':chapitre_id' => $chapitreId,
                ':deadline' => $deadline,
                ':description' => $description,
                ':priorite' => $priorite,
                ':directeur_id' => $directeurId
            ]);
            
            // Mettre à jour le chapitre
            $this->connexion->prepare("UPDATE chapitre_plan SET deadline = ?, date_attribution_deadline = NOW(), commentaire_deadline = ? WHERE idchapitre_plan = ?")
                            ->execute([$deadline, $description, $chapitreId]);
            
            return $this->connexion->lastInsertId();
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'assignation de la deadline: " . $e->getMessage());
        }
    }

    /**
     * Récupérer les plans en attente de validation pour un directeur
     */
    public function getPlansEnAttenteParDirecteur($directeurId) {
        $sql = "SELECT pt.*, 
                       s.intitule as sujet_intitule,
                       e.noms as etudiant_nom,
                       e.matricule,
                       sp.designation as specialisation
                FROM plan_travail pt
                JOIN sujets s ON pt.idsujets = s.idsujets
                JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                WHERE s.idDirecteur = :directeur_id 
                AND pt.statut_validation = 'En attente'
                ORDER BY pt.date_soumission DESC";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':directeur_id' => $directeurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer tous les plans d'un directeur
     */
    public function getPlansParDirecteur($directeurId, $statut = null) {
        $whereClause = "WHERE s.idDirecteur = :directeur_id";
        $params = [':directeur_id' => $directeurId];
        
        if ($statut) {
            $whereClause .= " AND pt.statut_validation = :statut";
            $params[':statut'] = $statut;
        }
        
        $sql = "SELECT pt.*, 
                       s.intitule as sujet_intitule,
                       e.noms as etudiant_nom,
                       e.matricule,
                       sp.designation as specialisation,
                       COUNT(cp.idchapitre_plan) as nb_chapitres,
                       COUNT(CASE WHEN cp.statut = 'Terminé' THEN 1 END) as nb_chapitres_termines
                FROM plan_travail pt
                JOIN sujets s ON pt.idsujets = s.idsujets
                JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
                LEFT JOIN chapitre_plan cp ON pt.idplan_travail = cp.idplan_travail
                $whereClause
                GROUP BY pt.idplan_travail
                ORDER BY pt.date_soumission DESC";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les deadlines à venir
     */
    public function getDeadlinesProchaines($directeurId = null, $jours = 7) {
        $whereClause = "WHERE da.statut_deadline = 'Active' AND da.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :jours DAY)";
        $params = [':jours' => $jours];
        
        if ($directeurId) {
            $whereClause .= " AND da.idDirecteur = :directeur_id";
            $params[':directeur_id'] = $directeurId;
        }
        
        $sql = "SELECT da.*, 
                       cp.titre_chapitre,
                       cp.numero_chapitre,
                       pt.titre_plan,
                       s.intitule as sujet_intitule,
                       e.noms as etudiant_nom,
                       e.matricule,
                       DATEDIFF(da.deadline, CURDATE()) as jours_restants
                FROM deadline_assignment da
                JOIN chapitre_plan cp ON da.idchapitre_plan = cp.idchapitre_plan
                JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                JOIN sujets s ON pt.idsujets = s.idsujets
                JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                $whereClause
                ORDER BY da.deadline ASC";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajouter une entrée dans l'historique
     */
    private function ajouterHistorique($planId, $statut, $commentaire, $userId, $version = 1) {
        $sql = "INSERT INTO plan_validation_history (idplan_travail, statut, commentaire, idUser, version_plan)
                VALUES (:plan_id, :statut, :commentaire, :user_id, :version)";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([
            ':plan_id' => $planId,
            ':statut' => $statut,
            ':commentaire' => $commentaire,
            ':user_id' => $userId,
            ':version' => $version
        ]);
    }

    /**
     * Récupérer l'historique d'un plan
     */
    public function getHistoriquePlan($planId) {
        $sql = "SELECT pvh.*, 
                       a.noms as auteur_nom
                FROM plan_validation_history pvh
                LEFT JOIN agent a ON pvh.idUser = a.idAgent
                WHERE pvh.idplan_travail = :plan_id
                ORDER BY pvh.date_action DESC";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un chapitre par son ID
     */
    public function getChapitreById($chapitreId) {
        $sql = "SELECT cp.*, pt.idsujets 
                FROM chapitre_plan cp
                JOIN plan_travail pt ON cp.idplan_travail = pt.idplan_travail
                WHERE cp.idchapitre_plan = :chapitre_id";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':chapitre_id' => $chapitreId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mettre à jour le statut d'un chapitre
     */
    public function mettreAJourChapitre($chapitreId, $donnees) {
        // Construction dynamique de la requête selon les données fournies
        $fields = [];
        $params = [':chapitre_id' => $chapitreId];
        
        if (isset($donnees['statut'])) {
            $fields[] = "statut = :statut";
            $params[':statut'] = $donnees['statut'];
        }
        
        if (isset($donnees['pourcentage_avancement'])) {
            $fields[] = "pourcentage_avancement = :pourcentage";
            $params[':pourcentage'] = $donnees['pourcentage_avancement'];
        }
        
        if (isset($donnees['fichier_chapitre'])) {
            $fields[] = "fichier_chapitre = :fichier";
            $params[':fichier'] = $donnees['fichier_chapitre'];
        }
        
        if (isset($donnees['commentaire_directeur'])) {
            $fields[] = "commentaire_directeur = :commentaire";
            $params[':commentaire'] = $donnees['commentaire_directeur'];
        }
        
        if (isset($donnees['date_soumission'])) {
            $fields[] = "date_soumission = :date_soumission";
            $params[':date_soumission'] = $donnees['date_soumission'];
        }
        
        if (empty($fields)) {
            return false; // Aucun champ à mettre à jour
        }
        
        $sql = "UPDATE chapitre_plan SET " . implode(', ', $fields) . " WHERE idchapitre_plan = :chapitre_id";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->rowCount() > 0;
        
        // Si la mise à jour réussit et que le statut ou pourcentage change, recalculer l'avancement global du plan
        if ($result && (isset($donnees['statut']) || isset($donnees['pourcentage_avancement']))) {
            $this->calculerAvancementGlobal($chapitreId);
        }
        
        return $result;
    }

    /**
     * Calculer l'avancement global d'un plan basé sur ses chapitres
     */
    public function calculerAvancementGlobal($chapitreId) {
        try {
            // Récupérer l'ID du plan depuis le chapitre
            $planId = $this->connexion->query("SELECT idplan_travail FROM chapitre_plan WHERE idchapitre_plan = $chapitreId")->fetchColumn();
            
            if (!$planId) return false;
            
            // Calculer l'avancement basé sur les chapitres validés vs total chapitres
            $sql = "SELECT 
                        COUNT(*) as total_chapitres,
                        SUM(CASE 
                            WHEN statut = 'Terminé' THEN 1 
                            WHEN statut = 'En révision' AND pourcentage_avancement >= 80 THEN 0.8
                            WHEN statut = 'En cours' THEN pourcentage_avancement / 100
                            ELSE 0 
                        END) as chapitres_ponderes
                    FROM chapitre_plan 
                    WHERE idplan_travail = :plan_id";
            
            $stmt = $this->connexion->prepare($sql);
            $stmt->execute([':plan_id' => $planId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total_chapitres'] > 0) {
                $pourcentageGlobal = round(($result['chapitres_ponderes'] / $result['total_chapitres']) * 100);
                
                // Ajouter/mettre à jour une colonne pourcentage_avancement dans plan_travail si elle existe
                try {
                    $this->connexion->prepare("UPDATE plan_travail SET pourcentage_avancement = ? WHERE idplan_travail = ?")
                                   ->execute([$pourcentageGlobal, $planId]);
                } catch (PDOException $e) {
                    // La colonne n'existe peut-être pas, l'ignorer pour l'instant
                    error_log("Colonne pourcentage_avancement manquante dans plan_travail: " . $e->getMessage());
                }
                
                return $pourcentageGlobal;
            }
            
            return 0;
            
        } catch (Exception $e) {
            error_log("Erreur calcul avancement global: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valider un chapitre soumis par le directeur
     */
    public function validerChapitre($chapitreId, $statut, $note = null, $commentaire = null, $directeurId = null) {
        try {
            $donnees = [
                'statut' => $statut, // 'Terminé' ou 'Rejeté'
                'commentaire_directeur' => $commentaire
            ];
            
            if ($note !== null) {
                $donnees['note_chapitre'] = $note;
            }
            
            // Si validé, mettre l'avancement à 100%
            if ($statut === 'Terminé') {
                $donnees['pourcentage_avancement'] = 100;
            }
            
            $success = $this->mettreAJourChapitre($chapitreId, $donnees);
            
            if ($success) {
                // Enregistrer l'action de validation
                $chapitre = $this->getChapitreById($chapitreId);
                if ($chapitre) {
                    $this->ajouterHistorique($chapitre['idplan_travail'], 
                        "Chapitre {$chapitre['numero_chapitre']} $statut", 
                        $commentaire, $directeurId);
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la validation du chapitre: " . $e->getMessage());
        }
    }
    
    /**
     * Récupérer l'avancement global d'un plan
     */
    public function getAvancementPlan($planId) {
        $sql = "SELECT 
                    COUNT(*) as total_chapitres,
                    COUNT(CASE WHEN statut = 'Terminé' THEN 1 END) as chapitres_termines,
                    COUNT(CASE WHEN statut = 'En révision' THEN 1 END) as chapitres_en_revision,
                    COUNT(CASE WHEN statut = 'En cours' THEN 1 END) as chapitres_en_cours,
                    AVG(pourcentage_avancement) as avancement_moyen
                FROM chapitre_plan 
                WHERE idplan_travail = :plan_id";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques des plans par statut
     */
    public function getStatistiquesPlans($directeurId = null) {
        $whereClause = $directeurId ? "WHERE s.idDirecteur = :directeur_id" : "";
        $params = $directeurId ? [':directeur_id' => $directeurId] : [];
        
        $sql = "SELECT 
                    pt.statut_validation,
                    COUNT(*) as nombre
                FROM plan_travail pt
                JOIN sujets s ON pt.idsujets = s.idsujets
                $whereClause
                GROUP BY pt.statut_validation";
        
        $stmt = $this->connexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
