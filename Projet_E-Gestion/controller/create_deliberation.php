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
            text: 'Vous n\'avez pas les droits pour créer une délibération.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit();
}

// Récupérer les données du formulaire
$bureauId = isset($_POST['bureau_id']) ? intval($_POST['bureau_id']) : 0;
$anneeId = isset($_POST['annee_id']) ? intval($_POST['annee_id']) : 0;
$sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
$dateDeliberation = isset($_POST['date_deliberation']) ? $_POST['date_deliberation'] : null;
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

// Validation des données
if (!$bureauId || !$anneeId || !$sessionId || !$promotionId || !$dateDeliberation) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Tous les champs obligatoires doivent être remplis.'
        }).then(() => {
            window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
        });
    </script>";
    exit();
}

// Vérifier que le bureau existe et que l'utilisateur a les droits
if (!$isAdmin) {
    $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
    $hasAccess = false;
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId) {
            $hasAccess = true;
            break;
        }
    }
    
    if (!$hasAccess) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas autorisé à créer une délibération pour ce jury.'
            }).then(() => {
                window.location.href = '../deliberation/seances';
            });
        </script>";
        exit();
    }
}

// Vérifier que la promotion est associée au bureau
$promotions = $universite->getPromotionsByJury($bureauId);
$promotionExists = false;
foreach ($promotions as $promotion) {
    if ($promotion['idpromotion'] == $promotionId) {
        $promotionExists = true;
        break;
    }
}

if (!$promotionExists) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'La promotion sélectionnée n\'est pas associée à ce bureau de jury.'
        }).then(() => {
            window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
        });
    </script>";
    exit();
}

// Vérifier s'il existe déjà une délibération pour cette promotion, session et année
$existingDeliberation = $universite->getDeliberationByFilters($bureauId, $promotionId, $sessionId, $anneeId);
if ($existingDeliberation) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Délibération existante',
            text: 'Une délibération existe déjà pour cette promotion, cette session et cette année académique.'
        }).then(() => {
            window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
        });
    </script>";
    exit();
}

// Créer la délibération
try {
    $result = $universite->createDeliberation(
        $bureauId,
        $promotionId,
        $dateDeliberation,
        $sessionId,
        $anneeId,
        $commentaire,
        $userId
    );
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La séance de délibération a été créée avec succès.'
            }).then(() => {
                window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création de la délibération.'
            }).then(() => {
                window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
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
                        window.location.href = '../deliberation/seances?bureau=" . $bureauId . "&annee=" . $anneeId . "&session=" . $sessionId . "';
        });
    </script>";
}
?>

