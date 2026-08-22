<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'ID de l'étudiant est fourni
if (!isset($_GET['studentId']) || empty($_GET['studentId'])) {
    echo json_encode(['success' => false, 'message' => 'ID étudiant non spécifié']);
    exit;
}

$studentId = intval($_GET['studentId']);
$pdo = Connexion::getInstance()->getPDO();

try {
    // Récupérer l'année académique active
    $stmtAnnee = $pdo->prepare("SELECT idannee_acad FROM annee_acad ORDER BY dateCreation DESC LIMIT 1");
    $stmtAnnee->execute();
    $anneeAcad = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    $idAnneeAcad = $anneeAcad['idannee_acad'] ?? null;

    if (!$idAnneeAcad) {
        echo json_encode(['success' => false, 'message' => 'Aucune année académique active trouvée']);
        exit;
    }
    
    // Récupérer les documents déjà téléchargés par l'étudiant
    $stmt = $pdo->prepare("
        SELECT ed.*, do.designation, do.est_obligatoire
        FROM etudiant_documents ed
        LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
        WHERE ed.idetudiant = :studentId AND ed.annee_acad_id = :anneeAcadId
    ");
    
    $stmt->execute([
        'studentId' => $studentId,
        'anneeAcadId' => $idAnneeAcad
    ]);
    
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
