<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idInvoice = isset($_POST['idInvoice']) ? intval($_POST['idInvoice']) : 0;
    $numeroFacture = isset($_POST['numeroFacture']) ? trim($_POST['numeroFacture']) : '';
    $dateFacture = isset($_POST['dateFacture']) ? trim($_POST['dateFacture']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0.0;

    if ($idInvoice <= 0 || empty($numeroFacture) || empty($dateFacture) || $montant <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.edit';
            });
        </script>";
        exit();
    }

    // Update the supplier invoice using the model
    if ($structure->updateSupplierInvoice($idInvoice, $numeroFacture, $dateFacture, $montant)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Facture fournisseur mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la facture fournisseur.'
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