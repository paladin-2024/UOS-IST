<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

$agent = new Agent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $serviceId = isset($_POST['serviceId']) ? intval($_POST['serviceId']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $typeContrat = isset($_POST['typeContrat']) ? trim($_POST['typeContrat']) : '';
    $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
    $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
    $fonction = isset($_POST['fonction']) ? trim($_POST['fonction']) : '';
    $salaireDeBase = isset($_POST['salaireDeBase']) ? floatval($_POST['salaireDeBase']) : 0.0;
    $transport = isset($_POST['transport']) ? floatval($_POST['transport']) : 0.0;
    $logement = isset($_POST['logement']) ? floatval($_POST['logement']) : 0.0;
    $anciennete = isset($_POST['anciennete']) ? floatval($_POST['anciennete']) : 0.0;
    $idUser = $_SESSION['id'];

    if (empty($designation) || empty($typeContrat) || empty($dateDebut) || $agentId <= 0 || $serviceId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
        exit();
    }

    if ($agent->addContract($agentId, $designation, $typeContrat, $dateDebut, $dateFin, $fonction, $salaireDeBase, $transport, $logement, $anciennete, $serviceId, $idUser)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Contrat ajouté avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du contrat.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
    }
} else {
    header("Location: ../grh/agent.contrat.add");
    exit();
}
?>