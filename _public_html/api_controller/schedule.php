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
$studentId = $auth->authenticate();
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
                            p."designationPromotion",
                            o."designationOrientation",
                            s."designationSection",
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
    
    $promotionId = $student['promotion_idpromotion'];
    $academicYearId = $student['annee_acad_idannee_acad'];
    
    // Utiliser la requête améliorée fournie pour récupérer l'emploi du temps
    $stmt = $conn->prepare('SELECT h.*, e."designationECUE", u."designationUE",
                           s."numeroSemestre", p."designationPromotion", a.noms as enseignant_nom,
                           to_char(h.date_cours, \'YYYY-MM-DD\') as date_cours_formatted,
                           to_char(h.date_cours, \'Day\') as jour_semaine
                           FROM horaires_cours h
                           JOIN ecue e ON h."idECUE" = e."idECUE"
                           JOIN ue u ON e."UE_idUE" = u."idUE"
                           JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                           JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                           LEFT JOIN (
                               SELECT ee."idECUE", ee."anneeAcad", ee."idAgent"
                               FROM enseignant_ecue ee
                               LEFT JOIN (
                                   SELECT "idECUE", "anneeAcad", MIN("idAgent") as "idAgent"
                                   FROM enseignant_ecue
                                   WHERE poste = \'Titulaire\'
                                   GROUP BY "idECUE", "anneeAcad"
                               ) tit ON ee."idECUE" = tit."idECUE" AND ee."anneeAcad" = tit."anneeAcad"
                               WHERE tit."idAgent" IS NOT NULL OR ee."idAgent" = (
                                   SELECT MIN("idAgent")
                                   FROM enseignant_ecue ee2
                                   WHERE ee2."idECUE" = ee."idECUE" AND ee2."anneeAcad" = ee."anneeAcad"
                               )
                               GROUP BY ee."idECUE", ee."anneeAcad"
                           ) ee ON e."idECUE" = ee."idECUE" AND ee."anneeAcad" = :idAnneeAcad
                           LEFT JOIN agent a ON ee."idAgent" = a."idAgent"
                           WHERE p.idpromotion = :idPromotion
                           AND h.annee_acad_idannee_acad = :idAnneeAcad
                           ORDER BY h.date_cours, h.heure_debut');
                           
    $stmt->bindParam(':idPromotion', $promotionId, PDO::PARAM_INT);
    $stmt->bindParam(':idAnneeAcad', $academicYearId, PDO::PARAM_INT);
    $stmt->execute();
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiser les cours par date
    $formattedSchedule = [];
    
    foreach ($schedules as $schedule) {
        $date = $schedule['date_cours_formatted'] ?? null;
        
        if ($date) {
            // Format pour les événements
            $event = [
                'id' => $schedule['idhoraire'],
                'type' => $schedule['type_cours'] ?? 'CM',
                'start_time' => $schedule['heure_debut'],
                'end_time' => $schedule['heure_fin'],
                'location' => $schedule['salle'],
                'course' => $schedule['designationECUE'],
                'ue' => $schedule['designationUE'],
                'professor' => $schedule['enseignant_nom'],
                'day' => $schedule['jour_semaine']
            ];
            
            // Vérifier si la date existe déjà dans notre tableau
            $dateFound = false;
            foreach ($formattedSchedule as &$item) {
                if ($item['date'] === $date) {
                    $item['events'][] = $event;
                    $dateFound = true;
                    break;
                }
            }
            
            // Si la date n'existe pas encore, l'ajouter
            if (!$dateFound) {
                $formattedSchedule[] = [
                    'date' => $date,
                    'day' => $schedule['jour_semaine'],
                    'events' => [$event]
                ];
            }
        }
    }
    
    // Trier les dates par ordre chronologique
    usort($formattedSchedule, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    // Préparer les données de l'étudiant pour le frontend
    $studentData = [
        'noms' => $student['noms'],
        'matricule' => $student['matricule'],
        'photo_url' => $student['photo'],
        'section' => $student['designationSection'],
        'orientation' => $student['designationOrientation'],
        'promotion' => $student['designationPromotion'],
        'academic_year' => $student['academic_year']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $formattedSchedule,
        'profile' => $studentData
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
