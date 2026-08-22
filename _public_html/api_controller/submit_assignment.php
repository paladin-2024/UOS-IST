<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once 'connexion.php';
require_once 'auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
$auth = new Auth();
$studentId = $auth->authenticate(); //Identifiant de l'étudiant
if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Vérifier les données requises
if (!isset($_POST['idDevoir']) || empty($_POST['idDevoir'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du devoir non spécifié'], JSON_UNESCAPED_UNICODE);
    exit();
}

$idDevoir = intval($_POST['idDevoir']);
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Fichier non reçu ou erreur lors de l\'upload: ' . ($_FILES['fichier']['error'] ?? 'Aucun fichier fourni')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // S'assurer que la connexion est en UTF-8
    $conn->exec("SET NAMES utf8mb4");
    
    // Vérifier si le devoir existe et n'est pas expiré
    $stmt = $conn->prepare("SELECT * FROM devoirs WHERE iddevoir = ? AND date_limite >= NOW()");
    $stmt->execute([$idDevoir]);
    $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$devoir) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Devoir introuvable ou date limite dépassée'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Vérifier si l'étudiant a déjà soumis une réponse
    $query = "SELECT COUNT(*) FROM reponses_devoir WHERE iddevoir = ? AND idetudiant = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$idDevoir, $studentId]);
    $reponseExistante = $stmt->fetchColumn() > 0;
    
    // Traiter le fichier uploadé
    $uploadDir = $_SERVER['DOCUMENT_ROOT'].'/uploads/reponses/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileInfo = pathinfo($_FILES['fichier']['name']);
    $extension = $fileInfo['extension'];
    $uniqueName = uniqid('devoir_' . $studentId . '_') . '.' . $extension;
    $fullPath = $uploadDir . $uniqueName;
    
    if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $fullPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Insertion ou mise à jour de la réponse
    if ($reponseExistante) {
        // Mettre à jour la soumission existante
        $query = "UPDATE reponses_devoir 
                  SET commentaire = ?, fichier = ?, date_soumission = NOW(),
                      note = NULL, feedback_enseignant = NULL 
                  WHERE iddevoir = ? AND idetudiant = ?";
        
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$commentaire, $uniqueName, $idDevoir, $studentId]);
    } else {
        // Créer une nouvelle soumission
        $query = "INSERT INTO reponses_devoir 
                  (iddevoir, idetudiant, commentaire, fichier, date_soumission) 
                  VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$idDevoir, $studentId, $commentaire, $uniqueName]);
    }
    
    if ($result) {
        // Mettre à jour le statut du devoir pour l'étudiant
        $statut = 'Soumis';
        
        // Vérifier si un statut existe déjà
        $query = "SELECT COUNT(*) FROM statut_devoir_etudiant 
                  WHERE iddevoir = ? AND idetudiant = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$idDevoir, $studentId]);
        
        if ($stmt->fetchColumn() > 0) {
            // Mettre à jour le statut existant
            $query = "UPDATE statut_devoir_etudiant 
                      SET statut = ?, date_modification = NOW() 
                      WHERE iddevoir = ? AND idetudiant = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$statut, $idDevoir, $studentId]);
        } else {
            // Créer un nouveau statut
            $query = "INSERT INTO statut_devoir_etudiant 
                      (iddevoir, idetudiant, statut, date_modification) 
                      VALUES (?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$idDevoir, $studentId, $statut]);
        }
        
        $conn->commit();
    } else {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la soumission du devoir'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Déterminer le message en fonction de si c'était une nouvelle soumission ou une mise à jour
    $message = $reponseExistante ? 'Réponse au devoir mise à jour avec succès' : 'Réponse au devoir soumise avec succès';
    
    // Récupérer les détails de la soumission pour la réponse
    $stmt = $conn->prepare("SELECT * FROM reponses_devoir WHERE iddevoir = ? AND idetudiant = ?");
    $stmt->execute([$idDevoir, $studentId]);
    $reponse = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $reponse
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
