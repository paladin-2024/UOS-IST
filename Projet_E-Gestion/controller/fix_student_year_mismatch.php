<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

$connexion = Connexion::getInstance()->getPDO();
$universite = new Universite();

try {
    

    // Cas 1: Correction d'un seul étudiant via JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['studentId']) || empty($data['newYearId'])) {
            throw new Exception('Paramètres manquants');
        }
        
        $studentId = (int)$data['studentId'];
        $newYearId = (int)$data['newYearId'];
        
        // Mettre à jour l'étudiant
        $updateQuery = "UPDATE etudiant SET annee_acad_idannee_acad = ? WHERE idetudiant = ?";
        $stmt = $connexion->prepare($updateQuery);
        $stmt->execute([$newYearId, $studentId]);
        
        // Récupérer les infos pour le log
        $selectQuery = "SELECT e.matricule, e.noms, aa.designation 
                       FROM etudiant e
                       JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                       WHERE e.idetudiant = ?";
        $stmtSelect = $connexion->prepare($selectQuery);
        $stmtSelect->execute([$studentId]);
        $studentInfo = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Étudiant ' . $studentInfo['matricule'] . ' corrigé - Année: ' . $studentInfo['designation']
        ]);
    }
    // Cas 2: Correction de tous les étudiants via POST form
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fixAllBtn'])) {
        
        // Trouver tous les étudiants avec incohérence
        $queryMismatch = "SELECT 
            e.idetudiant,
            e.matricule,
            e.noms,
            p.annee_acad_idannee_acad as promotion_annee
        FROM etudiant e
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        WHERE e.promotion_idpromotion IS NOT NULL 
        AND e.annee_acad_idannee_acad IS NOT NULL
        AND e.annee_acad_idannee_acad != p.annee_acad_idannee_acad";
        
        $stmt = $connexion->prepare($queryMismatch);
        $stmt->execute();
        $mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correctedCount = 0;
        
        foreach ($mismatches as $student) {
            $updateQuery = "UPDATE etudiant SET annee_acad_idannee_acad = ? WHERE idetudiant = ?";
            $stmtUpdate = $connexion->prepare($updateQuery);
            if ($stmtUpdate->execute([$student['promotion_annee'], $student['idetudiant']])) {
                $correctedCount++;
            }
        }
        
        // Redirection avec message
        header('Location: ../fix_student_promotion_year_mismatch.php?status=success&fixed=' . $correctedCount);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
