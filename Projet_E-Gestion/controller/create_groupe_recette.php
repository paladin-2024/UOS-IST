<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designationGR = isset($_POST['designationGR']) ? trim($_POST['designationGR']) : '';
    $soldeGR = isset($_POST['soldeGR']) ? floatval($_POST['soldeGR']) : 0.0;
    $budgetRecetteStructureId = isset($_POST['budgetRecetteStructureId']) ? intval($_POST['budgetRecetteStructureId']) : 0;

    if (empty($designationGR) || $soldeGR <= 0 || $budgetRecetteStructureId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.grp.edit';
            });
        </script>";
        exit();
    }

    // Check for duplicate designation within the same budget
    if ($structure->checkDuplicateGroupeRecette($designationGR, $budgetRecetteStructureId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation du groupe de recette existe déjà pour ce budget.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.grp.edit';
            });
        </script>";
        exit();
    }

    // Insert the new groupe recette using the model method
    if ($structure->addGroupeRecette($designationGR, $soldeGR, $budgetRecetteStructureId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Groupe de recette ajouté avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.grp.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du groupe de recette.'
            }).then(() => {
                window.location.href = '../budget/budget.recette.grp.edit';
            });
        </script>";
    }
} else {
    header("Location: ../budget/budget.recette.grp.edit");
    exit();
}
?>