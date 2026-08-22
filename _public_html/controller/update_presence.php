<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$agent = new Agent();
$structureModel = new Structure();

//echo (var_dump($_POST));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idPresence = isset($_POST['idPresence']) ? intval($_POST['idPresence']) : 0;
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $annee = isset($_POST['annee']) ? trim($_POST['annee']) : '';
    $mois = isset($_POST['mois']) ? trim($_POST['mois']) : '';
    $joursPresence = isset($_POST['joursPresence']) ? intval($_POST['joursPresence']) : 0;
    $joursAbsence = isset($_POST['joursAbsence']) ? intval($_POST['joursAbsence']) : 0;

    if (empty($annee) || empty($mois) || $agentId <= 0 || $idPresence <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit();
    }

    // Retrieve the agent's structure to get the working days
    $agentData = $agent->getAgentById($agentId);
    $structureId = $agentData['idStructure'];
    $structureData = $structureModel->getStructureById($structureId);
    $joursOuvrables = $structureData['joursOuvrables'];

    // Validate presence days do not exceed working days
    if ($joursPresence > $joursOuvrables) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les jours de présence ne peuvent pas dépasser les jours ouvrables configurés.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit();
    }

    // Calculate absence days automatically
    $joursAbsence = $joursOuvrables - $joursPresence;

    // Update the presence record
    if ($agent->updatePresence($idPresence, $annee, $mois, $joursPresence, $joursAbsence)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Présence mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la présence.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    }

    
    //exit();
} else {
    header("Location: ../grh/agent.pres.add");
    exit();
}
?>