<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données POST (JSON ou form-data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $idsoutenance = $data['idsoutenance'] ?? null;
    $note_finale = $data['note_finale'] ?? null;
} else {
    $idsoutenance = $_POST['idsoutenance'] ?? null;
    $note_finale = $_POST['note_finale'] ?? null;
}

// Validation des paramètres
if (empty($idsoutenance)) {
    echo json_encode(['success' => false, 'message' => 'ID soutenance requis']);
    exit();
}

if ($note_finale === null || $note_finale === '') {
    echo json_encode(['success' => false, 'message' => 'Note requise']);
    exit();
}

// Convertir et valider la note
$note_finale = floatval(str_replace(',', '.', $note_finale));

if ($note_finale < 0 || $note_finale > 20) {
    echo json_encode(['success' => false, 'message' => 'La note doit être comprise entre 0 et 20']);
    exit();
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Vérifier que la soutenance existe
    $checkStmt = $pdo->prepare("SELECT idsoutenance FROM soutenance WHERE idsoutenance = ?");
    $checkStmt->execute([$idsoutenance]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Soutenance non trouvée']);
        exit();
    }
    
    // Mettre à jour la note
    $updateStmt = $pdo->prepare("
        UPDATE soutenance 
        SET note_finale = ?, 
            date_encodage_note = NOW(), 
            id_encodeur = ?
        WHERE idsoutenance = ?
    ");
    
    $result = $updateStmt->execute([
        $note_finale,
        $_SESSION['id'],
        $idsoutenance
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Note enregistrée avec succès',
            'data' => [
                'idsoutenance' => $idsoutenance,
                'note_finale' => $note_finale,
                'date_encodage' => date('Y-m-d H:i:s'),
                'id_encodeur' => $_SESSION['id']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
    
} catch (PDOException $e) {
    error_log("Erreur save_note_soutenance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
