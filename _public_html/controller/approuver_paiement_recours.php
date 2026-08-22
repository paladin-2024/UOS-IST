<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Vérifier que la requête est bien une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../deliberation/recours');
    exit();
}

// Récupérer les données du formulaire
$id_recours = isset($_POST['id_recours']) ? intval($_POST['id_recours']) : 0;
$reference_paiement = isset($_POST['reference_paiement']) ? trim($_POST['reference_paiement']) : '';
$date_paiement = isset($_POST['date_paiement']) ? trim($_POST['date_paiement']) : date('Y-m-d');

// Valider l'ID de recours
if ($id_recours <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID de recours invalide.'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
    exit();
}

try {
    // Connexion à la base de données
    $conn = Connexion::getInstance()->getPDO();
    
    // Vérifier que le recours existe et est en attente
    $query_check = "SELECT id_recours, statut FROM recours WHERE id_recours = :id_recours";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bindParam(':id_recours', $id_recours);
    $stmt_check->execute();
    $recours = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$recours) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le recours spécifié n\'existe pas.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
        exit();
    }
    
    if ($recours['statut'] !== 'En attente') {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Ce recours n\'est pas en attente de paiement.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
        exit();
    }
    // Mettre à jour le recours pour indiquer qu'il est payé et passer au statut "En traitement"
    $query_update = "UPDATE recours 
                     SET est_paye = 1, 
                         statut = 'En traitement', 
                         reference_paiement = :reference_paiement,
                         date_paiement = :date_paiement,
                         date_modification = NOW(),
                         id_modificateur = :id_modificateur
                     WHERE id_recours = :id_recours";
    
    $stmt_update = $conn->prepare($query_update);
    $stmt_update->bindParam(':reference_paiement', $reference_paiement);
    $stmt_update->bindParam(':date_paiement', $date_paiement);
    $stmt_update->bindParam(':id_modificateur', $_SESSION['id']);
    $stmt_update->bindParam(':id_recours', $id_recours);
    
    $result = $stmt_update->execute();
    
    if ($result) {
        // Enregistrer un historique de cette action
        $query_log = "INSERT INTO recours_historique 
                      (id_recours, action, details, date_action, id_utilisateur) 
                      VALUES 
                      (:id_recours, 'Paiement approuvé', :details, NOW(), :id_utilisateur)";
        
        $details = "Paiement confirmé" . (!empty($reference_paiement) ? " (Réf: $reference_paiement)" : "") . 
                   ". Statut modifié de 'En attente' à 'En traitement'.";
        
        $stmt_log = $conn->prepare($query_log);
        $stmt_log->bindParam(':id_recours', $id_recours);
        $stmt_log->bindParam(':details', $details);
        $stmt_log->bindParam(':id_utilisateur', $_SESSION['id']);
        $stmt_log->execute();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le paiement du recours a été confirmé avec succès. Le recours est maintenant en traitement.'
            }).then(() => {
                window.location.href = '../deliberation/recours?action=recherche_recours&search_term=" . (isset($_POST['search_term']) ? urlencode($_POST['search_term']) : '') . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour du recours.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
    }
} catch (PDOException $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur de base de données',
            text: 'Une erreur est survenue: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
}
?>
