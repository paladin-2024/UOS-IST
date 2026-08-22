<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Conge.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'rh')) {
    header('Location: ../login');
    exit();
}

// Initialiser le modèle
$congeModel = new Conge();

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    try {
        $idTypeConge = intval($_GET['id']);
        
        // Vérifier si le type de congé existe
        $typeConge = $congeModel->getTypeCongeById($idTypeConge);
        if (!$typeConge) {
            throw new Exception("Type de congé non trouvé.");
        }
        
        // Vérifier si le type de congé est utilisé dans des demandes
        if ($congeModel->typeCongeIsUsed($idTypeConge)) {
            throw new Exception("Ce type de congé ne peut pas être supprimé car il est utilisé dans des demandes de congé.");
        }
        
        // Supprimer le type de congé
        $result = $congeModel->deleteTypeConge($idTypeConge);
        
        if ($result) {
            $_SESSION['swal_success'] = [
                'title' => 'Succès',
                'text' => 'Le type de congé a été supprimé avec succès.',
                'icon' => 'success'
            ];
        } else {
            throw new Exception("Erreur lors de la suppression du type de congé.");
        }
        
    } catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ];
    }
}

// Rediriger vers la page des types de congés
header('Location: ../grh/conges.types');
exit();
