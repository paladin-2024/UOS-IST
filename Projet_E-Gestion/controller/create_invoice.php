<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clientId = isset($_POST['clientId']) ? intval($_POST['clientId']) : 0;
    $dateFacture = isset($_POST['dateFacture']) ? trim($_POST['dateFacture']) : '';
    $montantTTC = isset($_POST['montant']) ? floatval($_POST['montant']) : 0.0; // Montant Toutes Taxes Comprises
    $motif = isset($_POST['motif']) ? trim($_POST['motif']) : '';
    $numeroFacture = isset($_POST['numeroFacture']) ? trim($_POST['numeroFacture']) : '';
    $compteProduit = isset($_POST['compteId']) ? trim($_POST['compteId']) : '';
    $statut = 'Non Paye'; // Default status
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    

    // Define VAT rate
    $tauxTVA = 0.16; // 16% VAT

    // Calculate montantHT and montantTVA
    $montantHT = $montantTTC / (1 + $tauxTVA);
    $montantTVA = $montantTTC - $montantHT;

    // Validate required fields
    if ($clientId <= 0 || empty($dateFacture) || $montantTTC <= 0 || empty($numeroFacture)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_cl.add';
            });
        </script>";
        exit();
    }

    // Check for future date
    if (strtotime($dateFacture) > time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de la facture ne peut pas être dans le futur.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_cl.add';
            });
        </script>";
        exit();
    }

    // Check for duplicate invoice number within the same structure
    if ($structure->checkDuplicateInvoice($numeroFacture, $clientId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une facture avec le même numéro existe déjà pour ce client.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_cl.add';
            });
        </script>";
        exit();
    }

    // Insert the new invoice using the model
    if ($structure->addInvoice($dateFacture, $montantTTC, $motif, $numeroFacture, $statut, $userId, $clientId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Facture ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_cl.add';
            });
        </script>";

        // Retrieve client data
        $client = $structure->getClientById($clientId);
        if (!empty($client)) {
            $compteClient = $client[0]['numeroCompte'];
            $libelleCompteClient = $client[0]['intituleCompte']; // Assuming 'intituleCompte' is available
            $structure_cl = $client[0]['Structure_idStructure'];

            //Récupération des données du compte
            $cmpt=$structure->getCompteById($compteProduit);

            $compteCredit = $cmpt['numeroCompte']; // Sales account
            $libelleCompteCredit = $cmpt['intituleCompte'];
            $compteTVA = "44571"; // VAT account
            $libelleCompteTVA = "TVA Collectée"; // Example label for VAT account

            // Record the journal entry
            $dateOperation = date('Y-m-d');
            $libele = "Facture client: $numeroFacture";
            $numPiece = $numeroFacture;

            // Debit the client's account with the total amount
            $structure->addJournalAutomatique($dateOperation, $compteClient, $libelleCompteClient, $montantTTC, 0, $libele, $numPiece, $structure_cl,$userId);
            // Credit the sales account with the amount excluding VAT
            $structure->addJournalAutomatique($dateOperation, $compteCredit, $libelleCompteCredit, 0, $montantHT, $libele, $numPiece, $structure_cl,$userId);
            // Credit the VAT account with the VAT amount
            $structure->addJournalAutomatique($dateOperation, $compteTVA, $libelleCompteTVA, 0, $montantTVA, $libele, $numPiece, $structure_cl,$userId);
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la récupération des données du client.'
                }).then(() => {
                    window.location.href = '../comptabilite/facture_cl.add';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la facture.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_cl.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/facture_cl.add");
    exit();
}
?>