<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dateElaboration = isset($_POST['dateElaboration']) ? trim($_POST['dateElaboration']) : '';
    $libelle = isset($_POST['libelle']) ? trim($_POST['libelle']) : '';
    $serviceId = isset($_POST['serviceId']) ? intval($_POST['serviceId']) : 0;
    $ligneDepenseId = isset($_POST['ligneDepenseId']) ? intval($_POST['ligneDepenseId']) : 0;

    // Assuming $userId is obtained from the session or authentication context
    $userId = $_SESSION['id']; // Example: Retrieve user ID from session

    if (empty($dateElaboration) || empty($libelle) || $serviceId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
        exit();
    }

    $montant=0.00;
    // Insert the new état de besoin using the model method
    $etatDeBesoinId = $structure->addEtatDeBesoin($dateElaboration, $libelle, $montant, $userId, $serviceId, $ligneDepenseId);

    if ($etatDeBesoinId) {
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'État de besoin ajouté avec succès.'
            }).then(() => {
                window.location.href = '../logistique/etat_besoin.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'état de besoin.'
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