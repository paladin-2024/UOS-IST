<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $manifestId = isset($_POST['idManifeste_sortie']) ? intval($_POST['idManifeste_sortie']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : '';
    $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 0;
    $userId = $_SESSION['id'];

    // Validate required fields
    if ($manifestId <= 0 || empty($designation) || $quantite <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
        exit();
    }

    try {
        $structure = new Structure();
        if ($structure->addDetailToSortie($manifestId, $designation, $unite, $quantite, $userId)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Détail ajouté avec succès.'
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
                text: 'Erreur lors de l\'ajout du détail: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../logistique/depot.sortie.add';
            });
        </script>";
    }
} else {
    header("Location: ../logistique/depot.sortie.add");
    exit();
}