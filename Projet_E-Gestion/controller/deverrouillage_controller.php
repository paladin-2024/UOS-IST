<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

$ecue = new Ecue();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../index.php?view=login';
        });
    </script>";
    exit();
}

$userId = $_SESSION['id'];

// Traiter l'action de déverrouillage
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Appeler la méthode de déverrouillage
    $result = $ecue->deverrouillerNotes($id, $userId);
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Les notes ont été déverrouillées avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/deverrouillage_notes';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors du déverrouillage des notes.'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/deverrouillage_notes';
            });
        </script>";
    }
    exit();
} else {
    // Redirection si aucun ID valide n'est fourni
    header("Location: ../index.php?view=enseignement/deverrouillage_notes");
    exit();
}
?>
