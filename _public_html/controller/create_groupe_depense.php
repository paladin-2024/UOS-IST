<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designationGD = isset($_POST['designationGD']) ? trim($_POST['designationGD']) : '';
    $soldeGD = isset($_POST['soldeGD']) ? floatval($_POST['soldeGD']) : 0.0;
    $budgetDepenseStructureId = isset($_POST['budgetDepenseStructureId']) ? intval($_POST['budgetDepenseStructureId']) : 0;

    if (empty($designationGD) || $soldeGD <= 0 || $budgetDepenseStructureId <= 0) {
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

    // Check for duplicate designation within the same budget
    if ($structure->checkDuplicateGroupeDepense($designationGD, $budgetDepenseStructureId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation du groupe de dépense existe déjà pour ce budget.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
        exit();
    }

    // Check if adding this groupe depense exceeds the budget
    $currentTotal = $structure->getTotalGroupeDepense($budgetDepenseStructureId);
    $budgetLimit = $structure->getBudgetLimit($budgetDepenseStructureId);

    if (($currentTotal + $soldeGD) > $budgetLimit) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le montant total des groupes de dépense dépasse le budget alloué.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
        exit();
    }

    // Insert the new groupe depense using the model method
    if ($structure->addGroupeDepense($designationGD, $soldeGD, $budgetDepenseStructureId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Groupe de dépense ajouté avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du groupe de dépense.'
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