<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;

    // Validation des champs requis
    if (empty($nom) || $idStructure <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../document/categorie.add';
            });
        </script>";
        exit();
    }

    try {
        // Préparation des données de la catégorie
        $categoryData = [
            'nom' => $nom,
            'description' => $description,
            'idStructure' => $idStructure,
            'date_creation' => date('Y-m-d H:i:s'),
            'idUser' => $_SESSION['id']
        ];

        // Tentative d'ajout de la catégorie
        if ($structure->addCategory($categoryData)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La catégorie a été ajoutée avec succès.'
                }).then(() => {
                    window.location.href = '../document/categorie.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout de la catégorie');
        }
    } catch (Exception $e) {
        // Gestion des erreurs
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la catégorie: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../document/categorie.add';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../document/categorie.add");
    exit();
}
?>