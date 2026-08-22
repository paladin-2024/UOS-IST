<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $etatDeBesoinId = isset($_POST['idEtatDeBesoin']) ? intval($_POST['idEtatDeBesoin']) : 0;
    $libelle = isset($_POST['libelle']) ? trim($_POST['libelle']) : '';
    $ligneBudget = isset($_POST['ligneDepenseId']) ? trim($_POST['ligneDepenseId']) : 0;

    if ($etatDeBesoinId <= 0 || empty($libelle)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }

    // Update the état de besoin using the model method
    $success = $structure->updateEtatDeBesoin($etatDeBesoinId, $libelle,$ligneBudget);

    if ($success) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'État de besoin mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de l\'état de besoin.'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} else {
    header("Location: ../logistique/etat_besoin.add");
    exit();
}
?>