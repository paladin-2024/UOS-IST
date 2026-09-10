<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emailId = isset($_POST['idcouriels_recu']) ? intval($_POST['idcouriels_recu']) : 0;
    $commentaire = trim($_POST['commentaire'] ?? '');
    $userId = $_SESSION['id'] ?? 0;

    if ($emailId <= 0 || $commentaire === '' || !$userId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../reception/courriel.coment?id=" . intval($emailId) . "';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        $structure->addCourrielComment($emailId, $userId, $commentaire);

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Commentaire ajouté avec succès.'
            }).then(() => {
                window.location.href = '../reception/courriel.coment?id=" . intval($emailId) . "';
            });
        </script>";
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du commentaire: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/courriel.coment?id=" . intval($emailId) . "';
            });
        </script>";
    }
} else {
    header("Location: ../reception/courriel.list");
    exit();
}
