<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $journalId = isset($_POST['journalId']) ? intval($_POST['journalId']) : 0;

    if ($userId > 0 && $journalId > 0) {
        $structure = new Structure();
        if ($structure->addUserToJournal($userId, $journalId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../comptabilite/journal.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de l\'ajout de l\'utilisateur.'
                }).then(() => {
                    window.location.href = '../comptabilite/journal.edit';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Données invalides.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.edit';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/journal.edit");
    exit();
}
?>