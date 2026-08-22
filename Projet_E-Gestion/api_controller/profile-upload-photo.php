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

try {
    // Check if file was uploaded
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucun fichier téléchargé ou erreur de téléchargement']);
        exit();
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $fileType = $_FILES['photo']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPG ou PNG']);
        exit();
    }
    
    // Validate file size (max 5MB)
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Taille de fichier trop grande. Maximum 5MB']);
        exit();
    }
    
    // Get student info to generate filename
    $conn = Connexion::getInstance()->getPDO();
    $stmt = $conn->prepare("SELECT matricule FROM etudiant WHERE idetudiant = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = $student['matricule'] . '_' . time() . '.' . $extension;
    $uploadPath = 'https://istmbeni.info/uploads/photos_etudiants/' . $filename; // Update with actual server path
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement du fichier']);
        exit();
    }
    
    // Update database
    $uploadDirFile = 'uploads/photos/'.$filename;
    $stmt = $conn->prepare("UPDATE etudiant SET photo = ? WHERE idetudiant = ?");
    $stmt->execute([$uploadDirFile, $studentId]);
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Photo téléchargée avec succès',
        'data' => [
            'photo_url' => 'https://istmbeni.info/' . $uploadDirFile
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
