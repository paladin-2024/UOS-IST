<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $etatDeBesoinId = isset($_POST['etatId']) ? intval($_POST['etatId']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $quantite = isset($_POST['quantite']) ? floatval($_POST['quantite']) : 0;
    $prixUnitaire = isset($_POST['prixUnitaire']) ? floatval($_POST['prixUnitaire']) : 0;
    $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';

    if ($etatDeBesoinId <= 0 || empty($designation) || $quantite <= 0 || $prixUnitaire < 0) {
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

    // Calculate the total amount for the line
    $montantLigne = $quantite * $prixUnitaire;

    // Add the new line to the état de besoin
    $success = $structure->addLigneEtatBesoin($designation, $quantite, $prixUnitaire, $observation, $etatDeBesoinId);

    if ($success > 0) {
        // Update the total amount of the état de besoin
        $structure->updateEtatDeBesoinMontant($etatDeBesoinId, $montantLigne);

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Ligne ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
        
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la ligne.'
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