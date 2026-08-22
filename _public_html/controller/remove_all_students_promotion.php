<?php
session_start();

header('Content-Type: application/json');

// Vérifier l'authentification et les permissions
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

$input = json_decode(file_get_contents('php://input'), true);
$idpromotion = isset($input['idpromotion']) ? intval($input['idpromotion']) : 0;

if (!$idpromotion) {
    echo json_encode(['success' => false, 'message' => 'ID promotion invalide']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Commencer une transaction
    $connexion->beginTransaction();
    
    // Récupérer le nom de la promotion pour la journalisation
    $stmt = $connexion->prepare("SELECT \"designationPromotion\" FROM promotion WHERE idpromotion = ?");
    $stmt->execute([$idpromotion]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        throw new Exception('Promotion introuvable');
    }
    
    $promotionName = $promotion['designationPromotion'];
    
    // Récupérer tous les IDs des étudiants de cette promotion
    $stmt = $connexion->prepare("SELECT idetudiant FROM etudiant WHERE promotion_idpromotion = ?");
    $stmt->execute([$idpromotion]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalEtudiants = count($etudiants);
    
    if ($totalEtudiants === 0) {
        $connexion->rollBack();
        echo json_encode(['success' => false, 'message' => 'Aucun étudiant à supprimer']);
        exit;
    }
    
    // Stocker les données avant suppression pour la journalisation
    $donnees_avant = [
        'promotion' => $promotionName,
        'nombre_etudiants' => $totalEtudiants,
        'id_promotion' => $idpromotion
    ];
    
    if (count($etudiants) > 0) {
        $placeholders = implode(',', array_fill(0, count($etudiants), '?'));
        
        // Supprimer les données liées des étudiants - chaque suppression dans un try/catch
        
        // 1. Supprimer les notes (cotes_grille)
        try {
            $sql = "DELETE FROM cotes_grille WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression cotes_grille: " . $e->getMessage());
        }
        
        // 2. Supprimer les inscriptions aux cours
        try {
            $sql = "DELETE FROM cours_etudiant WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression cours_etudiant: " . $e->getMessage());
        }
        
        // 3. Supprimer les absences
        try {
            $sql = "DELETE FROM absence WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression absence: " . $e->getMessage());
        }
        
        // 4. Supprimer les paiements
        try {
            $sql = "DELETE FROM paiement WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression paiement: " . $e->getMessage());
        }
        
        // 5. Supprimer les stages
        try {
            $sql = "DELETE FROM stage WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression stage: " . $e->getMessage());
        }
        
        // 6. Supprimer les dettes
        try {
            $sql = "DELETE FROM dette WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression dette: " . $e->getMessage());
        }
        
        // 7. Supprimer les dépôts de mémoires
        try {
            $sql = "DELETE FROM depot_memoire WHERE etudiant_idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression depot_memoire: " . $e->getMessage());
        }
        
        // 8. Supprimer les soutenances
        try {
            $sql = "DELETE FROM soutenance WHERE promotion_idpromotion = ?";
            $stmt = $connexion->prepare($sql);
            $stmt->execute([$idpromotion]);
        } catch (Exception $e) {
            error_log("Erreur suppression soutenance: " . $e->getMessage());
        }
        
        // 9. Supprimer les frais de soutenance
        try {
            $sql = "DELETE FROM paiement_soutenance WHERE soutenance_idsoutenance IN (
                SELECT idsoutenance FROM soutenance WHERE promotion_idpromotion = ?
            )";
            $stmt = $connexion->prepare($sql);
            $stmt->execute([$idpromotion]);
        } catch (Exception $e) {
            error_log("Erreur suppression paiement_soutenance: " . $e->getMessage());
        }
        
        // 10. Supprimer les lectures de soutenance
        try {
            $sql = "DELETE FROM lecteurs_soutenance WHERE soutenance_idsoutenance IN (
                SELECT idsoutenance FROM soutenance WHERE promotion_idpromotion = ?
            )";
            $stmt = $connexion->prepare($sql);
            $stmt->execute([$idpromotion]);
        } catch (Exception $e) {
            error_log("Erreur suppression lecteurs_soutenance: " . $e->getMessage());
        }
        
        // 11. Supprimer finalement les étudiants
        try {
            $sql = "DELETE FROM etudiant WHERE idetudiant IN ($placeholders)";
            $stmt = $connexion->prepare($sql);
            $stmt->execute($etudiants);
        } catch (Exception $e) {
            error_log("Erreur suppression etudiant: " . $e->getMessage());
            throw new Exception("Erreur lors de la suppression des étudiants: " . $e->getMessage());
        }
    }
    
    // Valider la transaction
    $connexion->commit();
    
    // Journaliser l'opération
    try {
        $idUserSession = $_SESSION['id'] ?? null;
        $nomUserSession = $_SESSION['nomAgent'] ?? $_SESSION['nom'] ?? $_SESSION['name'] ?? 'Utilisateur inconnu';
        
        $journal = new JournalServeur();
        $journal->enregistrerAction(
            'DELETE_ALL_STUDENTS',
            'PROMOTION_MANAGEMENT',
            'Suppression en masse de ' . $totalEtudiants . ' étudiant(s) de la promotion ' . $promotionName,
            $idUserSession,
            $nomUserSession,
            'etudiant',
            $idpromotion,
            $donnees_avant,
            [
                'promotion' => $promotionName,
                'nombre_etudiants_supprimes' => $totalEtudiants,
                'id_promotion' => $idpromotion,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'succes'
        );
    } catch (Exception $e) {
        error_log("Erreur journalisation: " . $e->getMessage());
        // Continuer malgré l'erreur de journalisation
    }
    
    echo json_encode([
        'success' => true,
        'message' => $totalEtudiants . ' étudiant(s) et toutes leurs données associées ont été supprimés avec succès.'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($connexion && $connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    error_log("Erreur remove_all_students_promotion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
