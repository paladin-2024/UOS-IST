<?php
session_start();

// Vérifier que l'utilisateur est connecté et est un étudiant
// Accepter deux formats de session: portail étudiant (student_id) et admin/agent (id + idRole)
$studentId = $_SESSION['student_id'] ?? $_SESSION['id'] ?? null;
$isStudent = isset($_SESSION['student_id']) || ($_SESSION['idRole'] ?? null) == 3;

if (!$studentId || !$isStudent) {
    http_response_code(403);
    // Vérifier si c'est une requête AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    } else {
        $_SESSION['error_message'] = 'Accès refusé';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?view=memoire'));
    }
    exit;
}

require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/Etudiant.php';
$sujetId = $_POST['sujet_id'] ?? null;

if (!$sujetId) {
    $_SESSION['error_message'] = 'ID du sujet non fourni.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Vérifier que l'étudiant a payé tous les frais de mémoire requis
try {
    $etudiantModel = new Etudiant();
    if (!$etudiantModel->hasStudentPaidMemoireFees($studentId)) {
        $_SESSION['error_message'] = 'Vous devez d\'abord payer tous les frais requis pour le dépôt du mémoire.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
} catch (Exception $e) {
    error_log("Erreur lors de la vérification des frais: " . $e->getMessage());
    $_SESSION['error_message'] = 'Erreur lors de la vérification de votre statut de paiement.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Valider que le fichier est fourni
if (!isset($_FILES['memoire_file'])) {
    $_SESSION['error_message'] = 'Aucun fichier n\'a été fourni.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$file = $_FILES['memoire_file'];

// Valider le fichier
$maxSize = 50 * 1024 * 1024; // 50MB

if ($file['size'] > $maxSize) {
    $_SESSION['error_message'] = 'Le fichier dépasse la taille maximale de 50MB.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

if ($file['type'] !== 'application/pdf') {
    $_SESSION['error_message'] = 'Le fichier doit être au format PDF.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = 'Erreur lors de l\'upload du fichier.';
    error_log("Upload error: " . $file['error']);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier que le sujet appartient à l'étudiant
    $query = "SELECT idsujets FROM sujets WHERE idsujets = :sujetId AND etudiant_idetudiant = :studentId";
    $stmt = $db->prepare($query);
    $stmt->execute(['sujetId' => $sujetId, 'studentId' => $studentId]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = 'Ce sujet ne vous appartient pas.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Créer le répertoire de destination s'il n'existe pas
    $uploadDir = __DIR__ . '/../uploads/memoires/';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            $_SESSION['error_message'] = 'Impossible de créer le répertoire de destination.';
            error_log("Failed to create directory: $uploadDir");
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }
    
    // Générer un nom de fichier unique
    $filename = 'memoire_' . $sujetId . '_' . time() . '.pdf';
    $filepath = $uploadDir . $filename;
    // Chemin relatif à la racine de l'app pour la BD
    $relativePath = 'uploads/memoires/' . $filename;
    error_log("Upload memoire - uploadDir: $uploadDir, filepath: $filepath, relativePath: $relativePath");
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $_SESSION['error_message'] = 'Erreur lors de la sauvegarde du fichier.';
        error_log("Failed to move uploaded file to: $filepath");
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Vérifier s'il existe déjà un dépôt pour ce sujet
    error_log("DEBUG: Checking for existing depot with sujetId = $sujetId");
    $query = "SELECT \"idDepot\" FROM depot_memoire WHERE sujets_idsujets = :sujetId";
    $stmt = $db->prepare($query);
    $checkResult = $stmt->execute(['sujetId' => $sujetId]);
    error_log("DEBUG: Check query result = " . ($checkResult ? 'true' : 'false'));
    $existingDepot = $stmt->fetch();
    error_log("DEBUG: Existing depot = " . ($existingDepot ? 'yes' : 'no'));
    
    if ($existingDepot) {
        // Supprimer l'ancien fichier
        $query = "SELECT fichier FROM depot_memoire WHERE sujets_idsujets = :sujetId";
        $stmt = $db->prepare($query);
        $stmt->execute(['sujetId' => $sujetId]);
        $oldFile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($oldFile && file_exists(__DIR__ . '/../' . $oldFile['fichier'])) {
            unlink(__DIR__ . '/../' . $oldFile['fichier']);
        }
        
        // Mettre à jour le dépôt existant
        $query = "UPDATE depot_memoire 
                  SET fichier = :fichier, \"dateDepot\" = CURRENT_TIMESTAMP
                  WHERE sujets_idsujets = :sujetId";
        $stmt = $db->prepare($query);
        $result = $stmt->execute(['fichier' => $relativePath, 'sujetId' => $sujetId]);
        if (!$result) {
            error_log("UPDATE depot_memoire échoué - Erreur: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("UPDATE depot_memoire réussi - Sujet: $sujetId");
        }
    } else {
        // Créer un nouveau dépôt
        $query = "INSERT INTO depot_memoire (fichier, observation, sujets_idsujets) 
                  VALUES (:fichier, 'Dépôt par l\'étudiant', :sujetId)";
        $stmt = $db->prepare($query);
        $result = $stmt->execute(['fichier' => $relativePath, 'sujetId' => $sujetId]);
        if (!$result) {
            error_log("INSERT depot_memoire échoué - Erreur: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("INSERT depot_memoire réussi - Sujet: $sujetId, Fichier: $relativePath");
        }
    }
    
    // Enregistrer l'action dans le log
    error_log("Mémoire uploadé avec succès - Étudiant: $studentId, Sujet: $sujetId, Fichier: $filename");
    
    $_SESSION['success_message'] = 'Votre mémoire a été uploadé avec succès!';
    
    } catch (Exception $e) {
    error_log("Erreur lors de l'upload du mémoire: " . $e->getMessage());
    $_SESSION['error_message'] = 'Une erreur est survenue lors de l\'upload: ' . $e->getMessage();
    }

    // Rediriger vers la page des mémoires du portail
    header('Location: ../portail/memoire');
    exit;
?>
