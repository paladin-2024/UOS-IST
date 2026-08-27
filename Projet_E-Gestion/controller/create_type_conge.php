<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Conge.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../login');
    exit();
}

// Initialiser le modèle
$congeModel = new Conge();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
        $dureeStandard = isset($_POST['duree_standard']) && !empty($_POST['duree_standard']) ? intval($_POST['duree_standard']) : null;
        $estCumulable = isset($_POST['est_cumulable']) ? 1 : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // Validation des données
        if (empty($designation)) {
            throw new Exception("La désignation est obligatoire.");
        }
        
        // Vérifier si un type de congé avec le même nom existe déjà
        if ($congeModel->typeCongeExistsByName($designation)) {
            throw new Exception("Un type de congé avec cette désignation existe déjà.");
        }
        
        // Créer le type de congé
        $result = $congeModel->createTypeConge($designation, $dureeStandard, $estCumulable, $description);
        
        if ($result) {
            $_SESSION['swal_success'] = [
                'title' => 'Succès',
                'text' => 'Le type de congé a été créé avec succès.',
                'icon' => 'success'
            ];
        } else {
            throw new Exception("Erreur lors de la création du type de congé.");
        }
        
        // Rediriger vers la page des types de congés
        header('Location: ../grh/conges.types');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ];
        
        // Rediriger vers la page des types de congés
        header('Location: ../grh/conges.types');
        exit();
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../grh/conges.types');
    exit();
}
