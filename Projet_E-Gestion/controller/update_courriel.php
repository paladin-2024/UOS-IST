<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emailId = isset($_POST['idcouriels_recu']) ? intval($_POST['idcouriels_recu']) : 0;
    $provenance = $_POST['provenance'] ?? '';
    $depositaire = $_POST['depositaire'] ?? '';
    $dateArrive = $_POST['dateArrive'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $userConcerne = $_POST['userConcerne'] ?? '';
    $objet = $_POST['objet'] ?? '';
    $resume = $_POST['resume'] ?? '';

    if ($emailId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de courriel invalide.'
            }).then(() => {
                window.location.href = '../reception/courriel.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        $structure->updateEmail($emailId, $provenance, $depositaire, $dateArrive, $serviceId, $userConcerne, $objet, $resume);

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Courriel mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../reception/courriel.add';
            });
        </script>";
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour du courriel: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/courriel.edit?id=" . intval($emailId) . "';
            });
        </script>";
    }
} else {
    header("Location: ../reception/courriel.add");
    exit();
}
