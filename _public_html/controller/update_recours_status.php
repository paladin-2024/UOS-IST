<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Vous devez être connecté pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../login';
        });
    </script>";
    exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['id_recours']) || !isset($_POST['nouveau_statut'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants.'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
    exit();
}

$id_recours = intval($_POST['id_recours']);
$nouveau_statut = $_POST['nouveau_statut'];

// Valider le statut
$statuts_valides = ['En attente', 'En traitement', 'Approuvé', 'Rejeté'];
if (!in_array($nouveau_statut, $statuts_valides)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Statut invalide.'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=$id_recours';
        });
    </script>";
    exit();
}

// Vérifier les autorisations pour certaines actions
if (($nouveau_statut == 'Approuvé' || $nouveau_statut == 'Rejeté') && 
    (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Administrateur' && $_SESSION['role'] != 'Jury'))) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits nécessaires pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=$id_recours';
        });
    </script>";
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Récupérer le statut actuel du recours
    $query_check = "SELECT statut FROM recours WHERE id_recours = :id_recours";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bindParam(':id_recours', $id_recours);
    $stmt_check->execute();
    $recours_actuel = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$recours_actuel) {
        throw new Exception("Le recours spécifié n'existe pas.");
    }
    
    // Vérifier la logique de transition d'état
    $statut_actuel = $recours_actuel['statut'];
    $transition_valide = false;
    
    switch ($statut_actuel) {
        case 'En attente':
            // Depuis "En attente", on peut passer à "En traitement" ou "Rejeté"
            $transition_valide = ($nouveau_statut == 'En traitement' || $nouveau_statut == 'Rejeté');
            break;
        case 'En traitement':
            // Depuis "En traitement", on peut passer à "Approuvé" ou "Rejeté"
            $transition_valide = ($nouveau_statut == 'Approuvé' || $nouveau_statut == 'Rejeté');
            break;
        default:
            // Les statuts terminaux (Approuvé/Rejeté) ne peuvent pas être modifiés
            $transition_valide = false;
    }
    
    if (!$transition_valide) {
        throw new Exception("La transition de statut demandée n'est pas autorisée.");
    }
    
    // Mettre à jour le statut du recours
    $query_update = "UPDATE recours SET statut = :nouveau_statut WHERE id_recours = :id_recours";
    $stmt_update = $conn->prepare($query_update);
    $stmt_update->bindParam(':nouveau_statut', $nouveau_statut);
    $stmt_update->bindParam(':id_recours', $id_recours);
    $stmt_update->execute();
    
    // Si le statut passe à "Rejeté", il faut également mettre à jour la réponse si elle existe
    if ($nouveau_statut == 'Rejeté') {
        $query_check_reponse = "SELECT id_reponse FROM recours_reponse WHERE id_recours = :id_recours";
        $stmt_check_reponse = $conn->prepare($query_check_reponse);
        $stmt_check_reponse->bindParam(':id_recours', $id_recours);
        $stmt_check_reponse->execute();
        
        if ($stmt_check_reponse->rowCount() > 0) {
            $query_update_reponse = "UPDATE recours_reponse 
                                     SET valide_jury = 0, 
                                         id_validateur = :id_validateur,
                                         date_validation = NOW()
                                     WHERE id_recours = :id_recours";
            $stmt_update_reponse = $conn->prepare($query_update_reponse);
            $stmt_update_reponse->bindParam(':id_validateur', $_SESSION['id']);
            $stmt_update_reponse->bindParam(':id_recours', $id_recours);
            $stmt_update_reponse->execute();
        }
    }
    
    // Message de succès selon le nouveau statut
    $message = '';
    switch ($nouveau_statut) {
        case 'En traitement':
            $message = 'Le recours a été marqué comme étant en traitement.';
            break;
        case 'Approuvé':
            $message = 'Le recours a été approuvé.';
            break;
        case 'Rejeté':
            $message = 'Le recours a été rejeté.';
            break;
    }
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '$message'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=$id_recours';
        });
    </script>";
    
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: '" . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=$id_recours';
        });
    </script>";
}
?>
