<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $dateOperation = isset($_POST['dateOperation']) ? trim($_POST['dateOperation']) : '';
    $referenceDocument = isset($_POST['referenceDocument']) ? trim($_POST['referenceDocument']) : '';
    $transporteur = isset($_POST['transporteur']) ? trim($_POST['transporteur']) : '';
    $depotId = isset($_POST['depotId']) ? intval($_POST['depotId']) : 0;
    $observation = isset($_POST['observation']) ? intval($_POST['observation']) : '';
    $fournisseurId = isset($_POST['fournisseurId']) ? intval($_POST['fournisseurId']) : null;
    $userId = $_SESSION['id'];

    // Validation des champs requis
    if (empty($dateOperation) || $depotId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date d\'opération et le dépôt sont obligatoires.'
            }).then(() => {
                window.location.href = '../logistique/depot.entree.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->addEntreeDepot($dateOperation, $observation, $transporteur,$referenceDocument, $userId, $depotId, $fournisseurId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Entrée en dépôt créée avec succès.'
                }).then(() => {
                    window.location.href = '../logistique/depot.entree.add';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création de l\'entrée: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.entree.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.entree.add");
    exit();
}