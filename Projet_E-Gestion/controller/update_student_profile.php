<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/models/Universite.php';

header('Content-Type: application/json');

// Initialiser la réponse
$response = [
    'success' => false,
    'message' => 'Erreur lors de la mise à jour du profil',
    'student' => null
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $studentId = isset($_POST['studentId']) ? intval($_POST['studentId']) : 0;
    
    if ($studentId <= 0) {
        $response['message'] = 'ID étudiant invalide';
        echo json_encode($response);
        exit;
    }
    
    // Vérifier les champs obligatoires
    $requiredFields = ['noms', 'sexe', 'nationalite', 'adressemail', 'telephone'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $response['message'] = 'Veuillez remplir tous les champs obligatoires: ' . implode(', ', $missingFields);
        echo json_encode($response);
        exit;
    }
    
    // Traiter la photo si elle est téléchargée
    $photoPath = isset($_POST['existingPhoto']) ? $_POST['existingPhoto'] : null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/students/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = 'student_' . $studentId . '_' . time() . '_' . basename($_FILES['photo']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        // Vérifier le type de fichier
        $fileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $response['message'] = 'Seuls les fichiers JPG, JPEG et PNG sont autorisés pour la photo de profil';
            echo json_encode($response);
            exit;
        }
        
        // Vérifier la taille du fichier (max 2 Mo)
        if ($_FILES['photo']['size'] > 2097152) {
            $response['message'] = 'La taille de la photo ne doit pas dépasser 2 Mo';
            echo json_encode($response);
            exit;
        }
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photoPath = 'uploads/students/' . $fileName;
        }
    }
    
    // Préparer les données à mettre à jour
    $studentData = [
        'idetudiant' => $studentId,
        'noms' => $_POST['noms'],
        'lieuNaissance' => $_POST['lieuNaissance'] ?? '',
        'dateNaissance' => $_POST['dateNaissance'] ?? '',
        'sexe' => $_POST['sexe'],
        'nationalite' => $_POST['nationalite'],
        'adressemail' => $_POST['adressemail'],
        'telephone' => $_POST['telephone'],
        'adresse' => $_POST['adresse'] ?? '',
        'personne_contact' => $_POST['personne_contact'] ?? '',
        'telephone_contact' => $_POST['telephone_contact'] ?? '',
        'photo' => $photoPath
    ];
    
    // Mettre à jour le profil
    $universite = new Universite();
    $result = $universite->updateStudentProfile($studentData['idetudiant'], 
        $studentData['noms'],
        $studentData['lieuNaissance'],
        $studentData['dateNaissance'],
        $studentData['sexe'],
        $studentData['nationalite'],
        $studentData['adressemail'],
        $studentData['telephone'],
        $studentData['adresse'],
        $studentData['personne_contact'],
        $studentData['telephone_contact'],
        $studentData['photo']
    );
    
    if ($result) {
        // Récupérer les données mises à jour
        $updatedStudent = $universite->getStudentById($studentId);
        
        $response['success'] = true;
        $response['message'] = 'Profil mis à jour avec succès';
        $response['student'] = $updatedStudent;
    }
}
echo json_encode($response);