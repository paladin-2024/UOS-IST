<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id'])) {
    try {
        $id_depot = intval($_GET['id']);
        
        if ($id_depot <= 0) {
            throw new Exception("ID dépôt invalide.");
        }
        
        // Vérifier si le dépôt existe
        $checkStmt = $db->prepare("SELECT * FROM depot WHERE id_depot = :id_depot");
        $checkStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            throw new Exception("Le dépôt demandé n'existe pas.");
        }
        
        $depot = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si le dépôt est utilisé dans des opérations
        $checkOperationsStmt = $db->prepare("
            SELECT COUNT(*) FROM entree_stock WHERE id_depot = :id_depot
            UNION ALL
            SELECT COUNT(*) FROM sortie_stock WHERE id_depot = :id_depot
            UNION ALL
            SELECT COUNT(*) FROM livraison_client WHERE id_depot = :id_depot
        ");
        $checkOperationsStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $checkOperationsStmt->execute();
        $operations = $checkOperationsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $totalOperations = array_sum($operations);
        
        if ($totalOperations > 0) {
            // Ne pas supprimer mais désactiver
            $updateStmt = $db->prepare("UPDATE depot SET actif = 0 WHERE id_depot = :id_depot");
            $updateStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
            $updateStmt->execute();
            
            // Message
            $message = "Le dépôt est utilisé dans des opérations et ne peut pas être supprimé. Il a été désactivé.";
            $operation = "désactivation";
        } else {
            // Supprimer le dépôt
            $deleteStmt = $db->prepare("DELETE FROM depot WHERE id_depot = :id_depot");
            $deleteStmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Message
            $message = "Le dépôt a été supprimé avec succès.";
            $operation = "suppression";
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
                        (:id_user, :type_operation, 'depot', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "$operation du dépôt: {$depot['libelle_depot']} (Code: {$depot['code_depot']})";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        $id_user = $_SESSION['id'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':type_operation', $operation, PDO::PARAM_STR);
        $logStmt->bindParam(':id_enregistrement', $id_depot, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Rediriger avec un message
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: '$message',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../depots/depots.list';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../depots/depots.list';
            });
        </script>";
        exit;
    }
} else {
    // Redirection si pas d'ID
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'ID dépôt non spécifié',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}
