<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $cycle = isset($_POST['cycle']) ? $_POST['cycle'] : 'Tous';
    $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
    $delai_jours = isset($_POST['delai_jours']) && $_POST['delai_jours'] !== '' ? intval($_POST['delai_jours']) : null;
    $idUser = $_SESSION['id'];

    if (empty($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation est obligatoire.'
            }).then(() => {
                window.location.href = '../etudiants/documents_obligatoires';
            });
        </script>";
        exit();
    }

    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Vérifier si un document avec le même nom existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents_obligatoires WHERE designation = ?");
        $stmt->execute([$designation]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Un document obligatoire avec cette désignation existe déjà.'
                }).then(() => {
                    window.location.href = '../etudiants/documents_obligatoires';
                });
            </script>";
            exit();
        }
        
        // Insérer le nouveau document obligatoire
        $stmt = $conn->prepare("
            INSERT INTO documents_obligatoires 
            (designation, description, cycle, est_obligatoire, delai_jours, \"idUser\") 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $designation,
            $description,
            $cycle,
            $est_obligatoire,
            $delai_jours,
            $idUser
        ]);
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document obligatoire a été ajouté avec succès.'
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