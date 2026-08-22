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

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    try {
        $id_sortie = intval($_GET['id']);
        $id_user = $_SESSION['id'];
        
        // Vérifier si la sortie existe et si elle est en état "En cours"
        $stmt = $db->prepare("SELECT * FROM sortie_stock WHERE id_sortie = :id_sortie");
        $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
        $stmt->execute();
        $sortie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sortie) {
            throw new Exception("Sortie de stock non trouvée.");
        }
        
        if ($sortie['etat'] != 'En cours') {
            throw new Exception("Cette sortie de stock ne peut pas être annulée car elle n'est pas en état 'En cours'.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mise à jour de l'état de la sortie
        $stmt = $db->prepare("UPDATE sortie_stock 
                              SET etat = 'Annulé', 
                                  id_user_validation = :id_user_validation, 
                                  date_validation = NOW() 
                              WHERE id_sortie = :id_sortie");
        
        $stmt->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
        $stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
        $stmt->execute();
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'annulation', 'sortie_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Annulation de la sortie de stock: {$sortie['numero_sortie']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_sortie, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La sortie de stock a été annulée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/stock.sortie.view&id=" . $id_sortie . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../stock/stock.sortie.view&id=" . $id_sortie . "';
            });
        </script>";
        exit;
    }
} else {
    // Paramètre id manquant ou invalide
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant de sortie invalide',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../stock/stock.sortie.list';
        });
    </script>";
    exit;
}
