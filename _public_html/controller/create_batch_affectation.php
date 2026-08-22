<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../ur/affecation_ur');
    exit;
}

// Récupérer les données du formulaire
$idSection = isset($_POST['idSection']) ? intval($_POST['idSection']) : 0;
$idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
$enseignants = isset($_POST['enseignants']) ? $_POST['enseignants'] : [];

// Validation des données
if (empty($idSection) || empty($idSpecialisation) || empty($enseignants)) {
    $_SESSION['error'] = "Veuillez fournir toutes les informations requises.";
    header('Location: ../ur/affecation_ur&section=' . $idSection);
    exit;
}

$db = Connexion::getInstance()->getPDO();
$db->beginTransaction();

try {
    $insertedCount = 0;
    $alreadyExistsCount = 0;
    
    foreach ($enseignants as $idAgent) {
        // Vérifier si l'affectation existe déjà
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM enseignant_specialisation 
                                WHERE \"idAgent\" = ? AND \"idSpecialisation\" = ?");
        $stmtCheck->execute([$idAgent, $idSpecialisation]);
        $exists = $stmtCheck->fetchColumn();
        
        if ($exists) {
            $alreadyExistsCount++;
            continue;
        }
        
        // Insérer la nouvelle affectation
        $stmt = $db->prepare("INSERT INTO enseignant_specialisation 
                             (\"idAgent\", \"idSpecialisation\", \"dateAffectation\", \"idUser\") 
                             VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$idAgent, $idSpecialisation, $_SESSION['id'] ?? 1]);
        $insertedCount++;
    }
    
    $db->commit();
    
    if ($insertedCount > 0) {
        $_SESSION['success'] = "Affectation par lot réussie pour $insertedCount enseignant(s).";
        if ($alreadyExistsCount > 0) {
            $_SESSION['success'] .= " ($alreadyExistsCount affectation(s) existaient déjà)";
        }
    } else {
        $_SESSION['warning'] = "Aucune nouvelle affectation créée. Toutes les affectations existaient déjà.";
    }
    
} catch (PDOException $e) {
    $db->rollBack();
    $_SESSION['error'] = "Erreur lors de l'affectation par lot: " . $e->getMessage();
}

header('Location: ../ur/affecation_ur&section=' . $idSection);
exit;