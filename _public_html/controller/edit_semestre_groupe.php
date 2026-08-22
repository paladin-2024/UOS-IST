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
    $semestreIds = isset($_POST['semestre_ids']) ? json_decode($_POST['semestre_ids'], true) : [];
    $numeroSemestre = isset($_POST['numeroSemestre']) ? trim($_POST['numeroSemestre']) : '';
    
    // Validation des données
    if (empty($numeroSemestre) || empty($semestreIds) || !is_array($semestreIds)) {
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
    
    // Mise à jour de tous les semestres
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($semestreIds as $id) {
        $result = $universite->updateSemestreNumero($id, $numeroSemestre);
        if ($result) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Message de résultat
    if ($successCount > 0) {
        $message = "$successCount semestre(s) mis à jour avec succès.";
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
                text: 'Une erreur est survenue lors de la mise à jour des semestres.'
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
