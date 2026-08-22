<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idunite_recherche'])) {
    $idUniteRecherche = intval($_GET['idunite_recherche']);
    $db = Connexion::getInstance()->getPDO();
    
    // Commencer une transaction
    $db->beginTransaction();
    
    try {
        // 1. Récupérer toutes les spécialisations associées à cette unité de recherche
        $stmtSpec = $db->prepare("SELECT idSpecialisation FROM specialisation WHERE idUnite_recherche = ?");
        $stmtSpec->execute([$idUniteRecherche]);
        $specialisations = $stmtSpec->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Supprimer les enseignants affectés à ces spécialisations
        if (!empty($specialisations)) {
            $placeholders = implode(',', array_fill(0, count($specialisations), '?'));
            $stmtDeleteTeachers = $db->prepare("DELETE FROM enseignant_specialisation WHERE idSpecialisation IN ($placeholders)");
            $stmtDeleteTeachers->execute($specialisations);
        }
        
        // 3. Supprimer les spécialisations associées
        $stmtDeleteSpec = $db->prepare("DELETE FROM specialisation WHERE idUnite_recherche = ?");
        $stmtDeleteSpec->execute([$idUniteRecherche]);
        
        // 4. Supprimer les associations avec les sections
        $stmtDeleteSections = $db->prepare("DELETE FROM unite_recherche_section WHERE idunite_recherche = ?");
        $stmtDeleteSections->execute([$idUniteRecherche]);
        
        // 5. Supprimer l'unité de recherche
        $stmtDeleteUnit = $db->prepare("DELETE FROM unite_recherche WHERE idunite_recherche = ?");
        $stmtDeleteUnit->execute([$idUniteRecherche]);
        
        // Valider la transaction
        $db->commit();
        
        // Redirection avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\'unité de recherche a été supprimée avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        error_log("Erreur lors de la suppression de l'unité de recherche: " . $e->getMessage());
        
        // Redirection avec erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression de l\'unité de recherche.'
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
