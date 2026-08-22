<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idLigne = isset($_POST['idLigne']) ? intval($_POST['idLigne']) : 0;
    $codeLigne = isset($_POST['codeLigne']) ? trim($_POST['codeLigne']) : '';
    $designation = isset($_POST['nomLigne']) ? trim($_POST['nomLigne']) : '';
    $montant = isset($_POST['montantLigne']) ? floatval($_POST['montantLigne']) : 0.0;

    if ($idLigne <= 0 || empty($codeLigne) || empty($designation) || $montant < 0) {
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

    // Use the model method to update the ligne de dépense
    if ($structure->updateLigneDepense($idLigne, $codeLigne, $designation, $montant)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Ligne de dépense mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la ligne de dépense.'
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