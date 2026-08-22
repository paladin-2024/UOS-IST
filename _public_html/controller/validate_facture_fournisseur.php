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

// Récupérer les paramètres
$factureId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($factureId <= 0 || !in_array($action, ['validate', 'cancel'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres invalides'
        }).then(() => {
            window.location.href = '../achats/factures/factures.list';
        });
    </script>";
    exit;
}

try {
    // Vérifier si la facture existe et est en état "En cours"
    $query = "SELECT * FROM facture_fournisseur WHERE id_facture = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $factureId, PDO::PARAM_INT);
    $stmt->execute();
    $facture = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$facture) {
        throw new Exception("Facture non trouvée");
    }
    
    if ($facture['etat'] !== 'En cours') {
        throw new Exception("Seules les factures en état 'En cours' peuvent être validées ou annulées");
    }
    
    // Mettre à jour l'état de la facture
    $nouvelEtat = ($action === 'validate') ? 'Validé' : 'Annulé';
    $userId = $_SESSION['id'];
    $dateValidation = date('Y-m-d H:i:s');
    
    $query = "UPDATE facture_fournisseur 
              SET etat = :etat, 
                  id_user_validation = :id_user, 
                  date_validation = :date_validation 
              WHERE id_facture = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':etat', $nouvelEtat, PDO::PARAM_STR);
    $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':date_validation', $dateValidation, PDO::PARAM_STR);
    $stmt->bindParam(':id', $factureId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Journalisation de l'action
    $description = ($action === 'validate') 
                 ? "Validation de la facture fournisseur N° " . $facture['numero_facture']
                 : "Annulation de la facture fournisseur N° " . $facture['numero_facture'];
    
    $queryLog = "INSERT INTO log_operation 
                (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                VALUES 
                (:id_user, :type_operation, 'facture_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)";
    
    $stmtLog = $db->prepare($queryLog);
    $typeOperation = ($action === 'validate') ? 'validation' : 'annulation';
    $adresseIp = $_SERVER['REMOTE_ADDR'];
    $navigateur = $_SERVER['HTTP_USER_AGENT'];
    
    $stmtLog->bindParam(':id_user', $userId, PDO::PARAM_INT);
    $stmtLog->bindParam(':type_operation', $typeOperation, PDO::PARAM_STR);
    $stmtLog->bindParam(':id_enregistrement', $factureId, PDO::PARAM_INT);
    $stmtLog->bindParam(':description', $description, PDO::PARAM_STR);
    $stmtLog->bindParam(':adresse_ip', $adresseIp, PDO::PARAM_STR);
    $stmtLog->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
    $stmtLog->execute();
    
    // Redirection avec message de succès
    $message = ($action === 'validate') 
             ? "La facture a été validée avec succès."
             : "La facture a été annulée avec succès.";
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '$message'
        }).then(() => {
            window.location.href = '../achats/factures/factures.view&id=$factureId';
        });
    </script>";
    
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: '" . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../achats/factures/factures.view&id=$factureId';
        });
    </script>";
}
