<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idDepot = isset($_POST['idDepot']) ? intval($_POST['idDepot']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
    $typeDepot = isset($_POST['typeDepot']) ? trim($_POST['typeDepot']) : '';
    $structureId = isset($_POST['Structure_idStructure']) ? intval($_POST['Structure_idStructure']) : 0;
    $userId = $_SESSION['id'];

    // Validation des champs requis
    if (empty($designation) || empty($adresse) || empty($typeDepot) || $structureId <= 0 || $idDepot <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
        exit();
    }

    // Vérification des doublons (en excluant le dépôt actuel)
    if ($structure->checkDuplicateDepotEdit($designation, $structureId, $idDepot)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un autre dépôt avec cette désignation existe déjà dans cette structure.'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
        exit();
    }

    // Mise à jour du dépôt
    try {
        if ($structure->updateDepot($idDepot, $designation, $adresse, $typeDepot, $userId, $structureId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Dépôt modifié avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la modification du dépôt');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification du dépôt: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.add");
    exit();
}
?>