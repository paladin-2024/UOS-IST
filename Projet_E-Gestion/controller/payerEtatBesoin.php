<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etatId = $_POST['etatId'] ?? null;
    $bankId = $_POST['bankId'] ?? null;
    $beneficiaire = $_POST['beneficiaire'] ?? null;
    $userId = $_SESSION['id'] ?? null;

    if (!$etatId || !$bankId || !$userId) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Paramètres manquants pour le paiement.'
        ]);
        exit;
    }

    try {
        $structure = new Structure();
        $db = Connexion::getInstance()->getPDO();
        $db->beginTransaction();

        // Récupérer les détails de l'état de besoin
        $etatBesoin = $structure->getEtatDeBesoinById($etatId);
        
        if (!$etatBesoin) {
            throw new Exception('État de besoin non trouvé.');
        }

        if (!$etatBesoin['validation1']) {
            throw new Exception('Cet état de besoin n\'est pas encore validé.');
        }

        // Vérifier si l'état de besoin n'est pas déjà payé
        if ($etatBesoin['statut'] === 'Paye') {
            throw new Exception('Cet état de besoin a déjà été payé.');
        }

        // Récupérer les informations du compte bancaire
        $bankDetails = $structure->getBanksById($bankId);
        if (empty($bankDetails)) {
            throw new Exception('Informations bancaires non trouvées.');
        }

        // Récupérer les détails de la ligne de dépense
        $ligneDepense = $structure->getLignesDepenseById($etatBesoin['idLigne_depense']);
        if (empty($ligneDepense)) {
            throw new Exception('Ligne de dépense non trouvée.');
        }

        // Créer la dépense associée
        $success = $structure->addDepense(
            $etatBesoin['montant'],
            $etatBesoin['libelle'],
            $beneficiaire,
            date('Y-m-d'),
            $userId,
            $etatBesoin['idLigne_depense'],
            $bankId,
            $etatId
        );

        if (!$success) {
            throw new Exception('Erreur lors de la création de la dépense.');
        }

        // Mettre à jour le statut de l'état de besoin
        $query = "UPDATE etat_de_besoin SET statut = 'Paye' WHERE \"idEtat_de_besoin\" = :etatId";
        $stmt = $db->prepare($query);
        $stmt->execute(['etatId' => $etatId]);

        // Mettre à jour le solde de la banque
        $query = "UPDATE banque SET solde = solde - :montant WHERE idBanque = :bankId";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'montant' => $etatBesoin['montant'],
            'bankId' => $bankId
        ]);

        $db->commit();

        // Enregistrer l'opération dans le journal automatique
        // Écriture au débit (compte de charge)
        $structure->addJournalAutomatique(
            date('Y-m-d'),
            $ligneDepense[0]['numeroCompte'], // Compte de charge de la ligne de dépense
            $ligneDepense[0]['intituleCompte'],
            $etatBesoin['montant'],
            0,
            "Paiement état de besoin N°" . sprintf("EDB-%05d", $etatId),
            sprintf("EDB-%05d", $etatId),
            $etatBesoin['idStructure'],
            $userId
        );

        // Écriture au crédit (compte bancaire)
        $structure->addJournalAutomatique(
            date('Y-m-d'),
            $bankDetails[0]['numeroCompte'], // Compte bancaire
            $bankDetails[0]['designation'],
            0,
            $etatBesoin['montant'],
            "Paiement état de besoin N°" . sprintf("EDB-%05d", $etatId),
            sprintf("EDB-%05d", $etatId),
            $etatBesoin['idStructure'],
            $userId
        );

        echo json_encode([
            'status' => 'success',
            'message' => 'Paiement effectué avec succès.'
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Méthode non autorisée.'
    ]);
}