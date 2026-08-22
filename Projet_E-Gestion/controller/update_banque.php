<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idBanque = isset($_POST['idBanque']) ? intval($_POST['idBanque']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $numeroCompte = isset($_POST['numeroCompte']) ? trim($_POST['numeroCompte']) : '';
    $solde = isset($_POST['solde']) ? floatval($_POST['solde']) : null;
    $compteId = isset($_POST['compteId']) ? intval($_POST['compteId']) : 0;

    if ($idBanque <= 0 || empty($designation) || empty($numeroCompte) || $compteId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.edit';
            });
        </script>";
        exit();
    }

    if ($banque->updateBanque($idBanque, $designation, $numeroCompte, $solde, $compteId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Banque mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la banque.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.edit';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/banque.edit");
    exit();
}
?>