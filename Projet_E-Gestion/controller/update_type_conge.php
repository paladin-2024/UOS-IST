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
        $idTypeConge = isset($_POST['idTypeConge']) ? intval($_POST['idTypeConge']) : 0;
        $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
        $dureeStandard = isset($_POST['duree_standard']) && !empty($_POST['duree_standard']) ? intval($_POST['duree_standard']) : null;
        $estCumulable = isset($_POST['est_cumulable']) ? 1 : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // Validation des données
        if ($idTypeConge <= 0) {
            throw new Exception("Identifiant de type de congé invalide.");
        }
        
        if (empty($designation)) {
            throw new Exception("La désignation est obligatoire.");
        }
        
        // Vérifier si le type de congé existe
        $typeConge = $congeModel->getTypeCongeById($idTypeConge);
        if (!$typeConge) {
            throw new Exception("Type de congé non trouvé.");
        }
        
        // Vérifier si un autre type de congé avec le même nom existe déjà
        if ($congeModel->typeCongeExistsByNameExcept($designation, $idTypeConge)) {
            throw new Exception("Un autre type de congé avec cette désignation existe déjà.");
        }
        
        // Mettre à jour le type de congé
        $result = $congeModel->updateTypeConge($idTypeConge, $designation, $dureeStandard, $estCumulable, $description);
        
        if ($result) {
            $_SESSION['swal_success'] = [
                'title' => 'Succès',
                'text' => 'Le type de congé a été mis à jour avec succès.',
                'icon' => 'success'
            ];
        } else {
            throw new Exception("Erreur lors de la mise à jour du type de congé.");
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
