<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once 'connexion.php';
require_once 'auth.php';

/**
 * Thesis/Project Management API
 * Handles fetching and submission of thesis/project details for students
 * Supports task management with chat-like interface
 */

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Initialize authentication
    $auth = new Auth();
    $studentId = $auth->authenticate();
    
    if (!$studentId) {
        throw new Exception('Non autorisé', 401);
    }
    
    $conn = Connexion::getInstance()->getPDO();
    
    // Get student info (used in both GET and POST methods)
    $studentInfo = getStudentInfo($conn, $studentId);
    
    if (!$studentInfo) {
        throw new Exception('Étudiant non trouvé', 404);
    }
    
    // Check if student is eligible for thesis/project (must be in terminal year)
    if ($studentInfo['est_terminale'] == 0) {
        throw new Exception('Vous n\'êtes pas éligible pour un sujet de mémoire', 400);
    }
    
    // Get action from query string if it exists
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    
    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'task' && isset($_GET['task_id'])) {
                handleGetTaskDetails($conn, $studentId, $_GET['task_id']);
            } elseif ($action === 'available_subjects') {
                handleGetAvailableSubjects($conn, $studentInfo);
            } elseif ($action === 'available_supervisors') {
                handleGetAvailableSupervisors($conn, $studentInfo);
            } elseif ($action === 'specializations') {
                handleGetSpecializations($conn, $studentInfo);
            } elseif ($action === 'thesis') {
                handleGetThesis($conn, $studentId, $studentInfo);
            } elseif ($action === 'task_comments' && isset($_GET['task_id'])) {
                handleGetTaskComments($conn, $studentId, $_GET['task_id']);
            } elseif ($action === 'profile') {
                handleGetProfile($conn, $studentId);
            } else {
                handleGetRequest($conn, $studentId, $studentInfo);
            }
            break;
            
        case 'POST':
            if ($action === 'create_task' && isset($_GET['thesis_id'])) {
                handleCreateTask($conn, $studentId, $_GET['thesis_id']);
            } elseif ($action === 'add_comment' && isset($_GET['task_id'])) {
                handleAddComment($conn, $studentId, $_GET['task_id']);
            } elseif ($action === 'select_subject' && isset($_GET['subject_id'])) {
                handleSelectSubject($conn, $studentId, $_GET['subject_id'], $studentInfo);
            } else {
                handlePostRequest($conn, $studentId, $studentInfo);
            }
            break;
            
        case 'PUT':
            if ($action === 'update_task' && isset($_GET['task_id'])) {
                handleUpdateTask($conn, $studentId, $_GET['task_id']);
            } elseif ($action === 'update_thesis') {
                handleUpdateThesis($conn, $studentId, $studentInfo);
            } else {
                throw new Exception('Action non reconnue', 400);
            }
            break;
            
        default:
            throw new Exception('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $statusCode
    ]);
}

/**
 * Get student information including promotion and academic year
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @return array|null Student information
 */
function getStudentInfo(PDO $conn, $studentId) {
    $stmt = $conn->prepare('
        SELECT
            e.idetudiant,
            e.noms,
            e.promotion_idpromotion,
            e.annee_acad_idannee_acad,
            p.cycle,
            s."designationSection",
            o."designationOrientation",
            o.idorientation,
            p.est_terminale
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        WHERE e.idetudiant = ?
    ');
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Handle GET request to fetch thesis details
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param array $studentInfo Student information
 */
function handleGetRequest(PDO $conn, $studentId, $studentInfo) {
    // Get thesis/project details
    $stmt = $conn->prepare('
        SELECT
            s.*,
            a1."idAgent" as directeur_id,
            a1.noms as directeur_nom,
            g1.designation as directeur_grade,
            a2."idAgent" as encadreur_id,
            a2.noms as encadreur_nom,
            g2.designation as encadreur_grade,
            sp."idSpecialisation" as specialisation_id,
            sp.designation as specialisation_nom,
            ur."designation_UR" as unite_recherche_nom
        FROM sujets s
        LEFT JOIN agent a1 ON s."idDirecteur" = a1."idAgent"
        LEFT JOIN grade g1 ON a1.grade_id = g1.idgrade
        LEFT JOIN agent a2 ON s."idEncadreur" = a2."idAgent"
        LEFT JOIN grade g2 ON a2.grade_id = g2.idgrade
        LEFT JOIN specialisation sp ON s."idSpecialisation" = sp."idSpecialisation"
        LEFT JOIN unite_recherche ur ON sp."idUnite_recherche" = ur.idunite_recherche
        WHERE s.etudiant_idetudiant = ?
        AND s.annee_acad_idannee_acad = ?
    ');
    $stmt->execute([$studentId, $studentInfo['annee_acad_idannee_acad']]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$thesis) {
        // If no thesis found, return empty data
        echo json_encode([
            'success' => true,
            'data' => null,
            'student' => [
                'id' => $studentId,
                'name' => $studentInfo['noms'],
                'cycle' => $studentInfo['cycle'],
                'est_terminale' => $studentInfo['est_terminale'],
                'section' => $studentInfo['designationSection'],
                'orientation' => $studentInfo['designationOrientation']
            ]
        ]);
        return;
    }
    
    // Get tasks for this thesis
    $tasks = getThesisTasks($conn, $thesis['idsujets']);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'id' => $thesis['idsujets'],
            'title' => $thesis['intitule'],
            'status' => $thesis['etatSujet'],
            'validation_status' => $thesis['statut_validation'],
            'validation_comment' => $thesis['commentaire_commission'],
            'validation_date' => $thesis['date_validation'],
            'specialization' => [
                'id' => $thesis['specialisation_id'],
                'name' => $thesis['specialisation_nom'],
                'research_unit' => $thesis['unite_recherche_nom']
            ],
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
            'tasks' => $tasks,
            'student' => [
                'id' => $studentId,
                'name' => $studentInfo['noms'],
                'cycle' => $studentInfo['cycle'],
                'section' => $studentInfo['designationSection'],
                'orientation' => $studentInfo['designationOrientation']
            ]
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Get tasks associated with a thesis
 *
 * @param PDO $conn Database connection
 * @param int $thesisId Thesis ID
 * @return array Array of tasks
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
        return [
            'id' => $task['idtaches'],
            'date' => $task['dateTache'],
            'description' => $task['description'],
            'file' => $task['fichierTache'] ? $baseUrl . '/uploads/' . $task['fichierTache'] : null,
            'validation_status' => $task['validation'],
            'progress' => $task['pourcentage_avancement'],
            'validation_date' => $task['date_validation'],
            'validation_comment' => $task['commentaire_validation'],
            'exchanges_count' => $task['nombre_echanges']
        ];
    }, $tasksList);
}

/**
 * Get the base URL of the application
 *
 * @return string Base URL
 */
function getBaseUrl() {
    return 'https://istmbeni.info/';
}

/**
 * Get details of a specific task including all comments/exchanges
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $taskId Task ID
 */
function handleGetTaskDetails($conn, $studentId, $taskId) {
    // First verify the student has access to this task
    $stmt = $conn->prepare("
        SELECT t.*, s.idsujets, s.intitule, s.etudiant_idetudiant
        FROM taches t
        JOIN sujets s ON t.sujets_idsujets = s.idsujets
        WHERE t.idtaches = ? AND s.etudiant_idetudiant = ?
    ");
    $stmt->execute([$taskId, $studentId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        throw new Exception('Tâche non trouvée ou accès non autorisé', 404);
    }
    
    // Get all comments/exchanges for this task
    $exchanges = getTaskExchanges($conn, $taskId);
    
    $baseUrl = getBaseUrl();
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'id' => $task['idtaches'],
            'thesis_id' => $task['idsujets'],
            'thesis_title' => $task['intitule'],
            'date' => $task['dateTache'],
            'description' => $task['description'],
            'file' => $task['fichierTache'] ? $baseUrl . '/uploads/' . $task['fichierTache'] : null,
            'validation_status' => $task['validation'],
            'progress' => $task['pourcentage_avancement'],
            'validation_date' => $task['date_validation'],
            'validation_comment' => $task['commentaire_validation'],
            'exchanges' => $exchanges
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Handle getting comments for a specific task
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $taskId Task ID
 */
function handleGetTaskComments($conn, $studentId, $taskId) {
    // Verify the student has access to this task
    $stmt = $conn->prepare("
        SELECT t.idtaches
        FROM taches t
        JOIN sujets s ON t.sujets_idsujets = s.idsujets
        WHERE t.idtaches = ? AND s.etudiant_idetudiant = ?
    ");
    $stmt->execute([$taskId, $studentId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Tâche non trouvée ou accès non autorisé', 404);
    }
    
    // Get comments for this task
    $comments = getTaskComments($conn, $taskId);
    
    echo json_encode([
        'success' => true,
        'data' => $comments
    ]);
}

/**
 * Handle selecting an existing subject by a student
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $subjectId Subject ID
 * @param array $studentInfo Student information
 */
function handleSelectSubject($conn, $studentId, $subjectId, $studentInfo) {
    // Get JSON data from request for director and supervisor
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate that director_id is provided (required)
    if (!isset($data['director_id']) || !is_numeric($data['director_id'])) {
        throw new Exception('Le directeur est obligatoire pour sélectionner ce sujet', 400);
    }
    
    $directorId = $data['director_id'];
    $supervisorId = isset($data['supervisor_id']) && is_numeric($data['supervisor_id']) 
        ? $data['supervisor_id'] 
        : null;
    
    // Verify the subject exists and is available
    $stmt = $conn->prepare('
        SELECT
            s.idsujets,
            s.intitule,
            s."idSpecialisation",
            s.cycle,
            s."idDirecteur",
            s."idEncadreur"
        FROM sujets s
        JOIN specialisation sp ON s."idSpecialisation" = sp."idSpecialisation"
        WHERE s.idsujets = ?
        AND s.statut_validation != \'Validé\'
        AND s.etudiant_idetudiant IS NULL
        AND s.cycle = ?
        AND sp.idsection IN (
            SELECT section_idsection
            FROM orientation
            WHERE idorientation = ?
        )
    ');
    $stmt->execute([$subjectId, $studentInfo['cycle'], $studentInfo['idorientation']]);
    
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subject) {
        throw new Exception('Sujet non disponible ou incompatible avec votre cursus', 404);
    }
    
    // Validate director exists
    $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
    $stmt->execute([$directorId]);
    if (!$stmt->fetch()) {
        throw new Exception('Le directeur spécifié n\'existe pas', 400);
    }
    
    // Validate supervisor if provided
    if ($supervisorId) {
        $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$supervisorId]);
        if (!$stmt->fetch()) {
            throw new Exception('L\'encadreur spécifié n\'existe pas', 400);
        }
    }
    
    // Check if student already has a thesis
    $stmt = $conn->prepare("
        SELECT idsujets
        FROM sujets
        WHERE etudiant_idetudiant = ?
        AND statut_validation != 'Rejeté'
        AND annee_acad_idannee_acad = ?
    ");
    $stmt->execute([$studentId, $studentInfo['annee_acad_idannee_acad']]);
    if ($stmt->fetch()) {
        throw new Exception('Vous avez déjà un sujet assigné pour cette année académique', 400);
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Assign student to subject with director and supervisor
        $stmt = $conn->prepare('
            UPDATE sujets
            SET etudiant_idetudiant = ?,
                annee_acad_idannee_acad = ?,
                "idUser" = ?,
                "idDirecteur" = ?,
                "idEncadreur" = ?,
                statut_validation = \'En attente\'
            WHERE idsujets = ?
        ');
        
        $stmt->execute([
            $studentId,
            $studentInfo['annee_acad_idannee_acad'],
            $studentId,
            $directorId,
            $supervisorId,
            $subjectId
        ]);
        
        // Commit transaction
        $conn->commit();
        
        // Get director and supervisor names for response
        $directorName = '';
        $supervisorName = '';
        
        $stmt = $conn->prepare('SELECT noms, grade_id FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$directorId]);
        $director = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($director) {
            $directorName = $director['noms'];
        }
        
        if ($supervisorId) {
            $stmt->execute([$supervisorId]);
            $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($supervisor) {
                $supervisorName = $supervisor['noms'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Sujet sélectionné avec succès',
            'data' => [
                'id' => $subject['idsujets'],
                'title' => $subject['intitule'],
                'director_id' => $directorId,
                'director_name' => $directorName,
                'supervisor_id' => $supervisorId,
                'supervisor_name' => $supervisorName
            ]
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
         // Log l'erreur complète pour le débogage
        error_log("PDO Error in handleSelectSubject: " . $e->getMessage() . "\n" . $e->getTraceAsString());


        throw new Exception('Erreur lors de la sélection du sujet : ' . $e->getMessage(), 500);
    }
}

/**
 * Get all exchanges/comments for a task
 *
 * @param PDO $conn Database connection
 * @param int $taskId Task ID
 * @return array Array of exchanges
 */
function getTaskExchanges($conn, $taskId) {
    $stmt = $conn->prepare('
        SELECT
            et.*,
            CASE et.type_auteur
                WHEN \'Directeur\' THEN (SELECT noms FROM agent WHERE "idAgent" = et."idAuteur")
                WHEN \'Encadreur\' THEN (SELECT noms FROM agent WHERE "idAgent" = et."idAuteur")
                WHEN \'Etudiant\' THEN (SELECT noms FROM etudiant WHERE idetudiant = et."idAuteur")
            END as nom_auteur
        FROM echanges_taches et
        WHERE et.taches_idtaches = ?
        ORDER BY et."dateEchange" ASC
    ');
    $stmt->execute([$taskId]);
    $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $baseUrl = getBaseUrl();
    
    return array_map(function($exchange) use ($baseUrl) {
        return [
            'id' => $exchange['idechange'],
            'date' => $exchange['dateEchange'],
            'comment' => $exchange['commentaire'],
            'file' => $exchange['fichierJoint'] ? $baseUrl . '/uploads/' . $exchange['fichierJoint'] : null,
            'author_type' => $exchange['type_auteur'],
            'author_id' => $exchange['idAuteur'],
            'author_name' => $exchange['nom_auteur']
        ];
    }, $exchanges);
}

/** 
 * Handle POST request to submit a new thesis
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param array $studentInfo Student information
 */
function handlePostRequest($conn, $studentId, $studentInfo) {
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['title']) || empty(trim($data['title']))) {
        throw new Exception('Le titre du sujet est requis', 400);
    }
    if (!isset($data['specialization_id']) || !is_numeric($data['specialization_id'])) {
        throw new Exception('ID de spécialisation invalide', 400);
    }
    
    // Optional director and supervisor
    $directorId = isset($data['director_id']) && is_numeric($data['director_id']) ? $data['director_id'] : null;
    $supervisorId = isset($data['supervisor_id']) && is_numeric($data['supervisor_id']) ? $data['supervisor_id'] : null;
    
    // Validate the specialization exists
    $stmt = $conn->prepare('SELECT "idSpecialisation" FROM specialisation WHERE "idSpecialisation" = ?');
    $stmt->execute([$data['specialization_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('La spécialisation spécifiée n\'existe pas', 400);
    }
    
    // Validate director and supervisor if provided
    if ($directorId) {
        $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$directorId]);
        if (!$stmt->fetch()) {
            throw new Exception('Le directeur spécifié n\'existe pas', 400);
        }
    }
    
    if ($supervisorId) {
        $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$supervisorId]);
        if (!$stmt->fetch()) {
            throw new Exception('L\'encadreur spécifié n\'existe pas', 400);
        }
    }
    
    // Check if student already has a thesis
    $stmt = $conn->prepare("
        SELECT idsujets
        FROM sujets
        WHERE etudiant_idetudiant = ?
        AND statut_validation != 'Rejeté'
        AND annee_acad_idannee_acad = ?
    ");
    $stmt->execute([$studentId, $studentInfo['annee_acad_idannee_acad']]);
    if ($stmt->fetch()) {
        throw new Exception('Vous avez déjà soumis un sujet pour cette année académique', 400);
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Insert new thesis
        $stmt = $conn->prepare('
            INSERT INTO sujets (
                intitule,
                "etatSujet",
                "idDirecteur",
                "idEncadreur",
                "idSpecialisation",
                etudiant_idetudiant,
                annee_acad_idannee_acad,
                cycle,
                "idUser",
                statut_validation
            ) VALUES (?, \'Encours\', ?, ?, ?, ?, ?, ?, ?, \'En attente\')
        ');
        
        $stmt->execute([
            trim($data['title']),
            $directorId,
            $supervisorId,
            $data['specialization_id'],
            $studentId,
            $studentInfo['annee_acad_idannee_acad'],
            $studentInfo['cycle'],
            $studentId
        ]);
        
        $thesisId = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sujet soumis avec succès',
            'data' => [
                'id' => $thesisId,
                'title' => trim($data['title']),
                'status' => 'Encours',
                'validation_status' => 'En attente',
                'director_id' => $directorId,
                'supervisor_id' => $supervisorId
            ]
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw new Exception('Erreur lors de l\'enregistrement du sujet: ' . $e->getMessage(), 500);
    }
}

/** 
 * Handle POST request to update a thesis
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param array $studentInfo Student information
 */
function handleUpdateThesis($conn, $studentId) {
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        throw new Exception('ID du sujet est requis', 400);
    }
    
    $thesisId = $data['id'];
    
    if (!isset($data['title']) || empty(trim($data['title']))) {
        throw new Exception('Le titre du sujet est requis', 400);
    }
    
    // Optional director and supervisor
    $directorId = isset($data['director_id']) && is_numeric($data['director_id']) ? $data['director_id'] : null;
    $supervisorId = isset($data['supervisor_id']) && is_numeric($data['supervisor_id']) ? $data['supervisor_id'] : null;
    
    // Verify the thesis exists and belongs to the student
    $stmt = $conn->prepare('
        SELECT s.idsujets, s.statut_validation, s."idSpecialisation"
        FROM sujets s
        WHERE s.idsujets = ? AND s.etudiant_idetudiant = ?
    ');
    $stmt->execute([$thesisId, $studentId]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$thesis) {
        throw new Exception('Sujet non trouvé ou accès non autorisé', 404);
    }
    
    // Check if thesis can be modified
    // $validationStatus = strtolower($thesis['statut_validation']);
    // if ($validationStatus != 'en attente' && $validationStatus != 'modifié') {
    //     throw new Exception('Ce sujet ne peut plus être modifié dans son état actuel', 400);
    // }
    
    // Validate director and supervisor if provided
    if ($directorId) {
        $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$directorId]);
        if (!$stmt->fetch()) {
            throw new Exception('Le directeur spécifié n\'existe pas', 400);
        }
    }
    
    if ($supervisorId) {
        $stmt = $conn->prepare('SELECT "idAgent" FROM agent WHERE "idAgent" = ?');
        $stmt->execute([$supervisorId]);
        if (!$stmt->fetch()) {
            throw new Exception('L\'encadreur spécifié n\'existe pas', 400);
        }
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Update the thesis
        $stmt = $conn->prepare('
            UPDATE sujets
            SET intitule = ?,
                "idDirecteur" = ?,
                "idEncadreur" = ?,
                statut_validation = \'En attente\'
            WHERE idsujets = ? AND etudiant_idetudiant = ?
        ');
        
        $stmt->execute([
            trim($data['title']),
            $directorId,
            $supervisorId,
            $thesisId,
            $studentId
        ]);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sujet modifié avec succès',
            'data' => [
                'id' => $thesisId,
                'title' => trim($data['title']),
                'status' => 'Encours',
                'validation_status' => 'En attente',
                'director_id' => $directorId,
                'supervisor_id' => $supervisorId
            ]
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw new Exception('Erreur lors de la modification du sujet: ' . $e->getMessage(), 500);
    }
}


/**
 * Handle creating a new task for a thesis
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $thesisId Thesis ID
 */
function handleCreateTask($conn, $studentId, $thesisId) {
    // Verify the student owns this thesis
    $stmt = $conn->prepare("SELECT idsujets FROM sujets WHERE idsujets = ? AND etudiant_idetudiant = ?");
    $stmt->execute([$thesisId, $studentId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Sujet non trouvé ou accès non autorisé', 404);
    }
    
    // Process form data
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    
    if (!$description) {
        throw new Exception('La description de la tâche est requise', 400);
    }
    
    // Handle file upload if present
    $uploadedFile = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = processFileUpload($_FILES['file']);
    }
    
    try {
        $conn->beginTransaction();
        
        // Create the task
        $stmt = $conn->prepare('
            INSERT INTO taches (
                "dateTache",
                description,
                "fichierTache",
                validation,
                pourcentage_avancement,
                sujets_idsujets,
                "idUser"
            ) VALUES (NOW(), ?, ?, \'En attente\', 0, ?, ?)
        ');
        
        $stmt->execute([
            $description,
            $uploadedFile,
            $thesisId,
            $studentId
        ]);
        
        $taskId = $conn->lastInsertId();
        
        $conn->commit();
        
        $baseUrl = getBaseUrl();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tâche créée avec succès',
            'data' => [
                'id' => $taskId,
                'description' => $description,
                'file' => $uploadedFile ? $baseUrl . '/uploads/' . $uploadedFile : null,
                'validation_status' => 'En attente',
                'progress' => 0
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        // If file was uploaded, delete it on error
        if ($uploadedFile) {
            @unlink($baseUrl .'/uploads/' . $uploadedFile);
        }
        throw new Exception('Erreur lors de la création de la tâche: ' . $e->getMessage(), 500);
    }
}

/**
 * Process file upload with enhanced security checks
 *
 * @param array $file File data from $_FILES
 * @return string|null New filename if successful, null otherwise
 */
function processFileUpload($file) {
    // Validation des erreurs de téléchargement
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                throw new Exception('Le fichier dépasse la taille maximale autorisée par la configuration PHP', 400);
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('Le fichier dépasse la taille maximale autorisée par le formulaire', 400);
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('Le fichier n\'a été que partiellement téléchargé', 400);
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Aucun fichier n\'a été téléchargé', 400);
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Dossier temporaire manquant', 500);
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Échec de l\'écriture du fichier sur le disque', 500);
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('Une extension PHP a arrêté le téléchargement du fichier', 500);
            default:
                throw new Exception('Erreur de téléchargement inconnue', 500);
        }
    }

    // Validation du type de fichier
    $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
        'txt', 'jpg', 'jpeg', 'png', 
        'mp3', 'mp4', 'avi'
    ];

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);

    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Type de fichier non autorisé', 400);
    }

    // Validation de la taille du fichier (max 10MB pour les fichiers multimédias)
    $maxFileSize = 10 * 1024 * 1024; // 10 Mo
    if ($file['size'] > $maxFileSize) {
        throw new Exception('Le fichier est trop volumineux (max 10 Mo)', 400);
    }

    // Vérification du contenu du fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/jpeg',
        'image/png',
        'audio/mpeg',  // mp3
        'video/mp4',   // mp4
        'video/x-msvideo' // avi
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('Type de fichier non autorisé', 400);
    }

    // Chemin de téléchargement sécurisé
    $uploadBaseDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    
    // Créer le répertoire s'il n'existe pas
    if (!file_exists($uploadBaseDir)) {
        if (!mkdir($uploadBaseDir, 0755, true)) {
            throw new Exception('Impossible de créer le répertoire de téléchargement', 500);
        }
    }

    // Vérifier les permissions
    if (!is_writable($uploadBaseDir)) {
        throw new Exception('Le répertoire de téléchargement n\'est pas accessible en écriture', 500);
    }

    // Générer un nom de fichier unique et sécurisé
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $destination = $uploadBaseDir . $fileName;

    // Déplacer le fichier téléchargé
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erreur lors du déplacement du fichier téléchargé', 500);
    }

    // Définir les permissions du fichier
    chmod($destination, 0644);

    return $fileName;
}

/**
 * Handle adding a comment to a task (exchange)
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $taskId Task ID
 */
function handleAddComment($conn, $studentId, $taskId) {
    // Logs de débogage
    error_log("Début de handleAddComment - StudentID: $studentId, TaskID: $taskId");
    error_log("Données POST: " . print_r($_POST, true));
    error_log("Fichiers uploadés: " . print_r($_FILES, true));

    try {
        // Vérifier que l'étudiant a accès à la tâche
        $stmt = $conn->prepare("
            SELECT t.idtaches 
            FROM taches t 
            JOIN sujets s ON t.sujets_idsujets = s.idsujets 
            WHERE t.idtaches = ? AND s.etudiant_idetudiant = ?
        ");
        $stmt->execute([$taskId, $studentId]);

        if (!$stmt->fetch()) {
            throw new Exception('Tâche non trouvée ou accès non autorisé', 404);
        }

        // Traitement des données de formulaire
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;

        if (!$comment && !isset($_FILES['file'])) {
            throw new Exception('Un commentaire ou un fichier est requis', 400);
        }

        // Gestion du téléchargement de fichier
        $uploadedFile = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploadedFile = processFileUpload($_FILES['file']);
            } catch (Exception $uploadError) {
                error_log("Erreur de téléchargement de fichier: " . $uploadError->getMessage());
                throw $uploadError;
            }
        }

        // Début de la transaction
        $conn->beginTransaction();

        // Ajouter le commentaire
        $stmt = $conn->prepare('
            INSERT INTO echanges_taches (
                "dateEchange",
                commentaire,
                "fichierJoint",
                taches_idtaches,
                type_auteur,
                "idAuteur"
            ) VALUES (NOW(), ?, ?, ?, \'Etudiant\', ?)
        ');
        $stmt->execute([
            $comment,
            $uploadedFile,
            $taskId,
            $studentId
        ]);

        $exchangeId = $conn->lastInsertId();

        // Récupérer le nom de l'étudiant
        $stmt = $conn->prepare("SELECT noms FROM etudiant WHERE idetudiant = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Valider les données de l'étudiant
        if (!$student) {
            throw new Exception('Informations de l\'étudiant introuvables', 404);
        }

        $baseUrl = getBaseUrl();

        // Validation et nettoyage des données avant l'envoi
        $responseData = [
            'success' => true,
            'message' => 'Commentaire ajouté avec succès',
            'data' => [
                'id' => (int)$exchangeId,
                'date' => date('Y-m-d H:i:s'),
                'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
                'file' => $uploadedFile ? htmlspecialchars($baseUrl . '/uploads/' . $uploadedFile, ENT_QUOTES, 'UTF-8') : null,
                'author_type' => 'Etudiant',
                'author_id' => (int)$studentId,
                'author_name' => htmlspecialchars($student['noms'], ENT_QUOTES, 'UTF-8')
            ]
        ];

        // Valider la réponse JSON
        $jsonResponse = json_encode($responseData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur de génération de la réponse JSON', 500);
        }

        // Commiter la transaction
        $conn->commit();

        echo $jsonResponse;

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        // Supprimer le fichier uploadé en cas d'erreur
        if ($uploadedFile) {
            $filePath = $baseUrl . 'uploads/' . $uploadedFile;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Log détaillé de l'erreur
        error_log("Erreur dans handleAddComment: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());

        // Réponse d'erreur standardisée
        http_response_code($e->getCode() ?: 500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => $e->getCode() ?: 500
        ]);
    }
}

/**
 * Handle updating a task (progress, etc.)
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $taskId Task ID
 */
function handleUpdateTask($conn, $studentId, $taskId) {
    // Verify the student has access to this task
    $stmt = $conn->prepare("
        SELECT t.idtaches
        FROM taches t
        JOIN sujets s ON t.sujets_idsujets = s.idsujets
        WHERE t.idtaches = ? AND s.etudiant_idetudiant = ?
    ");
    $stmt->execute([$taskId, $studentId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Tâche non trouvée ou accès non autorisé', 404);
    }
    
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate data
    if (!isset($data['progress']) || !is_numeric($data['progress']) || $data['progress'] < 0 || $data['progress'] > 100) {
        throw new Exception('Le pourcentage d\'avancement doit être un nombre entre 0 et 100', 400);
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE taches
            SET pourcentage_avancement = ?
            WHERE idtaches = ?
        ");
        
        $stmt->execute([
            $data['progress'],
            $taskId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tâche mise à jour avec succès',
            'data' => [
                'id' => $taskId,
                'progress' => $data['progress']
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la mise à jour de la tâche: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle getting available subjects for the student
 *
 * @param PDO $conn Database connection
 * @param array $studentInfo Student information
 */
function handleGetAvailableSubjects($conn, $studentInfo) {
    // Requête améliorée pour inclure les informations sur les directeurs et encadreurs
    $query = 'SELECT
        s.idsujets,
        s.intitule,
        s."idSpecialisation",
        s."idDirecteur",
        s."idEncadreur",
        sp.designation as specialisation_nom,
        ur."designation_UR" as unite_recherche_nom,
        d."idAgent" as directeur_id,
        d.noms as directeur_nom,
        dg.designation as directeur_grade,
        e."idAgent" as encadreur_id,
        e.noms as encadreur_nom,
        eg.designation as encadreur_grade
    FROM sujets s
    INNER JOIN specialisation sp ON s."idSpecialisation" = sp."idSpecialisation"
    INNER JOIN unite_recherche ur ON sp."idUnite_recherche" = ur.idunite_recherche
    LEFT JOIN agent d ON s."idDirecteur" = d."idAgent"
    LEFT JOIN grade dg ON d.grade_id = dg.idgrade
    LEFT JOIN agent e ON s."idEncadreur" = e."idAgent"
    LEFT JOIN grade eg ON e.grade_id = eg.idgrade
    WHERE s.etudiant_idetudiant IS NULL
    AND s.cycle = :cycle
    AND sp.idsection IN (
        SELECT section_idsection
        FROM orientation
        WHERE idorientation = :orientationId
    )';
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':cycle', $studentInfo['cycle'], PDO::PARAM_STR);
    $stmt->bindParam(':orientationId', $studentInfo['idorientation'], PDO::PARAM_INT);
    $stmt->execute();
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSubjects = array_map(function($subject) {
        return [
            'id' => $subject['idsujets'],
            'title' => $subject['intitule'],
            'specialization' => [
                'id' => $subject['idSpecialisation'],
                'name' => $subject['specialisation_nom'],
                'research_unit' => $subject['unite_recherche_nom']
            ],
            'director' => $subject['directeur_id'] ? [
                'id' => $subject['directeur_id'],
                'name' => $subject['directeur_nom'],
                'title' => $subject['directeur_grade']
            ] : null,
            'supervisor' => $subject['encadreur_id'] ? [
                'id' => $subject['encadreur_id'],
                'name' => $subject['encadreur_nom'],
                'title' => $subject['encadreur_grade']
            ] : null,
            'has_director' => $subject['directeur_id'] ? true : false,
            'has_supervisor' => $subject['encadreur_id'] ? true : false
        ];
    }, $subjects);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedSubjects
    ]);
}

/**
 * Handle getting available supervisors for the student
 *
 * @param PDO $conn Database connection
 * @param array $studentInfo Student information
 */
function handleGetAvailableSupervisors($conn, $studentInfo) {
    // Inspiré par getAgentsDirection()
    $query = 'SELECT
        a."idAgent",
        a.noms,
        g.designation as grade,
        g.idgrade
    FROM agent a
    JOIN grade g ON a.grade_id = g.idgrade
    JOIN agent_section ags ON a."idAgent" = ags."idAgent"
    WHERE g.type_agent = \'Enseignant\'
    AND ags.idsection IN (
        SELECT section_idsection
        FROM orientation
        WHERE idorientation = :orientationId
    )
    ORDER BY a.noms';
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':orientationId', $studentInfo['idorientation'], PDO::PARAM_INT);
    $stmt->execute();
    
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSupervisors = array_map(function($supervisor) {
        return [
            'id' => $supervisor['idAgent'],
            'name' => $supervisor['noms'],
            'title' => $supervisor['grade']
        ];
    }, $supervisors);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedSupervisors
    ]);
}

/**
 * Handle getting specializations available for the student's orientation
 *
 * @param PDO $conn Database connection
 * @param array $studentInfo Student information
 */
function handleGetSpecializations($conn, $studentInfo) {
    // Utiliser la requête inspirée par getSpecialisationsByOrientation()
    $query = 'SELECT
        s."idSpecialisation",
        s.designation,
        ur.idunite_recherche,
        ur."designation_UR"
    FROM specialisation s
    INNER JOIN unite_recherche ur ON s."idUnite_recherche" = ur.idunite_recherche
    INNER JOIN section sec ON s.idsection = sec.idsection
    INNER JOIN orientation o ON o.section_idsection = sec.idsection
    WHERE o.idorientation = :orientationId
    ORDER BY s.designation';
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':orientationId', $studentInfo['idorientation'], PDO::PARAM_INT);
    $stmt->execute();
    
    $specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSpecializations = array_map(function($spec) {
        return [
            'id' => $spec['idSpecialisation'],
            'name' => $spec['designation'],
            'research_unit' => [
                'id' => $spec['idunite_recherche'],
                'name' => $spec['designation_UR']
            ]
        ];
    }, $specializations);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedSpecializations
    ]);
}

/**
 * Handle getting the student's current thesis
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param array $studentInfo Student information
 */
function handleGetThesis($conn, $studentId, $studentInfo) {
    try {
        $stmt = $conn->prepare('
            SELECT
                s.idsujets,
                s.intitule,
                s."etatSujet",
                s.statut_validation,
                s.commentaire_commission,
                s.date_validation,
                s."idDirecteur",
                s."idEncadreur",
                sp."idSpecialisation",
                sp.designation as specialisation_nom,
                ur.idunite_recherche,
                ur."designation_UR",
                d."idAgent" as directeur_id,
                d.noms as directeur_nom,
                dg.designation as directeur_grade,
                e."idAgent" as encadreur_id,
                e.noms as encadreur_nom,
                eg.designation as encadreur_grade
            FROM sujets s
            LEFT JOIN specialisation sp ON s."idSpecialisation" = sp."idSpecialisation"
            LEFT JOIN unite_recherche ur ON sp."idUnite_recherche" = ur.idunite_recherche
            LEFT JOIN agent d ON s."idDirecteur" = d."idAgent"
            LEFT JOIN agent e ON s."idEncadreur" = e."idAgent"
            LEFT JOIN grade dg ON d.grade_id = dg.idgrade
            LEFT JOIN grade eg ON e.grade_id = eg.idgrade
            WHERE s.etudiant_idetudiant = :studentId
            AND s.annee_acad_idannee_acad = :academicYear
            LIMIT 1
        ');
        
        $stmt->bindParam(':studentId', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':academicYear', $studentInfo['annee_acad_idannee_acad'], PDO::PARAM_INT);
        $stmt->execute();
        
        $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$thesis) {
            echo json_encode([
                'success' => true,
                'message' => 'Aucun sujet trouvé pour cet étudiant',
                'data' => null
            ]);
            return;
        }
        
        $formattedThesis = [
            'id' => $thesis['idsujets'],
            'title' => $thesis['intitule'],
            'status' => $thesis['etatSujet'],
            'validation_status' => $thesis['statut_validation'],
            'validation_comment' => $thesis['commentaire_commission'],
            'validation_date' => $thesis['date_validation'],
            'specialization' => [
                'id' => $thesis['idSpecialisation'],
                'name' => $thesis['specialisation_nom'],
                'research_unit' => [
                    'id' => $thesis['idunite_recherche'],
                    'name' => $thesis['designation_UR']
                ]
            ],
            'director' => $thesis['directeur_id'] ? [
                'id' => $thesis['directeur_id'],
                'name' => $thesis['directeur_nom'],
                'title' => $thesis['directeur_grade']
            ] : null,
            'supervisor' => $thesis['encadreur_id'] ? [
                'id' => $thesis['encadreur_id'],
                'name' => $thesis['encadreur_nom'],
                'title' => $thesis['encadreur_grade']
            ] : null
        ];
        
        // Get tasks related to this thesis
        $formattedThesis['tasks'] = getThesisTasks($conn, $thesis['idsujets']);
        
        echo json_encode([
            'success' => true,
            'data' => $formattedThesis
        ]);
    } catch (Exception $e) {
        // Log l'erreur pour le débogage
        error_log("Error in handleGetThesis: " . $e->getMessage());
        
        // Renvoyer une réponse d'erreur formatée
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération du sujet: ' . $e->getMessage(),
            'error_code' => 500
        ]);
    }
}


/**
 * Get comments for a task
 *
 * @param PDO $conn Database connection
 * @param int $taskId Task ID
 * @return array Comments list
 */
function getTaskComments($conn, $taskId) {
    $stmt = $conn->prepare('
        SELECT
            idechange,
            "dateEchange",
            commentaire,
            "fichierJoint",
            type_auteur,
            "idAuteur"
        FROM echanges_taches
        WHERE taches_idtaches = ?
        ORDER BY "dateEchange" ASC
    ');
    
    $stmt->execute([$taskId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $baseUrl = getBaseUrl();
    
    $formattedComments = array_map(function($comment) use ($conn, $baseUrl) {
        // Get author name based on type
        $authorName = getAuthorName($conn, $comment['type_auteur'], $comment['idAuteur']);
        
        return [
            'id' => $comment['idechange'],
            'date' => $comment['dateEchange'],
            'comment' => $comment['commentaire'],
            'file' => $comment['fichierJoint'] ? $baseUrl . '/uploads/' . $comment['fichierJoint'] : null,
            'author_type' => $comment['type_auteur'],
            'author_id' => $comment['idAuteur'],
            'author_name' => $authorName
        ];
    }, $comments);
    
    return $formattedComments;
}

/**
 * Get author name based on type
 *
 * @param PDO $conn Database connection
 * @param string $type Author type (Etudiant, Enseignant)
 * @param int $id Author ID
 * @return string Author name
 */
function getAuthorName($conn, $type, $id) {
    if ($type === 'Etudiant') {
        $stmt = $conn->prepare("SELECT noms FROM etudiant WHERE idetudiant = ?");
    } else { // Directeur or Encadreur
        $stmt = $conn->prepare('SELECT noms FROM agent WHERE "idAgent" = ?');
    }
    
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['noms'] : 'Inconnu';
}

/**
 * Handle getting a single task
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 * @param int $taskId Task ID
 */
function handleGetTask($conn, $studentId, $taskId) {
    // Verify the student has access to this task
    $stmt = $conn->prepare("
        SELECT t.*
        FROM taches t
        JOIN sujets s ON t.sujets_idsujets = s.idsujets
        WHERE t.idtaches = ? AND s.etudiant_idetudiant = ?
    ");
    $stmt->execute([$taskId, $studentId]);
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        
        throw new Exception('Tâche non trouvée ou accès non autorisé', 404);
    }
    
    $baseUrl = getBaseUrl();
    
    // Get comments for this task
    $comments = getTaskComments($conn, $taskId);
    
    $formattedTask = [
        'id' => $task['idtaches'],
        'date' => $task['dateTache'],
        'description' => $task['description'],
        'file' => $task['fichierTache'] ? $baseUrl . '/uploads/' . $task['fichierTache'] : null,
        'validation_status' => $task['validation'],
        'progress' => (int)$task['pourcentage_avancement'],
        'comments' => $comments
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $formattedTask
    ]);
}

/**
 * Handle getting a student's profile
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student ID
 */
function handleGetProfile($conn, $studentId) {
    $stmt = $conn->prepare('
        SELECT
            e.idetudiant,
            e.noms,
            e.telephone,
            e.adressemail as email,
            e.sexe,
            p.cycle,
            o.idorientation,
            o."designationOrientation" as orientation_nom,
            s.idsection,
            s."designationSection" as section_nom,
            aa.idannee_acad,
            aa.designation as annee_academique
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
        WHERE e.idetudiant = ?
    ');
    
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Étudiant non trouvé', 404);
    }
    
    $profile = [
        'id' => $student['idetudiant'],
        'name' => $student['noms'],
        'email' => $student['email'],
        'phone' => $student['telephone'],
        'gender' => $student['sexe'],
        'cycle' => $student['cycle'],
        'orientation' => [
            'id' => $student['idorientation'],
            'name' => $student['orientation_nom']
        ],
        'section' => [
            'id' => $student['idsection'],
            'name' => $student['section_nom']
        ],
        'academic_year' => [
            'id' => $student['idannee_acad'],
            'name' => $student['annee_academique']
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $profile
    ]);
}
?>
