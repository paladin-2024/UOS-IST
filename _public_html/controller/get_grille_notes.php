<?php
require_once dirname(__DIR__)."/config/Connexion.php";
require_once dirname(__DIR__)."/models/Deliberation.php";
require_once dirname(__DIR__)."/models/Universite.php";
require_once dirname(__DIR__)."/models/Ecue.php";
require_once dirname(__DIR__)."/models/Agent.php";

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

try {
    // Récupérer les paramètres
    $bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
    $promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
    $semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
    $sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
    $anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
    $afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
    
    // Vérifier les paramètres obligatoires
    if (!$bureauId || !$promotionId || (!$semestreId && !$afficherDeuxSemestres) || !$sessionId || !$anneeId) {
        throw new Exception('Paramètres manquants');
    }
    
    // Instancier les modèles
    $deliberation = new Deliberation();
    $universite = new Universite();
    $ecue = new Ecue();
    $agent = new Agent();
    
    // Vérifier les droits d'accès
    $isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
    $userId = $_SESSION['id'];
    $agentId = $agent->getAgentIdByUserId($userId);
    
    if (!$isAdmin) {
        $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
        $hasAccess = false;
        foreach ($juryBureaux as $jury) {
            if ($jury['idbureau'] == $bureauId) {
                $hasAccess = true;
                break;
            }
        }
        
        if (!$hasAccess) {
            throw new Exception('Accès refusé à ce bureau de jury');
        }
    }
    
    // Récupérer les données paginées
    $offset = ($page - 1) * $limit;
    $etudiants = $deliberation->getEtudiantsByPromotionPaginated($promotionId, $anneeId, $offset, $limit);
    $totalEtudiants = $deliberation->countEtudiantsByPromotion($promotionId, $anneeId);
    
    // Récupérer les semestres concernés
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
    $semestresToShow = $afficherDeuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
        return $sem['idsemestre'] == $semestreId;
    }));
    
    // Récupérer les UE et ECUE pour chaque semestre
    $uesBySemestre = [];
    $ecuesByUE = [];
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        $uesBySemestre[$semId] = $deliberation->getUEsBySemestre($semId);
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $ecuesByUE[$ueId] = $ecue->getECUEsByUE2($ueId);
        }
    }
    
    // Calculer et récupérer toutes les données nécessaires
    $notes = [];
    $moyennesUE = [];
    $validationsUE = [];
    $moyennesSemestre = [];
    $validationsSemestre = [];
    $moyennesAnnuelles = [];
    $validationsAnnuelles = [];
    
    // Code de calcul des moyennes et validations...
    // (similaire à celui présent dans le fichier principal)
    
    // Construire la réponse
    $response = [
        'etudiants' => $etudiants,
        'totalEtudiants' => $totalEtudiants,
        'semestres' => $semestresToShow,
        'uesBySemestre' => $uesBySemestre,
        'ecuesByUE' => $ecuesByUE,
        'notes' => $notes,
        'moyennesUE' => $moyennesUE,
        'validationsUE' => $validationsUE,
        'moyennesSemestre' => $moyennesSemestre,
        'validationsSemestre' => $validationsSemestre
    ];
    
    if ($afficherDeuxSemestres) {
        $response['moyennesAnnuelles'] = $moyennesAnnuelles;
        $response['validationsAnnuelles'] = $validationsAnnuelles;
    }
    
    // Renvoyer les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}