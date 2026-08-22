<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit();
}

$agentModel = new Agent();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPresence = isset($_POST['idPresence']) ? intval($_POST['idPresence']) : 0;
    try {
        if ($idPresence <= 0) throw new Exception('Identifiant présence invalide.');
        $agentModel->deleteDailyPresence($idPresence);
        echo "<script>
            Swal.fire({ icon:'success', title:'Supprimé' }).then(()=>{ window.location.href='../grh/agent.pres.add';});
        </script>";
        exit();
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({ icon:'error', title:'Erreur', text:'" . addslashes($e->getMessage()) . "' }).then(()=>{ window.location.href='../grh/agent.pres.add';});
        </script>";
        exit();
    }
} else {
    header('Location: ../grh/agent.pres.add');
    exit();
}
?>

