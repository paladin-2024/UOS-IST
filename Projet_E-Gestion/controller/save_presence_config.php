<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jours = isset($_POST['jours']) && is_array($_POST['jours']) ? array_map('intval', $_POST['jours']) : [];
    $heure_debut = $_POST['heure_debut'] ?? '08:00';
    $heure_fin = $_POST['heure_fin'] ?? '17:00';
    $tolerance = isset($_POST['tolerance']) ? intval($_POST['tolerance']) : 15;
    $pause_debut = $_POST['pause_debut'] ?? null;
    $pause_fin = $_POST['pause_fin'] ?? null;

    try {
        if (empty($jours)) throw new Exception("Veuillez sélectionner au moins un jour de travail.");
        $csv = implode(',', $jours);
        $agentModel = new Agent();
        $agentModel->savePresenceConfig($csv, $heure_debut . ':00', $heure_fin . ':00', $tolerance, $pause_debut ? $pause_debut . ':00' : null, $pause_fin ? $pause_fin . ':00' : null, $_SESSION['id']);
        echo "<script>
            Swal.fire({ icon:'success', title:'Configuration enregistrée' }).then(()=>{ window.location.href='../grh/presence.config'; });
        </script>";
        exit();
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({ icon:'error', title:'Erreur', text:'" . addslashes($e->getMessage()) . "' }).then(()=>{ window.location.href='../grh/presence.config'; });
        </script>";
        exit();
    }
} else {
    header('Location: ../grh/presence.config');
    exit();
}
?>

