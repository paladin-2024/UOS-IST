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
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';
    $numeroSemestre = isset($_POST['numeroSemestre']) ? trim($_POST['numeroSemestre']) : '';
    
    // Validation des données
    if (empty($numeroSemestre)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le numéro du semestre est obligatoire.'
            }).then(() => {
                window.location.href = '../configuration/semestre';
            });
        </script>";
        exit();
    }
    
    if ($action === 'create_multiple') {
        // Récupération des promotions sélectionnées
        $promotions = isset($_POST['promotions']) ? $_POST['promotions'] : [];
        
        if (empty($promotions)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez sélectionner au moins une promotion.'
                }).then(() => {
                    window.location.href = '../configuration/semestre';
                });
            </script>";
            exit();
        }
        
        // Création du semestre dans chaque promotion sélectionnée
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($promotions as $promotionId) {
            $result = $universite->createSemestre($numeroSemestre, $promotionId);
            if ($result) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        // Message de résultat
        if ($successCount > 0) {
            $message = "Le semestre a été créé avec succès dans $successCount promotion(s).";
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
                    text: 'Une erreur est survenue lors de la création du semestre.'
                }).then(() => {
                    window.location.href = '../configuration/semestre';
                });
            </script>";
        }
    } else {
        // Ancienne logique pour un seul semestre
        $promotionId = isset($_POST['promotion_idpromotion']) ? intval($_POST['promotion_idpromotion']) : 0;
        
        if ($promotionId <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez sélectionner une promotion valide.'
                }).then(() => {
                    window.location.href = '../configuration/semestre';
                });
            </script>";
            exit();
        }
        
        // Création du semestre
        $result = $universite->createSemestre($numeroSemestre, $promotionId);
        
        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le semestre a été créé avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/semestre';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la création du semestre.'
                }).then(() => {
                    window.location.href = '../configuration/semestre';
                });
            </script>";
        }
    }
} else {
    // Redirection si accès direct au script
    header('Location: ../configuration/semestre');
    exit();
}
?>
