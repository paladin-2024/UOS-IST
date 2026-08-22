<?php
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../login';
        });
    </script>";
    exit();
}

// Initialiser les modèles
$universite = new Universite();
$agent = new Agent();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = $universite->isJuryPresident($agentId);

if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour modifier le statut d\'une délibération.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit();
}

// Récupérer les données du formulaire
$deliberationId = isset($_POST['deliberation_id']) ? intval($_POST['deliberation_id']) : 0;
$nouveauStatut = isset($_POST['nouveau_statut']) ? $_POST['nouveau_statut'] : '';
$motifChangement = isset($_POST['motif_changement']) ? trim($_POST['motif_changement']) : '';

// Validation des données
if (!$deliberationId || !$nouveauStatut || !$motifChangement) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Tous les champs obligatoires doivent être remplis.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit();
}

// Vérifier si l'utilisateur a les droits de modifier cette délibération
$deliberation = $universite->getDeliberationById($deliberationId);
if (!$deliberation) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Délibération non trouvée.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit();
}

// Vérifier si le président du jury a le droit de modifier cette délibération
if (!$isAdmin && $deliberation['statut'] === 'Publiée') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous ne pouvez pas modifier une délibération qui a déjà été publiée.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit();
}

// Vérifier si le président du jury est bien le président de ce jury
if (!$isAdmin && $isJuryPresident) {
    $bureau = $universite->getJuryById($deliberation['idbureau']);
    if (!$bureau || $bureau['president_id'] != $agentId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas le président de ce jury.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }
}

// Vérifier si un non-admin tente de mettre le statut à "Publiée"
if (!$isAdmin && $nouveauStatut === 'Publiée') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Seul l\'administrateur peut publier une délibération.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit();
}

// Mettre à jour le statut de la délibération
try {
    $result = $universite->updateDeliberationStatus(
        $deliberationId,
        $nouveauStatut,
        $motifChangement,
        $userId
    );
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le statut de la délibération a été mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../deliberation/seances?bureau=" . $deliberation['idbureau'] . "&annee=" . $deliberation['annee_acad_id'] . "&session=" . $deliberation['session_idsession'] . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour du statut.'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Exception: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.history.back();
        });
    </script>";
}
?>
