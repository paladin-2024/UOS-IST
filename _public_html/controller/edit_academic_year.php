<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $connexion = Connexion::getInstance()->getPDO();
    
    $idannee_acad = isset($_POST['idannee_acad']) ? intval($_POST['idannee_acad']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';

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

    if (empty($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation est obligatoire.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérification de l'unicité de la désignation (exclure l'année en cours de modification)
    $checkQuery = "SELECT COUNT(*) FROM annee_acad WHERE designation = :designation AND idannee_acad != :idannee_acad";
    $checkStmt = $connexion->prepare($checkQuery);
    $checkStmt->bindParam(':designation', $designation);
    $checkStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cette désignation d\'année académique existe déjà.'
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

    try {
        // Mise à jour de l'année académique
        $updateQuery = "UPDATE annee_acad SET designation = :designation WHERE idannee_acad = :idannee_acad";
        $updateStmt = $connexion->prepare($updateQuery);
        $updateStmt->bindParam(':designation', $designation);
        $updateStmt->bindParam(':idannee_acad', $idannee_acad, PDO::PARAM_INT);
        $updateStmt->execute();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Année académique modifiée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification de l\'année académique: " . addslashes($e->getMessage()) . "'
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