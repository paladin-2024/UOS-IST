<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idSpecialisation'])) {
    $idSpecialisation = intval($_GET['idSpecialisation']);
    
    // Validate the ID
    if ($idSpecialisation <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID invalide pour la spécialisation.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    $db->beginTransaction();
    
    try {
        // 1. Supprimer les associations avec les enseignants
        $stmtDeleteTeachers = $db->prepare("DELETE FROM enseignant_specialisation WHERE idSpecialisation = ?");
        $stmtDeleteTeachers->execute([$idSpecialisation]);
        
        // 2. Supprimer la spécialisation
        $stmtDeleteSpec = $db->prepare("DELETE FROM specialisation WHERE idSpecialisation = ?");
        $stmtDeleteSpec->execute([$idSpecialisation]);
        
        // Valider la transaction
        $db->commit();
        
        // Redirection avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La spécialisation a été supprimée avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        error_log("Erreur lors de la suppression de la spécialisation: " . $e->getMessage());
        
        // Redirection avec erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression de la spécialisation.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    }
} else {
    // Redirection si paramètre manquant
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètre manquant ou méthode non autorisée.'
        }).then(() => {
            window.location.href = '../index.php?view=ur/unite_recherche';
        });
    </script>";
}
