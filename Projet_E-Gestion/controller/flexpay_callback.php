<?php
/**
 * Callback FlexPay
 * Reçoit les notifications de paiement de FlexPay
 * Appelé directement par le serveur FlexPay (pas de session requise)
 */
require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/FlexPay.php';

header('Content-Type: application/json');

try {
    // Lire le corps de la requête envoyée par FlexPay
    $input = json_decode(file_get_contents('php://input'), true);

    error_log("FlexPay Callback - Données reçues: " . json_encode($input));

    if (!$input) {
        throw new Exception("Données de callback invalides.");
    }

    $reference = $input['reference'] ?? null;
    $code = $input['code'] ?? null;
    $message = $input['message'] ?? '';
    $orderNumber = $input['orderNumber'] ?? null;

    if (!$orderNumber) {
        throw new Exception("Numéro de commande manquant dans le callback.");
    }

    $flexpay = new FlexPay();
    $connexion = Connexion::getInstance()->getPDO();

    // Rechercher la transaction par orderNumber
    $transaction = $flexpay->getTransactionByOrderNumber($orderNumber);

    if (!$transaction) {
        error_log("FlexPay Callback - Transaction introuvable pour orderNumber: " . $orderNumber);
        throw new Exception("Transaction introuvable.");
    }

    // Éviter de traiter un callback en double pour une transaction déjà finalisée
    if ($transaction['statut'] === 'reussi' || $transaction['statut'] === 'echoue') {
        echo json_encode(['status' => 'ok', 'message' => 'Transaction déjà traitée.']);
        exit();
    }

    // Déterminer si le paiement est réellement réussi en analysant le code ET le message
    // Note: FlexPay peut retourner code "0" avec un message d'échec ou en attente
    $estPaiementEnAttente = $flexpay->estPaiementEnAttente($message);
    $estPaiementEchoue = $flexpay->estPaiementEchoue($message);
    
    if ($estPaiementEnAttente) {
        // Le paiement est en attente de confirmation par l'utilisateur (saisie du PIN)
        // Ne pas modifier le statut, garder "en_attente"
        error_log("FlexPay Callback - Paiement en attente de confirmation pour orderNumber: " . $orderNumber);
        echo json_encode(['status' => 'ok', 'message' => 'Paiement en attente de confirmation.']);
    } elseif ($code === '0' && !$estPaiementEchoue) {
        // Paiement confirmé avec succès
        $flexpay->mettreAJourStatut($orderNumber, 'reussi', $input);

        // Créer l'entrée dans paiements_frais pour intégration avec le système existant
        if ($transaction['affectation_frais_id']) {
            $connexion->beginTransaction();
            try {
                error_log("FlexPay Callback - Début traitement paiement réussi pour orderNumber: " . $orderNumber . ", affectation_id: " . $transaction['affectation_frais_id']);
                
                // Récupérer l'etudiant_id depuis le matricule
                $stmtEtudiant = $connexion->prepare("
                    SELECT idetudiant FROM etudiant WHERE matricule = :matricule LIMIT 1
                ");
                $stmtEtudiant->bindParam(':matricule', $transaction['matricule_etudiant']);
                $stmtEtudiant->execute();
                $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
                $etudiantId = $etudiant ? $etudiant['idetudiant'] : 0;
                
                error_log("FlexPay Callback - Etudiant trouvé: " . $etudiantId);

                // Vérifier que l'affectation_frais existe
                $stmtAffectation = $connexion->prepare("
                    SELECT af.*, f.designation, f.montant as montant_frais, f.devise as devise_frais
                    FROM affectation_frais af
                    INNER JOIN frais f ON af.frais_id = f.id
                    WHERE af.id = :affectation_id
                ");
                $stmtAffectation->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $stmtAffectation->execute();
                $affectation = $stmtAffectation->fetch(PDO::FETCH_ASSOC);
                
                if (!$affectation) {
                    throw new Exception("Affectation frais introuvable avec ID: " . $transaction['affectation_frais_id']);
                }
                
                error_log("FlexPay Callback - Affectation trouvée: " . json_encode($affectation));

                // Créer une transaction financière
                $refTransaction = 'FP-' . date('Ymd') . '-' . uniqid();
                $descriptionPaiement = "Paiement FlexPay - " . $transaction['reference'];
                $modePaiement = $transaction['type_paiement'] === 'carte' ? 'Carte bancaire' : 'Mobile Money';

                $stmtTransaction = $connexion->prepare('
                    INSERT INTO transactions (
                        reference,
                        type,
                        montant,
                        devise,
                        date_transaction,
                        source,
                        source_id,
                        description,
                        statut,
                        "idAgent",
                        date_creation,
                        "idUser"
                    ) VALUES (
                        :reference,
                        \'Recette\',
                        :montant,
                        :devise,
                        NOW(),
                        \'Banque\',
                        1,
                        :description,
                        \'Confirmée\',
                        0,
                        NOW(),
                        0
                    )
                ');

                $stmtTransaction->bindParam(':reference', $refTransaction);
                $stmtTransaction->bindParam(':montant', $transaction['montant']);
                $stmtTransaction->bindParam(':devise', $transaction['devise']);
                $stmtTransaction->bindParam(':description', $descriptionPaiement);
                $stmtTransaction->execute();
                $transactionFinanceId = $connexion->lastInsertId();
                
                error_log("FlexPay Callback - Transaction financière créée ID: " . $transactionFinanceId);

                // Créer l'entrée dans paiements_frais
                $commentaire = "Paiement automatique FlexPay ({$modePaiement}) - Réf: " . $transaction['reference'];

                $stmtPaiement = $connexion->prepare("
                    INSERT INTO paiements_frais (
                        transaction_id,
                        etudiant_id,
                        matricule_etudiant,
                        affectation_id,
                        montant,
                        devise,
                        mode_paiement,
                        reference_externe,
                        date_valeur,
                        commentaire,
                        est_confirme,
                        date_confirmation
                    ) VALUES (
                        :transaction_id,
                        :etudiant_id,
                        :matricule_etudiant,
                        :affectation_id,
                        :montant,
                        :devise,
                        :mode_paiement,
                        :reference_externe,
                        NOW(),
                        :commentaire,
                        1,
                        NOW()
                    )
                ");

                $stmtPaiement->bindParam(':transaction_id', $transactionFinanceId, PDO::PARAM_INT);
                $stmtPaiement->bindParam(':etudiant_id', $etudiantId, PDO::PARAM_INT);
                $stmtPaiement->bindParam(':matricule_etudiant', $transaction['matricule_etudiant']);
                $stmtPaiement->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $stmtPaiement->bindParam(':montant', $transaction['montant']);
                $stmtPaiement->bindParam(':devise', $transaction['devise']);
                $stmtPaiement->bindParam(':mode_paiement', $modePaiement);
                $stmtPaiement->bindParam(':reference_externe', $orderNumber);
                $stmtPaiement->bindParam(':commentaire', $commentaire);
                $stmtPaiement->execute();

                // Mettre à jour le montant payé dans affectation_frais
                $stmtUpdate = $connexion->prepare("
                    UPDATE affectation_frais 
                    SET montant_paye = montant_paye + :montant,
                        date_dernier_paiement = NOW(),
                        statut_paiement = CASE 
                            WHEN (montant_paye + :montant2) >= COALESCE(montant_specifique, (SELECT montant FROM frais WHERE id = frais_id)) THEN 'Complet'
                            WHEN (montant_paye + :montant3) > 0 THEN 'Partiel'
                            ELSE statut_paiement
                        END
                    WHERE id = :affectation_id
                ");
                $stmtUpdate->bindParam(':montant', $transaction['montant']);
                $stmtUpdate->bindParam(':montant2', $transaction['montant']);
                $stmtUpdate->bindParam(':montant3', $transaction['montant']);
                $stmtUpdate->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Créer une déclaration de paiement automatiquement validée
                $refDeclaration = 'AUTO-FP-' . date('Ymd') . '-' . uniqid();
                $commentaireDeclaration = "Paiement automatique FlexPay ({$modePaiement}) - Réf: " . $transaction['reference'];
                
                $stmtDeclaration = $connexion->prepare("
                    INSERT INTO declarations_paiement (
                        affectation_id,
                        matricule_etudiant,
                        montant,
                        devise,
                        mode_paiement,
                        lieu_paiement,
                        reference_paiement,
                        date_paiement,
                        preuve_paiement,
                        commentaire,
                        date_declaration,
                        statut_validation,
                        date_validation,
                        valide_par,
                        commentaire_validation
                    ) VALUES (
                        :affectation_id,
                        :matricule_etudiant,
                        :montant,
                        :devise,
                        :mode_paiement,
                        :lieu_paiement,
                        :reference_paiement,
                        NOW(),
                        NULL,
                        :commentaire,
                        NOW(),
                        'validé',
                        NOW(),
                        0,
                        :commentaire_validation
                    )
                ");
                
                $stmtDeclaration->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $stmtDeclaration->bindParam(':matricule_etudiant', $transaction['matricule_etudiant']);
                $stmtDeclaration->bindParam(':montant', $transaction['montant']);
                $stmtDeclaration->bindParam(':devise', $transaction['devise']);
                $stmtDeclaration->bindParam(':mode_paiement', $modePaiement);
                $stmtDeclaration->bindParam(':lieu_paiement', $modePaiement);
                $stmtDeclaration->bindParam(':reference_paiement', $orderNumber);
                $stmtDeclaration->bindParam(':commentaire', $commentaireDeclaration);
                $stmtDeclaration->bindParam(':commentaire_validation', $commentaireDeclaration);
                $stmtDeclaration->execute();

                $connexion->commit();
            } catch (Exception $e) {
                $connexion->rollBack();
                error_log("FlexPay Callback - Erreur enregistrement paiement: " . $e->getMessage());
            }
        }

        error_log("FlexPay Callback - Paiement réussi pour orderNumber: " . $orderNumber);
        echo json_encode(['status' => 'ok', 'message' => 'Paiement enregistré avec succès.']);
    } else {
        // Paiement échoué - utiliser le message d'erreur détaillé
        $messageDetaille = $flexpay->getMessageErreurPaiement($message);
        $input['message_detaille'] = $messageDetaille;
        $flexpay->mettreAJourStatut($orderNumber, 'echoue', $input);
        
        error_log("FlexPay Callback - Paiement échoué pour orderNumber: " . $orderNumber . " - Code: " . $code . " - Message: " . $messageDetaille);
        echo json_encode(['status' => 'ok', 'message' => 'Échec du paiement enregistré.']);
    }

} catch (Exception $e) {
    error_log("FlexPay Callback - Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
