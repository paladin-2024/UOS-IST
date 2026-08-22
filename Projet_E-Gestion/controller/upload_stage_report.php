<?php
session_start();
require_once '../config/Connexion.php';

// Log de débogage
error_log("=== UPLOAD STAGE REPORT START ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("SESSION student_id: " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'NOT SET'));

// Vérifier l'authentification - utiliser student_id pour le portail étudiant
if (!isset($_SESSION['student_id'])) {
    error_log("ERROR: student_id not in session");
    $_SESSION['error_message'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index.php?view=login');
    exit;
}

if (isset($_POST['uploadReportBtn'])) {
    error_log("uploadReportBtn is set, processing upload...");
    $stageId = $_POST['stage_id'];
    $studentId = $_SESSION['student_id'];  // Utiliser student_id au lieu de etudiant_id
    
    error_log("Stage ID: $stageId, Student ID: $studentId");
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Vérifier que le stage appartient bien à l'étudiant
        $checkStage = $db->prepare("SELECT idstage FROM stage_assignments WHERE idstage = ? AND idetudiant = ?");
        $checkStage->execute([$stageId, $studentId]);
        
        if (!$checkStage->fetch()) {
            throw new Exception("Stage invalide ou n'appartenant pas à cet étudiant.");
        }
        
        // Vérifier le fichier uploadé
        if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors de l'upload du fichier.");
        }
        
        $file = $_FILES['report_file'];
        
        // Vérifier que c'est un PDF
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileType != 'pdf') {
            throw new Exception("Le fichier doit être au format PDF.");
        }
        
        // Vérifier la taille du fichier (max 10MB)
        if ($file['size'] > 10485760) {
            throw new Exception("Le fichier ne doit pas dépasser 10MB.");
        }
        
        // Créer le dossier de destination si nécessaire
        $uploadDir = '../uploads/stage_reports/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Générer un nom unique pour le fichier
        $fileName = 'rapport_stage_' . $studentId . '_' . $stageId . '_' . date('YmdHis') . '.pdf';
        $uploadPath = $uploadDir . $fileName;
        $dbPath = 'uploads/stage_reports/' . $fileName;
        
        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Erreur lors de l'enregistrement du fichier.");
        }
        
        // Mettre à jour la base de données
        $updateStage = $db->prepare("UPDATE stage_assignments SET rapport_path = ? WHERE idstage = ?");
        $updateStage->execute([$dbPath, $stageId]);
        
        $_SESSION['success_message'] = "Votre rapport de stage a été envoyé avec succès.";
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'upload du rapport: " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
}

// Redirection vers la page des stages
header('Location: ../portail/stage');
exit;
?>