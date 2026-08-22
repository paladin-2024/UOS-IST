<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier si le matricule est fourni
if (!isset($_POST['matricule']) || empty($_POST['matricule'])) {
    echo json_encode(['success' => false, 'message' => 'Matricule non fourni']);
    exit;
}

$matricule = trim($_POST['matricule']);
$pdo = Connexion::getInstance()->getPDO();

try {
    // Vérifier si l'étudiant existe avec ce matricule
    $stmt = $pdo->prepare("
        SELECT e.*, p.cycle, a.designation as annee_academique
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
        WHERE e.matricule = :matricule AND e.est_actif = 1
    ");
    $stmt->execute([
        'matricule' => $matricule
    ]);
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé ou non actif pour l\'année académique en cours']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
