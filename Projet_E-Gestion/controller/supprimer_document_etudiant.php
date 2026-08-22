<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier si l'ID du document et l'ID de l'étudiant sont spécifiés
if (!isset($_GET['id']) || !isset($_GET['idetudiant'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit;
}

$documentId = intval($_GET['id']);
$idEtudiant = intval($_GET['idetudiant']);

if ($documentId <= 0 || $idEtudiant <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiants invalides.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit;
}

try {
    // Connexion à la base de données
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer le chemin du fichier avant de supprimer l'enregistrement
    $stmt = $connexion->prepare("SELECT chemin_fichier FROM etudiant_documents WHERE id = ? AND idetudiant = ?");
    $stmt->execute([$documentId, $idEtudiant]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Document non trouvé.'
            }).then(() => {
                window.location.href = '../enseignement/fiche_scolarite&id=" . $idEtudiant . "';
            });
        </script>";
        exit;
    }
    
    // Supprimer l'enregistrement de la base de données
    $stmt = $connexion->prepare("DELETE FROM etudiant_documents WHERE id = ? AND idetudiant = ?");
    $result = $stmt->execute([$documentId, $idEtudiant]);
    
    if ($result) {
        // Supprimer le fichier physique
        $filePath = dirname(__DIR__) . '/' . $document['chemin_fichier'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document a été supprimé avec succès.'
            }).then(() => {
                window.location.href = '../enseignement/fiche_scolarite&id=" . $idEtudiant . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression du document.'
            }).then(() => {
                window.location.href = '../enseignement/fiche_scolarite&id=" . $idEtudiant . "';
            });
        </script>";
    }
} catch (PDOException $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur de base de données: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../enseignement/fiche_scolarite&id=" . $idEtudiant . "';
        });
    </script>";
}