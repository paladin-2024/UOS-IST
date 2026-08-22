<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if (empty($id)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID invalide.'
            }).then(() => {
                window.location.href = '../etudiants/documents_obligatoires';
            });
        </script>";
        exit();
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Vérifier si des documents étudiants sont liés à ce document obligatoire
        $stmt = $conn->prepare("SELECT COUNT(*) FROM etudiant_documents WHERE document_obligatoire_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Ce document obligatoire ne peut pas être supprimé car il est lié à des documents existants.'
                }).then(() => {
                    window.location.href = '../etudiants/documents_obligatoires';
                });
            </script>";
            exit();
        }
        
        // Supprimer le document obligatoire
        $stmt = $conn->prepare("DELETE FROM documents_obligatoires WHERE id = ?");
        $stmt->execute([$id]);
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document obligatoire a été supprimé avec succès.'
            }).then(() => {
                window.location.href = '../etudiants/documents_obligatoires';
            });
        </script>";
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../etudiants/documents_obligatoires';
            });
        </script>";
    }
} else {
    header("Location: ../index.php");
    exit();
}