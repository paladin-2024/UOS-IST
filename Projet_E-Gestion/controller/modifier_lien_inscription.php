<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : '';
    $date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : '';
    $max_inscriptions = !empty($_POST['max_inscriptions']) ? intval($_POST['max_inscriptions']) : null;
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    
    // Validation
    if (empty($titre) || empty($reference) || empty($id)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le titre et la référence sont obligatoires.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
        exit();
    }
    
    try {
        $connexion = Connexion::getInstance()->getPDO();
        
        // Vérifier l'unicité de la référence (sauf pour le lien actuel)
        $stmt = $connexion->prepare("SELECT COUNT(*) FROM liens_inscription_externe WHERE reference = ? AND id != ?");
        $stmt->execute([$reference, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cette référence existe déjà. Veuillez en choisir une autre.'
                }).then(() => {
                    window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
                });
            </script>";
            exit();
        }
        
        // Mettre à jour le lien
        $stmt = $connexion->prepare("
            UPDATE liens_inscription_externe 
            SET titre = ?, reference = ?, description = ?, date_debut = ?, 
                date_fin = ?, max_inscriptions = ?, est_actif = ?,
                date_modification = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $titre, $reference, $description, $date_debut,
            $date_fin, $max_inscriptions, $est_actif, $id
        ]);
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le lien d\\'inscription a été modifié avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification : " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>