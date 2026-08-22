<?php
header('Content-Type: application/json');
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
$studentId = $auth->authenticate();

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Get student info
    $stmt = $conn->prepare('SELECT e.*, p."designationPromotion", p.cycle,
                           o."designationOrientation", s."designationSection",
                           a.designation as annee_academique
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
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    // Get academic years for student
    $stmt = $conn->prepare("SELECT DISTINCT a.idannee_acad, a.designation 
                           FROM inscription i
                           JOIN annee_acad a ON i.annee_acad_idannee_acad = a.idannee_acad
                           WHERE i.etudiant_idetudiant = ?
                           ORDER BY a.idannee_acad DESC");
    $stmt->execute([$studentId]);
    $academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    
    // Get results for each academic year
    foreach ($academicYears as $year) {
        // Get courses and results
        $stmt = $conn->prepare('SELECT e."designationECUE", e."CMI", e."TD", e."TP",
                               u."designationUE", u.credits as ue_credits,
                               r.note_cc, r.note_tp, r.note_examen, r.note_finale, r.mention,
                               r.est_valide, r.est_rattrapage
                               FROM resultats r
                               JOIN ecue e ON r."idECUE" = e."idECUE"
                               JOIN ue u ON e."UE_idUE" = u."idUE"
                               WHERE r.etudiant_idetudiant = ?
                               AND r.annee_acad_idannee_acad = ?
                               ORDER BY u."designationUE", e."designationECUE"');
        $stmt->execute([$studentId, $year['idannee_acad']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by UE
        $ueGroups = [];
        foreach ($courses as $course) {
            $ueName = $course['designationUE'];
            if (!isset($ueGroups[$ueName])) {
                $ueGroups[$ueName] = [
                    'name' => $ueName,
                    'credits' => $course['ue_credits'],
                    'courses' => []
                ];
            }
            
            $ueGroups[$ueName]['courses'][] = [
                'name' => $course['designationECUE'],
                'hours' => [
                    'cmi' => $course['CMI'],
                    'td' => $course['TD'],
                    'tp' => $course['TP']
                ],
                'scores' => [
                    'cc' => $course['note_cc'],
                    'tp' => $course['note_tp'],
                    'exam' => $course['note_examen'],
                    'final' => $course['note_finale']
                ],
                'mention' => $course['mention'],
                'is_valid' => $course['est_valide'] == 1,
                'is_retake' => $course['est_rattrapage'] == 1
            ];
        }
        
        // Get overall results for this year
        $stmt = $conn->prepare("SELECT r.moyenne_generale, r.credits_valides, r.credits_total, 
                               r.mention_generale, r.est_admis
                               FROM resultats_annuels r
                               WHERE r.etudiant_idetudiant = ? 
                               AND r.annee_acad_idannee_acad = ?");
        $stmt->execute([$studentId, $year['idannee_acad']]);
        $yearResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get promotion for this year
        $stmt = $conn->prepare('SELECT p."designationPromotion", p.cycle
                               FROM inscription i
                               JOIN promotion p ON i.promotion_idpromotion = p.idpromotion
                               WHERE i.etudiant_idetudiant = ?
                               AND i.annee_acad_idannee_acad = ?');
        $stmt->execute([$studentId, $year['idannee_acad']]);
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $results[] = [
            'academic_year' => [
                'id' => $year['idannee_acad'],
                'name' => $year['designation']
            ],
            'promotion' => $promotion ? $promotion['designationPromotion'] : null,
            'cycle' => $promotion ? $promotion['cycle'] : null,
            'units' => array_values($ueGroups),
            'summary' => $yearResult ? [
                'average' => $yearResult['moyenne_generale'],
                'credits_validated' => $yearResult['credits_valides'],
                'total_credits' => $yearResult['credits_total'],
                'mention' => $yearResult['mention_generale'],
                'is_admitted' => $yearResult['est_admis'] == 1
            ] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => [
                'id' => $student['idetudiant'],
                'matricule' => $student['matricule'],
                'name' => $student['noms'],
                'current_promotion' => $student['designationPromotion'],
                'current_cycle' => $student['cycle'],
                'orientation' => $student['designationOrientation'],
                'section' => $student['designationSection'],
                'current_academic_year' => $student['annee_academique']
            ],
            'results' => $results
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
