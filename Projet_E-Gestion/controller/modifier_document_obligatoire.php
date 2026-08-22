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
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $cycle = isset($_POST['cycle']) ? $_POST['cycle'] : 'Tous';
    $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
    $delai_jours = isset($_POST['delai_jours']) && $_POST['delai_jours'] !== '' ? intval($_POST['delai_jours']) : null;
    $idUser = $_SESSION['id'];

    if (empty($id) || empty($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID et désignation sont obligatoires.'
            }).then(() => {
                window.location.href = '../etudiants/documents_obligatoires';
            });
        </script>";
        exit();
    }

    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Vérifier si un document avec le même nom existe déjà (sauf le document actuel)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents_obligatoires WHERE designation = ? AND id != ?");
        $stmt->execute([$designation, $id]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Un autre document obligatoire avec cette désignation existe déjà.'
                }).then(() => {
                    window.location.href = '../etudiants/documents_obligatoires';
                });
            </script>";
            exit();
        }
        
        // Mettre à jour le document obligatoire
        $stmt = $conn->prepare("
            UPDATE documents_obligatoires 
            SET designation = ?, description = ?, cycle = ?, 
                est_obligatoire = ?, delai_jours = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $designation,
            $description,
            $cycle,
            $est_obligatoire,
            $delai_jours,
            $id
        ]);
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document obligatoire a été mis à jour avec succès.'
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