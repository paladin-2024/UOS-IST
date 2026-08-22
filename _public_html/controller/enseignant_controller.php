<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Récupérer l'action à effectuer
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Instancier les classes nécessaires
$enseignant = new Enseignant();
$ecue = new Ecue();

// Traiter l'action demandée
switch ($action) {
    case 'add_assistant':
         // Vérifier que tous les paramètres nécessaires sont présents
         if (!isset($_POST['idECUE']) || !isset($_POST['assistant_id']) || !isset($_POST['poste']) || !isset($_POST['annee_acad_id'])) {
            $_SESSION['error'] = "Tous les champs sont obligatoires.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        $idECUE = intval($_POST['idECUE']);
        $assistantId = intval($_POST['assistant_id']);
        $poste = htmlspecialchars($_POST['poste']);
        $anneeAcadId = intval($_POST['annee_acad_id']);
        
        // Vérifier que l'utilisateur actuel est bien titulaire du cours
        $userId = $_SESSION['id'];
        $idEnseignant = $enseignant->getAgentIdByUserId($userId);
        
        if (!$idEnseignant) {
            $_SESSION['error'] = "Vous n'êtes pas autorisé à effectuer cette action.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Vérifier que l'enseignant est titulaire du cours
        $isTitulaire = $enseignant->isEnseignantTitulaire($idEnseignant, $idECUE, $anneeAcadId);
        
        if (!$isTitulaire) {
            $_SESSION['error'] = "Vous devez être titulaire du cours pour ajouter un assistant.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Vérifier que l'assistant n'est pas déjà affecté à ce cours
        $isAlreadyAssigned = $enseignant->isEnseignantAssignedToEcue($assistantId, $idECUE, $anneeAcadId);
        
        if ($isAlreadyAssigned) {
            $_SESSION['error'] = "Cet enseignant est déjà affecté à ce cours.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Ajouter l'assistant au cours
        $result = $enseignant->addEnseignantToEcue($assistantId, $idECUE, $poste, $anneeAcadId);
        
        if ($result) {
            $_SESSION['success'] = "L'assistant a été ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de l'ajout de l'assistant.";
        }
        
        header('Location: ../?view=recherche/mes_cours');
        exit;
        break;
        
    case 'remove_assistant':
        // Vérifier que tous les paramètres nécessaires sont présents
        if (!isset($_GET['ecue']) || !isset($_GET['assistant'])) {
            $_SESSION['error'] = "Paramètres manquants.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        $idECUE = intval($_GET['ecue']);
        $assistantId = intval($_GET['assistant']);
        
        // Récupérer l'année académique actuelle
        $universite = new Universite();
        $currentYear = $universite->getCurrentAcademicYear();
        $anneeAcadId = $currentYear['idannee_acad'];
        
        // Vérifier que l'utilisateur actuel est bien titulaire du cours
        $userId = $_SESSION['id'];
        $idEnseignant = $enseignant->getAgentIdByUserId($userId);
        
        if (!$idEnseignant) {
            $_SESSION['error'] = "Vous n'êtes pas autorisé à effectuer cette action.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Vérifier que l'enseignant est titulaire du cours
        $isTitulaire = $enseignant->isEnseignantTitulaire($idEnseignant, $idECUE, $anneeAcadId);
        
        if (!$isTitulaire) {
            $_SESSION['error'] = "Vous devez être titulaire du cours pour retirer un assistant.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Vérifier que l'assistant n'est pas titulaire
        $isAssistantTitulaire = $enseignant->isEnseignantTitulaire($assistantId, $idECUE, $anneeAcadId);
        
        if ($isAssistantTitulaire) {
            $_SESSION['error'] = "Vous ne pouvez pas retirer un enseignant titulaire.";
            header('Location: ../?view=recherche/mes_cours');
            exit;
        }
        
        // Retirer l'assistant du cours
        $result = $enseignant->removeEnseignantFromEcue($assistantId, $idECUE, $anneeAcadId);
        
        if ($result) {
            $_SESSION['success'] = "L'enseignant a été retiré avec succès.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors du retrait de l'enseignant.";
        }
        
        header('Location: ../?view=recherche/mes_cours');
        exit;
        
    default:
        header('Location: ../?view=recherche/mes_cours');
        exit;
}