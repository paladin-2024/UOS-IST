<?php
// controller/save_moyennes.php
require_once '../config/config.php';
require_once '../config/Connexion.php';
require_once '../models/Deliberation.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

// Vérifier si la requête est en POST et contient des données JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

// Récupérer les paramètres
$bureauId = isset($input['bureau']) ? intval($input['bureau']) : 0;
$promotionId = isset($input['promotion']) ? intval($input['promotion']) : 0;
$semestreId = isset($input['semestre']) ? intval($input['semestre']) : 0;
$afficherDeuxSemestres = isset($input['deux_semestres']) && $input['deux_semestres'] == 1;
$sessionId = isset($input['session']) ? intval($input['session']) : 0;
$anneeId = isset($input['annee']) ? intval($input['annee']) : 0;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Paramètres incomplets']);
    exit();
}

try {
    $deliberation = new Deliberation();
    
    // Récupérer les semestres à afficher
    $semestres = $afficherDeuxSemestres 
        ? $deliberation->getSemestresByPromotion($promotionId) 
        : array_values(array_filter($deliberation->getSemestresByPromotion($promotionId), 
            function ($sem) use ($semestreId) {
                return $sem['idsemestre'] == $semestreId;
            }));
    
    // Récupérer les étudiants
    $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    
    // Récupérer les données calculées (moyennes, validations, etc.)
    // Cette partie dépend de comment vous avez structuré votre code
    // Vous devrez peut-être recalculer ces valeurs ici
    
    // Exemple simplifié (à adapter selon votre structure)
    $moyennesSemestre = [];
    $validationsSemestre = [];
    $moyennesAnnuelles = [];
    $validationsAnnuelles = [];
    
    // Pour chaque étudiant, calculer les moyennes
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        
        // Calculer les moyennes par semestre
        foreach ($semestres as $semestre) {
            $semestreId = $semestre['idsemestre'];
            
            // Récupérer la moyenne du semestre
            $moyenneSemestre = $deliberation->getMoyenneSemestre($matricule, $sessionId, $anneeId, $semestreId);
            if ($moyenneSemestre !== null) {
                $moyennesSemestre[$matricule][$semestreId] = $moyenneSemestre;
            }
            
            // Récupérer les crédits validés et totaux
            $creditsValides = $deliberation->getCreditsValides($matricule, $sessionId, $anneeId, [$semestre]);
            $creditsTotal = $deliberation->getCreditsTotal([$semestre]);
            
            // Calculer le pourcentage et déterminer si le semestre est validé
            $pourcentage = ($creditsTotal > 0) ? (($moyenneSemestre / 20) * 100) : 0;
            $estValide = ($moyenneSemestre >= 10 && $creditsValides == $creditsTotal);
            
            $validationsSemestre[$matricule][$semestreId] = [
                'credits_valides' => $creditsValides,
                'credits_total' => $creditsTotal,
                'pourcentage' => $pourcentage,
                'est_valide' => $estValide
            ];
        }
        
        // Calculer la moyenne annuelle si nécessaire
        if ($afficherDeuxSemestres) {
            $moyenneAnnuelle = $deliberation->getMoyenneAnnuelle($matricule, $sessionId, $anneeId, $semestres);
            if ($moyenneAnnuelle !== null) {
                $moyennesAnnuelles[$matricule] = $moyenneAnnuelle;
            }
            
            // Récupérer les crédits validés et totaux pour l'année
            $creditsValidesAnnee = $deliberation->getCreditsValides($matricule, $sessionId, $anneeId, $semestres);
            $creditsTotalAnnee = $deliberation->getCreditsTotal($semestres);
            
            // Calculer le pourcentage et déterminer si l'année est validée
            $pourcentageAnnee = ($creditsTotalAnnee > 0) ? (($moyenneAnnuelle / 20) * 100) : 0;
            $estAdmis = ($moyenneAnnuelle >= 10 && $creditsValidesAnnee == $creditsTotalAnnee);
            
            $validationsAnnuelles[$matricule] = [
                'credits_valides' => $creditsValidesAnnee,
                'credits_total' => $creditsTotalAnnee,
                'pourcentage' => $pourcentageAnnee,
                'est_valide' => $estAdmis
            ];
        }
    }
    
        // Sauvegarder les moyennes
        $userId = $_SESSION['id'];
        $success = $deliberation->sauvegarderMoyennes(
            $etudiants, 
            $moyennesSemestre, 
            $validationsSemestre, 
            $moyennesAnnuelles, 
            $validationsAnnuelles, 
            $sessionId, 
            $anneeId, 
            $semestres, 
            $promotionId, 
            $userId, 
            $afficherDeuxSemestres
        );
        
        // Renvoyer la réponse
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Moyennes sauvegardées avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde des moyennes']);
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    ?>
    