<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idBudget = isset($_POST['idBudget']) ? intval($_POST['idBudget']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $annee = isset($_POST['annee']) ? trim($_POST['annee']) : '';
    $solde = isset($_POST['solde']) ? floatval($_POST['solde']) : 0.0;

    if ($idBudget <= 0 || empty($designation) || empty($annee) || $solde < 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
        exit();
    }

    // Use the model method to update the budget
    if ($structure->updateBudget($idBudget, $designation, $annee, $solde)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Budget mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du budget.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.depense.edit");
    exit();
}
?>