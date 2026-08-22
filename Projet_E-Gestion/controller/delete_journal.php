<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idJournaux = isset($_POST['idJournaux']) ? intval($_POST['idJournaux']) : 0;

    if ($idJournaux <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de journal invalide.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.edit';
            });
        </script>";
        exit();
    }

    if ($structure->deleteJournal($idJournaux)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Journal supprimé avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du journal.'
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