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
$pdo = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPresence = isset($_POST['idPresence']) ? intval($_POST['idPresence']) : 0;
    $datePresence = isset($_POST['datePresence']) ? trim($_POST['datePresence']) : '';
    $heureArrivee = isset($_POST['heureArrivee']) ? trim($_POST['heureArrivee']) : '';
    $heureDepart = isset($_POST['heureDepart']) ? trim($_POST['heureDepart']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $userId = $_SESSION['id'];

    try {
        if ($idPresence <= 0) throw new Exception('Identifiant présence invalide.');

        $fullArrivee = !empty($heureArrivee) ? $datePresence . ' ' . $heureArrivee . ':00' : null;
        $fullDepart = !empty($heureDepart) ? $datePresence . ' ' . $heureDepart . ':00' : null;
        if ($fullArrivee && $fullDepart && strtotime($fullDepart) < strtotime($fullArrivee)) {
            throw new Exception("L'heure de sortie ne peut pas être antérieure à l'heure d'arrivée.");
        }

        $pdo->beginTransaction();
        $agentModel->updateDailyPresence($idPresence, $fullArrivee, $fullDepart, $commentaire, $userId);
        $pdo->commit();

        echo "<script>
            Swal.fire({ icon:'success', title:'Mise à jour effectuée' }).then(()=>{ window.location.href='../grh/agent.pres.add';});
        </script>";
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
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

