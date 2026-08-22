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
    $beneficiaire = isset($_POST['beneficiaire']) ? trim($_POST['beneficiaire']) : '';
    $modePaiement = isset($_POST['modePaiement']) ? trim($_POST['modePaiement']) : '';
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
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
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
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
            });
        </script>";
        exit();
    }

    // Retrieve invoice details and total payments
    $invoice = $structure->getSupplierInvoiceById($idInvoice);
    $totalPaid = $structure->getTotalSupplierPaymentsForInvoice($idInvoice);

    if (!$invoice) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Facture introuvable.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
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
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
            });
        </script>";
        exit();
    }

    // Insert the new payment using the model
    if ($structure->addSupplierPayment($datePaiement, $montant, $libelle, $beneficiaire, $modePaiement, $userId, $idInvoice, $bankId)) {
        // Update invoice status
        if ($newTotalPaid == $invoiceAmount) {
            $structure->updateSupplierInvoiceStatus($idInvoice, 'Paye');
        } elseif ($newTotalPaid > 0) {
            $structure->updateSupplierInvoiceStatus($idInvoice, 'Encours');
        }

        // Update bank account balance
        $banque->updateBankBalance($bankId, -$montant);

        // Retrieve bank and supplier account details
        $bankAccount = $banque->getBanksById($bankId);
        $supplierAccount = $structure->getSupplierById($invoice['Fournisseur_idFournisseur']);

        if (!empty($bankAccount) && !empty($supplierAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $supplierAccountNumber = $supplierAccount['numeroCompte'];
            $supplierAccountLabel = $supplierAccount['intituleCompte'];
            $structureId = $supplierAccount['Structure_idStructure'];

            
            // Ensure all necessary data is available before creating journal entries
            if ($bankAccountNumber && $bankAccountLabel && $supplierAccountNumber && $supplierAccountLabel && $structureId) {
                // Create journal entries
                $structure->addJournalAutomatique($datePaiement, $supplierAccountNumber, $supplierAccountLabel, $montant, 0, $libelle, $invoice['numeroFacture'], $structureId,$userId);
                $structure->addJournalAutomatique($datePaiement, $bankAccountNumber, $bankAccountLabel, 0, $montant, $libelle, $invoice['numeroFacture'], $structureId,$userId);

            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Données de compte manquantes pour l\'écriture du journal.'
                    }).then(() => {
                        window.location.href = '../comptabilite/paiement.facture.fourni.add';
                    });
                </script>";
                exit();
            }
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
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du paiement.'
            }).then(() => {
                window.location.href = '../comptabilite/paiement.facture.fourni.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/paiement.facture.fourni.add");
    exit();
}
?>