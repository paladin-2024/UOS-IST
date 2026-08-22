<?php
class Comptabilite
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Obtient le solde reporté pour une structure donnée
     * @param int $structureId ID de la structure
     * @param string $dateDebut Date de début (format Y-m-d)
     * @return float Le solde reporté
     */
    public function getSoldeReport($userId,$structureId, $dateDebut)
    {
        try {
            // Recettes
            $queryRecettes = "SELECT COALESCE(SUM(r.montantR), 0) as total
                            FROM recette_structure r 
                            INNER JOIN ligne_recette_structure lr ON r.ligne_recette_structure_idligne_recette_structure = lr.idligne_recette_structure
                            INNER JOIN groupe_recette_structure gr ON lr.Groupe_recette_structure_idGroupe_recette_structure = gr.\"idGroupe_recette_structure\"
                            INNER JOIN budget_recette_structure br ON gr.\"Budget_recette_structure_idBudget_recette_structure\" = br.idBudget_recette_structure
                            INNER JOIN user_budget_recette ubr ON br.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
                            WHERE br.\"Structure_idStructure\" = :structureId
                            AND ubr.\"idUser\" = :userId 
                            AND r.dateOperation < :dateDebut";

            // Paiements Clients
            $queryPaiementsClients = "SELECT COALESCE(SUM(pc.montant), 0) as total
                                     FROM paiement_client pc
                                     INNER JOIN facture_client fc ON pc.Facture_client_idFacture_client = fc.idFacture_client
                                     INNER JOIN client c ON fc.Client_idClient = c.idClient
                                     WHERE c.\"Structure_idStructure\" = :structureId 
                                     AND pc.\"datePaiement\" < :dateDebut";

            // Dépenses
            $queryDepenses = "SELECT COALESCE(SUM(d.montantD), 0) as total
                            FROM depense_structure d
                            INNER JOIN ligne_depense_structure ld ON d.ligne_depense_structure_idligne_depense_structure = ld.idligne_depense_structure
                            INNER JOIN groupe_depense_structure gd ON ld.Groupe_depense_structure_idGroupe_depense_structure = gd.\"idGroupe_depense_structure\"
                            INNER JOIN budget_depense_structure bd ON gd.\"Budget_depense_structure_idBudget_depense_structure\" = bd.idBudget_depense_structure
                            INNER JOIN user_budget_depense ubd ON bd.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
                            WHERE bd.\"Structure_idStructure\" = :structureId 
                            AND ubd.\"idUser\" = :userId
                            AND d.dateoperation < :dateDebut";

            // Paiements Fournisseurs
            $queryPaiementsFournisseurs = "SELECT COALESCE(SUM(pf.montant), 0) as total
                                          FROM paiement_fournisseur pf
                                          INNER JOIN facture_fournisseur ff ON pf.Facture_fournisseur_idFacture_fournisseur = ff.idFacture_fournisseur
                                          INNER JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
                                          WHERE f.\"Structure_idStructure\" = :structureId 
                                          AND pf.\"datePaiement\" < :dateDebut";

            $stmt = $this->db->prepare($queryRecettes);
            $stmt->execute(['structureId' => $structureId, 'userId' => $userId, 'dateDebut' => $dateDebut]);
            $recettes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare($queryPaiementsClients);
            $stmt->execute(['structureId' => $structureId, 'dateDebut' => $dateDebut]);
            $paiementsClients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare($queryDepenses);
            $stmt->execute(['structureId' => $structureId, 'userId' => $userId, 'dateDebut' => $dateDebut]);
            $depenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare($queryPaiementsFournisseurs);
            $stmt->execute(['structureId' => $structureId, 'dateDebut' => $dateDebut]);
            $paiementsFournisseurs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return floatval($recettes) + floatval($paiementsClients) - floatval($depenses) - floatval($paiementsFournisseurs);
        } catch (PDOException $e) {
            error_log("Erreur calcul solde report: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtient le total des recettes pour une période donnée
     */
    public function getRecettes($userId, $structureId, $dateDebut, $dateFin)
    {
        try {
            $query = "SELECT COALESCE(SUM(r.montantR), 0) as total
                     FROM recette_structure r
                     INNER JOIN ligne_recette_structure lr ON r.ligne_recette_structure_idligne_recette_structure = lr.idligne_recette_structure
                     INNER JOIN groupe_recette_structure gr ON lr.Groupe_recette_structure_idGroupe_recette_structure = gr.\"idGroupe_recette_structure\"
                     INNER JOIN budget_recette_structure br ON gr.\"Budget_recette_structure_idBudget_recette_structure\" = br.idBudget_recette_structure
                     INNER JOIN user_budget_recette ubr ON br.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
                     WHERE br.\"Structure_idStructure\" = :structureId 
                     AND ubr.\"idUser\" = :userId
                     AND r.dateOperation BETWEEN :dateDebut AND :dateFin";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'structureId' => $structureId,
                'userId' => $userId,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin
            ]);
            
            return floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        } catch (PDOException $e) {
            error_log("Erreur calcul recettes: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtient le total des paiements clients pour une période donnée
     */
    public function getPaiementsClients($structureId, $dateDebut, $dateFin)
    {
        try {
            $query = "SELECT COALESCE(SUM(pc.montant), 0) as total
                     FROM paiement_client pc
                     INNER JOIN facture_client fc ON pc.Facture_client_idFacture_client = fc.idFacture_client
                     INNER JOIN client c ON fc.Client_idClient = c.idClient
                     WHERE c.\"Structure_idStructure\" = :structureId 
                     AND pc.\"datePaiement\" BETWEEN :dateDebut AND :dateFin";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'structureId' => $structureId,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin
            ]);
            
            return floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        } catch (PDOException $e) {
            error_log("Erreur calcul paiements clients: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtient le total des dépenses pour une période donnée
     */
    public function getDepenses($userId, $structureId, $dateDebut, $dateFin)
    {
        try {
            $query = "SELECT COALESCE(SUM(d.montantD), 0) as total
                     FROM depense_structure d
                     INNER JOIN ligne_depense_structure ld ON d.ligne_depense_structure_idligne_depense_structure = ld.idligne_depense_structure
                     INNER JOIN groupe_depense_structure gd ON ld.Groupe_depense_structure_idGroupe_depense_structure = gd.\"idGroupe_depense_structure\"
                     INNER JOIN budget_depense_structure bd ON gd.\"Budget_depense_structure_idBudget_depense_structure\" = bd.idBudget_depense_structure
                     INNER JOIN user_budget_depense ubd ON bd.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
                     WHERE bd.\"Structure_idStructure\" = :structureId 
                     AND ubd.\"idUser\" = :userId
                     AND d.dateoperation BETWEEN :dateDebut AND :dateFin";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'structureId' => $structureId,
                'userId' => $userId,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin
            ]);
            
            return floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        } catch (PDOException $e) {
            error_log("Erreur calcul dépenses: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtient le total des paiements fournisseurs pour une période donnée
     */
    public function getPaiementsFournisseurs($structureId, $dateDebut, $dateFin)
    {
        try {
            $query = "SELECT COALESCE(SUM(pf.montant), 0) as total
                     FROM paiement_fournisseur pf
                     INNER JOIN facture_fournisseur ff ON pf.Facture_fournisseur_idFacture_fournisseur = ff.idFacture_fournisseur
                     INNER JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
                     WHERE f.\"Structure_idStructure\" = :structureId 
                     AND pf.\"datePaiement\" BETWEEN :dateDebut AND :dateFin";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'structureId' => $structureId,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin
            ]);
            
            return floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        } catch (PDOException $e) {
            error_log("Erreur calcul paiements fournisseurs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
 * Obtient les détails des recettes
 */



 public function getDetailsRecettesParGroupe($userId, $structureId, $dateDebut, $dateFin)
 {
     try {
         // 1. Récupérer tous les groupes de recettes avec contrôle d'accès utilisateur
         $queryGroupes = "SELECT 
             gr.\"idGroupe_recette_structure\",
             gr.\"designationGR\" as nom_groupe,
             COALESCE(SUM(r.montantR), 0) as total_groupe
         FROM groupe_recette_structure gr
         INNER JOIN budget_recette_structure br 
             ON gr.\"Budget_recette_structure_idBudget_recette_structure\" = br.idBudget_recette_structure
         INNER JOIN user_budget_recette ubr 
             ON br.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
         LEFT JOIN ligne_recette_structure lr 
             ON gr.\"idGroupe_recette_structure\" = lr.Groupe_recette_structure_idGroupe_recette_structure
         LEFT JOIN recette_structure r 
             ON lr.idligne_recette_structure = r.ligne_recette_structure_idligne_recette_structure
             AND r.dateOperation BETWEEN :dateDebut AND :dateFin
         WHERE br.\"Structure_idStructure\" = :structureId
         AND ubr.\"idUser\" = :userId
         GROUP BY gr.\"idGroupe_recette_structure\", gr.\"designationGR\"
         ORDER BY gr.designationGR";
 
         $stmt = $this->db->prepare($queryGroupes);
         $stmt->execute([
             'structureId' => $structureId,
             'userId' => $userId,
             'dateDebut' => $dateDebut,
             'dateFin' => $dateFin
         ]);
         $groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
         // 2. Pour chaque groupe, récupérer ses lignes
         foreach ($groupes as &$groupe) {
             $queryLignes = "SELECT 
                 lr.idligne_recette_structure,
                 lr.designation as nom_ligne,
                 lr.codeLigne,
                 COALESCE(SUM(r.montantR), 0) as total_ligne
             FROM ligne_recette_structure lr
             LEFT JOIN recette_structure r 
                 ON lr.idligne_recette_structure = r.ligne_recette_structure_idligne_recette_structure
                 AND r.dateOperation BETWEEN :dateDebut AND :dateFin
             WHERE lr.Groupe_recette_structure_idGroupe_recette_structure = :groupeId
             GROUP BY lr.idligne_recette_structure, lr.designation, lr.codeLigne
             ORDER BY lr.codeLigne";
 
             $stmt = $this->db->prepare($queryLignes);
             $stmt->execute([
                 'dateDebut' => $dateDebut,
                 'dateFin' => $dateFin,
                 'groupeId' => $groupe['idGroupe_recette_structure']
             ]);
             $groupe['lignes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
             // 3. Pour chaque ligne, récupérer les opérations
             foreach ($groupe['lignes'] as &$ligne) {
                 $queryOperations = "SELECT 
                     r.dateOperation,
                     r.montantR as montant,
                     r.motif,
                     r.depositaire
                 FROM recette_structure r
                 WHERE r.ligne_recette_structure_idligne_recette_structure = :ligneId
                     AND r.dateOperation BETWEEN :dateDebut AND :dateFin
                 ORDER BY r.dateOperation";
 
                 $stmt = $this->db->prepare($queryOperations);
                 $stmt->execute([
                     'ligneId' => $ligne['idligne_recette_structure'],
                     'dateDebut' => $dateDebut,
                     'dateFin' => $dateFin
                 ]);
                 $ligne['operations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
             }
         }
 
         return $groupes;
     } catch (PDOException $e) {
         error_log("Erreur récupération détails recettes par groupe: " . $e->getMessage());
         throw $e;
     }
 }


 public function getDetailsDepensesParGroupe($userId, $structureId, $dateDebut, $dateFin)
 {
     try {
         // 1. Récupérer tous les groupes de dépenses avec contrôle d'accès utilisateur
         $queryGroupes = "SELECT 
             gd.\"idGroupe_depense_structure\",
             gd.\"designationGD\" as nom_groupe,
             COALESCE(SUM(d.montantD), 0) as total_groupe
         FROM groupe_depense_structure gd
         INNER JOIN budget_depense_structure bd 
             ON gd.\"Budget_depense_structure_idBudget_depense_structure\" = bd.idBudget_depense_structure
         INNER JOIN user_budget_depense ubd 
             ON bd.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
         LEFT JOIN ligne_depense_structure ld 
             ON gd.\"idGroupe_depense_structure\" = ld.Groupe_depense_structure_idGroupe_depense_structure
         LEFT JOIN depense_structure d 
             ON ld.idligne_depense_structure = d.ligne_depense_structure_idligne_depense_structure
             AND d.dateoperation BETWEEN :dateDebut AND :dateFin
         WHERE bd.\"Structure_idStructure\" = :structureId
         AND ubd.\"idUser\" = :userId
         GROUP BY gd.\"idGroupe_depense_structure\", gd.\"designationGD\"
         ORDER BY gd.designationGD";
 
         $stmt = $this->db->prepare($queryGroupes);
         $stmt->execute([
             'structureId' => $structureId,
             'userId' => $userId,
             'dateDebut' => $dateDebut,
             'dateFin' => $dateFin
         ]);
         $groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
         // 2. Pour chaque groupe, récupérer ses lignes
         foreach ($groupes as &$groupe) {
             $queryLignes = "SELECT 
                 ld.idligne_depense_structure,
                 ld.designation as nom_ligne,
                 ld.codeLigne,
                 COALESCE(SUM(d.montantD), 0) as total_ligne
             FROM ligne_depense_structure ld
             LEFT JOIN depense_structure d 
                 ON ld.idligne_depense_structure = d.ligne_depense_structure_idligne_depense_structure
                 AND d.dateoperation BETWEEN :dateDebut AND :dateFin
             WHERE ld.Groupe_depense_structure_idGroupe_depense_structure = :groupeId
             GROUP BY ld.idligne_depense_structure, ld.designation, ld.codeLigne
             ORDER BY ld.codeLigne";
 
             $stmt = $this->db->prepare($queryLignes);
             $stmt->execute([
                 'dateDebut' => $dateDebut,
                 'dateFin' => $dateFin,
                 'groupeId' => $groupe['idGroupe_depense_structure']
             ]);
             $groupe['lignes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
             // 3. Pour chaque ligne, récupérer les opérations
             foreach ($groupe['lignes'] as &$ligne) {
                 $queryOperations = "SELECT 
                     d.dateoperation as dateOperation,
                     d.montantD as montant,
                     d.motifD as motif,
                     d.beneficiaire
                 FROM depense_structure d
                 WHERE d.ligne_depense_structure_idligne_depense_structure = :ligneId
                     AND d.dateoperation BETWEEN :dateDebut AND :dateFin
                 ORDER BY d.dateoperation";
 
                 $stmt = $this->db->prepare($queryOperations);
                 $stmt->execute([
                     'ligneId' => $ligne['idligne_depense_structure'],
                     'dateDebut' => $dateDebut,
                     'dateFin' => $dateFin
                 ]);
                 $ligne['operations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
             }
         }
 
         return $groupes;
     } catch (PDOException $e) {
         error_log("Erreur récupération détails dépenses par groupe: " . $e->getMessage());
         throw $e;
     }
 }

 public function getJournauxByUserAccess($userId)
    {
        $query = "SELECT * FROM journaux j INNER JOIN structure s ON j.\"Structure_idStructure\"=s.\"idStructure\"
        INNER JOIN user_structure u ON u.\"idStructure\"=s.\"idStructure\" WHERE u.\"idUser\"='$userId'";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getEcrituresByJournalAndPeriod($journalId, $startDate, $endDate)
    {
        try {
            $query = "
                SELECT 
                    e.\"idEcriture\",
                    e.\"dateEcriture\", 
                    e.\"numeroPiece\", 
                    e.description AS libelle,
                    COALESCE(SUM(CASE WHEN ed.typeCompte = 'debit' THEN ed.montant ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN ed.typeCompte = 'credit' THEN ed.montant ELSE 0 END), 0) AS total_credit
                FROM ecriture e
                LEFT JOIN ecriture_detail ed ON e.\"idEcriture\" = ed.\"idEcriture\"
                WHERE e.\"Journaux_idJournaux\" = :journalId
                AND e.\"dateEcriture\" BETWEEN :startDate AND :endDate
                GROUP BY e.\"idEcriture\", e.\"dateEcriture\", e.\"numeroPiece\", e.description
                ORDER BY e.\"dateEcriture\" ASC, e.\"numeroPiece\" ASC";
    
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'journalId' => $journalId,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
    
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération écritures: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve details for a given journal entry.
     */
    public function getDetailsByEcritureId($ecritureId)
{
    try {
        $query = "
            SELECT 
                ed.compteId AS compte, 
                c.intituleCompte AS intitule,
                CASE WHEN ed.typeCompte = 'debit' THEN ed.montant ELSE 0 END AS debit,
                CASE WHEN ed.typeCompte = 'credit' THEN ed.montant ELSE 0 END AS credit
            FROM ecriture_detail ed
            JOIN compte c ON ed.compteId = c.numeroCompte
            WHERE ed.\"idEcriture\" = :ecritureId";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['ecritureId' => $ecritureId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération détails écriture: " . $e->getMessage());
        throw $e;
    }
}

public function getReportPeriodBalances($journalId, $startDate)
{
    try {
        $query = "
            SELECT 
                COALESCE(SUM(CASE WHEN ed.typeCompte = 'debit' THEN ed.montant ELSE 0 END), 0) AS report_debit,
                COALESCE(SUM(CASE WHEN ed.typeCompte = 'credit' THEN ed.montant ELSE 0 END), 0) AS report_credit
            FROM ecriture e
            LEFT JOIN ecriture_detail ed ON e.\"idEcriture\" = ed.\"idEcriture\"
            WHERE e.\"Journaux_idJournaux\" = :journalId
            AND e.\"dateEcriture\" < :startDate";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'journalId' => $journalId,
            'startDate' => $startDate
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur calcul solde report: " . $e->getMessage());
        throw $e;
    }
}

public function getComptesByUserAccess($userId)
    {
        $query = "
            SELECT c.*
            FROM compte c
            INNER JOIN structure s ON c.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId
            ORDER BY c.intituleCompte ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


public function getReportPeriodBalancesByCompte($compteId, $startDate)
{
    try {
        $query = "
            SELECT 
                COALESCE(SUM(CASE WHEN ed.typeCompte = 'debit' THEN ed.montant ELSE 0 END), 0) AS report_debit,
                COALESCE(SUM(CASE WHEN ed.typeCompte = 'credit' THEN ed.montant ELSE 0 END), 0) AS report_credit
            FROM ecriture_detail ed
            JOIN ecriture e ON ed.\"idEcriture\" = e.\"idEcriture\"
            WHERE ed.compteId = (SELECT numeroCompte FROM compte WHERE idCompte = :compteId)
            AND e.\"dateEcriture\" < :startDate
            UNION ALL
            SELECT 
                COALESCE(SUM(ja.montant_debit), 0) AS report_debit,
                COALESCE(SUM(ja.montant_credit), 0) AS report_credit
            FROM journal_automatique ja
            WHERE ja.compte = (SELECT numeroCompte FROM compte WHERE idCompte = :compteId)
            AND ja.dateOperation < :startDate
            AND ja.\"Structure_idStructure\" = (SELECT \"Structure_idStructure\" FROM compte WHERE idCompte = :compteId)
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'compteId' => $compteId,
            'startDate' => $startDate
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportDebit = 0;
        $reportCredit = 0;
        foreach ($results as $result) {
            $reportDebit += $result['report_debit'];
            $reportCredit += $result['report_credit'];
        }

        return ['report_debit' => $reportDebit, 'report_credit' => $reportCredit];
    } catch (PDOException $e) {
        error_log("Erreur calcul solde report: " . $e->getMessage());
        throw $e;
    }
}

public function getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate)
{
    try {
        $query = "
            SELECT 
                e.\"dateEcriture\" AS date,
                e.description AS libelle,
                CASE WHEN ed.typeCompte = 'debit' THEN ed.montant ELSE 0 END AS debit,
                CASE WHEN ed.typeCompte = 'credit' THEN ed.montant ELSE 0 END AS credit
            FROM ecriture_detail ed
            JOIN ecriture e ON ed.\"idEcriture\" = e.\"idEcriture\"
            WHERE ed.compteId = (SELECT numeroCompte FROM compte WHERE idCompte = :compteId)
            AND e.\"dateEcriture\" BETWEEN :startDate AND :endDate
            UNION ALL
            SELECT 
                ja.dateOperation AS date,
                ja.libele AS libelle,
                ja.montant_debit AS debit,
                ja.montant_credit AS credit
            FROM journal_automatique ja
            WHERE ja.compte = (SELECT numeroCompte FROM compte WHERE idCompte = :compteId)
            AND ja.dateOperation BETWEEN :startDate AND :endDate
            AND ja.\"Structure_idStructure\" = (SELECT \"Structure_idStructure\" FROM compte WHERE idCompte = :compteId)
            ORDER BY date ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'compteId' => $compteId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération transactions: " . $e->getMessage());
        throw $e;
    }
}

public function getComptesByStructure($structureId)
    {
        $query = "SELECT * FROM compte WHERE \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getStructuresByUserAccess($userId, $search = '', $limit = 150)
{
    $query = "
        SELECT s.*
        FROM structure s
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND s.designation LIKE :search";
    }

    $query .= " ORDER BY s.designation ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getCompteDetails($id)
    {
        $query = "SELECT c.*,s.* FROM compte as c INNER JOIN structure as s
        ON c.\"Structure_idStructure\"=s.\"idStructure\" WHERE c.idCompte = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }





}