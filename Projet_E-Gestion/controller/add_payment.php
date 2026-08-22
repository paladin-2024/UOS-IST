<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$structure = new Structure();
$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idInvoice = isset($_POST['idInvoice']) ? intval($_POST['idInvoice']) : 0;
    $datePaiement = isset($_POST['datePaiement']) ? trim($_POST['datePaiement']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0.0;
    $libelle = isset($_POST['libelle']) ? trim($_POST['libelle']) : '';
    $depositaire = isset($_POST['depositaire']) ? trim($_POST['depositaire']) : '';
    $bankId = isset($_POST['bankId']) ? intval($_POST['bankId']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate required fields
    if ($idInvoice <= 0 || empty($datePaiement) || $montant <= 0 || $bankId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
        exit();
    }

    // Check for future date
    if (strtotime($datePaiement) > time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de paiement ne peut pas être dans le futur.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
        exit();
    }

    // Retrieve invoice details and total payments
    $invoice = $structure->getInvoiceById($idInvoice);
    $totalPaid = $structure->getTotalPaymentsForInvoice($idInvoice);

    if (!$invoice) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Facture introuvable.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
        exit();
    }

    $invoiceAmount = $invoice['montant'];
    $newTotalPaid = $totalPaid + $montant;

    // Check if the payment exceeds the invoice amount
    if ($newTotalPaid > $invoiceAmount) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le paiement dépasse le montant de la facture.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
        exit();
    }

    // Insert the new payment using the model
    if ($structure->addPayment($datePaiement, $montant, $libelle, $depositaire, $userId, $idInvoice, $bankId)) {
        // Update invoice status
        if ($newTotalPaid == $invoiceAmount) {
            $structure->updateInvoiceStatus($idInvoice, 'Paye');
        } elseif ($newTotalPaid > 0) {
            $structure->updateInvoiceStatus($idInvoice, 'Encours');
        }

        // Update bank account balance
        $banque->updateBankBalance($bankId, $montant);

        // Retrieve bank and client account details
        $bankAccount = $banque->getBanksById($bankId);
        $clientAccount = $structure->getClientById($invoice['Client_idClient']);

        if (!empty($bankAccount) && !empty($clientAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $clientAccountNumber = $clientAccount[0]['numeroCompte'];
            $clientAccountLabel = $clientAccount[0]['intituleCompte'];
            $structureId = $clientAccount[0]['Structure_idStructure'];

            // Create journal entries
            $structure->addJournalAutomatique($datePaiement, $bankAccountNumber, $bankAccountLabel, $montant, 0, $libelle, $invoice['numeroFacture'], $structureId,$userId);
            $structure->addJournalAutomatique($datePaiement, $clientAccountNumber, $clientAccountLabel, 0, $montant, $libelle, $invoice['numeroFacture'], $structureId,$userId);
        }

        $structure->logUserActivity(
            $_SESSION['id'],
            'paiement',
            $_POST['libelle'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
          );

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Paiement ajouté avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du paiement.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.client.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/paiement.facture.client.add");
    exit();
}
?>