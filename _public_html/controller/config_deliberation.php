<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header("Location: ../accueil");
    exit();
}

$universite = new Universite();
$agent = new Agent();

// Vérifier si l'utilisateur est administrateur ou président d'un jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = false;

// Vérifier si l'utilisateur est président d'un jury actif
$jurysPresides = [];
if ($agentId) {
    $jurysPresides = $universite->getJurysPresidesByAgent($agentId);
    $isJuryPresident = !empty($jurysPresides);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = '../accueil';
        });
    </script>";
    exit();
}

// Récupérer l'ID du bureau de jury si spécifié
$idBureau = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Validation des paramètres
if ($idBureau && (!$isAdmin && !$universite->isAgentJuryPresident($agentId, $idBureau))) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'êtes pas autorisé à configurer ce jury.'
        }).then(() => {
            window.location.href = '../deliberation/config';
        });
    </script>";
    exit();
}

// Traitement du formulaire de configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idBureau = $_POST['idbureau'] ?? 0;
    $sessionId = $_POST['session_id'] ?? 0;
    $anneeId = $_POST['annee_id'] ?? 0;
    
    // Valider que l'utilisateur a le droit de configurer ce jury
    if (!$isAdmin && !$universite->isAgentJuryPresident($agentId, $idBureau)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Vous n\'êtes pas autorisé à configurer ce jury.'
            }).then(() => {
                window.location.href = '../deliberation/config';
            });
        </script>";
        exit();
    }
    
    // Récupérer les paramètres de configuration
   // Récupérer les paramètres de configuration
    $configParams = [
        'idbureau' => $idBureau,
        'session_idsession' => $sessionId,
        'annee_acad_idannee_acad' => $anneeId,
        'compensation_intra_ue' => isset($_POST['compensation_intra_ue']) ? 1 : 0,
        'seuil_compensation_intra_ue' => $_POST['seuil_compensation_intra_ue'] ?? 8.00,
        'compensation_inter_ue' => isset($_POST['compensation_inter_ue']) ? 1 : 0,
        'seuil_compensation_inter_ue' => $_POST['seuil_compensation_inter_ue'] ?? 8.00,
        'exiger_meme_credit_ue' => isset($_POST['exiger_meme_credit_ue']) ? 1 : 0,
        'compensation_inter_semestre' => isset($_POST['compensation_inter_semestre']) ? 1 : 0,
        'seuil_compensation_inter_semestre' => $_POST['seuil_compensation_inter_semestre'] ?? 8.00,
        'limiter_compensation_annee' => isset($_POST['limiter_compensation_annee']) ? 1 : 0,
        'note_passage' => $_POST['note_passage'] ?? 10.00,
        'pourcentage_passage_semestre' => $_POST['pourcentage_passage_semestre'] ?? 50.00,
        'calculer_moyenne_avec_notes_vides' => isset($_POST['calculer_moyenne_avec_notes_vides']) ? 1 : 0,
        'idUser' => $userId
    ];

    // Sauvegarder la configuration
    $result = $universite->saveDeliberationConfig($configParams);
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Configuration de délibération enregistrée avec succès.'
            }).then(() => {
                window.location.href = '../deliberation/config_deliberation?bureau=" . $idBureau . "&session=" . $sessionId . "&annee=" . $anneeId . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'enregistrement de la configuration.'
            }).then(() => {
                window.location.href = '../deliberation/config_deliberation?bureau=" . $idBureau . "&session=" . $sessionId . "&annee=" . $anneeId . "';
            });
        </script>";
    }
    exit();
}

// Rediriger vers la vue
include dirname(__DIR__) . '/views/deliberation/config_deliberation';
