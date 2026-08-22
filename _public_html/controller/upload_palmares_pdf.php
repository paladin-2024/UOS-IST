<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// Vérifier si l'ID du palmarès est fourni
if (!isset($_POST['idpalmares']) || empty($_POST['idpalmares'])) {
    echo json_encode(['success' => false, 'message' => 'ID du palmarès non spécifié.']);
    exit;
}

$idPalmares = intval($_POST['idpalmares']);

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['fichier_scanne']) || $_FILES['fichier_scanne']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'Aucun fichier ou erreur lors de l\'upload.';
    
    if (isset($_FILES['fichier_scanne'])) {
        switch ($_FILES['fichier_scanne']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = 'Le fichier dépasse la taille maximale autorisée par PHP.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'Le fichier dépasse la taille maximale autorisée par le formulaire.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'Le fichier n\'a été que partiellement téléchargé.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'Aucun fichier n\'a été téléchargé.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Dossier temporaire manquant.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Échec de l\'écriture du fichier sur le disque.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = 'Une extension PHP a arrêté l\'upload du fichier.';
                break;
        }
    }
    
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

// Vérifier le type de fichier
$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fileInfo, $_FILES['fichier_scanne']['tmp_name']);
finfo_close($fileInfo);

if ($mimeType !== 'application/pdf') {
    echo json_encode(['success' => false, 'message' => 'Seuls les fichiers PDF sont acceptés.']);
    exit;
}

// Vérifier la taille du fichier (max 10MB)
if ($_FILES['fichier_scanne']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'La taille du fichier ne doit pas dépasser 10 MB.']);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Vérifier si le palmarès existe
    $query = "SELECT * FROM palmares_archives WHERE idpalmares = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
    $stmt->execute();
    $palmares = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$palmares) {
        echo json_encode(['success' => false, 'message' => 'Palmarès introuvable.']);
        exit;
    }
    
    // Créer le répertoire d'upload s'il n'existe pas
    $uploadDir = dirname(__DIR__) . '/uploads/palmares/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $newFilename = 'palmares_' . $idPalmares . '_' . time() . '_' . uniqid() . '.pdf';
    $uploadFile = $uploadDir . $newFilename;
    
    // Déplacer le fichier téléchargé
    if (move_uploaded_file($_FILES['fichier_scanne']['tmp_name'], $uploadFile)) {
        // Mettre à jour le chemin du fichier dans la base de données
        $relativePath = 'uploads/palmares/' . $newFilename;
        
        $updateQuery = "UPDATE palmares_archives SET fichier_scanne = :fichier_scanne WHERE idpalmares = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':fichier_scanne', $relativePath);
        $updateStmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Le fichier a été téléchargé avec succès.',
            'file_path' => $relativePath
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du déplacement du fichier téléchargé.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
