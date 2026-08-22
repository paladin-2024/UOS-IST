<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit();
}

// Traiter la suppression du groupe d'UE
if (isset($_GET['ids'])) {
    try {
        $idsJson = $_GET['ids'];
        $ids = json_decode($idsJson);
        
        if (!is_array($ids) || empty($ids)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Liste d\'IDs invalide.']);
            exit();
        }
        
        $success = true;
        $message = 'Toutes les UEs ont été supprimées avec succès.';
        
        // Supprimer chaque UE du groupe
        foreach ($ids as $idUE) {
            $result = $universite->deleteUE($idUE);
            if (!$result) {
                $success = false;
                $message = 'Certaines UEs n\'ont pas pu être supprimées. Vérifiez qu\'elles n\'ont pas d\'ECUEs associées.';
            }
        }
        
        echo "<script>
            Swal.fire({
                icon: '" . ($success ? 'success' : 'warning') . "',
                title: '" . ($success ? 'Succès' : 'Attention') . "',
                text: '" . addslashes($message) . "'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/unites_enseignement';
            });
        </script>";
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/unites_enseignement';
            });
        </script>";
    }
    
} else {
    header("Location: ../index.php?view=enseignement/unites_enseignement");
    exit();
}
?>
