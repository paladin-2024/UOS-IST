<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $connexion = Connexion::getInstance()->getPDO();
    
    $idannee_acad = isset($_GET['idannee_acad']) ? intval($_GET['idannee_acad']) : 0;
    $status = isset($_GET['status']) ? intval($_GET['status']) : 0;

    if ($idannee_acad <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de l\'année académique invalide.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérifier que l'année académique existe
    $checkQuery = "SELECT COUNT(*) FROM annee_acad WHERE idannee_acad = :idannee_acad";
    $checkStmt = $connexion->prepare($checkQuery);
    $checkStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() == 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cette année académique n\'existe pas.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Début de la transaction
    $connexion->beginTransaction();
    
    try {
        if ($status == 1) {
            // Si on active une année, désactiver toutes les autres d'abord
            $deactivateQuery = "UPDATE annee_acad SET est_active = 0";
            $connexion->exec($deactivateQuery);
            
            // Activer l'année sélectionnée
            $activateQuery = "UPDATE annee_acad SET est_active = 1 WHERE idannee_acad = :idannee_acad";
            $activateStmt = $connexion->prepare($activateQuery);
            $activateStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
            $activateStmt->execute();
            
            $message = "Année académique activée avec succès.";
        } else {
            // Désactiver l'année sélectionnée
            $deactivateQuery = "UPDATE annee_acad SET est_active = 0 WHERE idannee_acad = :idannee_acad";
            $deactivateStmt = $connexion->prepare($deactivateQuery);
            $deactivateStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
            $deactivateStmt->execute();
            
            $message = "Année académique désactivée avec succès.";
        }
        
        // Valider la transaction
        $connexion->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '{$message}'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $connexion->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification du statut: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/annee.php");
    exit();
}
?>