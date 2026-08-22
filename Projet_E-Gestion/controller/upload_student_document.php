<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier les données nécessaires
if (!isset($_POST['docId']) || !isset($_POST['studentId']) || !isset($_POST['matricule']) || !isset($_POST['anneeAcadId'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

// Vérifier si un fichier a été téléchargé
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier téléchargé ou erreur de téléchargement']);
    exit;
}

$docId = intval($_POST['docId']);
$studentId = intval($_POST['studentId']);
$matricule = trim($_POST['matricule']);
$anneeAcadId = intval($_POST['anneeAcadId']);
$pdo = Connexion::getInstance()->getPDO();

try {
    // Vérifier si le document obligatoire existe
    $stmtDoc = $pdo->prepare("SELECT * FROM documents_obligatoires WHERE id = :docId");
    $stmtDoc->execute(['docId' => $docId]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document obligatoire non trouvé']);
        exit;
    }
    
    // Vérifier l'étudiant
    $stmtStudent = $pdo->prepare("SELECT * FROM etudiant WHERE idetudiant = :studentId AND matricule = :matricule");
    $stmtStudent->execute(['studentId' => $studentId, 'matricule' => $matricule]);
    
    if (!$stmtStudent->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Traitement du fichier
    $uploadDir = dirname(__DIR__) . '/uploads/documents/';
    
    // Créer le répertoire s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Vérifier le type de fichier
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['document']['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Format de fichier non autorisé. Utilisez PDF, JPG ou PNG.']);
        exit;
    }
    
    // Vérifier la taille du fichier (5 Mo max)
    $maxSize = 5 * 1024 * 1024; // 5 Mo
    if ($_FILES['document']['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'La taille du fichier ne doit pas dépasser 5 Mo.']);
        exit;
    }
    
    // Générer un nom de fichier unique
    $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    $fileName = 'doc_' . $matricule . '_' . $docId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    
        // Déplacer le fichier téléchargé
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du déplacement du fichier.']);
            exit;
        }
        
        $filePath = 'uploads/documents/' . $fileName;
        $titre = $_FILES['document']['name'];
        
        // Vérifier si un document pour ce type existe déjà
        $stmtCheck = $pdo->prepare("
            SELECT id FROM etudiant_documents 
            WHERE idetudiant = :studentId AND document_obligatoire_id = :docId AND annee_acad_id = :anneeAcadId
        ");
        $stmtCheck->execute([
            'studentId' => $studentId,
            'docId' => $docId,
            'anneeAcadId' => $anneeAcadId
        ]);
        
        $existingDoc = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existingDoc) {
            // Mettre à jour le document existant
            $stmt = $pdo->prepare("
                UPDATE etudiant_documents
                SET chemin_fichier = :cheminFichier,
                    titre = :titre,
                    description = :description,
                    date_ajout = NOW(),
                    statut = 'En attente de validation'
                WHERE id = :id
            ");
            
            $stmt->execute([
                'cheminFichier' => $filePath,
                'titre' => $titre,
                'description' => 'Document mis à jour le ' . date('d/m/Y à H:i'),
                'id' => $existingDoc['id']
            ]);
        } else {
            // Insérer un nouveau document
            $stmt = $pdo->prepare("
                INSERT INTO etudiant_documents 
                (idetudiant, matricule, document_obligatoire_id, type_document, titre, description, chemin_fichier, date_ajout, annee_acad_id, idUser, statut)
                VALUES 
                (:studentId, :matricule, :docId, :typeDocument, :titre, :description, :cheminFichier, NOW(), :anneeAcadId, :idUser, 'En attente de validation')
            ");
            
            $stmt->execute([
                'studentId' => $studentId,
                'matricule' => $matricule,
                'docId' => $docId,
                'typeDocument' => $document['designation'],
                'titre' => $titre,
                'description' => 'Document ajouté le ' . date('d/m/Y à H:i'),
                'cheminFichier' => $filePath,
                'anneeAcadId' => $anneeAcadId,
                'idUser' => $_SESSION['idUser'] ?? 1 // Utilisateur par défaut si non connecté
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Document téléchargé avec succès',
            'filePath' => $filePath
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
    }
    