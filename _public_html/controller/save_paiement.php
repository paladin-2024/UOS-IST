<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

// Vérifier l'authentification de l'utilisateur
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion et les données utilisateur
$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();
$journal = new JournalServeur();

// Récupérer l'ID de l'agent associé à l'utilisateur
$stmt = $connexion->prepare("SELECT idAgent FROM t_users WHERE idUser = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

// Vérifier la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Démarrer une transaction
        $connexion->beginTransaction();
        
        // Récupérer les données communes du paiement
        $action = $_POST['action'] ?? '';
        $matricule_etudiant = $_POST['matricule_etudiant'] ?? '';
        $montant = floatval($_POST['montant'] ?? 0);
        $date_valeur = $_POST['date_valeur'] ?? date('Y-m-d');
        $mode_paiement = $_POST['mode_paiement'] ?? '';
        $reference_externe = $_POST['reference_externe'] ?? '';
        $source_paiement = $_POST['source_paiement'] ?? '';
        $commentaire = $_POST['commentaire'] ?? 'Paiement Frais académiques';
        $affectation_id = intval($_POST['affectation_id'] ?? 0);
        $echelonnement_id = isset($_POST['echelonnement_id']) ? intval($_POST['echelonnement_id']) : null;
        
        // Validation de base
        if (empty($matricule_etudiant)) {
            throw new Exception('Le matricule de l\'étudiant est requis');
        }
        
        if ($montant <= 0) {
            throw new Exception('Le montant doit être supérieur à zéro');
        }
        
        if (empty($mode_paiement)) {
            throw new Exception('Le mode de paiement est requis');
        }
        
        if (empty($source_paiement)) {
            throw new Exception('La source du paiement est requise');
        }
        
        // Vérifier la référence externe pour certains modes de paiement
        if (in_array($mode_paiement, ['Chèque', 'Virement', 'Mobile Money']) && empty($reference_externe)) {
            throw new Exception('La référence externe est requise pour ce mode de paiement');
        }
        
        // Récupérer l'ID de la source (caisse ou compte bancaire) et sa devise
        $source_id = null;
        $devise = null;
        
        if ($source_paiement === 'Caisse') {
            $caisse_id = intval($_POST['caisse_id'] ?? 0);
            if ($caisse_id <= 0) {
                throw new Exception('Veuillez sélectionner une caisse valide');
            }
            $source_id = $caisse_id;
            
            // Récupérer la devise de la caisse
            $stmt = $connexion->prepare("SELECT devise FROM caisses WHERE id = :id");
            $stmt->bindParam(':id', $caisse_id);
            $stmt->execute();
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$caisse) {
                throw new Exception('Caisse non trouvée');
            }
            $devise = $caisse['devise'];
        } elseif ($source_paiement === 'Banque') {
            $compte_id = intval($_POST['compte_bancaire_id'] ?? 0);
            if ($compte_id <= 0) {
                throw new Exception('Veuillez sélectionner un compte bancaire valide');
            }
            $source_id = $compte_id;
            
            // Récupérer la devise du compte bancaire
            $stmt = $connexion->prepare("SELECT devise FROM comptes_bancaires WHERE id = :id");
            $stmt->bindParam(':id', $compte_id);
            $stmt->execute();
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$compte) {
                throw new Exception('Compte bancaire non trouvé');
            }
            $devise = $compte['devise'];
        } else {
            throw new Exception('Source de paiement non valide');
        }
        
        // Récupérer les informations de l'étudiant à partir du matricule
        $stmt = $connexion->prepare("
            SELECT idetudiant, promotion_idpromotion
            FROM etudiant 
            WHERE matricule = :matricule AND est_actif=1
        ");
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            throw new Exception('Étudiant non trouvé avec le matricule: ' . $matricule_etudiant);
        }
        
        $etudiant_id = $etudiant['idetudiant'];
        $promotion_id = $etudiant['promotion_idpromotion'];
        
        // Vérifier si l'affectation existe et est valide pour l'étudiant
        $stmt = $connexion->prepare("
            SELECT af.*, f.designation, f.devise AS frais_devise 
            FROM affectation_frais af
            INNER JOIN frais f ON af.frais_id = f.id
            WHERE af.id = :id AND 
                  (af.matricule_etudiant = :matricule OR 
                   (af.promotion_id = :promotion_id AND af.matricule_etudiant IS NULL))
        ");
        $stmt->bindParam(':id', $affectation_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->bindParam(':promotion_id', $promotion_id);
        $stmt->execute();
        $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affectation) {
            throw new Exception('Affectation de frais non trouvée ou non applicable pour cet étudiant');
        }
        
        // Vérifier la correspondance des devises
        $frais_devise = $affectation['devise'] ?? $affectation['frais_devise'];
        if ($devise !== $frais_devise) {
            throw new Exception('La devise du paiement (' . $devise . ') ne correspond pas à celle du frais (' . $frais_devise . ')');
        }
        
        // Déterminer le montant total du frais
        $montant_frais = $affectation['montant_specifique'] > 0 ? 
                         $affectation['montant_specifique'] : 
                         $connexion->query("SELECT montant FROM frais WHERE id = {$affectation['frais_id']}")->fetchColumn();
        
        // Déterminer le montant déjà payé
        $stmt = $connexion->prepare("
            SELECT COALESCE(SUM(montant), 0) as montant_paye
            FROM paiements_frais
            WHERE affectation_id = :affectation_id 
            AND matricule_etudiant = :matricule
            AND est_confirme = 1
        ");
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $montant_deja_paye = $stmt->fetchColumn();
        
        // Calculer le montant restant à payer
        $montant_restant = $montant_frais - $montant_deja_paye;
        
        if ($montant > $montant_restant) {
            throw new Exception(
                "Le montant payé ({$montant} {$devise}) ne peut pas être supérieur au montant restant ({$montant_restant} {$devise})."
            );
        }
        
        // Générer une référence de transaction unique
        $reference_transaction = 'TR' . date('YmdHis') . rand(1000, 9999);
        
        // Créer la transaction financière
        $stmt = $connexion->prepare("
            INSERT INTO transactions (
                reference, date_transaction, montant, devise, type,
                source, source_id, idUser, commentaire, statut, idAgent
            ) VALUES (
                :reference, :date_transaction, :montant, :devise, 'Recette',
                :source, :source_id, :idUser, :commentaire, 'Confirmée', :agent
            )
        ");
        
        $stmt->bindParam(':reference', $reference_transaction);
        $stmt->bindParam(':date_transaction', $date_valeur);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':source', $source_paiement);
        $stmt->bindParam(':source_id', $source_id);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':agent', $idAgent);
        $stmt->bindParam(':commentaire', $commentaire);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la création de la transaction');
        }
        
        $transaction_id = $connexion->lastInsertId();
        
        // Enregistrer le paiement dans la table paiements_frais
        $stmt = $connexion->prepare("
            INSERT INTO paiements_frais (
                transaction_id, etudiant_id, matricule_etudiant, affectation_id, 
                echelonnement_id, montant, devise, mode_paiement, reference_externe, 
                date_valeur, commentaire, est_confirme, date_confirmation, idConfirmateur
            ) VALUES (
                :transaction_id, :etudiant_id, :matricule_etudiant, :affectation_id,
                :echelonnement_id, :montant, :devise, :mode_paiement, :reference_externe,
                :date_valeur, :commentaire, 1, NOW(), :idConfirmateur
            )
        ");
        
        $stmt->bindParam(':transaction_id', $transaction_id);
        $stmt->bindParam(':etudiant_id', $etudiant_id);
        $stmt->bindParam(':matricule_etudiant', $matricule_etudiant);
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->bindParam(':echelonnement_id', $echelonnement_id);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':mode_paiement', $mode_paiement);
        $stmt->bindParam(':reference_externe', $reference_externe);
        $stmt->bindParam(':date_valeur', $date_valeur);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idConfirmateur', $idUser);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de l\'enregistrement du paiement');
        }
        
        $paiement_id = $connexion->lastInsertId();
        
        // Mettre à jour le montant payé et le statut dans la table affectation_frais
        $nouveau_montant_paye = $montant_deja_paye + $montant;
        $nouveau_montant_restant = $montant_frais - $nouveau_montant_paye;
        $nouveau_statut = ($nouveau_montant_paye >= $montant_frais) ? 'Complet' : 'Partiel';
        
        $stmt = $connexion->prepare("
            UPDATE affectation_frais 
            SET montant_paye = :montant_paye, 
                montant_restant = :montant_restant,
                statut_paiement = :statut_paiement,
                date_dernier_paiement = :date_paiement
            WHERE id = :id
        ");
        
        $stmt->bindParam(':montant_paye', $nouveau_montant_paye);
        $stmt->bindParam(':montant_restant', $nouveau_montant_restant);
        $stmt->bindParam(':statut_paiement', $nouveau_statut);
        $stmt->bindParam(':date_paiement', $date_valeur);
        $stmt->bindParam(':id', $affectation_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la mise à jour de l\'affectation');
        }
        
        // Si le paiement concerne une tranche, mettre à jour la table d'échelonnement
        if ($echelonnement_id) {
            $stmt = $connexion->prepare("
                INSERT INTO paiements_tranches (
                    echelonnement_id, paiement_id, montant, date_creation
                ) VALUES (
                    :echelonnement_id, :paiement_id, :montant, NOW()
                )
            ");
            
            $stmt->bindParam(':echelonnement_id', $echelonnement_id);
            $stmt->bindParam(':paiement_id', $paiement_id);
            $stmt->bindParam(':montant', $montant);
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de l\'enregistrement du paiement de tranche');
            }
        } else if ($affectation['est_echelonnable'] == 1 && $montant == $montant_frais) {
            // Paiement du montant total d'un frais échelonnable
            // Récupérer toutes les tranches et enregistrer le paiement global
            $stmt = $connexion->prepare("
                SELECT COUNT(*) as nombre_tranches
                FROM echelonnement_paiement
                WHERE affectation_id = :affectation_id
            ");
            $stmt->bindParam(':affectation_id', $affectation_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nombre_tranches = $result['nombre_tranches'] ?? 0;
            
            // Marquer ce paiement comme montant total
            $stmt = $connexion->prepare("
                UPDATE paiements_frais
                SET est_montant_total = 1
                WHERE id = :paiement_id
            ");
            $stmt->bindParam(':paiement_id', $paiement_id);
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la mise à jour du statut montant total');
            }
            
            // Enregistrer dans la table de suivi des paiements de montant total
            if ($nombre_tranches > 0) {
                $stmt = $connexion->prepare("
                    INSERT INTO paiements_montant_total (
                        paiement_id, affectation_id, montant_total, nombre_tranches_couvertes
                    ) VALUES (
                        :paiement_id, :affectation_id, :montant_total, :nombre_tranches
                    )
                ");
                $stmt->bindParam(':paiement_id', $paiement_id);
                $stmt->bindParam(':affectation_id', $affectation_id);
                $stmt->bindParam(':montant_total', $montant);
                $stmt->bindParam(':nombre_tranches', $nombre_tranches);
                
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de l\'enregistrement du paiement de montant total');
                }
            }
        }
        
        // Mettre à jour le solde de la caisse ou du compte bancaire
        if ($source_paiement === 'Caisse') {
            $stmt = $connexion->prepare("
                UPDATE caisses 
                SET solde_actuel = solde_actuel + :montant 
                WHERE id = :id
            ");
        } else {
            $stmt = $connexion->prepare("                UPDATE comptes_bancaires 
                SET solde_actuel = solde_actuel + :montant 
                WHERE id = :id
            ");
        }
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':id', $source_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la mise à jour du solde de la source de paiement');
        }
        
        // Pour les frais de promotion, créer ou mettre à jour le suivi individuel
        if ($affectation['promotion_id'] && !$affectation['matricule_etudiant']) {
            // Vérifier si le suivi existe déjà
            $stmt = $connexion->prepare("
                SELECT id FROM suivi_paiements_promotion
                WHERE affectation_id = :affectation_id AND matricule_etudiant = :matricule
            ");
            $stmt->bindParam(':affectation_id', $affectation_id);
            $stmt->bindParam(':matricule', $matricule_etudiant);
            $stmt->execute();
            $suivi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$suivi) {
                // Créer un nouveau suivi
                $stmt = $connexion->prepare("
                    INSERT INTO suivi_paiements_promotion (
                        affectation_id, etudiant_id, matricule_etudiant, 
                        montant_paye, montant_restant, statut_paiement, 
                        date_dernier_paiement, date_creation
                    ) VALUES (
                        :affectation_id, :etudiant_id, :matricule,
                        :montant_paye, :montant_restant, :statut_paiement,
                        :date_paiement, NOW()
                    )
                ");
                
                $stmt->bindParam(':affectation_id', $affectation_id);
                $stmt->bindParam(':etudiant_id', $etudiant_id);
                $stmt->bindParam(':matricule', $matricule_etudiant);
                $stmt->bindParam(':montant_paye', $nouveau_montant_paye);
                $stmt->bindParam(':montant_restant', $nouveau_montant_restant);
                $stmt->bindParam(':statut_paiement', $nouveau_statut);
                $stmt->bindParam(':date_paiement', $date_valeur);
                
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de la création du suivi de paiement');
                }
            } else {
                // Mettre à jour le suivi existant
                $stmt = $connexion->prepare("
                    UPDATE suivi_paiements_promotion 
                    SET montant_paye = :montant_paye, 
                        montant_restant = :montant_restant,
                        statut_paiement = :statut_paiement,
                        date_dernier_paiement = :date_paiement
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':montant_paye', $nouveau_montant_paye);
                $stmt->bindParam(':montant_restant', $nouveau_montant_restant);
                $stmt->bindParam(':statut_paiement', $nouveau_statut);
                $stmt->bindParam(':date_paiement', $date_valeur);
                $stmt->bindParam(':id', $suivi['id']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de la mise à jour du suivi de paiement');
                }
            }
        }
        
        // Mettre à jour la situation financière globale de l'étudiant
        // Récupérer l'année académique actuelle
        $stmt = $connexion->prepare("
            SELECT annee_acad_idannee_acad 
            FROM promotion 
            WHERE idpromotion = :promotion_id
        ");
        $stmt->bindParam(':promotion_id', $promotion_id);
        $stmt->execute();
        $annee_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $annee_acad_id = $annee_data['annee_acad_idannee_acad'] ?? null;
        
        if ($annee_acad_id) {
            // 1. Calculer le total dû - tous les frais affectés à l'étudiant et à sa promotion
            $stmt = $connexion->prepare("
                SELECT SUM(CASE 
                    WHEN af.montant_specifique > 0 THEN af.montant_specifique 
                    ELSE f.montant END) AS total_du
                FROM affectation_frais af
                JOIN frais f ON af.frais_id = f.id
                WHERE ((af.matricule_etudiant = :matricule) OR 
                      (af.promotion_id = :promotion_id AND af.matricule_etudiant IS NULL))
                  AND f.annee_acad_id = :annee_acad_id
                  AND af.est_exempte = 0
            ");
            $stmt->bindParam(':matricule', $matricule_etudiant);
            $stmt->bindParam(':promotion_id', $promotion_id);
            $stmt->bindParam(':annee_acad_id', $annee_acad_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_du = $result['total_du'] ?? 0;
            
            // 2. Calculer le total payé
            $stmt = $connexion->prepare("
                SELECT SUM(pf.montant) AS total_paye
                FROM paiements_frais pf
                WHERE pf.matricule_etudiant = :matricule
                  AND pf.est_confirme = 1
            ");
            $stmt->bindParam(':matricule', $matricule_etudiant);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_paye = $result['total_paye'] ?? 0;
            
            // Calculer le solde
            $solde = $total_du - $total_paye;
            
            // Vérifier si une situation financière existe déjà
            $stmt = $connexion->prepare("
                SELECT id FROM situation_financiere_etudiant
                WHERE etudiant_id = :etudiant_id AND annee_acad_id = :annee_acad_id
            ");
            $stmt->bindParam(':etudiant_id', $etudiant_id);
            $stmt->bindParam(':annee_acad_id', $annee_acad_id);
            $stmt->execute();
            $situation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($situation) {
                // Mettre à jour la situation existante
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
            } else {
                // Créer une nouvelle situation
                $stmt = $connexion->prepare("
                    INSERT INTO situation_financiere_etudiant (
                        etudiant_id, matricule_etudiant, annee_acad_id,
                        total_du, total_paye, solde, devise,
                        date_derniere_maj
                    ) VALUES (
                        :etudiant_id, :matricule, :annee_acad_id,
                        :total_du, :total_paye, :solde, :devise,
                        NOW()
                    )
                ");
                $stmt->bindParam(':etudiant_id', $etudiant_id);
                $stmt->bindParam(':matricule', $matricule_etudiant);
                $stmt->bindParam(':annee_acad_id', $annee_acad_id);
                $stmt->bindParam(':total_du', $total_du);
                $stmt->bindParam(':total_paye', $total_paye);
                $stmt->bindParam(':solde', $solde);
                $stmt->bindParam(':devise', $devise);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Erreur lors de la mise à jour de la situation financière');
            }
        }
        
        // Générer le numéro de reçu
        $numero_recu = 'RECU-' . date('Ymd') . '-' . $transaction_id;
        
        // Mettre à jour la transaction avec le numéro de reçu
        $stmt = $connexion->prepare("
            UPDATE transactions 
            SET reference = :recu_numero
            WHERE id = :id
        ");
        $stmt->bindParam(':recu_numero', $numero_recu);
        $stmt->bindParam(':id', $transaction_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la mise à jour du numéro de reçu de la transaction');
        }
        
        // Mettre à jour le paiement avec le numéro de reçu
        $stmt = $connexion->prepare("
            UPDATE paiements_frais 
            SET recu_numero = :recu_numero
            WHERE transaction_id = :id
        ");
        $stmt->bindParam(':recu_numero', $numero_recu);
        $stmt->bindParam(':id', $transaction_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de la mise à jour du numéro de reçu du paiement');
        }
        
        // Valider la transaction
        $connexion->commit();
        
        // Enregistrer le paiement dans le journal
        $description = "Paiement enregistré pour l'étudiant {$matricule_etudiant} - Frais: {$affectation['designation']} - Montant: {$montant} {$devise} - Mode: {$mode_paiement}";
        $donneeApres = [
            'paiement_id' => $paiement_id,
            'numero_recu' => $numero_recu,
            'matricule_etudiant' => $matricule_etudiant,
            'etudiant_id' => $etudiant_id,
            'affectation_id' => $affectation_id,
            'montant' => $montant,
            'devise' => $devise,
            'mode_paiement' => $mode_paiement,
            'reference_externe' => $reference_externe,
            'date_valeur' => $date_valeur,
            'source_paiement' => $source_paiement,
            'transaction_id' => $transaction_id
        ];
        
        $journal->enregistrerAction(
            'CREATION',
            'Paiements Frais Académiques',
            $description,
            $idUser,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'paiements_frais',
            $paiement_id,
            null,
            $donneeApres,
            'succes'
        );
        
        // Stocker les informations du paiement en session pour affichage
        $_SESSION['paiement_success'] = array(
            'id' => $paiement_id,
            'numero_recu' => $numero_recu,
            'montant' => $montant,
            'devise' => $devise,
            'frais' => $affectation['designation'],
            'etudiant' => $matricule_etudiant,
            'matricule' => $matricule_etudiant
        );
        
        // Rediriger vers la page de succès avec SweetAlert
        header('Location: ../?view=finance/paiements_etudiants&success=1&matricule=' . urlencode($matricule_etudiant));
        exit();
        
    } catch (Exception $e) {
         // Annuler la transaction en cas d'erreur
         if ($connexion->inTransaction()) {
             $connexion->rollBack();
         }
         
         // Journaliser l'erreur
         error_log('Erreur dans save_paiement.php: ' . $e->getMessage());
         
         // Enregistrer l'erreur de paiement dans le journal
         $description = "Erreur lors de l'enregistrement d'un paiement pour l'étudiant {$matricule_etudiant} - Montant tenté: {$montant} - Erreur: " . $e->getMessage();
         $journal->enregistrerAction(
             'CREATION',
             'Paiements Frais Académiques',
             $description,
             $idUser,
             $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
             'paiements_frais',
             null,
             null,
             null,
             'erreur',
             $e->getMessage()
         );
         
         // Stocker le message d'erreur
         $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
         $_SESSION['messageType'] = 'danger';
         
         // Rediriger avec le message d'erreur
         if (!empty($matricule_etudiant)) {
             header('Location: ../?view=finance/paiements_etudiants&matricule=' . urlencode($matricule_etudiant));
         } else {
             header('Location: ../?view=finance/paiements_etudiants');
         }
         exit();
     }
} else {
    // Accès direct au fichier sans soumission de formulaire
    header('Location: ../?view=finance/paiements_etudiants');
    exit();
}

