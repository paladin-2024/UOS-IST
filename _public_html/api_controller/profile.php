<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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

// Handle profile photo upload
if (isset($_GET['upload-photo'])) {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit();
    }
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune photo valide fournie']);
        exit();
    }
    
    $uploadDir = $_SERVER['DOCUMENT_ROOT'].'/uploads/photos_etudiants/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format de fichier non autorisé. Utilisez JPG, JPEG ou PNG']);
        exit();
    }
    
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['photo']['size'] > $maxFileSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La taille du fichier ne doit pas dépasser 5MB']);
        exit();
    }
    
    $fileName = 'etudiant_' . $studentId . '_' . time() . '.' . $fileExtension;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
        try {
            $conn = Connexion::getInstance()->getPDO();
            
            // Get current photo to delete it
            $stmt = $conn->prepare("SELECT photo FROM etudiant WHERE idetudiant = ?");
            $stmt->execute([$studentId]);
            $currentPhoto = $stmt->fetchColumn();
            
            if ($currentPhoto && file_exists($uploadDir . $currentPhoto)) {
                unlink($uploadDir . $currentPhoto);
            }
            
            // Update photo in database
            $uploadDirFile = 'uploads/photos_etudiants/'.$fileName;
            $stmt = $conn->prepare("UPDATE etudiant SET photo = ? WHERE idetudiant = ?");
            $stmt->execute([$uploadDirFile, $studentId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Photo de profil mise à jour avec succès',
                'data' => [
                    'photo_url' => 'https://istmbeni.info/' . $uploadDirFile
                ]
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec du téléchargement de la photo']);
    }
    
    exit();
}

// Handle profile update
if (isset($_GET['update'])) {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit();
    }
    
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Fields that can be updated
    $allowedFields = [
        'telephone', 
        'adresse', 
        'personne_contact', 
        'telephone_contact'
    ];
    
    $updateData = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune donnée valide à mettre à jour']);
        exit();
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Add student ID to params
        $params[] = $studentId;
        
        // Update profile
        $sql = "UPDATE etudiant SET " . implode(', ', $updateData) . " WHERE idetudiant = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Get updated profile
        $stmt = $conn->prepare('SELECT e.*, p."designationPromotion", p.cycle,
                               o."designationOrientation", s."designationSection",
                               a.designation as annee_academique
                               FROM etudiant e
                               JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                               JOIN orientation o ON p.orientation_idorientation = o.idorientation
                               JOIN section s ON o.section_idsection = s.idsection
                               JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                               WHERE e.idetudiant = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'data' => [
                'id' => $student['idetudiant'],
                'matricule' => $student['matricule'],
                'name' => $student['noms'],
                'email' => $student['adressemail'],
                'phone' => $student['telephone'],
                'address' => $student['adresse'],
                'contact_person' => $student['personne_contact'],
                'contact_phone' => $student['telephone_contact'],
                'photo' => $student['photo'] ? 'hhttps://istmbeni.info/' . $student['photo'] : null,
                'gender' => $student['sexe'],
                'nationality' => $student['nationalite'],
                'birth_date' => $student['dateNaissance'],
                'birth_place' => $student['lieuNaissance'],
                'promotion' => $student['designationPromotion'],
                'cycle' => $student['cycle'],
                'orientation' => $student['designationOrientation'],
                'section' => $student['designationSection'],
                'academic_year' => $student['annee_academique']
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    
    exit();
}

// Get profile
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        $stmt = $conn->prepare('SELECT e.*, p."designationPromotion", p.cycle,
                               o."designationOrientation", s."designationSection",
                               a.designation as annee_academique
                               FROM etudiant e
                               JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                               JOIN orientation o ON p.orientation_idorientation = o.idorientation
                               JOIN section s ON o.section_idsection = s.idsection
                               JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                               WHERE e.idetudiant = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
            exit();
        }
        
            // Formater l'URL de la photo si elle existe et si le fichier est présent sur le serveur
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
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $student['idetudiant'],
                'matricule' => $student['matricule'],
                'name' => $student['noms'],
                'email' => $student['adressemail'],
                'phone' => $student['telephone'],
                'address' => $student['adresse'],
                'contact_person' => $student['personne_contact'],
                'contact_phone' => $student['telephone_contact'],
                'photo' => $photoUrl,
                'gender' => $student['sexe'],
                'nationality' => $student['nationalite'],
                'birth_date' => $student['dateNaissance'],
                'birth_place' => $student['lieuNaissance'],
                'promotion' => $student['designationPromotion'],
                'cycle' => $student['cycle'],
                'orientation' => $student['designationOrientation'],
                'section' => $student['designationSection'],
                'academic_year' => $student['annee_academique']
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    
    exit();
}

// If we get here, method not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
?>

