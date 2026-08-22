<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $numeroCompte = isset($_POST['numeroCompte']) ? trim($_POST['numeroCompte']) : '';
    $solde = isset($_POST['solde']) ? floatval($_POST['solde']) : null;
    $compteId = isset($_POST['compteId']) ? intval($_POST['compteId']) : 0;

    if (empty($designation) || empty($numeroCompte) || $compteId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.add';
            });
        </script>";
        exit();
    }

    // Add the bank
    if ($banque->addBanque($designation, $numeroCompte, $solde, $compteId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Banque ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la banque.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/banque.add");
    exit();
}
?>