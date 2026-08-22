<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Vous devez être connecté pour accéder à cette page.'
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

// Récupérer l'ID de la délibération
if (!isset($_GET['deliberation_id']) || empty($_GET['deliberation_id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiant de délibération manquant.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

$deliberationId = intval($_GET['deliberation_id']);

// Récupérer les informations de la délibération
$deliberationInfo = $universite->getDeliberationById($deliberationId);
if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Délibération introuvable.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Vérifier si la délibération peut être lancée
if ($deliberationInfo['statut'] === 'Publiée') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Cette délibération a déjà été publiée et ne peut plus être modifiée.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les paramètres nécessaires
$bureauId = $deliberationInfo['idbureau'];
$promotionId = $deliberationInfo['idpromotion'];
$sessionId = $deliberationInfo['session_idsession'];
$anneeId = $deliberationInfo['annee_acad_id'];

// Rediriger vers la page de choix du périmètre de délibération
header("Location: ../index.php?view=deliberation/process&deliberation_id=$deliberationId&bureau_id=$bureauId&promotion_id=$promotionId&session_id=$sessionId&annee_id=$anneeId");
exit();
?>
