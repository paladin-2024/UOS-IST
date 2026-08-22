<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../?view=dashboard';
        });
    </script>";
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ecueId = isset($_POST['ecue_id']) ? intval($_POST['ecue_id']) : 0;
    $sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $anneeId = isset($_POST['annee_id']) ? intval($_POST['annee_id']) : 0;
    
    if ($ecueId > 0 && $sessionId > 0 && $anneeId > 0) {
        try {
            $ecue = new Ecue();
            // Supprimer l'entrée de verrouillage
            $success = $ecue->deverrouillerNotes($ecueId, $sessionId, $anneeId);
            
            if ($success) {
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Les notes ont été déverrouillées avec succès.'
                    }).then(() => {
                        window.location.href = '../?view=enseignement/deverrouillage_notes&annee_id={$anneeId}&session_id={$sessionId}';
                    });
                </script>";
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors du déverrouillage des notes.'
                    }).then(() => {
                        window.location.href = '../?view=enseignement/deverrouillage_notes&annee_id={$anneeId}&session_id={$sessionId}';
                    });
                </script>";
            }
        } catch (Exception $e) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur: " . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../?view=enseignement/deverrouillage_notes&annee_id={$anneeId}&session_id={$sessionId}';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres invalides pour le déverrouillage.'
            }).then(() => {
                window.location.href = '../?view=enseignement/deverrouillage_notes';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au contrôleur
    header("Location: ../?view=enseignement/deverrouillage_notes");
    exit;
}
?>
