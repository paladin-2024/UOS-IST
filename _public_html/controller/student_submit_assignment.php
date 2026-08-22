<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    echo "<script>
        alert('Vous devez être connecté pour effectuer cette action.');
        window.location.href = '../portail/login';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "<script>
        alert('Méthode non autorisée.');
        window.history.back();
    </script>";
    exit;
}

// Récupération des données
$idDevoir = isset($_POST['iddevoir']) ? intval($_POST['iddevoir']) : 0;
$idEtudiant = isset($_POST['idetudiant']) ? intval($_POST['idetudiant']) : 0;
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

// Validation des données
if ($idDevoir <= 0 || $idEtudiant <= 0) {
    echo "<script>
        alert('Données invalides.');
        window.history.back();
    </script>";
    exit;
}

// Vérifier que l'étudiant connecté est bien celui qui soumet
if ($idEtudiant != $_SESSION['student_id']) {
    echo "<script>
        alert('Vous n\'êtes pas autorisé à soumettre pour un autre étudiant.');
        window.history.back();
    </script>";
    exit;
}

// Vérifier si le devoir existe et n'est pas expiré
$ecueModel = new Ecue();
$etudiantModel = new Etudiant();

$devoir = $ecueModel->getAssignmentById($idDevoir);
if (!$devoir) {
    echo "<script>
        alert('Devoir non trouvé.');
        window.history.back();
    </script>";
    exit;
}

// Vérifier si la date limite est dépassée
$now = new DateTime();
$deadline = new DateTime($devoir['date_limite']);
if ($now > $deadline) {
    echo "<script>
        alert('La date limite de soumission est dépassée.');
        window.history.back();
    </script>";
    exit;
}

// Vérifier si l'étudiant a déjà soumis une réponse
$existingResponse = $etudiantModel->getStudentAssignmentResponse($idEtudiant, $idDevoir);
if ($existingResponse) {
    echo "<script>
        alert('Vous avez déjà soumis une réponse pour ce devoir.');
        window.history.back();
    </script>";
    exit;
}

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    echo "<script>
        alert('Veuillez sélectionner un fichier valide.');
        window.history.back();
    </script>";
    exit;
}

// Traitement du fichier
$uploadDir = dirname(__DIR__) . '/uploads/reponses/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
$fileName = uniqid('rep_') . '.' . $fileExtension;
$uploadFile = $uploadDir . $fileName;

// Vérifier la taille du fichier (max 10 Mo)
if ($_FILES['fichier']['size'] > 10 * 1024 * 1024) {
    echo "<script>
        alert('Le fichier est trop volumineux (max 10 Mo).');
        window.history.back();
    </script>";
    exit;
}

// Vérifier les types de fichiers autorisés
$allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'txt'];
if (!in_array(strtolower($fileExtension), $allowedTypes)) {
    echo "<script>
        alert('Type de fichier non autorisé. Les formats acceptés sont: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, RAR, TXT.');
        window.history.back();
    </script>";
    exit;
}

if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
    echo "<script>
        alert('Erreur lors de l\'upload du fichier.');
        window.history.back();
    </script>";
    exit;
}

// Enregistrer la réponse dans la base de données
$result = $etudiantModel->submitAssignmentResponse($idDevoir, $idEtudiant, $fileName, $commentaire);

if ($result) {
    echo "<script>
        alert('Votre réponse a été soumise avec succès.');
        window.location.href = '../portail/student';
    </script>";
} else {
    // Supprimer le fichier en cas d'échec
    if (file_exists($uploadFile)) {
        unlink($uploadFile);
    }
    
    echo "<script>
        alert('Une erreur est survenue lors de l\'enregistrement de votre réponse.');
        window.history.back();
    </script>";
}
?>
