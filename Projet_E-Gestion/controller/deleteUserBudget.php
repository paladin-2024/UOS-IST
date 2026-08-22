<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $idUserBudget = intval($_GET['id']);

    if ($idUserBudget <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant invalide.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
        exit();
    }

    // Use the model method to delete the user from the budget
    if ($structure->deleteUserFromBudget($idUserBudget)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Utilisateur supprimé avec succès du budget.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'utilisateur du budget.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.depense.edit.php");
    exit();
}
?>