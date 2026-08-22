<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

// Vérifier si l'ID est fourni et valide
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiant de catégorie invalide'
        }).then(() => {
            window.location.href = '../document/categorie.add';
        });
    </script>";
    exit();
}

$categoryId = intval($_GET['id']);

try {
    

    // Tenter de supprimer la catégorie
    if ($structure->deleteCategory($categoryId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La catégorie a été supprimée avec succès'
            }).then(() => {
                window.location.href = '../document/categorie.add';
            });
        </script>";
    } else {
        throw new Exception('Erreur lors de la suppression de la catégorie');
    }
} catch (Exception $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors de la suppression : " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../document/categorie.add';
        });
    </script>";
}