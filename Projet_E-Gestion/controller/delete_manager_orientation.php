<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if (isset($_GET['idresponsable_orientation'])) {
    $managerId = intval($_GET['idresponsable_orientation']);
    
    if ($managerId > 0) {
        // Get current manager data to delete signature file
        $currentManager = $universite->getOrientationManagerById($managerId);
        $signature = $currentManager['signature'] ?? '';
        
        $result = $universite->deleteOrientationManager($managerId);
        
        if ($result) {
            // Delete signature file if it exists
            if (!empty($signature) && file_exists(dirname(__DIR__) . '/uploads/' . $signature)) {
                unlink(dirname(__DIR__) . '/uploads/' . $signature);
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Responsable supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/orientation';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression du responsable.'
                }).then(() => {
                    window.location.href = '../configuration/orientation';
                });
            </script>";
        }
    } else {
        header("Location: ../configuration/orientation");
    }
} else {
    header("Location: ../configuration/orientation");
}
?>
