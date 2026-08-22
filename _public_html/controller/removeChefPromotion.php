<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée'
        }).then(() => {
            window.location.href = '../reception/chef_promotion';
        });
    </script>";
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $userId = $_SESSION['id'];
    
    $chefId = filter_input(INPUT_GET, 'chef_id', FILTER_VALIDATE_INT);
    
    if (!$chefId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de chef invalide'
            }).then(() => {
                window.location.href = '../reception/chef_promotion';
            });
        </script>";
        exit;
    }
    
    $db->beginTransaction();
    
    // Vérifier que le chef existe et est actif
    $checkQuery = "SELECT cp.*, e.noms, p.\"designationPromotion\" 
                   FROM chef_promotion cp
                   JOIN etudiant e ON cp.idetudiant = e.idetudiant
                   JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                   WHERE cp.id_chef = :chef_id AND cp.est_actif = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Chef de promotion non trouvé ou déjà inactif'
            }).then(() => {
                window.location.href = '../reception/chef_promotion';
            });
        </script>";
        exit;
    }
    
    $chef = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    // Désactiver le chef (au lieu de le supprimer pour garder l'historique)
    $updateQuery = "DELETE FROM chef_promotion 
                    WHERE id_chef = :chef_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        $db->commit();
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Chef de promotion retiré avec succès'
            }).then(() => {
                window.location.href = '../reception/chef_promotion';
            });
        </script>";
    } else {
        $db->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression du chef'
            }).then(() => {
                window.location.href = '../reception/chef_promotion';
            });
        </script>";
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur removeChefPromotion: " . $e->getMessage());
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur système',
            text: 'Une erreur est survenue : " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../reception/chef_promotion';
        });
    </script>";
}
?>