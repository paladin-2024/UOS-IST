<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données
    $ueIds = isset($_POST['ue_ids']) ? json_decode($_POST['ue_ids'], true) : [];
    $codeUE = isset($_POST['codeUE']) ? trim($_POST['codeUE']) : '';
    $designationUE = isset($_POST['designationUE']) ? trim($_POST['designationUE']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validation des données
    if (empty($codeUE) || empty($designationUE) || empty($ueIds) || !is_array($ueIds)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Données invalides.'
            }).then(() => {
                window.location.href = '../enseignement/unites_enseignement';
            });
        </script>";
        exit();
    }
    
    // Mise à jour de toutes les UE
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($ueIds as $id) {
        $result = $universite->updateUEGroupe($id, $codeUE, $designationUE, $description);
        if ($result) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Message de résultat
    if ($successCount > 0) {
        $message = "$successCount UE(s) mise(s) à jour avec succès.";
        if ($errorCount > 0) {
            $message .= " $errorCount erreur(s) rencontrée(s).";
        }
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '{$message}'
            }).then(() => {
                window.location.href = '../enseignement/unites_enseignement';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour des UE.'
            }).then(() => {
                window.location.href = '../enseignement/unites_enseignement';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au script
    header('Location: ../enseignement/unites_enseignement');
    exit();
}
?>
