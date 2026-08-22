<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idJournaux = isset($_POST['idJournaux']) ? intval($_POST['idJournaux']) : 0;
    $nomJournal = isset($_POST['nomJournal']) ? trim($_POST['nomJournal']) : '';
    $codeJournal = isset($_POST['codeJournal']) ? trim($_POST['codeJournal']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $structureId = isset($_POST['structureId']) ? intval($_POST['structureId']) : 0;

    if ($idJournaux <= 0 || empty($nomJournal) || empty($codeJournal) || empty($description) || $structureId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.edit';
            });
        </script>";
        exit();
    }

    if ($structure->updateJournal($idJournaux, $nomJournal, $codeJournal, $description, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Journal mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du journal.'
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