<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

if (!isset($_SESSION['id']) || !isset($_POST['affectation_id'])) {
    $_SESSION['messageType'] = 'danger';
    $_SESSION['message'] = 'Accès non autorisé ou données manquantes.';
    header('Location: ../?view=finance/affectation_frais');
    exit;
}

$affectation_id = intval($_POST['affectation_id']);
$user_id = $_SESSION['id'];

try {
    $connexion = Connexion::getInstance()->getPDO();
    $connexion->beginTransaction();

    // 1. Vérifier si l'affectation existe et n'a pas encore été payée
    $stmt = $connexion->prepare("
        SELECT af.*, f.designation 
        FROM affectation_frais af
        JOIN frais f ON af.frais_id = f.id
        WHERE af.id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();
    $affectation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$affectation) {
        throw new Exception('Affectation introuvable.');
    }

    if ($affectation['statut_paiement'] !== 'Non payé') {
        throw new Exception('Impossible de supprimer cette affectation car elle a déjà été payée partiellement ou complètement.');
    }

    // 2. Vérifier s'il existe des paiements associés
    $stmt = $connexion->prepare("
        SELECT COUNT(*) as count FROM paiements_frais WHERE affectation_id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        throw new Exception('Impossible de supprimer cette affectation car elle a des paiements associés.');
    }

    // 3. Supprimer les tranches d'échelonnement associées
    $stmt = $connexion->prepare("
        DELETE FROM echelonnement_paiement WHERE affectation_id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();

    // 4. Supprimer les tranches d'échelonnement des étudiants associées
    $stmt = $connexion->prepare("
        DELETE FROM echelonnement_paiement_etudiant WHERE affectation_id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();

    // 5. Supprimer les suivis de paiement pour les promotions si applicable
    $stmt = $connexion->prepare("
        DELETE FROM suivi_paiements_promotion WHERE affectation_id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();

    // 6. Enfin, supprimer l'affectation elle-même
    $stmt = $connexion->prepare("
        DELETE FROM affectation_frais WHERE id = :id
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->execute();

    // Journal d'activité (si une table existe pour ça)
    // Ceci est facultatif, ajustez selon votre structure
    /*
    $stmt = $connexion->prepare("
        INSERT INTO journal_activites (action, description, table_concernee, id_enregistrement, idUser)
        VALUES ('Suppression', 'Suppression d\'une affectation de frais', 'affectation_frais', :id, :user_id)
    ");
    $stmt->bindParam(':id', $affectation_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    */

    $connexion->commit();

    $_SESSION['messageType'] = 'success';
    $_SESSION['message'] = 'L\'affectation de frais "' . htmlspecialchars($affectation['designation']) . '" a été supprimée avec succès.';
} catch (Exception $e) {
    if ($connexion) {
        $connexion->rollBack();
    }
    
    $_SESSION['messageType'] = 'danger';
    $_SESSION['message'] = 'Erreur lors de la suppression: ' . $e->getMessage();
}

// Redirection vers la page d'affectation
header('Location: ../?view=finance/affectation_frais');
exit;