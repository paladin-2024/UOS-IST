<?php
session_start();
include_once "../config/Connexion.php";
include_once "../models/PlanTravail.php";

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs PHP pour éviter de corrompre le JSON
ini_set('display_errors', 0);
error_reporting(0);

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Récupérer les paramètres
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit = 20;

// Initialiser la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$planTravailModel = new PlanTravail();

// Fonctions utilitaires (reprises de fiches.php)
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

function calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel = null) {
    if (!$etudiantId) return ['pourcentage_global' => 0, 'details' => []];

    try {
        // Récupérer le sujet validé de l'étudiant
        $query = "SELECT idsujets FROM sujets WHERE etudiant_idetudiant = :etudiant_id AND statut_validation = 'Validé' LIMIT 1";
        $stmt = $connexion->prepare($query);
        $stmt->execute(['etudiant_id' => $etudiantId]);
        $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sujet) {
            return ['pourcentage_global' => 0, 'details' => []];
        }
        
        $sujetId = $sujet['idsujets'];
        
        // Récupérer le plan de travail
        $planTravail = $planTravailModel->getPlanBySujet($sujetId);
        if (!$planTravail) return ['pourcentage_global' => 0, 'details' => []];

        // Récupérer les chapitres
        $chapitres = $planTravailModel->getChapitresByPlan($planTravail['id_plan']);
        if (empty($chapitres)) return ['pourcentage_global' => 0, 'details' => []];

        $totalChapitres = count($chapitres);
        $chapitresTermines = 0;

        foreach ($chapitres as $chapitre) {
            if ($chapitre['statut'] === 'Terminé') {
                $chapitresTermines++;
            }
        }

        $progression = $totalChapitres > 0 ? round(($chapitresTermines / $totalChapitres) * 100, 1) : 0;

        return [
            'pourcentage_global' => $progression,
            'details' => [
                'total' => $totalChapitres,
                'termines' => $chapitresTermines,
                'en_cours' => $totalChapitres - $chapitresTermines
            ]
        ];
    } catch (Exception $e) {
        return ['pourcentage_global' => 0, 'details' => []];
    }
}

try {
    // Vérification des responsabilités de l'utilisateur connecté
    $currentUserId = $_SESSION['id'];
    $hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
    $currentAcademicYear = getCurrentAcademicYear($connexion);
    $userSections = [];
    $isResponsableSection = false;

    if (!$hasFullAccess && $currentAcademicYear) {
        $userSections = getUserSections($connexion, $currentUserId, $currentAcademicYear);
        $isResponsableSection = !empty($userSections);
    }

    // Si l'utilisateur n'a pas les droits d'accès
    if (!$hasFullAccess && !$isResponsableSection) {
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    // Récupérer les filtres (mêmes que dans fiches.php)
    $filtreNom = isset($_POST['recherche']) ? $_POST['recherche'] : '';
    $filtreSection = isset($_POST['section']) ? $_POST['section'] : '';
    $filtrePromotion = isset($_POST['promotion']) ? $_POST['promotion'] : '';
    $filtreAnnee = isset($_POST['annee']) ? $_POST['annee'] : '';

    // Construction de la requête avec les mêmes conditions que fiches.php
    $whereConditions = [];
    $queryParams = [];

    // Condition d'accès selon le rôle
    if (!$hasFullAccess && $isResponsableSection) {
        if (!empty($userSections)) {
            $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
            $whereConditions[] = "sec.idsection IN ($sectionsParams)";
            $queryParams = array_merge($queryParams, $userSections);
        }
    }

    // Filtres de recherche
    if (!empty($filtreNom)) {
        $whereConditions[] = "e.noms LIKE ?";
        $queryParams[] = '%' . $filtreNom . '%';
    }

    if (!empty($filtreSection)) {
        $whereConditions[] = "sec.idsection = ?";
        $queryParams[] = $filtreSection;
    }

    if (!empty($filtrePromotion)) {
        $whereConditions[] = "p.idpromotion = ?";
        $queryParams[] = $filtrePromotion;
    }

    if (!empty($filtreAnnee)) {
        $whereConditions[] = "aa.idannee_acad = ?";
        $queryParams[] = $filtreAnnee;
    }

    // CONDITION: Seulement les promotions terminales
    $whereConditions[] = "p.est_terminale = 1";
    
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);

    // Requête avec pagination
    $queryEtudiants = "SELECT DISTINCT e.idetudiant, e.matricule, e.noms, e.photo,
                              p.designationPromotion as promotion, p.idpromotion, p.cycle,
                              o.designationOrientation as orientation,
                              sec.designationSection as section, sec.idsection,
                              aa.designation as annee_academique,
                              s.idsujets, s.intitule as sujet_intitule, s.statut_validation as sujet_statut
                       FROM etudiant e
                       JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       JOIN orientation o ON p.orientation_idorientation = o.idorientation
                       JOIN section sec ON o.section_idsection = sec.idsection
                       JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                       LEFT JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant AND s.statut_validation = 'Validé'
                       $whereClause
                       AND e.est_actif = 1
                       ORDER BY e.noms
                       LIMIT $limit OFFSET $offset";

    $stmtEtudiants = $connexion->prepare($queryEtudiants);
    $stmtEtudiants->execute($queryParams);
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données par étudiant (même logique que fiches.php)
    $etudiantsSujets = [];
    foreach ($etudiants as $row) {
        $etudiantId = $row['idetudiant'];
        
        if (!isset($etudiantsSujets[$etudiantId])) {
            $etudiantsSujets[$etudiantId] = [
                'idetudiant' => $row['idetudiant'],
                'matricule' => $row['matricule'],
                'noms' => $row['noms'],
                'photo' => $row['photo'],
                'promotion' => $row['promotion'],
                'idpromotion' => $row['idpromotion'],
                'cycle' => $row['cycle'],
                'orientation' => $row['orientation'],
                'section' => $row['section'],
                'idsection' => $row['idsection'],
                'annee_academique' => $row['annee_academique'],
                'sujets' => []
            ];
        }

        if ($row['idsujets']) {
            $etudiantsSujets[$etudiantId]['sujets'][] = [
                'idsujets' => $row['idsujets'],
                'intitule' => $row['sujet_intitule'],
                'statut_validation' => $row['sujet_statut']
            ];
        }
    }

    // Calculer les progressions pour chaque étudiant
    foreach ($etudiantsSujets as &$etudiant) {
        $progressionData = calculerProgressionEtudiant($connexion, $etudiant['idetudiant'], $planTravailModel);
        $etudiant['progression'] = $progressionData['pourcentage_global'];
        $etudiant['progression_details'] = $progressionData['details'];
    }

    echo json_encode([
        'success' => true,
        'etudiants' => array_values($etudiantsSujets),
        'hasMore' => count($etudiants) === $limit
    ]);

} catch (Exception $e) {
    error_log("Erreur load_more_fiches: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
