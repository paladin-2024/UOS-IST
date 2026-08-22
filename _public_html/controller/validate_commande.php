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

// Vérifier les paramètres
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0 || !in_array($action, ['validate', 'cancel'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres invalides'
        }).then(() => {
            window.location.href = '../achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

try {
    // Vérifier si la commande existe et est en état "En cours"
    $query = "SELECT * FROM commande_fournisseur WHERE id_commande = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        throw new Exception("Commande non trouvée.");
    }
    
    if ($commande['etat'] != 'En cours') {
        throw new Exception("Seules les commandes en état 'En cours' peuvent être validées ou annulées.");
    }
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    $nouvelEtat = ($action == 'validate') ? 'Validé' : 'Annulé';
    $userId = $_SESSION['id'];
    $dateValidation = date('Y-m-d H:i:s');
    
    // Mettre à jour l'état de la commande
    $updateQuery = "UPDATE commande_fournisseur 
                   SET etat = :etat, 
                       id_user_validation = :id_user_validation, 
                       date_validation = :date_validation 
                   WHERE id_commande = :id_commande";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':etat', $nouvelEtat, PDO::PARAM_STR);
    $updateStmt->bindParam(':id_user_validation', $userId, PDO::PARAM_INT);
    $updateStmt->bindParam(':date_validation', $dateValidation, PDO::PARAM_STR);
    $updateStmt->bindParam(':id_commande', $id, PDO::PARAM_INT);
    $updateStmt->execute();
    
    // Journalisation de l'action
    $logStmt = $db->prepare("INSERT INTO log_operation 
        (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
        VALUES 
        (:id_user, :type_operation, 'commande_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
    
    $typeOperation = ($action == 'validate') ? 'validation' : 'annulation';
    $description = ($action == 'validate') 
                 ? "Validation de la commande fournisseur: {$commande['numero_commande']}" 
                 : "Annulation de la commande fournisseur: {$commande['numero_commande']}";
    $adresse_ip = $_SERVER['REMOTE_ADDR'];
    $navigateur = $_SERVER['HTTP_USER_AGENT'];
    
    $logStmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
    $logStmt->bindParam(':type_operation', $typeOperation, PDO::PARAM_STR);
    $logStmt->bindParam(':id_enregistrement', $id, PDO::PARAM_INT);
    $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
    $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
    $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
    
    $logStmt->execute();
    
    // Valider la transaction
    $db->commit();
    
    // Message de succès
    $message = ($action == 'validate') 
             ? "La commande a été validée avec succès." 
             : "La commande a été annulée avec succès.";
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '$message'
        }).then(() => {
            window.location.href = '../achats/commandes/commandes.view&id=$id';
        });
    </script>";
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: '" . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../achats/commandes/commandes.view&id=$id';
        });
    </script>";
}
