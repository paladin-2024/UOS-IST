<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$idHoraire = $_GET['id'] ?? null;

if (!$idHoraire) {
    echo json_encode(['error' => 'ID de l\'horaire non spécifié']);
    exit;
}

try {
    $horaireModel = new Horaire();
    $universite = new Universite();
    $horaire = $horaireModel->getHoraireById($idHoraire);
    
    if (!$horaire) {
        echo json_encode(['error' => 'Horaire non trouvé']);
        exit;
    }
    
    // Vérifier si l'utilisateur est administrateur
    $isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
    $userId = $_SESSION['id'];
    
    // Si ce n'est pas un admin, vérifier l'accès
    if (!$isAdmin) {
        // Récupérer l'année académique actuelle
        $currentYear = $universite->getCurrentAcademicYear();
        if (!$currentYear) {
            echo json_encode(['error' => 'Année académique non trouvée']);
            exit;
        }
        
        // Vérifier si l'utilisateur a accès à cet horaire via la promotion
        $pdo = Connexion::getInstance()->getPDO();
        $query = 'SELECT COUNT(*)
                  FROM horaire h
                  INNER JOIN ecue e ON e."idECUE" = h."ECUE_idECUE"
                  INNER JOIN ue u ON u."idUE" = e."UE_idUE"
                  INNER JOIN semestre sem ON sem.idsemestre = u.semestre_idsemestre
                  INNER JOIN promotion p ON p.idpromotion = sem.promotion_idpromotion
                  INNER JOIN orientation o ON o.idorientation = p.orientation_idorientation
                  INNER JOIN section s ON s.idsection = o.section_idsection
                  INNER JOIN responsable_section rs ON rs.section_idsection = s.idsection
                  WHERE h.idhoraire = :horaireId
                  AND rs."idUser" = :userId
                  AND rs.annee_acad_idannee_acad = :anneeId';
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':horaireId', $idHoraire);
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }
    }
    
    echo json_encode($horaire);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
