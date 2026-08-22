<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération et validation des données
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $depotId = isset($_POST['idDepot']) ? intval($_POST['idDepot']) : 0;

    // Validation des champs requis
    if ($userId <= 0 || $depotId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez sélectionner un utilisateur et un dépôt valides.'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
        exit();
    }

    try {
        // Tentative d'ajout de l'utilisateur au dépôt
        if ($structure->addUserToDepot($userId, $depotId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Utilisateur ajouté au dépôt avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout de l\'utilisateur au dépôt');
        }
    } catch (Exception $e) {
        // Gestion des erreurs
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'utilisateur au dépôt: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../logistique/depot.add");
    exit();
}
?>