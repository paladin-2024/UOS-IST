<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $idManifesteSortie = isset($_POST['idManifeste_sortie']) ? intval($_POST['idManifeste_sortie']) : 0;
    $dateOperation = isset($_POST['dateOperation']) ? trim($_POST['dateOperation']) : '';
    $referenceDocument = isset($_POST['referenceDocument']) ? trim($_POST['referenceDocument']) : '';
    $transporteur = isset($_POST['transporteur']) ? trim($_POST['transporteur']) : '';
    $depotId = isset($_POST['depotId']) ? intval($_POST['depotId']) : 0;
    $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';
    $clientId = isset($_POST['clientId']) ? intval($_POST['clientId']) : null;
    $userId = $_SESSION['id'];

    // Validate required fields
    if ($idManifesteSortie <= 0 || empty($dateOperation) || $depotId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->updateSortieDepot($idManifesteSortie, $dateOperation, $observation, $transporteur, $referenceDocument, $userId, $depotId, $clientId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Sortie de dépôt mise à jour avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.sortie.add';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la sortie: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.sortie.add");
    exit();
}
?>