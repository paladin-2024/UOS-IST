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

// Récupérer l'ID de l'utilisateur connecté
$id_user = $_SESSION['id'];

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants'
        }).then(() => {
            window.location.href = '../ventes/devis/devis.list';
        });
    </script>";
    exit;
}

$id_devis = intval($_GET['id']);
$action = $_GET['action'];

// Vérifier que l'action est valide
if ($action != 'validate' && $action != 'cancel') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Action non valide'
        }).then(() => {
            window.location.href = '../ventes/devis/devis.list';
        });
    </script>";
    exit;
}

try {
    // Initialiser la connexion
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si le devis existe et s'il est en état "En cours"
    $query = "SELECT * FROM devis WHERE id_devis = :id_devis";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
    $stmt->execute();
    $devis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$devis) {
        throw new Exception("Le devis demandé n'existe pas.");
    }
    
    if ($devis['etat'] != 'En cours') {
        throw new Exception("Seuls les devis en état 'En cours' peuvent être validés ou annulés.");
    }
    
    // Mettre à jour l'état du devis selon l'action
    $nouvel_etat = ($action == 'validate') ? 'Validé' : 'Annulé';
    
    $query = "UPDATE devis SET 
              etat = :etat, 
              id_user_validation = :id_user_validation, 
              date_validation = NOW() 
              WHERE id_devis = :id_devis";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':etat', $nouvel_etat, PDO::PARAM_STR);
    $stmt->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
    $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
    $stmt->execute();
    
    // Journalisation de l'action
    $action_type = ($action == 'validate') ? 'validation' : 'annulation';
    
    // Récupérer les informations du client pour le log
    $query = "SELECT c.nom_client, d.numero_devis 
              FROM devis d 
              JOIN client c ON d.id_client = c.id_client 
              WHERE d.id_devis = :id_devis";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
    $stmt->execute();
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $description = ucfirst($action_type) . " du devis: " . $info['numero_devis'] . " pour le client: " . $info['nom_client'];
    
    $logStmt = $db->prepare("INSERT INTO log_operation 
        (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
        VALUES 
        (:id_user, :type_operation, 'devis', :id_enregistrement, :description, :adresse_ip, :navigateur)");
    
    $adresse_ip = $_SERVER['REMOTE_ADDR'];
    $navigateur = $_SERVER['HTTP_USER_AGENT'];
    
    $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
    $logStmt->bindParam(':type_operation', $action_type, PDO::PARAM_STR);
    $logStmt->bindParam(':id_enregistrement', $id_devis, PDO::PARAM_INT);
    $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
    $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
    $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
    
    $logStmt->execute();
    
    // Message de succès et redirection
    $message = ($action == 'validate') ? 'validé' : 'annulé';
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: 'Le devis a été " . $message . " avec succès.'
        }).then(() => {
            window.location.href = '../ventes/devis/devis.view&id=" . $id_devis . "';
        });
    </script>";
    
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: '" . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../ventes/devis/devis.list';
        });
    </script>";
}
