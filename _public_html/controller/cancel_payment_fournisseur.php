<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$userId=$_SESSION['id'];

$structureModel = new Structure();

if (isset($_GET['idPaiement'])) {
    $idPaiement = intval($_GET['idPaiement']);

    // Retrieve the original supplier payment details
    $payment = $structureModel->getSupplierPaymentById($idPaiement);
    if (!$payment) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Paiement fournisseur introuvable.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.fournisseur.edit';
                }
            });
        </script>";
        exit();
    }

    // Retrieve related supplier invoice details
    $invoice = $structureModel->getSupplierInvoiceById($payment['Facture_fournisseur_idFacture_fournisseur']);
    if (!$invoice) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Facture fournisseur introuvable.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.fournisseur.edit';
                }
            });
        </script>";
        exit();
    }

    // Retrieve supplier and bank account details
    $supplier = $structureModel->getSupplierById($invoice['Fournisseur_idFournisseur']);
    $bankDetails = $structureModel->getBanksById($payment['Banque_idBanque']);

    if (empty($supplier) || empty($bankDetails)) {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Détails du fournisseur ou de la banque introuvables.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.fournisseur.edit';
                }
            });
        </script>";
        exit();
    }

    $compteSupplier = $supplier['numeroCompte'];
    $libelleCompteSupplier = $supplier['intituleCompte'];
    $bankAccountNumber = $bankDetails[0]['numeroCompte'];
    $bankAccountLabel = $bankDetails[0]['designation'];
    $structureId = $supplier['Structure_idStructure'];

    // Create the journal entry for the payment
    $dateOperation = date('Y-m-d');
    $libele = "Annulation Paiement Fournisseur: " . $payment['libelle'];
    $numPiece = $invoice['numeroFacture'];

    // Supplier's account entry
    $structureModel->addJournalAutomatique($dateOperation, $compteSupplier, $libelleCompteSupplier, 0, $payment['montant'], $libele, $numPiece, $structureId,$userId);

    // Bank account entry
    $structureModel->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, $payment['montant'], 0, $libele, $numPiece, $structureId,$userId);

    // Proceed with payment cancellation
    if ($structureModel->cancelPayment_fournisseur($idPaiement)) {
        // Add journal entries to reverse the payment
        echo "<script>
                Swal.fire({
                    title: 'Succès!',
                    text: 'Paiement fournisseur annulé avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../comptabilite/paiement.facture.fourni.edit';
                    }
                });
            </script>";
        
    } else {
        echo "<script>
            Swal.fire({
                title: 'Erreur!',
                text: 'Erreur lors de l\'annulation du paiement fournisseur.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../comptabilite/paiement.facture.fourni.edit';
                }
            });
        </script>";
    }
} else {
    header('Location: ../comptabilite/paiement.facture.fourni.edit');
    exit();
}