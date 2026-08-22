<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codeLigne = isset($_POST['codeLigne']) ? trim($_POST['codeLigne']) : '';
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0.0;
    $solde = isset($_POST['solde']) ? floatval($_POST['solde']) : 0.0;
    $groupeDepenseId = isset($_POST['Groupe_depense_structure_idGroupe_depense_structure']) ? intval($_POST['Groupe_depense_structure_idGroupe_depense_structure']) : 0;
    $compteId = isset($_POST['Compte_idCompte']) ? intval($_POST['Compte_idCompte']) : 0;

    if (empty($codeLigne) || empty($designation) || $montant <= 0 || $solde < 0 || $groupeDepenseId <= 0 || $compteId <= 0) {
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

    // Insert the new ligne de dépense using the model method
    if ($structure->addLigneDepense($codeLigne, $designation, $montant, $solde, $groupeDepenseId, $compteId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Ligne de dépense ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../budget/budget.depense.groupe.edit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la ligne de dépense.'
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