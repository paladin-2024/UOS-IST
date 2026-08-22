<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'connexion.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['matricule']) || !isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Matricule et email requis']);
    exit();
}

$matricule = $data['matricule'];
$email = $data['email'];

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Find student by matricule and email
    $stmt = $conn->prepare("SELECT idetudiant, noms FROM etudiant WHERE matricule = ? AND adressemail = ?");
    $stmt->execute([$matricule, $email]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aucun compte trouvé avec ces informations']);
        exit();
    }
    
    // Generate temporary password
    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE etudiant SET pwd = ? WHERE idetudiant = ?");
    $stmt->execute([$hashedPassword, $student['idetudiant']]);
    
    // Send email with temporary password
    $to = $email;
    $subject = "ISTM BENI - Réinitialisation de mot de passe";
    $message = "Bonjour " . $student['noms'] . ",\n\n";
    $message .= "Votre mot de passe temporaire est: " . $tempPassword . "\n\n";
    $message .= "Veuillez vous connecter avec ce mot de passe et le changer immédiatement.\n\n";
    $message .= "Cordialement,\nL'équipe ISTM BENI";
    $headers = "From: noreply@istmbeni.info";
    
    mail($to, $subject, $message, $headers);
    
    echo json_encode([
        'success' => true,
        'message' => 'Un mot de passe temporaire a été envoyé à votre adresse email'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
