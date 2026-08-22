<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'connexion.php';
require_once 'auth.php';

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

if (!isset($data['matricule']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Matricule et mot de passe requis']);
    exit();
}

$matricule = $data['matricule'];
$password = $data['password'];

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Find student by matricule
    $stmt = $conn->prepare("SELECT idetudiant, matricule, noms, adressemail, pwd, photo 
                           FROM etudiant WHERE matricule = ?");
    $stmt->execute([$matricule]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Matricule ou mot de passe incorrect']);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $student['pwd'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Matricule ou mot de passe incorrect']);
        exit();
    }
    
    // Generate token
    $auth = new Auth();
    $token = $auth->generateToken($student['idetudiant']);
    
    if (!$token) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération du token']);
        exit();
    }
    
    // Get additional student info
    $stmt = $conn->prepare('SELECT e.*, p."designationPromotion", p.cycle, p.est_terminale,
                           o."designationOrientation", s."designationSection",
                           a.designation as annee_academique
                           FROM etudiant e
                           JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                           JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           JOIN section s ON o.section_idsection = s.idsection
                           JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                           WHERE e.idetudiant = ?');
    $stmt->execute([$student['idetudiant']]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $photoUrl = null;
    if ($student['photo']) {
        // Convertir l'URL relative en chemin absolu du serveur
        $relativePath = str_replace('https://istmbeni.info/', '', $student['photo']);
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        
        // Vérifier si le fichier existe physiquement sur le serveur
        if (file_exists($serverPath)) {
            $photoUrl = 'https://istmbeni.info/' . $student['photo'];
        } else {
            // Essayer de trouver le fichier avec le motif du nom (pour les noms avec timestamp)
            if (preg_match('/etudiant_(\d+)_/', $relativePath, $matches)) {
                $studentId = $matches[1];
                $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/photos_etudiants/etudiant_' . $studentId . '_*.jpg';
                $files = glob($pattern);
                
                if (!empty($files)) {
                    // Utiliser le premier fichier correspondant trouvé
                    $foundImage = str_replace($_SERVER['DOCUMENT_ROOT'], '', $files[0]);
                    $photoUrl = 'https://istmbeni.info/' . $foundImage;
                }
            }
        }
    }
    
    // Return user data and token
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $student['idetudiant'],
                'matricule' => $student['matricule'],
                'name' => $student['noms'],
                'email' => $student['adressemail'],
                'photo' => $photoUrl,
                'promotion' => $studentInfo['designationPromotion'],
                'cycle' => $studentInfo['cycle'],
                'orientation' => $studentInfo['designationOrientation'],
                'section' => $studentInfo['designationSection'],
                'academic_year' => $studentInfo['annee_academique'],
                'est_terminale' => $studentInfo['est_terminale']
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
