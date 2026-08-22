<?php
session_start();
require_once '../config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_matricule'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier si l'étudiant est chef de promotion
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'ID du chef de promotion pour cet étudiant
$queryChef = "SELECT cp.id_chef 
              FROM chef_promotion cp 
              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
              WHERE e.idetudiant = :student_id 
              AND cp.annee_acad_idannee_acad = :annee_acad 
              AND cp.est_actif = 1";

$stmtChef = $connexion->prepare($queryChef);
$stmtChef->bindParam(':student_id', $_SESSION['student_id']);
$stmtChef->bindParam(':annee_acad', $_SESSION['current_year_id']);
$stmtChef->execute();

$chefPromotion = $stmtChef->fetch(PDO::FETCH_ASSOC);

if (!$chefPromotion) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Vous n\'êtes pas autorisé à effectuer cette action.']);
    exit();
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_suivi = filter_input(INPUT_POST, 'id_suivi', FILTER_VALIDATE_INT);

        if (!$id_suivi) {
            throw new Exception("ID de suivi invalide.");
        }

        // Vérifier que le suivi appartient bien à ce chef de promotion
        $queryVerif = "SELECT id_suivi 
                       FROM suivi_enseignements 
                       WHERE id_suivi = :id_suivi 
                       AND chef_promotion_id = :chef_id";

        $stmtVerif = $connexion->prepare($queryVerif);
        $stmtVerif->bindParam(':id_suivi', $id_suivi);
        $stmtVerif->bindParam(':chef_id', $chefPromotion['id_chef']);
        $stmtVerif->execute();

        if (!$stmtVerif->fetch()) {
            throw new Exception("Ce suivi n'existe pas ou ne vous appartient pas.");
        }

        // Supprimer le suivi
        $queryDelete = "DELETE FROM suivi_enseignements WHERE id_suivi = :id_suivi";
        $stmtDelete = $connexion->prepare($queryDelete);
        $stmtDelete->bindParam(':id_suivi', $id_suivi);

        if ($stmtDelete->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Suivi supprimé avec succès.']);
        } else {
            throw new Exception("Erreur lors de la suppression.");
        }

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Méthode non autorisée']);
}
?>