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

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['ids'])) {
    // Récupération des données
    $semestreIds = json_decode($_GET['ids'], true);
    
    // Validation des données
    if (empty($semestreIds) || !is_array($semestreIds)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Données invalides.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
    // Suppression de tous les semestres
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($semestreIds as $id) {
        $result = $universite->deleteSemestre($id);
        if ($result) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Message de résultat
    if ($successCount > 0) {
        $message = "$successCount semestre(s) supprimé(s) avec succès.";
        if ($errorCount > 0) {
            $message .= " $errorCount erreur(s) rencontrée(s).";
        }
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '{$message}'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression des semestres.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au script
    header('Location: ../configuration/semestre');
    exit();
}
?>
