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
            window.location.href = '../achats/demandes/demandes.list';
        });
    </script>";
    exit;
}

try {
    // Vérifier si la demande existe et est en état "En cours"
    $query = "SELECT * FROM demande_prix WHERE id_demande_prix = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        throw new Exception("Demande de prix non trouvée.");
    }
    
    if ($demande['etat'] != 'En cours') {
        throw new Exception("Seules les demandes en cours peuvent être validées ou annulées.");
    }
    
    // Mettre à jour l'état de la demande
    $nouvelEtat = ($action == 'validate') ? 'Validé' : 'Annulé';
    $id_user_validation = $_SESSION['id'];
    $date_validation = date('Y-m-d H:i:s');
    
    $updateQuery = "UPDATE demande_prix 
                   SET etat = :etat, 
                       id_user_validation = :id_user_validation, 
                       date_validation = :date_validation 
                   WHERE id_demande_prix = :id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':etat', $nouvelEtat, PDO::PARAM_STR);
    $updateStmt->bindParam(':id_user_validation', $id_user_validation, PDO::PARAM_INT);
    $updateStmt->bindParam(':date_validation', $date_validation, PDO::PARAM_STR);
    $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $updateStmt->execute();
    
    // Journalisation de l'action
    $logStmt = $db->prepare("INSERT INTO log_operation 
        (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
        VALUES 
        (:id_user, :type_operation, 'demande_prix', :id_enregistrement, :description, :adresse_ip, :navigateur)");
    
    $type_operation = ($action == 'validate') ? 'validation' : 'annulation';
    $description = ($action == 'validate') 
                  ? "Validation de la demande de prix: {$demande['numero_demande']}" 
                  : "Annulation de la demande de prix: {$demande['numero_demande']}";
    $adresse_ip = $_SERVER['REMOTE_ADDR'];
    $navigateur = $_SERVER['HTTP_USER_AGENT'];
    
    $logStmt->bindParam(':id_user', $id_user_validation, PDO::PARAM_INT);
    $logStmt->bindParam(':type_operation', $type_operation, PDO::PARAM_STR);
    $logStmt->bindParam(':id_enregistrement', $id, PDO::PARAM_INT);
    $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
    $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
    $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
    
    $logStmt->execute();
    
    // Message de succès
    $message = ($action == 'validate') 
              ? "La demande de prix a été validée avec succès." 
              : "La demande de prix a été annulée.";
    $icon = ($action == 'validate') ? 'success' : 'info';
    
    echo "<script>
        Swal.fire({
            title: 'Succès',
            text: '$message',
            icon: '$icon',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../achats/demandes/demandes.view&id=$id';
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
            window.location.href = '../achats/demandes/demandes.view&id=$id';
        });
    </script>";
    exit;
}
