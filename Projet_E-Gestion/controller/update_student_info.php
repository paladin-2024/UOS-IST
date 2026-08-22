<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données du formulaire
$studentId = isset($_POST['studentId']) ? intval($_POST['studentId']) : 0;
$matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
$noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
$lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
$dateNaissance = isset($_POST['dateNaissance']) ? trim($_POST['dateNaissance']) : null;
$sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
$nationalite = isset($_POST['nationalite']) ? trim($_POST['nationalite']) : '';
$adressemail = isset($_POST['adressemail']) ? trim($_POST['adressemail']) : '';
$telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
$adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
$personne_contact = isset($_POST['personne_contact']) ? trim($_POST['personne_contact']) : '';
$telephone_contact = isset($_POST['telephone_contact']) ? trim($_POST['telephone_contact']) : '';

// Validation de base
if (empty($studentId) || empty($matricule) || empty($noms)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides ou manquantes']);
    exit;
}

$pdo = Connexion::getInstance()->getPDO();
$photoPath = null;

try {
    // Vérifier d'abord si l'étudiant existe
    $checkStudent = $pdo->prepare("SELECT * FROM etudiant WHERE idetudiant = :studentId AND matricule = :matricule");
    $checkStudent->execute(['studentId' => $studentId, 'matricule' => $matricule]);
    
    if (!$checkStudent->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Traitement de la photo si elle existe
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/etudiants/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES['photo']['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Format de fichier non autorisé. Utilisez JPG ou PNG.']);
            exit;
        }
        
        // Vérifier la taille du fichier (2 Mo max)
        $maxSize = 2 * 1024 * 1024; // 2 Mo
        if ($_FILES['photo']['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'La taille du fichier ne doit pas dépasser 2 Mo.']);
            exit;
        }
        
        // Générer un nom de fichier unique
        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $fileName = 'etudiant_' . $matricule . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;
        
        // Déplacer le fichier téléchargé
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $photoPath = 'uploads/etudiants/' . $fileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement de la photo.']);
            exit;
        }
    } else if (!empty($_POST['existingPhoto'])) {
        // Conserver la photo existante
        $photoPath = $_POST['existingPhoto'];
    }
    
    // Mettre à jour les informations de l'étudiant
    $stmt = $pdo->prepare("
        UPDATE etudiant
        SET noms = :noms,
            \"lieuNaissance\" = :\"lieuNaissance\",
            \"dateNaissance\" = :\"dateNaissance\",
            sexe = :sexe,
            nationalite = :nationalite,
            adressemail = :adressemail,
            telephone = :telephone,
            adresse = :adresse,
            personne_contact = :personne_contact,
            telephone_contact = :telephone_contact
            " . ($photoPath ? ", photo = :photo" : "") . "
        WHERE idetudiant = :studentId AND matricule = :matricule
    ");
    
    $params = [
        'noms' => $noms,
        'lieuNaissance' => $lieuNaissance,
        'dateNaissance' => $dateNaissance ?: null,
        'sexe' => $sexe,
        'nationalite' => $nationalite,
        'adressemail' => $adressemail,
        'telephone' => $telephone,
        'adresse' => $adresse,
        'personne_contact' => $personne_contact,
        'telephone_contact' => $telephone_contact,
        'studentId' => $studentId,
        'matricule' => $matricule
    ];
    
    if ($photoPath) {
        $params['photo'] = $photoPath;
    }
    
    $stmt->execute($params);
    
    // Récupérer les données mises à jour, que l'UPDATE ait modifié des lignes ou non
    $stmtSelect = $pdo->prepare("SELECT * FROM etudiant WHERE idetudiant = :studentId");
    $stmtSelect->execute(['studentId' => $studentId]);
    $updatedStudent = $stmtSelect->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Informations mises à jour avec succès',
        'updatedData' => $updatedStudent
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
