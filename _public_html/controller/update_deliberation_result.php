<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Utilisateur non connecté'
        }).then(() => {
            window.location.href = '../login';
        });
    </script>";
    exit();
}

// Vérifier les droits d'accès
$universite = new Universite();
$agent = new Agent();
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$isJuryPresident = $universite->isJuryPresident($agentId);

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$deliberationId = isset($_POST['deliberation_id']) ? intval($_POST['deliberation_id']) : 0;
$matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
$decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
$creditsObtenus = isset($_POST['credits_obtenus']) ? intval($_POST['credits_obtenus']) : 0;
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

// Valider les paramètres
if (!$deliberationId || empty($matricule) || empty($decision) || $creditsObtenus < 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres invalides'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/resultats&deliberation_id=$deliberationId';
        });
    </script>";
    exit();
}

// Créer une instance de la classe Deliberation
$deliberation = new Deliberation();

// Récupérer les informations de la délibération
$deliberationInfo = $deliberation->getDeliberationInfo($deliberationId);
if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Délibération introuvable'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Vérifier si la délibération n'est pas déjà publiée
if ($deliberationInfo['statut'] === 'Publiée') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de modifier une délibération publiée'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/resultats&deliberation_id=$deliberationId';
        });
    </script>";
    exit();
}

// Mettre à jour le résultat
try {
    $result = $deliberation->updateStudentResult(
        $deliberationId,
        $matricule,
        $decision,
        $creditsObtenus,
        $commentaire,
        $userId
    );
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le résultat a été mis à jour avec succès'
            }).then(() => {
                window.location.href = '../index.php?view=deliberation/resultats&deliberation_id=$deliberationId';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour du résultat'
            }).then(() => {
                window.location.href = '../index.php?view=deliberation/resultats&deliberation_id=$deliberationId';
            });
        </script>";
    }
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/resultats&deliberation_id=$deliberationId';
        });
    </script>";
}
