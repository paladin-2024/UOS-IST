<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if (isset($_GET['idorientation'])) {
    $orientationId = intval($_GET['idorientation']);
    
    if ($orientationId > 0) {
        try {
            $result = $universite->deleteOrientation($orientationId);
            
            if ($result) {
                $_SESSION['success'] = 'Orientation supprimée avec succès.';
            } else {
                $_SESSION['error'] = 'Erreur lors de la suppression de l\'orientation.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erreur : Cette orientation ne peut pas être supprimée car elle est liée à d\'autres données.';
        }
    } else {
        $_SESSION['error'] = 'ID d\'orientation invalide.';
    }
} else {
    $_SESSION['error'] = 'ID d\'orientation manquant.';
}

header("Location: ../index.php?view=configuration/orientation");
exit();
?>
