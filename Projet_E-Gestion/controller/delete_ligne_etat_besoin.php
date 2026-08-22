<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $ligneId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $etatDeBesoinId = isset($_GET['etatId']) ? intval($_GET['etatId']) : 0;

    if ($ligneId <= 0 || $etatDeBesoinId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant de ligne ou d\'état de besoin invalide.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }

    // Retrieve the line details to adjust the total amount
    $ligneDetails = $structure->getLigneEtatBesoinById($ligneId);
    if (!$ligneDetails) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Ligne non trouvée.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }

    $montantLigne = $ligneDetails['quantite'] * $ligneDetails['prixUnitaire'];

    // Delete the line from the état de besoin
    $success = $structure->deleteLigneEtatBesoin($ligneId);

    if ($success) {
        // Update the total amount of the état de besoin
        $structure->updateEtatDeBesoinMontant($etatDeBesoinId, -$montantLigne);

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Ligne supprimée avec succès.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la ligne.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/etat_besoin.add");
    exit();
}
?>