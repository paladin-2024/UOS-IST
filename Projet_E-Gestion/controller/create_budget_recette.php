<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $annee = isset($_POST['annee']) ? trim($_POST['annee']) : '';
    $solde_b_recette = isset($_POST['solde_b_recette']) ? floatval($_POST['solde_b_recette']) : 0.0;
    $structureId = isset($_POST['Structure_idStructure']) ? intval($_POST['Structure_idStructure']) : 0;

    // Assuming $userId is obtained from the session or authentication context
    $userId = $_SESSION['id']; // Example: Retrieve user ID from session

    if (empty($designation) || empty($annee) || $solde_b_recette <= 0 || $structureId <= 0) {
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

    // Check for duplicate designation within the same year and structure
    if ($structure->checkDuplicateBudgetRecette($designation, $annee, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation du budget de recette existe déjà pour cette année et cette structure.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.edit';
            });
        </script>";
        exit();
    }

    // Insert the new budget recette using the model method
    if ($structure->addBudgetRecette($designation, $annee, $solde_b_recette, $userId, $structureId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Budget de recette ajouté avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du budget de recette.'
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