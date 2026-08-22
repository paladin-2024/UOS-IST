<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/models/PlanTravail.php';

// Vérifier l'authentification
if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['plan_id'])) {
    $_SESSION['plan_message'] = "ID du plan manquant";
    $_SESSION['plan_message_type'] = 'danger';
    header('Location: ../portail/student#plan');
    exit();
}

try {
    $planModel = new PlanTravail();
    $planId = (int)$_GET['plan_id'];
    
    // Récupérer le plan avec toutes les informations
    $plan = $planModel->getPlanById($planId);
    
    if (!$plan) {
        throw new Exception("Plan non trouvé");
    }
    
    // Vérifier que le plan appartient à l'étudiant connecté
    if ($plan['etudiant_idetudiant'] != $_SESSION['student_id']) {
        throw new Exception("Accès non autorisé à ce plan");
    }
    
    // Récupérer les chapitres
    $chapitres = $planModel->getChapitresByPlan($planId);
    
    // Génération du contenu du plan en format texte
    $contenu = "";
    $contenu .= "PLAN DE TRAVAIL\n";
    $contenu .= "================\n\n";
    
    $contenu .= "Titre: " . $plan['titre_plan'] . "\n";
    $contenu .= "Étudiant: " . $plan['etudiant_nom'] . " (" . $plan['matricule'] . ")\n";
    $contenu .= "Sujet: " . $plan['sujet_intitule'] . "\n";
    $contenu .= "Directeur: " . ($plan['directeur_nom'] ?? 'Non assigné') . "\n";
    if ($plan['encadreur_nom']) {
        $contenu .= "Encadreur: " . $plan['encadreur_nom'] . "\n";
    }
    $contenu .= "Spécialisation: " . ($plan['specialisation'] ?? 'Non spécifiée') . "\n";
    $contenu .= "Version: " . $plan['version'] . "\n";
    $contenu .= "Statut: " . $plan['statut_validation'] . "\n";
    $contenu .= "Date de soumission: " . date('d/m/Y', strtotime($plan['date_soumission'])) . "\n";
    if ($plan['date_validation']) {
        $contenu .= "Date de validation: " . date('d/m/Y', strtotime($plan['date_validation'])) . "\n";
    }
    $contenu .= "\n";
    
    if ($plan['introduction']) {
        $contenu .= "INTRODUCTION\n";
        $contenu .= "============\n";
        $contenu .= $plan['introduction'] . "\n\n";
    }
    
    if ($plan['problematique']) {
        $contenu .= "PROBLÉMATIQUE\n";
        $contenu .= "=============\n";
        $contenu .= $plan['problematique'] . "\n\n";
    }
    
    if ($plan['objectifs']) {
        $contenu .= "OBJECTIFS\n";
        $contenu .= "=========\n";
        $contenu .= $plan['objectifs'] . "\n\n";
    }
    
    if ($plan['methodologie']) {
        $contenu .= "MÉTHODOLOGIE\n";
        $contenu .= "============\n";
        $contenu .= $plan['methodologie'] . "\n\n";
    }
    
    if (!empty($chapitres)) {
        $contenu .= "STRUCTURE DU PLAN (CHAPITRES)\n";
        $contenu .= "=============================\n\n";
        
        foreach ($chapitres as $chapitre) {
            $contenu .= "Chapitre " . $chapitre['numero_chapitre'] . ": " . $chapitre['titre_chapitre'] . "\n";
            if ($chapitre['description']) {
                $contenu .= "Description: " . $chapitre['description'] . "\n";
            }
            $contenu .= "Statut: " . $chapitre['statut'] . "\n";
            if ($chapitre['pourcentage_avancement'] > 0) {
                $contenu .= "Avancement: " . $chapitre['pourcentage_avancement'] . "%\n";
            }
            if ($chapitre['deadline']) {
                $contenu .= "Deadline: " . date('d/m/Y', strtotime($chapitre['deadline'])) . "\n";
            }
            $contenu .= "\n";
        }
    }
    
    if ($plan['commentaire_directeur']) {
        $contenu .= "COMMENTAIRE DU DIRECTEUR\n";
        $contenu .= "========================\n";
        $contenu .= $plan['commentaire_directeur'] . "\n\n";
    }
    
    // Générer le nom du fichier
    $nomFichier = "Plan_Travail_" . preg_replace('/[^A-Za-z0-9]/', '_', $plan['etudiant_nom']) . "_v" . $plan['version'] . ".txt";
    
    // Headers pour téléchargement
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header('Content-Length: ' . strlen($contenu));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Sortir le contenu
    echo $contenu;
    exit();
    
} catch (Exception $e) {
    error_log("Erreur téléchargement plan: " . $e->getMessage());
    
    $_SESSION['plan_message'] = "Erreur lors du téléchargement: " . $e->getMessage();
    $_SESSION['plan_message_type'] = 'danger';
    header('Location: ../portail/student#plan');
    exit();
}
?>
