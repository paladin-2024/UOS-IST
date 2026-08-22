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

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les paramètres du formulaire
$deliberationId = isset($_POST['deliberation_id']) ? intval($_POST['deliberation_id']) : 0;
$bureauId = isset($_POST['bureau_id']) ? intval($_POST['bureau_id']) : 0;
$promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
$sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$anneeId = isset($_POST['annee_id']) ? intval($_POST['annee_id']) : 0;
$typeDeliberation = isset($_POST['type_deliberation']) ? $_POST['type_deliberation'] : '';
$semestreId = isset($_POST['semestre_id']) ? intval($_POST['semestre_id']) : 0;
$etapes = isset($_POST['etapes']) ? $_POST['etapes'] : [];

// Vérifier si tous les paramètres nécessaires sont présents
if (!$deliberationId || !$bureauId || !$promotionId || !$sessionId || !$anneeId || !$typeDeliberation || empty($etapes)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants pour la délibération.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Vérifier si le type de délibération est valide
if ($typeDeliberation === 'semestre' && !$semestreId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Veuillez sélectionner un semestre pour la délibération.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/process&deliberation_id=$deliberationId&bureau_id=$bureauId&promotion_id=$promotionId&session_id=$sessionId&annee_id=$anneeId';
        });
    </script>";
    exit();
}

// Si type annuelle, mettre semestreId à 0 pour traiter tous les semestres
if ($typeDeliberation === 'annuelle') {
    $semestreId = 0;
}

// Créer une instance de la classe Deliberation
$deliberation = new Deliberation();

// Initialiser le processus de délibération
$processId = $deliberation->initializeProcess($deliberationId, $userId);
if (!$processId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible d\'initialiser le processus de délibération.'
        }).then(() => {
            window.location.href = '../index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Exécuter le processus de délibération en arrière-plan
// Pour un environnement de production, il serait préférable d'utiliser une file d'attente de tâches
// comme RabbitMQ, Redis ou un système de tâches asynchrones

// Démarrer l'exécution du processus
$success = $deliberation->executeDeliberation($processId, $deliberationId, $typeDeliberation, $semestreId, $etapes, $userId);

// Rediriger vers la page de suivi du processus
header("Location: ../index.php?view=deliberation/execution&process_id=$processId&deliberation_id=$deliberationId");
exit();
?>
