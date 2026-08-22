<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

$agent = new Agent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $presenceId = isset($_POST['idPresence']) ? intval($_POST['idPresence']) : 0;

    if ($presenceId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de présence invalide.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit();
    }

    // Delete the presence record
    if ($agent->deletePresence($presenceId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Présence supprimée avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la présence.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    }
} else {
    header("Location: ../grh/agent.pres.add");
    exit();
}
?>