<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$userId=$_SESSION['id'];

$structureModel = new Structure();

if (isset($_GET['idPaiement'])) {
    $idPaiement = intval($_GET['idPaiement']);

    // Retrieve the original payment details
    $payment = $structureModel->getPaymentById($idPaiement);
    if (!$payment) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Paiement introuvable.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.client.edit';
                }
            });
        </script>";
        exit();
    }

    // Retrieve related invoice details
    $invoice = $structureModel->getInvoiceById($payment['Facture_client_idFacture_client']);
    if (!$invoice) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Facture introuvable.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.client.edit';
                }
            });
        </script>";
        exit();
    }

    // Retrieve client and bank account details
    $client = $structureModel->getClientById($invoice['Client_idClient']);
    $bankDetails = $structureModel->getBanksById($payment['Banque_idBanque']);

    if (empty($client) || empty($bankDetails)) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Détails du client ou de la banque introuvables.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.client.edit';
                }
            });
        </script>";
        exit();
    }

    $compteClient = $client[0]['numeroCompte'];
    $libelleCompteClient = $client[0]['intituleCompte'];
    $bankAccountNumber = $bankDetails[0]['numeroCompte'];
    $bankAccountLabel = $bankDetails[0]['designation'];
    $structureId = $client[0]['Structure_idStructure'];

    // Create the journal entry for the payment
    $dateOperation = date('Y-m-d');
    $libele = "Annulation Paiement: " . $payment['libelle'];
    $numPiece = $invoice['numeroFacture'];

    // Client's account entry
    $structureModel->addJournalAutomatique($dateOperation, $compteClient, $libelleCompteClient,$payment['montant'], 0,$libele, $numPiece, $structureId,$userId);


    // Bank account entry
    $structureModel->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, 0,$payment['montant'], $libele, $numPiece, $structureId,$userId);


    // Proceed with payment cancellation
    if ($structureModel->cancelPayment($idPaiement)) {
        // Add journal entries to reverse the payment
        echo "<script>
                Swal.fire({
                    title: 'Succès!',
                    text: 'Paiement annulé avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../comptabilite/paiement.facture.client.edit';
                    }
                });
            </script>";
        
    } else {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Erreur lors de l\'annulation du paiement.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.client.edit';
                }
            });
        </script>";
    }
} else {
    header('Location: ../comptabilite/paiement.facture.client.edit');
    exit();
}