<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/JournalServeur.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['message'] = "Vous devez être connecté pour effectuer cette action.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../index.php');
    exit();
}

$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];
$journal = new JournalServeur();

// Récupérer l'idAgent
$stmt = $connexion->prepare("SELECT idAgent FROM t_users WHERE idUser = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

try {
    $connexion->beginTransaction();

    $action = $_POST['action'] ?? '';
    $declaration_id = $_POST['declaration_id'] ?? 0;

    if (empty($action) || empty($declaration_id)) {
        throw new Exception("Paramètres manquants.");
    }

    // Récupérer les informations de la déclaration
    $stmt = $connexion->prepare("
        SELECT dp.*, 
               e.noms AS etudiant_nom,
               f.designation AS frais_designation,
               af.montant_specifique,
               f.montant AS montant_frais,
               f.devise AS devise_frais
        FROM declarations_paiement dp
        INNER JOIN etudiant e ON dp.matricule_etudiant = e.matricule
        INNER JOIN affectation_frais af ON dp.affectation_id = af.id
        INNER JOIN frais f ON af.frais_id = f.id
        WHERE dp.id = :declaration_id
    ");
    $stmt->bindParam(':declaration_id', $declaration_id);
    $stmt->execute();
    $declaration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$declaration) {
        throw new Exception("Déclaration introuvable.");
    }

    if ($declaration['statut_validation'] !== 'en_attente') {
        throw new Exception("Cette déclaration a déjà été traitée.");
    }

    if ($action === 'valider') {
        // Validation de la déclaration
        $montant = floatval($_POST['montant'] ?? 0);
        $date_valeur = $_POST['date_valeur'] ?? '';
        $mode_paiement = $_POST['mode_paiement'] ?? '';
        $reference_externe = $_POST['reference_externe'] ?? '';
        $source_paiement = $_POST['source_paiement'] ?? '';
        $caisse_id = $_POST['caisse_id'] ?? null;
        $compte_bancaire_id = $_POST['compte_bancaire_id'] ?? null;
        $commentaire = $_POST['commentaire'] ?? '';
        $affectation_id = $_POST['affectation_id'] ?? 0;
        $matricule_etudiant = $_POST['matricule_etudiant'] ?? '';
        $devise_frais = $_POST['devise_frais'] ?? 'USD';

        // Validations
        if ($montant <= 0) {
            throw new Exception("Le montant doit être supérieur à zéro.");
        }

        if (empty($date_valeur) || empty($mode_paiement) || empty($source_paiement)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        if ($source_paiement === 'Caisse' && empty($caisse_id)) {
            throw new Exception("Veuillez sélectionner une caisse.");
        }

        if ($source_paiement === 'Banque' && empty($compte_bancaire_id)) {
            throw new Exception("Veuillez sélectionner un compte bancaire.");
        }

        // Vérifier que le montant ne dépasse pas le reste à payer
        $montant_total_frais = $declaration['montant_specifique'] > 0 ? 
            $declaration['montant_specifique'] : $declaration['montant_frais'];
        
        $stmt = $connexion->prepare("
            SELECT COALESCE(SUM(montant), 0) AS total_paye 
            FROM paiements_frais 
            WHERE affectation_id = :affectation_id 
            AND matricule_etudiant = :matricule
        ");
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $montant_deja_paye = floatval($result['total_paye']);
        $montant_restant = $montant_total_frais - $montant_deja_paye;

        if ($montant > $montant_restant) {
            throw new Exception("Le montant à valider (" . number_format($montant, 2) . " " . $devise_frais . 
                              ") dépasse le montant restant à payer (" . number_format($montant_restant, 2) . " " . $devise_frais . ").");
        }

        // Vérifier la devise de la source
        if ($source_paiement === 'Caisse') {
            $stmt = $connexion->prepare("SELECT devise FROM caisses WHERE id = :caisse_id");
            $stmt->bindParam(':caisse_id', $caisse_id);
            $stmt->execute();
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($caisse['devise'] !== $devise_frais) {
                throw new Exception("La devise de la caisse ne correspond pas à celle du frais.");
            }
        } elseif ($source_paiement === 'Banque') {
            $stmt = $connexion->prepare("SELECT devise FROM comptes_bancaires WHERE id = :compte_id");
            $stmt->bindParam(':compte_id', $compte_bancaire_id);
            $stmt->execute();
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($compte['devise'] !== $devise_frais) {
                throw new Exception("La devise du compte bancaire ne correspond pas à celle du frais.");
            }
        }

        // Générer une référence unique pour la transaction
        $reference_transaction = 'TRX-' . date('YmdHis') . '-' . rand(1000, 9999);

        // Créer la transaction
        $stmt = $connexion->prepare("
            INSERT INTO transactions (reference, date_transaction, montant, devise, type, 
                                     source, source_id, idAgent, idUser, description)
            VALUES (:reference, :date_transaction, :montant, :devise, 'Recette', 
                    :source, :source_id, :idAgent, :idUser, :description)
        ");
        
        $description = "Paiement de frais - " . $declaration['frais_designation'] . 
                      " - Étudiant: " . $declaration['etudiant_nom'] . 
                      " (" . $matricule_etudiant . ") - Validation déclaration #" . $declaration_id;
        
        $source_id = $source_paiement === 'Caisse' ? $caisse_id : $compte_bancaire_id;
        
        $stmt->bindParam(':reference', $reference_transaction);
        $stmt->bindParam(':date_transaction', $date_valeur);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':devise', $devise_frais);
        $stmt->bindParam(':source', $source_paiement);
        $stmt->bindParam(':source_id', $source_id);
        $stmt->bindParam(':idAgent', $idAgent);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
        
        $transaction_id = $connexion->lastInsertId();

        // Enregistrer le paiement (sans date_paiement car elle n'existe pas dans la table)
        $stmt = $connexion->prepare("
            INSERT INTO paiements_frais (affectation_id, matricule_etudiant, montant, devise, 
                                        mode_paiement, reference_externe, transaction_id, 
                                        commentaire)
            VALUES (:affectation_id, :matricule, :montant, :devise, 
                    :mode_paiement, :reference_externe, :transaction_id, 
                    :commentaire)
        ");
        
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->bindParam(':matricule', $matricule_etudiant);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':devise', $devise_frais);
        $stmt->bindParam(':mode_paiement', $mode_paiement);
        $stmt->bindParam(':reference_externe', $reference_externe);
        $stmt->bindParam(':transaction_id', $transaction_id);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->execute();
        
        $paiement_id = $connexion->lastInsertId();

        // Mettre à jour le solde de la caisse ou du compte bancaire
        if ($source_paiement === 'Caisse') {
            $stmt = $connexion->prepare("UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :caisse_id");
            $stmt->bindParam(':montant', $montant);
            $stmt->bindParam(':caisse_id', $caisse_id);
            $stmt->execute();
        } elseif ($source_paiement === 'Banque') {
            $stmt = $connexion->prepare("UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :compte_id");
            $stmt->bindParam(':montant', $montant);
            $stmt->bindParam(':compte_id', $compte_bancaire_id);
            $stmt->execute();
        }

        // Mettre à jour le statut de la déclaration
        $stmt = $connexion->prepare("
            UPDATE declarations_paiement 
            SET statut_validation = 'validé',
                date_validation = NOW(),
                valide_par = :valide_par,
                commentaire_validation = :commentaire
            WHERE id = :declaration_id
        ");
        $stmt->bindParam(':valide_par', $idUser);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':declaration_id', $declaration_id);
        $stmt->execute();

        // Mettre à jour le statut de paiement de l'affectation
        $nouveau_total_paye = $montant_deja_paye + $montant;
        if ($nouveau_total_paye >= $montant_total_frais) {
            $nouveau_statut = 'Complet';
        } elseif ($nouveau_total_paye > 0) {
            $nouveau_statut = 'Partiel';
        } else {
            $nouveau_statut = 'Non payé';
        }

        $stmt = $connexion->prepare("
            UPDATE affectation_frais 
            SET statut_paiement = :statut 
            WHERE id = :affectation_id
        ");
        $stmt->bindParam(':statut', $nouveau_statut);
        $stmt->bindParam(':affectation_id', $affectation_id);
        $stmt->execute();

        $connexion->commit();
        
        // Enregistrer la validation dans le journal
        $description_log = "Validation de déclaration de paiement - Étudiant: {$declaration['etudiant_nom']} ({$matricule_etudiant}) - Frais: {$declaration['frais_designation']} - Montant: {$montant} {$devise_frais}";
        $donnee_apres = [
            'paiement_id' => $paiement_id,
            'transaction_id' => $transaction_id,
            'reference_transaction' => $reference_transaction,
            'matricule_etudiant' => $matricule_etudiant,
            'affectation_id' => $affectation_id,
            'montant' => $montant,
            'devise' => $devise_frais,
            'mode_paiement' => $mode_paiement,
            'source_paiement' => $source_paiement,
            'date_valeur' => $date_valeur,
            'statut_declaration' => 'validé',
            'date_validation' => date('Y-m-d H:i:s')
        ];
        
        $journal->enregistrerAction(
            'VALIDATION',
            'Déclarations de Paiements',
            $description_log,
            $idUser,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'declarations_paiement',
            $declaration_id,
            null,
            $donnee_apres,
            'succes'
        );

        $_SESSION['message'] = "Déclaration validée avec succès. Le paiement a été enregistré.";
        $_SESSION['messageType'] = "success";

    } elseif ($action === 'rejeter') {
        // Rejet de la déclaration
        $commentaire = $_POST['commentaire'] ?? '';

        if (empty($commentaire)) {
            throw new Exception("Veuillez indiquer le motif du rejet.");
        }

        // Mettre à jour le statut de la déclaration
        $stmt = $connexion->prepare("
            UPDATE declarations_paiement 
            SET statut_validation = 'rejeté',
                date_validation = NOW(),
                valide_par = :valide_par,
                commentaire_validation = :commentaire
            WHERE id = :declaration_id
        ");
        $stmt->bindParam(':valide_par', $idUser);
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':declaration_id', $declaration_id);
        $stmt->execute();

        $connexion->commit();
        
        // Enregistrer le rejet dans le journal
        $description_log = "Rejet de déclaration de paiement - Étudiant: {$declaration['etudiant_nom']} ({$declaration['matricule_etudiant']}) - Frais: {$declaration['frais_designation']} - Motif: {$commentaire}";
        $donnee_apres = [
            'statut_declaration' => 'rejeté',
            'date_validation' => date('Y-m-d H:i:s'),
            'motif_rejet' => $commentaire
        ];
        
        $journal->enregistrerAction(
            'REJET',
            'Déclarations de Paiements',
            $description_log,
            $idUser,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'declarations_paiement',
            $declaration_id,
            null,
            $donnee_apres,
            'succes'
        );

        $_SESSION['message'] = "Déclaration rejetée. L'étudiant a été notifié.";
        $_SESSION['messageType'] = "warning";

    } else {
        throw new Exception("Action non reconnue.");
    }

} catch (Exception $e) {
    $connexion->rollBack();
    
    // Enregistrer l'erreur dans le journal
    $journal->enregistrerAction(
        'ERREUR',
        'Déclarations de Paiements',
        "Erreur lors du traitement d'une déclaration: " . $e->getMessage(),
        $idUser,
        $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
        'declarations_paiement',
        $declaration_id ?? null,
        null,
        null,
        'erreur',
        $e->getMessage()
    );
    
    $_SESSION['message'] = "Erreur: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
    error_log("Erreur validation déclaration: " . $e->getMessage());
}

// Redirection
header('Location: ../index.php?view=finance/declarations_paiements_etudiants');
exit();
