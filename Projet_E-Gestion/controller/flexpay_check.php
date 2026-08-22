<?php
session_start();
require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/FlexPay.php';

header('Content-Type: application/json');

// Vérifier que l'étudiant est connecté
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit();
}

// Vérifier que la requête est en GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit();
}

try {
    // Historique des transactions de l'étudiant
    if (isset($_GET['action']) && $_GET['action'] === 'historique') {
        $flexpay = new FlexPay();
        $matricule = $_SESSION['student_matricule'];
        $transactions = $flexpay->getTransactionsByMatricule($matricule);

        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
        ]);
        exit();
    }

    $orderNumber = $_GET['order_number'] ?? null;

    if (!$orderNumber) {
        throw new Exception("Le numéro de commande est obligatoire.");
    }

    $flexpay = new FlexPay();

    // Vérifier que la transaction appartient bien à l'étudiant connecté
    $transaction = $flexpay->getTransactionByOrderNumber($orderNumber);

    if (!$transaction) {
        throw new Exception("Transaction introuvable.");
    }

    if ($transaction['matricule_etudiant'] !== $_SESSION['student_matricule']) {
        throw new Exception("Accès non autorisé à cette transaction.");
    }

    // Si la transaction est déjà finalisée, retourner le statut local
    if ($transaction['statut'] === 'reussi' || $transaction['statut'] === 'echoue') {
        // S'assurer que le paiement de travaux est enregistré
        // Détecter via devoir_id (plus fiable que nature qui peut être NULL si ENUM non mis à jour)
        if ($transaction['statut'] === 'reussi' && !empty($transaction['devoir_id'])) {
            $connexion = Connexion::getInstance()->getPDO();
            $txDevoirId = $transaction['devoir_id'];
            $txGroupeId = $transaction['groupe_id'] ?? null;
            $txMontant = $transaction['montant'];
            $txMode = $transaction['type_paiement'] ?? 'mobile_money';
            $txRef = $transaction['reference'];
            $txOrder = $transaction['order_number'];

            if ($txGroupeId) { // Groupe si groupe_id est défini
                $stmtMbr = $connexion->prepare("SELECT id_etudiant FROM membres_groupe_travail WHERE id_groupe = :g");
                $stmtMbr->bindParam(':g', $txGroupeId, PDO::PARAM_INT);
                $stmtMbr->execute();
                $membresEarlyReturn = $stmtMbr->fetchAll(PDO::FETCH_ASSOC);
                $nbMbr = count($membresEarlyReturn);
                $montantParMembre = $nbMbr > 0 ? round(floatval($txMontant) / $nbMbr, 2) : floatval($txMontant);
                foreach ($membresEarlyReturn as $m) {
                    $mId = $m['id_etudiant'];
                    $chkEr = $connexion->prepare("SELECT id_paiement FROM paiements_travaux WHERE id_devoir=:d AND id_etudiant=:e AND id_groupe=:g AND statut='reussi' LIMIT 1");
                    $chkEr->bindParam(':d', $txDevoirId, PDO::PARAM_INT);
                    $chkEr->bindParam(':e', $mId, PDO::PARAM_INT);
                    $chkEr->bindParam(':g', $txGroupeId, PDO::PARAM_INT);
                    $chkEr->execute();
                    if (!$chkEr->fetch()) {
                        $insEr = $connexion->prepare("INSERT INTO paiements_travaux (id_groupe,id_etudiant,id_devoir,montant,mode_paiement,reference_transaction,order_number_flexpay,statut,date_paiement,date_confirmation) VALUES(:g,:e,:d,:m,:mode,:ref,:ord,'reussi',NOW(),NOW())");
                        $insEr->bindParam(':g', $txGroupeId, PDO::PARAM_INT);
                        $insEr->bindParam(':e', $mId, PDO::PARAM_INT);
                        $insEr->bindParam(':d', $txDevoirId, PDO::PARAM_INT);
                        $insEr->bindParam(':m', $montantParMembre);
                        $insEr->bindParam(':mode', $txMode);
                        $insEr->bindParam(':ref', $txRef);
                        $insEr->bindParam(':ord', $txOrder);
                        $insEr->execute();
                    }
                }
                // Marquer groupe payé
                $connexion->prepare("UPDATE groupes_travail SET est_paye=1, montant_paye=:m, date_paiement=NOW(), reference_paiement=:r WHERE id_groupe=:g")->execute([':m'=>$txMontant,':r'=>$txRef,':g'=>$txGroupeId]);
            } else {
                // Individuel
                $stmtEtuEr = $connexion->prepare("SELECT idetudiant FROM etudiant WHERE matricule=:mat LIMIT 1");
                $stmtEtuEr->bindParam(':mat', $transaction['matricule_etudiant']);
                $stmtEtuEr->execute();
                $etuRowEr = $stmtEtuEr->fetch(PDO::FETCH_ASSOC);
                if ($etuRowEr) {
                    $etuIdEr = $etuRowEr['idetudiant'];
                    $chkEr = $connexion->prepare("SELECT id_paiement FROM paiements_travaux WHERE id_devoir=:d AND id_etudiant=:e AND statut='reussi' LIMIT 1");
                    $chkEr->bindParam(':d', $txDevoirId, PDO::PARAM_INT);
                    $chkEr->bindParam(':e', $etuIdEr, PDO::PARAM_INT);
                    $chkEr->execute();
                    if (!$chkEr->fetch()) {
                        $insEr = $connexion->prepare("INSERT INTO paiements_travaux (id_groupe,id_etudiant,id_devoir,montant,mode_paiement,reference_transaction,order_number_flexpay,statut,date_paiement,date_confirmation) VALUES(NULL,:e,:d,:m,:mode,:ref,:ord,'reussi',NOW(),NOW())");
                        $insEr->bindParam(':e', $etuIdEr, PDO::PARAM_INT);
                        $insEr->bindParam(':d', $txDevoirId, PDO::PARAM_INT);
                        $insEr->bindParam(':m', $txMontant);
                        $insEr->bindParam(':mode', $txMode);
                        $insEr->bindParam(':ref', $txRef);
                        $insEr->bindParam(':ord', $txOrder);
                        $insEr->execute();
                    }
                }
            }
        }

        // S'assurer que le paiement de frais académiques est enregistré si la transaction est réussie
        if ($transaction['statut'] === 'reussi' && !empty($transaction['affectation_frais_id'])) {
            $connexion = Connexion::getInstance()->getPDO();
            $checkStmt = $connexion->prepare("
                SELECT id FROM paiements_frais 
                WHERE affectation_id = :affectation_id 
                AND matricule_etudiant = :matricule 
                AND reference_externe = :reference
            ");
            $checkStmt->bindParam(':affectation_id', $transaction['affectation_frais_id']);
            $checkStmt->bindParam(':matricule', $transaction['matricule_etudiant']);
            $checkStmt->bindParam(':reference', $transaction['reference']);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertPaiemement = $connexion->prepare("
                    INSERT INTO paiements_frais (
                        affectation_id,
                        matricule_etudiant,
                        montant,
                        devise,
                        mode_paiement,
                        reference_externe,
                        date_valeur,
                        est_confirme,
                        date_confirmation
                    ) VALUES (
                        :affectation_id,
                        :matricule,
                        :montant,
                        :devise,
                        :mode_paiement,
                        :reference,
                        NOW(),
                        1,
                        NOW()
                    )
                ");
                
                $modePaiement = $transaction['type_paiement'] ?? 'Mobile Money';
                $insertPaiemement->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $insertPaiemement->bindParam(':matricule', $transaction['matricule_etudiant']);
                $insertPaiemement->bindParam(':montant', $transaction['montant']);
                $insertPaiemement->bindParam(':devise', $transaction['devise']);
                $insertPaiemement->bindParam(':mode_paiement', $modePaiement);
                $insertPaiemement->bindParam(':reference', $transaction['reference']);
                $insertPaiemement->execute();
                
                // Only update affectation_frais if it's a student-specific affectation (not a shared promotion one)
                $checkAffectationStmt = $connexion->prepare("
                    SELECT matricule_etudiant FROM affectation_frais WHERE id = :affectation_id
                ");
                $checkAffectationStmt->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                $checkAffectationStmt->execute();
                $affectationInfo = $checkAffectationStmt->fetch(PDO::FETCH_ASSOC);

                if ($affectationInfo && $affectationInfo['matricule_etudiant'] !== null) {
                    $updateAffectation = $connexion->prepare("
                        UPDATE affectation_frais 
                        SET montant_paye = COALESCE(montant_paye, 0) + :montant,
                            date_mise_a_jour = NOW()
                        WHERE id = :affectation_id
                    ");
                    $updateAffectation->bindParam(':montant', $transaction['montant']);
                    $updateAffectation->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                    $updateAffectation->execute();
                    
                    $checkTotalStmt = $connexion->prepare("
                        SELECT af.montant_specifique, f.montant as montant_frais, af.montant_paye
                        FROM affectation_frais af
                        LEFT JOIN frais f ON af.frais_id = f.id
                        WHERE af.id = :affectation_id
                    ");
                    $checkTotalStmt->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                    $checkTotalStmt->execute();
                    $infoAffectation = $checkTotalStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $montantTotal = !empty($infoAffectation['montant_specifique']) ? $infoAffectation['montant_specifique'] : $infoAffectation['montant_frais'];
                    $montantPaye = $infoAffectation['montant_paye'];
                    
                    if ($montantPaye >= $montantTotal) {
                        $updateStatut = $connexion->prepare("
                            UPDATE affectation_frais 
                            SET statut_paiement = 'payé',
                                date_mise_a_jour = NOW()
                            WHERE id = :affectation_id
                        ");
                        $updateStatut->bindParam(':affectation_id', $transaction['affectation_frais_id'], PDO::PARAM_INT);
                        $updateStatut->execute();
                    }
                }
            }
        }
        
        $messageErreur = '';
        if ($transaction['statut'] === 'echoue' && !empty($transaction['message_reponse'])) {
            $flexpayModel = new FlexPay();
            $messageErreur = $flexpayModel->getMessageErreurPaiement($transaction['message_reponse']);
        }
        echo json_encode([
            'success' => true,
            'statut' => $transaction['statut'],
            'message' => $transaction['statut'] === 'reussi' 
                ? 'Paiement confirmé avec succès.' 
                : ($messageErreur ?: 'Le paiement a échoué.'),
            'message_detaille' => $messageErreur,
            'transaction' => [
                'order_number' => $transaction['order_number'],
                'reference' => $transaction['reference'],
                'montant' => $transaction['montant'],
                'devise' => $transaction['devise'],
                'statut' => $transaction['statut'],
                'date_creation' => $transaction['date_creation'],
                'date_mise_a_jour' => $transaction['date_mise_a_jour'],
                'message_reponse' => $transaction['message_reponse'],
            ],
        ]);
        exit();
    }

    // Vérifier le statut auprès de FlexPay
    $reponseApi = $flexpay->verifierTransaction($orderNumber);

    $codeReponse = $reponseApi['code'] ?? null;
    $messageApi = $reponseApi['message'] ?? '';
    $nouveauStatut = $transaction['statut'];

    // Analyser le statut du paiement à partir de la réponse API
    // Priorité : échec > succès > en attente (pour éviter les faux positifs)
    $estPaiementEchoue = $flexpay->estPaiementEchoue($messageApi);
    $estPaiementReussi = $flexpay->estPaiementReussi($messageApi);
    $estPaiementEnAttente = $flexpay->estPaiementEnAttente($messageApi);

    if ($estPaiementEchoue) {
        $nouveauStatut = 'echoue';
    } elseif ($estPaiementReussi) {
        $nouveauStatut = 'reussi';
    } elseif ($estPaiementEnAttente) {
        $nouveauStatut = 'en_attente';
    } elseif ($codeReponse !== null && $codeReponse !== '0') {
        $nouveauStatut = 'echoue';
    } elseif ($codeReponse === '0') {
        // Code 0 sans message reconnu - garder en attente par défaut
        $nouveauStatut = 'en_attente';
    }

    // Mettre à jour le statut local si changé
    if ($nouveauStatut !== $transaction['statut']) {
        $flexpay->mettreAJourStatut($orderNumber, $nouveauStatut, $reponseApi);
        
        // Si le paiement est réussi, créer l'enregistrement dans paiements_frais ou paiements_travaux
        if ($nouveauStatut === 'reussi') {
            $connexion = Connexion::getInstance()->getPDO();
            
            // Récupérer les détails de la transaction pour le paiement
            $transactionComplete = $flexpay->getTransactionByOrderNumber($orderNumber);

            // --- Traitement paiement de travail pratique (individuel ou groupe) ---
            // Détecter via devoir_id (plus fiable que nature qui peut être NULL si ENUM non mis à jour)
            if ($transactionComplete && !empty($transactionComplete['devoir_id'])) {
                $devoirId = $transactionComplete['devoir_id'];
                $matricule = $transactionComplete['matricule_etudiant'];
                $montant = $transactionComplete['montant'];
                $modePaiement = $transactionComplete['type_paiement'] ?? 'mobile_money';
                $groupeIdFromTx = $transactionComplete['groupe_id'] ?? null;

                if ($groupeIdFromTx) { // Groupe si groupe_id est défini
                    // Paiement en groupe : insérer un enregistrement pour CHAQUE membre du groupe
                    $stmtMembres = $connexion->prepare("SELECT mgt.id_etudiant FROM membres_groupe_travail mgt WHERE mgt.id_groupe = :groupe");
                    $stmtMembres->bindParam(':groupe', $groupeIdFromTx, PDO::PARAM_INT);
                    $stmtMembres->execute();
                    $membres = $stmtMembres->fetchAll(PDO::FETCH_ASSOC);
                    $nbMembres = count($membres);
                    $montantParMembre = $nbMembres > 0 ? round(floatval($montant) / $nbMembres, 2) : floatval($montant);

                    foreach ($membres as $membre) {
                        $membreId = $membre['id_etudiant'];
                        // Vérifier doublon par étudiant
                        $checkDbl = $connexion->prepare("SELECT id_paiement FROM paiements_travaux WHERE id_devoir = :devoir AND id_etudiant = :etudiant AND id_groupe = :groupe AND statut = 'reussi' LIMIT 1");
                        $checkDbl->bindParam(':devoir', $devoirId, PDO::PARAM_INT);
                        $checkDbl->bindParam(':etudiant', $membreId, PDO::PARAM_INT);
                        $checkDbl->bindParam(':groupe', $groupeIdFromTx, PDO::PARAM_INT);
                        $checkDbl->execute();
                        if (!$checkDbl->fetch()) {
                            $ins = $connexion->prepare("INSERT INTO paiements_travaux (id_groupe, id_etudiant, id_devoir, montant, mode_paiement, reference_transaction, order_number_flexpay, statut, date_paiement, date_confirmation) VALUES (:groupe, :etudiant, :devoir, :montant, :mode, :reference, :order_num, 'reussi', NOW(), NOW())");
                            $ins->bindParam(':groupe', $groupeIdFromTx, PDO::PARAM_INT);
                            $ins->bindParam(':etudiant', $membreId, PDO::PARAM_INT);
                            $ins->bindParam(':devoir', $devoirId, PDO::PARAM_INT);
                            $ins->bindParam(':montant', $montantParMembre);
                            $ins->bindParam(':mode', $modePaiement);
                            $ins->bindParam(':reference', $transactionComplete['reference']);
                            $ins->bindParam(':order_num', $orderNumber);
                            $ins->execute();
                        }
                    }

                    // Marquer le groupe comme payé
                    $updateGroupe = $connexion->prepare("UPDATE groupes_travail SET est_paye = 1, montant_paye = :montant, date_paiement = NOW(), reference_paiement = :reference WHERE id_groupe = :groupe");
                    $updateGroupe->bindParam(':montant', $montant);
                    $updateGroupe->bindParam(':reference', $transactionComplete['reference']);
                    $updateGroupe->bindParam(':groupe', $groupeIdFromTx, PDO::PARAM_INT);
                    $updateGroupe->execute();

                } else {
                    // Paiement individuel : insérer un enregistrement pour le payeur
                    $stmtEtu = $connexion->prepare("SELECT idetudiant FROM etudiant WHERE matricule = :matricule LIMIT 1");
                    $stmtEtu->bindParam(':matricule', $matricule);
                    $stmtEtu->execute();
                    $etudiantRow = $stmtEtu->fetch(PDO::FETCH_ASSOC);
                    $etudiantId = $etudiantRow ? $etudiantRow['idetudiant'] : 0;

                    if ($etudiantId) {
                        $checkStmt = $connexion->prepare("SELECT id_paiement FROM paiements_travaux WHERE id_devoir = :devoir AND id_etudiant = :etudiant AND statut = 'reussi' LIMIT 1");
                        $checkStmt->bindParam(':devoir', $devoirId, PDO::PARAM_INT);
                        $checkStmt->bindParam(':etudiant', $etudiantId, PDO::PARAM_INT);
                        $checkStmt->execute();
                        if (!$checkStmt->fetch()) {
                            $ins = $connexion->prepare("INSERT INTO paiements_travaux (id_groupe, id_etudiant, id_devoir, montant, mode_paiement, reference_transaction, order_number_flexpay, statut, date_paiement, date_confirmation) VALUES (:groupe, :etudiant, :devoir, :montant, :mode, :reference, :order_num, 'reussi', NOW(), NOW())");
                            $nullGroupe = null;
                            $ins->bindParam(':groupe', $nullGroupe, PDO::PARAM_INT);
                            $ins->bindParam(':etudiant', $etudiantId, PDO::PARAM_INT);
                            $ins->bindParam(':devoir', $devoirId, PDO::PARAM_INT);
                            $ins->bindParam(':montant', $montant);
                            $ins->bindParam(':mode', $modePaiement);
                            $ins->bindParam(':reference', $transactionComplete['reference']);
                            $ins->bindParam(':order_num', $orderNumber);
                            $ins->execute();
                        }
                    }
                }
            }
            
            if ($transactionComplete && !empty($transactionComplete['affectation_frais_id'])) {
                $affectationId = $transactionComplete['affectation_frais_id'];
                $matricule = $transactionComplete['matricule_etudiant'];
                $montant = $transactionComplete['montant'];
                $devise = $transactionComplete['devise'];
                
                // Vérifier si un paiement n'existe pas déjà pour cette transaction
                $checkStmt = $connexion->prepare("
                    SELECT id FROM paiements_frais 
                    WHERE affectation_id = :affectation_id 
                    AND matricule_etudiant = :matricule 
                    AND reference_externe = :reference
                ");
                $checkStmt->bindParam(':affectation_id', $affectationId);
                $checkStmt->bindParam(':matricule', $matricule);
                $checkStmt->bindParam(':reference', $transactionComplete['reference']);
                $checkStmt->execute();
                
                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    // Insérer le paiement dans paiements_frais
                    $insertPaiemement = $connexion->prepare("
                        INSERT INTO paiements_frais (
                            affectation_id,
                            matricule_etudiant,
                            montant,
                            devise,
                            mode_paiement,
                            reference_externe,
                            date_valeur,
                            est_confirme,
                            date_confirmation
                        ) VALUES (
                            :affectation_id,
                            :matricule,
                            :montant,
                            :devise,
                            :mode_paiement,
                            :reference,
                            NOW(),
                            1,
                            NOW()
                        )
                    ");
                    
                    $modePaiement = $transactionComplete['type_paiement'] ?? 'Mobile Money';
                    $insertPaiemement->bindParam(':affectation_id', $affectationId, PDO::PARAM_INT);
                    $insertPaiemement->bindParam(':matricule', $matricule);
                    $insertPaiemement->bindParam(':montant', $montant);
                    $insertPaiemement->bindParam(':devise', $devise);
                    $insertPaiemement->bindParam(':mode_paiement', $modePaiement);
                    $insertPaiemement->bindParam(':reference', $transactionComplete['reference']);
                    $insertPaiemement->execute();
                    
                    // Only update affectation_frais if it's a student-specific affectation (not a shared promotion one)
                    $checkAffectationStmt = $connexion->prepare("
                        SELECT matricule_etudiant FROM affectation_frais WHERE id = :affectation_id
                    ");
                    $checkAffectationStmt->bindParam(':affectation_id', $affectationId, PDO::PARAM_INT);
                    $checkAffectationStmt->execute();
                    $affectationInfo = $checkAffectationStmt->fetch(PDO::FETCH_ASSOC);

                    if ($affectationInfo && $affectationInfo['matricule_etudiant'] !== null) {
                        // Mettre à jour le montant payé dans affectation_frais
                        $updateAffectation = $connexion->prepare("
                            UPDATE affectation_frais 
                            SET montant_paye = COALESCE(montant_paye, 0) + :montant,
                                date_mise_a_jour = NOW()
                            WHERE id = :affectation_id
                        ");
                        $updateAffectation->bindParam(':montant', $montant);
                        $updateAffectation->bindParam(':affectation_id', $affectationId, PDO::PARAM_INT);
                        $updateAffectation->execute();
                        
                        // Mettre à jour le statut de l'affectation si le paiement est complet
                        $checkTotalStmt = $connexion->prepare("
                            SELECT af.montant_specifique, f.montant as montant_frais, af.montant_paye
                            FROM affectation_frais af
                            LEFT JOIN frais f ON af.frais_id = f.id
                            WHERE af.id = :affectation_id
                        ");
                        $checkTotalStmt->bindParam(':affectation_id', $affectationId, PDO::PARAM_INT);
                        $checkTotalStmt->execute();
                        $infoAffectation = $checkTotalStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $montantTotal = !empty($infoAffectation['montant_specifique']) ? $infoAffectation['montant_specifique'] : $infoAffectation['montant_frais'];
                        $montantPaye = $infoAffectation['montant_paye'];
                        
                        if ($montantPaye >= $montantTotal) {
                            $updateStatut = $connexion->prepare("
                                UPDATE affectation_frais 
                                SET statut_paiement = 'payé',
                                    date_mise_a_jour = NOW()
                                WHERE id = :affectation_id
                            ");
                            $updateStatut->bindParam(':affectation_id', $affectationId, PDO::PARAM_INT);
                            $updateStatut->execute();
                        }
                    }
                }
            }
        }
    }

    $messages = [
        'en_attente' => 'Paiement en cours de traitement...',
        'reussi' => 'Paiement confirmé avec succès.',
        'echoue' => 'Le paiement a échoué.',
        'annule' => 'Le paiement a été annulé.',
    ];

    echo json_encode([
        'success' => true,
        'statut' => $nouveauStatut,
        'message' => $messages[$nouveauStatut] ?? 'Statut inconnu',
        'transaction' => [
            'order_number' => $transaction['order_number'],
            'reference' => $transaction['reference'],
            'montant' => $transaction['montant'],
            'devise' => $transaction['devise'],
            'statut' => $nouveauStatut,
            'date_creation' => $transaction['date_creation'],
        ],
    ]);

} catch (Exception $e) {
    error_log("FlexPay Check - Erreur: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
