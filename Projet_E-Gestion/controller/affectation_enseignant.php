<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$ecueModel = new Ecue();
$enseignantModel = new Enseignant();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $idEcue = isset($_POST['idEcue']) ? intval($_POST['idEcue']) : 0;
    $idAgent = isset($_POST['enseignant']) ? intval($_POST['enseignant']) : 0;
    $poste = isset($_POST['poste']) ? trim($_POST['poste']) : '';
    $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;

    // Validation des données
    if ($idEcue <= 0 || $idAgent <= 0 || empty($poste) || $idAnneeAcad <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }

    // Récupérer l'UE associée à l'ECUE
    $ecueInfo = $ecueModel->getEcueById($idEcue);
    if (!$ecueInfo) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ECUE non trouvé.'
            }).then(() => {
                window.location.href = '../enseignement/unites_enseignement';
            });
        </script>";
        exit();
    }

    $ueId = $ecueInfo['UE_idUE'];

    // Vérifier si l'affectation existe déjà
    if ($enseignantModel->checkAffectationExists($idAgent, $idEcue, $idAnneeAcad)) {
        // Mettre à jour l'affectation existante
        $result = $enseignantModel->updateAffectation($idAgent, $idEcue, $poste, $idAnneeAcad);
        $message = $result ? 'L\'affectation a été mise à jour avec succès.' : 'Une erreur est survenue lors de la mise à jour de l\'affectation.';
    } else {
        // Créer une nouvelle affectation
        $result = $enseignantModel->affecterEnseignant($idAgent, $idEcue, $poste, $idAnneeAcad);
        $message = $result ? 'L\'enseignant a été affecté avec succès.' : 'Une erreur est survenue lors de l\'affectation de l\'enseignant.';
    }

    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../enseignement/ecues?ue={$ueId}';
        });
    </script>";
    exit();
} else {
    // Redirection si accès direct
    header("Location: ../enseignement/unites_enseignement");
    exit();
}
?>
