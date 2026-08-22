<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomJournal = isset($_POST['nomJournal']) ? trim($_POST['nomJournal']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $codeJournal = isset($_POST['codeJournal']) ? trim($_POST['codeJournal']) : '';
    $structureId = isset($_POST['structureId']) ? intval($_POST['structureId']) : 0;

    if (empty($nomJournal) || empty($codeJournal) || $structureId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.add';
            });
        </script>";
        exit();
    }

    // Check for duplicate journal code within the same structure
    if ($structure->checkDuplicateJournal($codeJournal, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le code du journal existe déjà pour cette structure.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.add';
            });
        </script>";
        exit();
    }

    if ($structure->addJournal($nomJournal, $description, $codeJournal, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Journal ajouté avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du journal.'
            }).then(() => {
                window.location.href = '../comptabilite/journal.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/journal.add");
    exit();
}
?>