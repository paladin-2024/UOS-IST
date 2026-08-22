<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'connexion.php';
require_once 'auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
$auth = new Auth();
$studentId = $auth->authenticate(); //Identifiant de l'étudiant

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // S'assurer que la connexion est en UTF-8
    $conn->exec("SET NAMES utf8mb4");
    
    // Get student's current promotion and information
    $stmt = $conn->prepare('SELECT e.promotion_idpromotion, e.annee_acad_idannee_acad,
                             e.noms, e.matricule, e.photo,
                            s."designationSection", o."designationOrientation", p."designationPromotion",
                            a.designation as academic_year
                            FROM etudiant e
                            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                            JOIN orientation o ON p.orientation_idorientation = o.idorientation
                            JOIN section s ON o.section_idsection = s.idsection
                            JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                            WHERE e.idetudiant = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $promotionId = $student['promotion_idpromotion']; //Promotion 
    $academicYearId = $student['annee_acad_idannee_acad']; //Année academique
       
    // Get courses for student's promotion
    $stmt = $conn->prepare('SELECT e."idECUE", e."designationECUE", e."CMI", e."TD", e."TP",
                           u."idUE", u."designationUE", u."codeUE",
                           s."numeroSemestre",
                           a.noms, gr.designation as titre
                           FROM ecue e
                           JOIN ue u ON e."UE_idUE" = u."idUE"
                           JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                           JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                           LEFT JOIN enseignant_ecue ee ON e."idECUE" = ee."idECUE" AND ee."anneeAcad" = ?
                           LEFT JOIN agent a ON ee."idAgent" = a."idAgent"
                           LEFT JOIN grade gr ON gr.idgrade = a.grade_id
                           WHERE s.promotion_idpromotion = ?
                           AND p.annee_acad_idannee_acad = ?
                           AND e."estVisible" = 1
                           ORDER BY s."numeroSemestre", u."designationUE", e."designationECUE"');
    $stmt->execute([$academicYearId, $promotionId, $academicYearId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group courses by UE
    $ueGroups = [];
    foreach ($courses as $course) {
        $ueId = $course['idUE'];
        if (!isset($ueGroups[$ueId])) {
            $ueGroups[$ueId] = [
                'id' => $ueId,
                'name' => $course['designationUE'],
                'code' => $course['codeUE'],
                'semestre' => $course['numeroSemestre'],
                'courses' => []
            ];
        }
        
        // Format professor name
        $professorName = null;
        if ($course['noms']) {
            $professorName = $course['titre'] . ' ' . $course['noms'];
        }
        
        $ueGroups[$ueId]['courses'][] = [
            'id' => $course['idECUE'],
            'name' => $course['designationECUE'],
            'hours' => [
                'cmi' => $course['CMI'],
                'td' => $course['TD'],
                'tp' => $course['TP']
            ],
            'professor' => $professorName
        ];
    }
    
    // Préparer les données de l'étudiant pour le frontend
    $studentData = [
        'noms' => $student['noms'],         // Garder le champ original
        'name' => $student['noms'],         // Ajouter un alias pour la compatibilité
        'matricule' => $student['matricule'],
        'photo_url' => $student['photo'],
        'section' => $student['designationSection'],
        'orientation' => $student['designationOrientation'],
        'promotion' => $student['designationPromotion'],
        'academic_year' => $student['academic_year']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => array_values($ueGroups),
        'profile' => $studentData
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
