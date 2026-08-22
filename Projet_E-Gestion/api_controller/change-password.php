<?php
header('Content-Type: application/json');
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
$studentId = $auth->authenticate();

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['current_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mot de passe actuel et nouveau mot de passe requis']);
    exit();
}

$currentPassword = $data['current_password'];
$newPassword = $data['new_password'];

// Validate new password
if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères']);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Get current password
    $stmt = $conn->prepare("SELECT pwd FROM etudiant WHERE idetudiant = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $student['pwd'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE etudiant SET pwd = ? WHERE idetudiant = ?");
    $stmt->execute([$hashedPassword, $studentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe modifié avec succès'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
