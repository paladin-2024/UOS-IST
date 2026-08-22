<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $banqueId = isset($_POST['banqueId']) ? intval($_POST['banqueId']) : 0;
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;

    if ($banqueId <= 0 || $userId <= 0) {
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

    // Add the user to the bank
    if ($banque->addUserToBanque($userId, $banqueId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Utilisateur ajouté à la banque avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/banque.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur à la banque.'
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