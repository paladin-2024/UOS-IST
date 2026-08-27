<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Conge.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser le modèle
$congeModel = new Conge();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $idDemande = isset($_POST['idDemande']) ? intval($_POST['idDemande']) : 0;
        
        // Validation des données
        if ($idDemande <= 0) {
            throw new Exception("Identifiant de demande invalide.");
        }
        
        // Vérifier si la demande existe et est en attente
        $demande = $congeModel->getDemandeCongeById($idDemande);
        if (!$demande) {
            throw new Exception("Demande de congé non trouvée.");
        }
        
        if ($demande['statut'] != 'En attente') {
            throw new Exception("Cette demande a déjà été traitée et ne peut plus être annulée.");
        }
        
        // Vérifier si l'utilisateur est le propriétaire de la demande ou un administrateur
        $isAdmin = isset($_SESSION['idRole']) && ($_SESSION['idRole'] == 1 || $_SESSION['idRole'] == 2);
        $isOwner = isset($_SESSION['id']) && $_SESSION['id'] == $demande['id'];
        
        if (!$isAdmin && !$isOwner) {
            throw new Exception("Vous n'êtes pas autorisé à annuler cette demande.");
        }
        
        // Annuler la demande
        $result = $congeModel->annulerDemandeConge($idDemande, $_SESSION['id']);
        
        if ($result) {
            $_SESSION['swal_success'] = [
                'title' => 'Succès',
                'text' => 'La demande de congé a été annulée.',
                'icon' => 'success'
            ];
        } else {
            throw new Exception("Erreur lors de l'annulation de la demande.");
        }
        
        // Rediriger vers la page de détails de la demande
        header('Location: ../grh/conges.view&id=' . $idDemande);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ];
        
        // Rediriger vers la page précédente
        if (isset($_POST['idDemande'])) {
            header('Location: ../grh/conges.view&id=' . $_POST['idDemande']);
        } else {
            header('Location: ../grh/conges.list');
        }
        exit();
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../grh/conges.list');
    exit();
}
