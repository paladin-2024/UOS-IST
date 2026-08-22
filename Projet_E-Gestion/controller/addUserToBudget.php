<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idBudget = isset($_POST['idBudgetRecette']) ? intval($_POST['idBudgetRecette']) : 0;
    $idUser = isset($_POST['userId']) ? intval($_POST['userId']) : 0;

    if ($idBudget <= 0 || $idUser <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.edit';
            });
        </script>";
        exit();
    }

    // Use the model method to add the user to the budget
    if ($structure->addUserToBudgetRecette($idUser, $idBudget)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Utilisateur ajouté avec succès au budget.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur au budget.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.recette.edit");
    exit();
}
?>