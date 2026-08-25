<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Stage.php';
require_once '../models/JournalServeur.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    $_SESSION['error_message'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index.php?view=login');
    exit;
}

// Vérifier les droits d'accès
$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

$stage = new Stage();
$journalServeur = new JournalServeur();

// Vérifier si l'utilisateur a des responsabilités (si pas admin)
if (!$hasFullAccess) {
    $userResponsibilities = $stage->getUserResponsibilities($userId);
    if (empty($userResponsibilities)) {
        $_SESSION['error_message'] = "Vous n'avez pas les droits pour effectuer cette action.";
        header('Location: ../index.php?view=stage');
        exit;
    }
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Traitement de l'affectation individuelle
    if (isset($_POST['individual_assign'])) {
        $studentId = $_POST['student_id'];
        $supervisorId = $_POST['supervisor_id'];
        $stageLocation = $_POST['stage_location'];
        $dateDebut = $_POST['date_debut'];
        $dateFin = $_POST['date_fin'];
        $promotionId = $_POST['promotion_id'];
        
        // Vérifier que l'étudiant existe et appartient à la promotion
        $checkStudent = $db->prepare("SELECT idetudiant FROM etudiant WHERE idetudiant = ? AND promotion_idpromotion = ?");
        $checkStudent->execute([$studentId, $promotionId]);
        
        if (!$checkStudent->fetch()) {
            throw new Exception("Étudiant invalide ou n'appartenant pas à cette promotion.");
        }
        
        // Insérer ou mettre à jour l'affectation
        $sql = "INSERT INTO stage_assignments (idetudiant, idencadreur, lieu_stage, date_debut, date_fin)
                VALUES (:studentId, :supervisorId, :location, :dateDebut, :dateFin)
                ON CONFLICT (idetudiant) DO UPDATE SET
                idencadreur = :supervisorId2,
                lieu_stage = :location2,
                date_debut = :dateDebut2,
                date_fin = :dateFin2";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'studentId' => $studentId,
            'supervisorId' => $supervisorId,
            'location' => $stageLocation,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'supervisorId2' => $supervisorId,
            'location2' => $stageLocation,
            'dateDebut2' => $dateDebut,
            'dateFin2' => $dateFin
        ]);
        
        // Journaliser l'affectation individuelle
        $description = "Étudiant affecté au stage - Lieu: $stageLocation, Période: $dateDebut à $dateFin";
        $journalServeur->enregistrerAction(
            'CREATE',
            'STAGE_ASSIGNMENT',
            $description,
            $userId,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'stage_assignments',
            $studentId,
            null,
            ['superviseur' => $supervisorId, 'lieu' => $stageLocation, 'date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'succes'
        );
        
        $_SESSION['success_message'] = "L'étudiant a été affecté avec succès au stage.";
        
    // Traitement de l'affectation en masse
    } elseif (isset($_POST['bulk_assign'])) {
        $studentIds = explode(',', $_POST['students']);
        $supervisorId = $_POST['supervisor_id'];
        $stageLocation = $_POST['stage_location'];
        $dateDebut = $_POST['date_debut'];
        $dateFin = $_POST['date_fin'];
        $promotionId = $_POST['promotion_id'];
        
        if (empty($studentIds)) {
            throw new Exception("Aucun étudiant sélectionné.");
        }
        
        // Préparer la requête d'insertion/mise à jour
        $sql = "INSERT INTO stage_assignments (idetudiant, idencadreur, lieu_stage, date_debut, date_fin)
                VALUES (:studentId, :supervisorId, :location, :dateDebut, :dateFin)
                ON CONFLICT (idetudiant) DO UPDATE SET
                idencadreur = :supervisorId2,
                lieu_stage = :location2,
                date_debut = :dateDebut2,
                date_fin = :dateFin2";

        $stmt = $db->prepare($sql);

        $successCount = 0;
        foreach ($studentIds as $studentId) {
            if (empty($studentId)) continue;
            
            // Vérifier que l'étudiant existe et appartient à la promotion
            $checkStudent = $db->prepare("SELECT idetudiant FROM etudiant WHERE idetudiant = ? AND promotion_idpromotion = ?");
            $checkStudent->execute([$studentId, $promotionId]);
            
            if ($checkStudent->fetch()) {
                $stmt->execute([
                    'studentId' => $studentId,
                    'supervisorId' => $supervisorId,
                    'location' => $stageLocation,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin,
                    'supervisorId2' => $supervisorId,
                    'location2' => $stageLocation,
                    'dateDebut2' => $dateDebut,
                    'dateFin2' => $dateFin
                ]);
                
                // Journaliser chaque affectation en masse
                $description = "Étudiant affecté au stage (affectation en masse) - Lieu: $stageLocation, Période: $dateDebut à $dateFin";
                $journalServeur->enregistrerAction(
                    'CREATE',
                    'STAGE_ASSIGNMENT',
                    $description,
                    $userId,
                    $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                    'stage_assignments',
                    $studentId,
                    null,
                    ['superviseur' => $supervisorId, 'lieu' => $stageLocation, 'date_debut' => $dateDebut, 'date_fin' => $dateFin, 'type' => 'bulk'],
                    'succes'
                );
                
                $successCount++;
            }
        }
        
        // Journaliser le résumé de l'affectation en masse
        if ($successCount > 0) {
            $journalServeur->enregistrerAction(
                'CREATE',
                'STAGE_ASSIGNMENT',
                "Affectation en masse complétée - $successCount étudiant(s) affectés",
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'stage_assignments',
                null,
                null,
                ['nombre_etudiants' => $successCount, 'superviseur' => $supervisorId, 'lieu' => $stageLocation],
                'succes'
            );
        }
        
        $_SESSION['success_message'] = "$successCount étudiant(s) ont été affectés avec succès au stage.";
    } else {
        throw new Exception("Action non reconnue.");
    }
    
} catch (Exception $e) {
    error_log("Erreur lors de l'affectation au stage: " . $e->getMessage());
    $_SESSION['error_message'] = "Erreur lors de l'affectation: " . $e->getMessage();
    
    // Journaliser l'erreur
    $journalServeur->enregistrerAction(
        'CREATE',
        'STAGE_ASSIGNMENT',
        "Tentative d'affectation au stage échouée",
        $userId,
        $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
        'stage_assignments',
        null,
        null,
        null,
        'erreur',
        $e->getMessage()
    );
}

// Redirection vers la page d'affectation avec les mêmes paramètres
$redirectUrl = '../index.php?view=stage/assign';
if (isset($_POST['promotion_id'])) {
    $redirectUrl .= '&promotion=' . $_POST['promotion_id'];
}
if (isset($_GET['annee_acad'])) {
    $redirectUrl .= '&annee_acad=' . $_GET['annee_acad'];
}

header('Location: ' . $redirectUrl);
exit;
?>