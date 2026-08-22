<?php
/**
 * Dashboard API Controller
 * Provides student dashboard data including profile, upcoming courses, announcements,
 * payment status, assignments and thesis information
 */

// Headers pour une API REST sécurisée
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'connexion.php';
require_once 'auth.php';

// Gérer les requêtes preflight CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentification de l'étudiant
$auth = new Auth();
$studentId = $auth->authenticate();

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Configurer UTF-8 pour les requêtes
    $conn->exec("SET NAMES utf8mb4");
    
    // Obtenir les informations de l'étudiant
    $studentInfo = getStudentInfo($conn, $studentId);
    
    if (!$studentInfo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Récupérer les données pour le dashboard
    $upcomingCourses = getUpcomingCourses($conn, $studentInfo);
    $announcements = getAnnouncements($conn, $studentInfo);
    $payments = getPaymentStatus($conn, $studentId, $studentInfo);
    $assignments = getRecentAssignments($conn, $studentId, $studentInfo);
    $thesis = getThesisStatus($conn, $studentId, $studentInfo);
    
    // Préparer le résumé des paiements
    $paymentSummary = preparePaymentSummary($payments);
    $baseUrl = getBaseUrl();
    
    // Formater l'URL de la photo si elle existe et si le fichier est présent sur le serveur
    $photoUrl = null;
    if ($studentInfo['photo']) {
        // Convertir l'URL relative en chemin absolu du serveur
        $relativePath = str_replace($baseUrl, '', $studentInfo['photo']);
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        
        // Vérifier si le fichier existe physiquement sur le serveur
        if (file_exists($serverPath)) {
            $photoUrl = $baseUrl . $studentInfo['photo'];
        } else {
            // Essayer de trouver le fichier avec le motif du nom (pour les noms avec timestamp)
            if (preg_match('/etudiant_(\d+)_/', $relativePath, $matches)) {
                $studentId = $matches[1];
                $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/photos_etudiants/etudiant_' . $studentId . '_*.jpg';
                $files = glob($pattern);
                
                if (!empty($files)) {
                    // Utiliser le premier fichier correspondant trouvé
                    $foundImage = str_replace($_SERVER['DOCUMENT_ROOT'], '', $files[0]);
                    $photoUrl = $baseUrl . $foundImage;
                }
            }
        }
    }

    
    // Préparer la réponse
    $response = [
        'success' => true,
        'data' => [
            'student' => [
                'id' => $studentInfo['idetudiant'],
                'name' => $studentInfo['noms'],
                'matricule' => $studentInfo['matricule'],
                'photo_url' => $photoUrl,
                'promotion' => $studentInfo['designationPromotion'],
                'orientation' => $studentInfo['designationOrientation'],
                'section' => $studentInfo['designationSection'],
                'academic_year' => $studentInfo['academic_year']
            ],
            'upcoming_courses' => $upcomingCourses,
            'announcements' => $announcements,
            'payments' => [
                'summary' => $paymentSummary,
                'details' => $payments
            ],
            'assignments' => $assignments,
            'thesis' => $thesis
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    $errorMessage = 'Erreur serveur: ' . $e->getMessage();
    error_log("Dashboard API error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupère les informations de l'étudiant
 * 
 * @param PDO $conn Connexion à la base de données
 * @param int $studentId ID de l'étudiant
 * @return array|false Informations de l'étudiant ou false si non trouvé
 */
function getStudentInfo(PDO $conn, $studentId) {
    $stmt = $conn->prepare('
        SELECT
            e.idetudiant, e.noms, e.matricule, e.photo,
            e.promotion_idpromotion, e.annee_acad_idannee_acad,
            p."designationPromotion", p.cycle,
            o."designationOrientation", o.idorientation,
            s."designationSection", s.idsection,
            a.designation as academic_year
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
        WHERE e.idetudiant = ?
    ');
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les cours à venir pour l'étudiant (7 prochains jours)
 * 
 * @param PDO $conn Connexion à la base de données
 * @param array $studentInfo Informations de l'étudiant
 * @return array Liste des cours à venir
 */
function getUpcomingCourses(PDO $conn, $studentInfo) {
    $stmt = $conn->prepare('
        SELECT
            h.idhoraire, h.date_cours, h.salle, h.type_cours,
            to_char(h.heure_debut, \'HH24:MI\') as heure_debut,
            to_char(h.heure_fin, \'HH24:MI\') as heure_fin,
            to_char(h.date_cours, \'Day\') as jour_semaine,
            e."designationECUE", e."idECUE", u."designationUE",
            a.noms as enseignant_nom
        FROM horaires_cours h
        JOIN ecue e ON h."idECUE" = e."idECUE"
        JOIN ue u ON e."UE_idUE" = u."idUE"
        JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
        LEFT JOIN (
            SELECT ee."idECUE", ee."anneeAcad", MIN(ee."idAgent") as "idAgent"
            FROM enseignant_ecue ee
            WHERE ee."anneeAcad" = ?
            GROUP BY ee."idECUE", ee."anneeAcad"
        ) ee ON e."idECUE" = ee."idECUE"
        LEFT JOIN agent a ON ee."idAgent" = a."idAgent"
        WHERE h.date_cours >= CURRENT_DATE
        AND h.date_cours <= CURRENT_DATE + INTERVAL \'7 days\'
        AND s.promotion_idpromotion = ?
        AND h.annee_acad_idannee_acad = ?
        ORDER BY h.date_cours, h.heure_debut
        LIMIT 5
    ');
    
    $stmt->execute([
        $studentInfo['annee_acad_idannee_acad'],
        $studentInfo['promotion_idpromotion'],
        $studentInfo['annee_acad_idannee_acad']
    ]);
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les résultats pour plus de lisibilité
    return array_map(function($course) {
        // Formater la date pour l'affichage
        $date = new DateTime($course['date_cours']);
        
        return [
            'id' => $course['idhoraire'],
            'course' => $course['designationECUE'],
            'ue' => $course['designationUE'],
            'date' => $date->format('Y-m-d'),
            'day' => $course['jour_semaine'],
            'start_time' => $course['heure_debut'],
            'end_time' => $course['heure_fin'],
            'location' => $course['salle'],
            'type' => $course['type_cours'],
            'professor' => $course['enseignant_nom'] ?? 'Non assigné'
        ];
    }, $courses);
}

/**
 * Récupère les annonces récentes pertinentes pour l'étudiant
 * 
 * @param PDO $conn Connexion à la base de données
 * @param array $studentInfo Informations de l'étudiant
 * @return array Liste des annonces
 */
function getAnnouncements(PDO $conn, $studentInfo) {
    $stmt = $conn->prepare("
        SELECT 
            a.idactualite, a.titre, a.contenu, a.date_publication,
            a.cible, a.niveau, a.date_expiration
        FROM actualites a
        WHERE (a.cible = 'Etudiants' OR a.cible = 'Tous')
        AND (a.niveau = 'Global' OR (a.niveau = 'Section' AND a.idsection = ?))
        AND (a.date_expiration IS NULL OR a.date_expiration >= CURRENT_DATE)
        ORDER BY a.date_publication DESC
        LIMIT 5
    ");
    
    $stmt->execute([$studentInfo['idsection']]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les dates pour l'affichage
    return array_map(function($announcement) {
        $datePublication = new DateTime($announcement['date_publication']);
        
        return [
            'id' => $announcement['idactualite'],
            'title' => $announcement['titre'],
            'content' => $announcement['contenu'],
            'date' => $datePublication->format('Y-m-d'),
            'formatted_date' => $datePublication->format('d F Y'),
            'target' => $announcement['cible'],
            'level' => $announcement['niveau'],
            'expires_on' => $announcement['date_expiration']
        ];
    }, $announcements);
}

/**
 * Récupère le statut des paiements de l'étudiant
 * 
 * @param PDO $conn Connexion à la base de données
 * @param int $studentId ID de l'étudiant
 * @param array $studentInfo Informations de l'étudiant
 * @return array Détails des paiements
 */
function getPaymentStatus(PDO $conn, $studentId, $studentInfo) {
    // Vérification des entrées
    if (!$studentId || !isset($studentInfo['promotion_idpromotion']) || !isset($studentInfo['annee_acad_idannee_acad'])) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare('
            SELECT
                f.idfrais,
                f.designation,
                f.montant,
                f.devise,
                COALESCE(SUM(p."montantPaye"), 0) as montant_paye,
                f.montant - COALESCE(SUM(p."montantPaye"), 0) as reste_a_payer,
                CASE WHEN f.montant <= COALESCE(SUM(p."montantPaye"), 0) THEN 1 ELSE 0 END as est_complet
            FROM frais f
            LEFT JOIN paiement p ON f.idfrais = p.frais_idfrais AND p.etudiant_idetudiant = :studentId
            WHERE f.promotion_idpromotion = :promotionId
            AND f.annee_acad_idannee_acad = :academicYearId
            GROUP BY f.idfrais, f.designation, f.montant, f.devise
            ORDER BY f.idfrais
        ');
        
        $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':promotionId', $studentInfo['promotion_idpromotion'], PDO::PARAM_INT);
        $stmt->bindParam(':academicYearId', $studentInfo['annee_acad_idannee_acad'], PDO::PARAM_INT);
        $stmt->execute();
        
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données
        foreach ($payments as &$payment) {
            // Formater les montants en nombres (facilite les calculs côté client)
            $payment['montant'] = (float)$payment['montant'];
            $payment['montant_paye'] = (float)$payment['montant_paye'];
            $payment['reste_a_payer'] = (float)$payment['reste_a_payer'];
        }
        
        return $payments;
        
    } catch (PDOException $e) {
        // Log l'erreur
        error_log("Erreur lors de la récupération des paiements: " . $e->getMessage());
        return [];
    }
}

/**
 * Prépare le résumé des paiements avec support multi-devises
 * 
 * @param array $payments Détails des paiements
 * @return array Résumé des paiements groupés par devise
 */
function preparePaymentSummary($payments) {
    // Initialiser le résumé global
    $overallSummary = [
        'is_complete' => true,
        'has_payments' => !empty($payments),
        'currencies' => [],  // Stockera les totaux par devise
        'primary_currency' => null, // Devise principale (la plus utilisée)
        'totals' => []      // Les totaux pour chaque devise
    ];
    
    if (empty($payments)) {
        $overallSummary['is_complete'] = false;
        return $overallSummary;
    }
    
    // Grouper les paiements par devise
    $paymentsByDevise = [];
    $deviseCount = [];
    
    foreach ($payments as $payment) {
        $devise = $payment['devise'] ?? 'USD';
        
        if (!isset($paymentsByDevise[$devise])) {
            $paymentsByDevise[$devise] = [
                'total_due' => 0,
                'total_paid' => 0,
                'total_remaining' => 0,
                'currency' => $devise,
                'is_complete' => true,
                'items' => []
            ];
            $deviseCount[$devise] = 0;
        }
        
        // Incrémenter le compteur de cette devise
        $deviseCount[$devise]++;
        
        // Ajouter au total pour cette devise
        $paymentsByDevise[$devise]['total_due'] += (float)$payment['montant'];
        $paymentsByDevise[$devise]['total_paid'] += (float)$payment['montant_paye'];
        $paymentsByDevise[$devise]['total_remaining'] += (float)$payment['reste_a_payer'];
        $paymentsByDevise[$devise]['items'][] = $payment;
        
        // Mettre à jour le statut de complétion
        if ((int)$payment['est_complet'] === 0) {
            $paymentsByDevise[$devise]['is_complete'] = false;
            $overallSummary['is_complete'] = false;
        }
    }
    
    // Déterminer la devise principale (la plus utilisée)
    $primaryDevise = array_keys($deviseCount, max($deviseCount))[0] ?? 'USD';
    $overallSummary['primary_currency'] = $primaryDevise;
    
    // Ajouter les résumés par devise au résumé global
    foreach ($paymentsByDevise as $devise => $summary) {
        $overallSummary['currencies'][] = $devise;
        $overallSummary['totals'][$devise] = [
            'total_due' => $summary['total_due'],
            'total_paid' => $summary['total_paid'],
            'total_remaining' => $summary['total_remaining'],
            'is_complete' => $summary['is_complete'],
            'count' => count($summary['items'])
        ];
    }
    
    // Pour la compatibilité descendante, ajouter également les totaux de la devise principale
    // directement au résumé (ancienne structure)
    $overallSummary['total_due'] = $paymentsByDevise[$primaryDevise]['total_due'];
    $overallSummary['total_paid'] = $paymentsByDevise[$primaryDevise]['total_paid'];
    $overallSummary['total_remaining'] = $paymentsByDevise[$primaryDevise]['total_remaining'];
    $overallSummary['currency'] = $primaryDevise;
    
    return $overallSummary;
}

/**
 * Récupère les devoirs récents
 * 
 * @param PDO $conn Connexion à la base de données
 * @param int $studentId ID de l'étudiant
 * @param array $studentInfo Informations de l'étudiant
 * @return array Liste des devoirs
 */
function getRecentAssignments(PDO $conn, $studentId, $studentInfo) {
    $stmt = $conn->prepare('
        SELECT
            d.iddevoir, d.titre, d.description, d."dateCreation",
            d.date_limite, d.fichier, d.est_payant,
            e."idECUE", e."designationECUE", u."designationUE",
                        CASE WHEN rd.idreponse IS NOT NULL THEN 1 ELSE 0 END as est_soumis
        FROM devoirs d
        JOIN ecue e ON d."idECUE" = e."idECUE"
        JOIN ue u ON e."UE_idUE" = u."idUE"
        JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
        LEFT JOIN reponses_devoir rd ON d.iddevoir = rd.iddevoir AND rd.idetudiant = ?
        WHERE s.promotion_idpromotion = ?
        AND (d.date_limite IS NULL OR d.date_limite >= CURRENT_DATE)
        ORDER BY d.date_limite ASC
        LIMIT 5
    ');
    
    $stmt->execute([
        $studentId,
        $studentInfo['promotion_idpromotion']
    ]);
    
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $baseUrl = getBaseUrl();
    
    // Formater les résultats
    return array_map(function($assignment) {
        $dateLimite = $assignment['date_limite'] ? new DateTime($assignment['date_limite']) : null;
        $datePublication = new DateTime($assignment['date_publication']);
        
        return [
            'id' => $assignment['iddevoir'],
            'title' => $assignment['titre'],
            'description' => $assignment['description'],
            'course' => $assignment['designationECUE'],
            'ue' => $assignment['designationUE'],
            'publication_date' => $datePublication->format('Y-m-d'),
            'deadline' => $dateLimite ? $dateLimite->format('Y-m-d H:i') : null,
            'deadline_formatted' => $dateLimite ? $dateLimite->format('d F Y à H:i') : 'Pas de date limite',
            'file' => $assignment['fichier'] ? $baseUrl  . 'uploads/' . $assignment['fichier'] : null,
            'is_submitted' => (bool)$assignment['est_soumis'],
            'is_paid' => (bool)$assignment['est_payant']
        ];
    }, $assignments);
}

/**
 * Récupère le statut du projet/thèse de l'étudiant si applicable
 * 
 * @param PDO $conn Connexion à la base de données
 * @param int $studentId ID de l'étudiant
 * @param array $studentInfo Informations de l'étudiant
 * @return array|null Informations sur le projet/thèse ou null si non applicable
 */
function getThesisStatus(PDO $conn, $studentId, $studentInfo) {
    // Si l'étudiant n'est pas dans un cycle approprié pour un projet/thèse
    // if ($studentInfo['est_terminale'] == 0) {
    //     return null;
    // }
    
    $stmt = $conn->prepare('
        SELECT
            s.idsujets, s.intitule, s."etatSujet", s.statut_validation,
            s.commentaire_commission, s.date_validation,
            d."idAgent" as directeur_id, d.noms as directeur_nom,
            dg.designation as directeur_grade,
            e."idAgent" as encadreur_id, e.noms as encadreur_nom,
            eg.designation as encadreur_grade,
            sp.designation as specialisation_nom,
            COUNT(t.idtaches) as nombre_taches,
            AVG(t.pourcentage_avancement) as avancement_moyen
        FROM sujets s
        LEFT JOIN agent d ON s."idDirecteur" = d."idAgent"
        LEFT JOIN grade dg ON d.grade_id = dg.idgrade
        LEFT JOIN agent e ON s."idEncadreur" = e."idAgent"
        LEFT JOIN grade eg ON e.grade_id = eg.idgrade
        LEFT JOIN specialisation sp ON s."idSpecialisation" = sp."idSpecialisation"
        LEFT JOIN taches t ON s.idsujets = t.sujets_idsujets
        WHERE s.etudiant_idetudiant = ?
        AND s.annee_acad_idannee_acad = ?
        GROUP BY s.idsujets, s.intitule, s."etatSujet", s.statut_validation, s.commentaire_commission,
                 s.date_validation, directeur_id, directeur_nom, directeur_grade,
                 encadreur_id, encadreur_nom, encadreur_grade, specialisation_nom
    ');
    
    $stmt->execute([
        $studentId,
        $studentInfo['annee_acad_idannee_acad']
    ]);
    
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$thesis) {
        return null;
    }
    
    // Récupérer les tâches récentes
    $tasks = [];
    if ($thesis['idsujets']) {
        $tasks = getThesisTasks($conn, $thesis['idsujets']);
    }
    
    // Retourner le format souhaité
    return [
        'id' => $thesis['idsujets'],
        'title' => $thesis['intitule'],
        'status' => $thesis['etatSujet'],
        'validation_status' => $thesis['statut_validation'],
        'validation_comment' => $thesis['commentaire_commission'],
        'validation_date' => $thesis['date_validation'],
        'specialization' => $thesis['specialisation_nom'],
        'director' => $thesis['directeur_id'] ? [
            'id' => $thesis['directeur_id'],
            'name' => $thesis['directeur_nom'],
            'title' => $thesis['directeur_grade']
        ] : null,
        'supervisor' => $thesis['encadreur_id'] ? [
            'id' => $thesis['encadreur_id'],
            'name' => $thesis['encadreur_nom'],
            'title' => $thesis['encadreur_grade']
        ] : null,
        'tasks_count' => (int)$thesis['nombre_taches'],
        'average_progress' => $thesis['avancement_moyen'] ? round((float)$thesis['avancement_moyen']) : 0,
        'recent_tasks' => array_slice($tasks, 0, 3) // Limiter à 3 tâches récentes
    ];
}

/**
 * Récupère les tâches associées à un projet/thèse
 * 
 * @param PDO $conn Connexion à la base de données
 * @param int $thesisId ID du projet/thèse
 * @return array Liste des tâches
 */
function getThesisTasks(PDO $conn, $thesisId) {
    $stmt = $conn->prepare('
        SELECT
            t.*,
            (SELECT COUNT(*) FROM echanges_taches et WHERE et.taches_idtaches = t.idtaches) as nombre_echanges
        FROM taches t
        WHERE t.sujets_idsujets = ?
        ORDER BY t."dateTache" DESC
    ');
    
    $stmt->execute([$thesisId]);
    $tasksList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $baseUrl = getBaseUrl();
    
    return array_map(function($task) use ($baseUrl) {
        $dateCreation = new DateTime($task['dateTache']);
        $dateValidation = $task['date_validation'] ? new DateTime($task['date_validation']) : null;
        
        return [
            'id' => $task['idtaches'],
            'date' => $dateCreation->format('Y-m-d'),
            'date_formatted' => $dateCreation->format('d F Y'),
            'description' => $task['description'],
            'file' => $task['fichierTache'] ? $baseUrl . 'uploads/' . $task['fichierTache'] : null,
            'validation_status' => $task['validation'],
            'progress' => (int)$task['pourcentage_avancement'],
            'validation_date' => $dateValidation ? $dateValidation->format('Y-m-d') : null,
            'validation_comment' => $task['commentaire_validation'],
            'exchanges_count' => (int)$task['nombre_echanges']
        ];
    }, $tasksList);
}

/**
 * Récupère l'URL de base de l'application
 * 
 * @return string URL de base
 */
function getBaseUrl() {
    return 'https://istmbeni.info/';
}
?>