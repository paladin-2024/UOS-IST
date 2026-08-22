<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$userId=$_SESSION['id'];

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idInvoice = isset($_POST['idInvoice']) ? intval($_POST['idInvoice']) : 0;

    if ($idInvoice <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant de facture invalide.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.edit';
            });
        </script>";
        exit();
    }

    // Reverse the journal entries for the supplier invoice
    if ($structure->reverseSupplierInvoiceJournalEntries($idInvoice,$userId)) {
        // Delete the supplier invoice using the model
        if ($structure->deleteSupplierInvoice($idInvoice)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Facture fournisseur supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../comptabilite/facture_frs.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression de la facture fournisseur.'
                }).then(() => {
                    window.location.href = '../comptabilite/facture_frs.edit';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'annulation des écritures comptables.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.edit';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/facture_frs.edit");
    exit();
}
?>