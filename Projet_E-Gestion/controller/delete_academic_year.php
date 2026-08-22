<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $connexion = Connexion::getInstance()->getPDO();
    
    $idannee_acad = isset($_GET['idannee_acad']) ? intval($_GET['idannee_acad']) : 0;

    if ($idannee_acad <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de l\'année académique invalide.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérifier que l'année académique existe
    $existsQuery = "SELECT COUNT(*) FROM annee_acad WHERE idannee_acad = :idannee_acad";
    $existsStmt = $connexion->prepare($existsQuery);
    $existsStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
    $existsStmt->execute();
    
    if ($existsStmt->fetchColumn() == 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cette année académique n\'existe pas.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérifier s'il y a des données liées à cette année académique
    $checkDataQuery = "
        SELECT 
            (SELECT COUNT(*) FROM etudiant WHERE annee_acad_idannee_acad = :idannee_acad) as etudiants,
            (SELECT COUNT(*) FROM promotion WHERE annee_acad_idannee_acad = :idannee_acad) as promotions,
            (SELECT COUNT(*) FROM sujets WHERE annee_acad_idannee_acad = :idannee_acad) as sujets
    ";
    $checkDataStmt = $connexion->prepare($checkDataQuery);
    $checkDataStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
    $checkDataStmt->execute();
    $dataCount = $checkDataStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalData = $dataCount['etudiants'] + $dataCount['promotions'] + $dataCount['sujets'];
    
    if ($totalData > 0) {
        $message = "Impossible de supprimer cette année académique car elle contient des données liées :\\n";
        if ($dataCount['etudiants'] > 0) $message .= "- {$dataCount['etudiants']} étudiant(s)\\n";
        if ($dataCount['promotions'] > 0) $message .= "- {$dataCount['promotions']} promotion(s)\\n";
        if ($dataCount['sujets'] > 0) $message .= "- {$dataCount['sujets']} sujet(s) de recherche\\n";
        $message .= "\\nVeuillez d'abord supprimer ou déplacer ces données.";
        
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Suppression impossible',
                text: '{$message}'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    try {
        // Suppression de l'année académique
        $deleteQuery = "DELETE FROM annee_acad WHERE idannee_acad = :idannee_acad";
        $deleteStmt = $connexion->prepare($deleteQuery);
        $deleteStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Année académique supprimée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de l\'année académique: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/annee.php");
    exit();
}
?>