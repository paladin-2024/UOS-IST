<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idGroupe = isset($_POST['idGroupe']) ? intval($_POST['idGroupe']) : 0;
    $designation = isset($_POST['nomGroupe']) ? trim($_POST['nomGroupe']) : '';
    $solde = isset($_POST['soldeGD']) ? floatval($_POST['soldeGD']) : 0.0;

    if ($idGroupe <= 0 || empty($designation) || $solde < 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
        exit();
    }

    // Use the model method to update the group
    if ($structure->updateGroupeDepense($idGroupe, $designation, $solde)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Groupe mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du groupe.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.depense.groupe.edit");
    exit();
}
?>