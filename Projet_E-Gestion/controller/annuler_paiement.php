<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

header('Content-Type: application/json');

// Vérifier l'authentification de l'utilisateur
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit();
}

// Vérifier la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();
$journal = new JournalServeur();

try {
    // Récupérer les données de la requête
    $paiement_id = intval($_POST['paiement_id'] ?? 0);
    $motif_annulation = trim($_POST['motif_annulation'] ?? '');
    
    if ($paiement_id <= 0) {
        throw new Exception('ID de paiement invalide');
    }
    
    if (empty($motif_annulation)) {
        throw new Exception('Le motif d\'annulation est obligatoire');
    }
    
    // Démarrer une transaction
    $connexion->beginTransaction();
    
    // 1. Récupérer les informations complètes du paiement avant annulation
    $stmt = $connexion->prepare("
        SELECT pf.*, 
               t.id AS transaction_id, t.source, t.source_id, t.montant AS transaction_montant, t.devise AS transaction_devise,
               af.frais_id, af.promotion_id, af.montant_specifique,
               f.designation AS frais_designation, f.montant AS frais_montant,
               e.idetudiant, e.noms AS etudiant_nom,
               p.annee_acad_idannee_acad
        FROM paiements_frais pf
        LEFT JOIN transactions t ON pf.transaction_id = t.id
        LEFT JOIN affectation_frais af ON pf.affectation_id = af.id
        LEFT JOIN frais f ON af.frais_id = f.id
        LEFT JOIN etudiant e ON pf.etudiant_id = e.idetudiant
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        WHERE pf.id = :paiement_id
    ");
    $stmt->bindParam(':paiement_id', $paiement_id);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paiement) {
        throw new Exception('Paiement non trouvé');
    }
    
    // Sauvegarder les données avant annulation pour le journal
    $donnees_avant = [
        'paiement_id' => $paiement['id'],
        'transaction_id' => $paiement['transaction_id'],
        'matricule_etudiant' => $paiement['matricule_etudiant'],
        'etudiant_nom' => $paiement['etudiant_nom'],
        'affectation_id' => $paiement['affectation_id'],
        'frais_designation' => $paiement['frais_designation'],
        'montant' => $paiement['montant'],
        'devise' => $paiement['devise'],
        'mode_paiement' => $paiement['mode_paiement'],
        'date_valeur' => $paiement['date_valeur'],
        'recu_numero' => $paiement['recu_numero'],
        'source' => $paiement['source'],
        'source_id' => $paiement['source_id']
    ];
    
    $matricule_etudiant = $paiement['matricule_etudiant'];
    $etudiant_id = $paiement['idetudiant'];
    $affectation_id = $paiement['affectation_id'];
    $montant_annule = floatval($paiement['montant']);
    $devise = $paiement['devise'];
    $transaction_id = $paiement['transaction_id'];
    $source = $paiement['source'];
    $source_id = $paiement['source_id'];
    $promotion_id = $paiement['promotion_id'];
    $annee_acad_id = $paiement['annee_acad_idannee_acad'];
    $echelonnement_id = $paiement['echelonnement_id'];
    
    // 2. Marquer le paiement comme annulé (soft delete)
    $stmt = $connexion->prepare("
        UPDATE paiements_frais 
        SET est_confirme = 0,
            commentaire = CONCAT(IFNULL(commentaire, ''), ' [ANNULÉ le ', NOW(), ' par utilisateur ', :idUser, ' - Motif: ', :motif, ']')
        WHERE id = :paiement_id
    ");
    $stmt->bindParam(':paiement_id', $paiement_id);
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':motif', $motif_annulation);
    
    if (!$stmt->execute()) {
        throw new Exception('Erreur lors de l\'annulation du paiement');
    }
    
    // 3. Annuler la transaction financière associée
    if ($transaction_id) {
        $stmt = $connexion->prepare("
            UPDATE transactions 
            SET statut = 'Annulée',
                commentaire = CONCAT(IFNULL(commentaire, ''), ' [ANNULÉ le ', NOW(), ' - Motif: ', :motif, ']')
            WHERE id = :transaction_id
        ");
        $stmt->bindParam(':transaction_id', $transaction_id);
        $stmt->bindParam(':motif', $motif_annulation);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de l\'annulation de la transaction');
        }
        
        // 4. Créer une transaction d'extourne (écriture inverse)
        $reference_extourne = 'EXT-' . date('YmdHis') . '-' . $transaction_id;
        $commentaire_extourne = "Extourne du paiement #" . $paiement_id . " - " . $motif_annulation;
        
        $stmt = $connexion->prepare("
            INSERT INTO transactions (
                reference, date_transaction, montant, devise, type,
                source, source_id, idUser, commentaire, statut
            ) VALUES (
                :reference, NOW(), :montant, :devise, 'Extourne',
                :source, :source_id, :idUser, :commentaire, 'Confirmée'
            )
        ");
        $stmt->bindParam(':reference', $reference_extourne);
        $stmt->bindParam(':montant', $montant_annule);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':source', $source);
        $stmt->bindParam(':source_id', $source_id);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':commentaire', $commentaire_extourne);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la création de l\'extourne');
        }
    }
    
    // 5. Recalculer les montants dans affectation_frais
    // Calculer le nouveau total payé (excluant le paiement annulé)
    $stmt = $connexion->prepare("
        SELECT COALESCE(SUM(montant), 0) as nouveau_total_paye
        FROM paiements_frais
        WHERE affectation_id = :affectation_id 
        AND matricule_etudiant = :matricule
        AND est_confirme = 1
    ");
    $stmt->bindParam(':affectation_id', $affectation_id);
    $stmt->bindParam(':matricule', $matricule_etudiant);
    $stmt->execute();
    $nouveau_total_paye = floatval($stmt->fetchColumn());
    
    // Récupérer le montant total du frais
    $montant_frais = $paiement['montant_specifique'] > 0 ? 
                     floatval($paiement['montant_specifique']) : 
                     floatval($paiement['frais_montant']);
    
    $nouveau_montant_restant = $montant_frais - $nouveau_total_paye;
    $nouveau_statut = ($nouveau_total_paye >= $montant_frais) ? 'Complet' : 
                      ($nouveau_total_paye > 0 ? 'Partiel' : 'Non payé');
    
    $stmt = $connexion->prepare("
        UPDATE affectation_frais 
        SET montant_paye = :montant_paye, 
            montant_restant = :montant_restant,
            statut_paiement = :statut_paiement
        WHERE id = :id
    ");
    $stmt->bindParam(':montant_paye', $nouveau_total_paye);
    $stmt->bindParam(':montant_restant', $nouveau_montant_restant);
    $stmt->bindParam(':statut_paiement', $nouveau_statut);
    $stmt->bindParam(':id', $affectation_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erreur lors de la mise à jour de l\'affectation');
    }
    
    // 6. Si c'est un paiement de tranche, mettre à jour l'échelonnement
    if ($echelonnement_id) {
        // Recalculer le montant payé pour cette tranche
        $stmt = $connexion->prepare("
            SELECT COALESCE(SUM(montant), 0) as tranche_payee
            FROM paiements_frais
            WHERE echelonnement_id = :echelonnement_id 
            AND matricule_etudiant = :matricule
            AND est_confirme = 1
        ");
        $stmt->bindParam(':echelonnement_id', $echelonnement_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $tranche_payee = floatval($stmt->fetchColumn());
        
        // Récupérer le montant de la tranche
        $stmt = $connexion->prepare("SELECT montant FROM echelonnements WHERE id = :id");
        $stmt->bindParam(':id', $echelonnement_id);
        $stmt->execute();
        $tranche_montant = floatval($stmt->fetchColumn());
        
        $tranche_statut = ($tranche_payee >= $tranche_montant) ? 'Complet' : 
                          ($tranche_payee > 0 ? 'Partiel' : 'Non payé');
        
        $stmt = $connexion->prepare("
            UPDATE echelonnements 
            SET montant_paye = :montant_paye,
                statut_paiement = :statut_paiement
            WHERE id = :id
        ");
        $stmt->bindParam(':montant_paye', $tranche_payee);
        $stmt->bindParam(':statut_paiement', $tranche_statut);
        $stmt->bindParam(':id', $echelonnement_id);
        $stmt->execute();
    }
    
    // 7. Mettre à jour le suivi de paiement pour les frais de promotion
    if ($promotion_id && empty($paiement['matricule_etudiant_affectation'])) {
        $stmt = $connexion->prepare("
            SELECT id FROM suivi_paiements_promotion
            WHERE affectation_id = :affectation_id AND matricule_etudiant = :matricule
        ");
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $suivi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($suivi) {
            $stmt = $connexion->prepare("
                UPDATE suivi_paiements_promotion 
                SET montant_paye = :montant_paye, 
                    montant_restant = :montant_restant,
                    statut_paiement = :statut_paiement
                WHERE id = :id
            ");
            $stmt->bindParam(':montant_paye', $nouveau_total_paye);
            $stmt->bindParam(':montant_restant', $nouveau_montant_restant);
            $stmt->bindParam(':statut_paiement', $nouveau_statut);
            $stmt->bindParam(':id', $suivi['id']);
            $stmt->execute();
        }
    }
    
    // 8. Recalculer la situation financière globale de l'étudiant
    if ($annee_acad_id && $etudiant_id) {
        // Calculer le total dû
        $stmt = $connexion->prepare("
            SELECT COALESCE(SUM(CASE 
                WHEN af.montant_specifique > 0 THEN af.montant_specifique 
                ELSE f.montant END), 0) AS total_du
            FROM affectation_frais af
            JOIN frais f ON af.frais_id = f.id
            JOIN etudiant e ON e.matricule = :matricule
            WHERE ((af.matricule_etudiant = :matricule2) OR 
                  (af.promotion_id = e.promotion_idpromotion AND af.matricule_etudiant IS NULL))
              AND f.annee_acad_id = :annee_acad_id
              AND af.est_exempte = 0
        ");
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->bindParam(':matricule2', $matricule_etudiant);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id);
        $stmt->execute();
        $total_du = floatval($stmt->fetchColumn());
        
        // Calculer le total payé (confirmé uniquement)
        $stmt = $connexion->prepare("
            SELECT COALESCE(SUM(montant), 0) AS total_paye
            FROM paiements_frais
            WHERE matricule_etudiant = :matricule
              AND est_confirme = 1
        ");
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $total_paye = floatval($stmt->fetchColumn());
        
        $solde = $total_du - $total_paye;
        
        // Mettre à jour ou créer la situation financière
        $stmt = $connexion->prepare("
            SELECT id FROM situation_financiere_etudiant
            WHERE etudiant_id = :etudiant_id AND annee_acad_id = :annee_acad_id
        ");
        $stmt->bindParam(':etudiant_id', $etudiant_id);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id);
        $stmt->execute();
        $situation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($situation) {
            $stmt = $connexion->prepare("
                UPDATE situation_financiere_etudiant
                SET total_du = :total_du,
                    total_paye = :total_paye,
                    solde = :solde,
                    date_derniere_maj = NOW()
                WHERE id = :id
            ");
            $stmt->bindParam(':total_du', $total_du);
            $stmt->bindParam(':total_paye', $total_paye);
            $stmt->bindParam(':solde', $solde);
            $stmt->bindParam(':id', $situation['id']);
            $stmt->execute();
        }
    }
    
    // Valider la transaction
    $connexion->commit();
    
    // Enregistrer dans le journal
    $donnees_apres = [
        'statut' => 'Annulé',
        'motif_annulation' => $motif_annulation,
        'date_annulation' => date('Y-m-d H:i:s'),
        'annule_par' => $idUser,
        'nouveau_montant_paye_affectation' => $nouveau_total_paye,
        'nouveau_statut_affectation' => $nouveau_statut
    ];
    
    $description = "Annulation du paiement #{$paiement_id} - Étudiant: {$matricule_etudiant} - Montant: {$montant_annule} {$devise} - Motif: {$motif_annulation}";
    
    $journal->enregistrerAction(
        'ANNULATION',
        'Paiements Frais Académiques',
        $description,
        $idUser,
        $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
        'paiements_frais',
        $paiement_id,
        $donnees_avant,
        $donnees_apres,
        'succes'
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Paiement annulé avec succès. Toutes les écritures comptables ont été extournées.'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    error_log('Erreur dans annuler_paiement.php: ' . $e->getMessage());
    
    // Journaliser l'erreur
    $journal->enregistrerAction(
        'ANNULATION',
        'Paiements Frais Académiques',
        "Échec d'annulation du paiement #{$paiement_id} - Erreur: " . $e->getMessage(),
        $idUser,
        $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
        'paiements_frais',
        $paiement_id ?? null,
        null,
        null,
        'erreur',
        $e->getMessage()
    );
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
