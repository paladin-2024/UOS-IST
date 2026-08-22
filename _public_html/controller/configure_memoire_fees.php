<?php
session_start();

require_once __DIR__ . '/../config/Connexion.php';

// Vérifier que l'utilisateur est admin ou responsable
if (!isset($_SESSION['id'])) {
    http_response_code(403);
    $_SESSION['error_message'] = 'Accès refusé.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Vérifier les droits d'accès pour responsable de section
if (!$hasFullAccess) {
    try {
        $db = Connexion::getInstance()->getPDO();
        $query = "SELECT COUNT(*) FROM responsable_section WHERE idUser = :userId";
        $stmt = $db->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $hasSectionRole = $stmt->fetchColumn() > 0;
        
        if (!$hasSectionRole) {
            http_response_code(403);
            $_SESSION['error_message'] = 'Accès refusé.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(403);
        $_SESSION['error_message'] = 'Accès refusé.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$promotionId = $_POST['promotion_id'] ?? null;
$selectedMemoireFees = $_POST['memoire_fees'] ?? [];
$selectedSujetFees = $_POST['sujet_fees'] ?? [];

if (!$promotionId) {
    $_SESSION['error_message'] = 'ID de promotion non fourni.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Supprimer les anciens frais requis pour ce mémoire (type memoire)
    $query = "DELETE FROM frais_memoire WHERE promotion_idpromotion = :promotionId AND type = 'memoire'";
    $stmt = $db->prepare($query);
    $stmt->execute(['promotionId' => $promotionId]);
    
    // Supprimer les anciens frais requis pour le sujet (type sujet)
    $query = "DELETE FROM frais_memoire WHERE promotion_idpromotion = :promotionId AND type = 'sujet'";
    $stmt = $db->prepare($query);
    $stmt->execute(['promotionId' => $promotionId]);
    
    // Insérer les nouveaux frais pour le mémoire
    if (!empty($selectedMemoireFees)) {
        $query = "INSERT INTO frais_memoire (frais_id, promotion_idpromotion, type) VALUES (:feeId, :promotionId, 'memoire')";
        $stmt = $db->prepare($query);
        
        foreach ($selectedMemoireFees as $feeId) {
            $stmt->execute([
                'feeId' => $feeId,
                'promotionId' => $promotionId
            ]);
        }
    }
    
    // Insérer les nouveaux frais pour le sujet
    if (!empty($selectedSujetFees)) {
        $query = "INSERT INTO frais_memoire (frais_id, promotion_idpromotion, type) VALUES (:feeId, :promotionId, 'sujet')";
        $stmt = $db->prepare($query);
        
        foreach ($selectedSujetFees as $feeId) {
            $stmt->execute([
                'feeId' => $feeId,
                'promotionId' => $promotionId
            ]);
        }
    }
    
    error_log("Frais configurés - Promotion: $promotionId, Frais Mémoire: " . count($selectedMemoireFees) . ", Frais Sujet: " . count($selectedSujetFees));
    
    $_SESSION['success_message'] = 'Les frais requis ont été configurés avec succès!';
    
} catch (Exception $e) {
    error_log("Erreur lors de la configuration des frais: " . $e->getMessage());
    $_SESSION['error_message'] = 'Une erreur est survenue lors de la sauvegarde: ' . $e->getMessage();
}

$anneeAcad = isset($_POST['annee_acad']) ? $_POST['annee_acad'] : (isset($_GET['annee_acad']) ? $_GET['annee_acad'] : '');
header('Location: ?view=memoire/fees&annee_acad=' . urlencode($anneeAcad) . '&promotion=' . urlencode($promotionId));
exit;
?>
