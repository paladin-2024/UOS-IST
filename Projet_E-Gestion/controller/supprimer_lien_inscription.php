<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID manquant.'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
        });
    </script>";
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    $connexion->beginTransaction();
    
    $id = intval($_GET['id']);
    
    // Récupérer les informations du lien avant suppression
    $stmt = $connexion->prepare("SELECT * FROM liens_inscription_externe WHERE id = ?");
    $stmt->execute([$id]);
    $lien = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lien) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Lien non trouvé.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
        exit();
    }
    
    // Supprimer les fichiers uploadés associés
    $stmt = $connexion->prepare("
        SELECT die.chemin_fichier 
        FROM documents_inscription_externe die
        JOIN inscriptions_externes ie ON die.inscription_externe_id = ie.id
        WHERE ie.lien_inscription_id = ?
    ");
    $stmt->execute([$id]);
    $fichiers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($fichiers as $fichier) {
        if (file_exists($fichier)) {
            unlink($fichier);
        }
    }
    
    // Supprimer le logo personnalisé s'il existe
    if ($lien['logo_personnalise']) {
        $logo_path = '../uploads/logos_inscription/' . $lien['logo_personnalise'];
        if (file_exists($logo_path)) {
            unlink($logo_path);
        }
    }
    
    // Supprimer les dossiers d'inscription
    $stmt = $connexion->prepare("SELECT id FROM inscriptions_externes WHERE lien_inscription_id = ?");
    $stmt->execute([$id]);
    $inscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($inscriptions as $inscription_id) {
        $dossier = '../uploads/inscriptions_externes/' . $inscription_id;
        if (is_dir($dossier)) {
            // Supprimer récursivement le dossier
            $files = array_diff(scandir($dossier), array('.', '..'));
            foreach ($files as $file) {
                unlink($dossier . '/' . $file);
            }
            rmdir($dossier);
        }
    }
    
    // La suppression en cascade se fera automatiquement grâce aux contraintes FK
    $stmt = $connexion->prepare("DELETE FROM liens_inscription_externe WHERE id = ?");
    $stmt->execute([$id]);
    
    $connexion->commit();
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: 'Le lien d\\'inscription et toutes les données associées ont été supprimés avec succès.'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
        });
    </script>";
    
} catch (Exception $e) {
    $connexion->rollBack();
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors de la suppression : " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
        });
    </script>";
}
?>