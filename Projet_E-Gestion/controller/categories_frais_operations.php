<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialisation
$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    try {
        switch ($action) {
            case 'ajouter':
                // Récupération des données du formulaire
                $designation = trim($_POST['designation']);
                $description = trim($_POST['description'] ?? '');
                $compte_comptable = trim($_POST['compte_comptable'] ?? '');
                $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
                $est_echelonnable = isset($_POST['est_echelonnable']) ? 1 : 0;
                $est_remboursable = isset($_POST['est_remboursable']) ? 1 : 0;
                
                // Validation des données
                if (empty($designation)) {
                    throw new Exception('La désignation est obligatoire');
                }
                
                // Vérifier si la catégorie existe déjà
                $stmt = $connexion->prepare("SELECT id FROM categories_frais WHERE designation = :designation");
                $stmt->bindParam(':designation', $designation);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Une catégorie avec cette désignation existe déjà');
                }
                
                // Insertion de la nouvelle catégorie
                $stmt = $connexion->prepare("
                    INSERT INTO categories_frais 
                    (designation, description, est_obligatoire, est_echelonnable, est_remboursable, compte_comptable, idUser) 
                    VALUES 
                    (:designation, :description, :est_obligatoire, :est_echelonnable, :est_remboursable, :compte_comptable, :idUser)
                ");
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':est_obligatoire', $est_obligatoire);
                $stmt->bindParam(':est_echelonnable', $est_echelonnable);
                $stmt->bindParam(':est_remboursable', $est_remboursable);
                $stmt->bindParam(':compte_comptable', $compte_comptable);
                $stmt->bindParam(':idUser', $idUser);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'La catégorie de frais a été ajoutée avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de l\'ajout de la catégorie');
                }
                
                break;
                
            case 'modifier':
                // Récupération des données du formulaire
                $id = intval($_POST['id']);
                $designation = trim($_POST['designation']);
                $description = trim($_POST['description'] ?? '');
                $compte_comptable = trim($_POST['compte_comptable'] ?? '');
                $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
                $est_echelonnable = isset($_POST['est_echelonnable']) ? 1 : 0;
                $est_remboursable = isset($_POST['est_remboursable']) ? 1 : 0;
                
                // Validation des données
                if (empty($designation)) {
                    throw new Exception('La désignation est obligatoire');
                }
                
                if ($id <= 0) {
                    throw new Exception('ID de catégorie invalide');
                }
                
                // Vérifier si la catégorie existe avec cette désignation (sauf pour la catégorie en cours)
                $stmt = $connexion->prepare("SELECT id FROM categories_frais WHERE designation = :designation AND id <> :id");
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Une autre catégorie avec cette désignation existe déjà');
                }
                
                // Mise à jour de la catégorie
                $stmt = $connexion->prepare("
                    UPDATE categories_frais 
                    SET designation = :designation,
                        description = :description,
                        est_obligatoire = :est_obligatoire,
                        est_echelonnable = :est_echelonnable,
                        est_remboursable = :est_remboursable,
                        compte_comptable = :compte_comptable
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':est_obligatoire', $est_obligatoire);
                $stmt->bindParam(':est_echelonnable', $est_echelonnable);
                $stmt->bindParam(':est_remboursable', $est_remboursable);
                $stmt->bindParam(':compte_comptable', $compte_comptable);
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'La catégorie de frais a été mise à jour avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de la mise à jour de la catégorie');
                }
                
                break;
                
            case 'supprimer':
                // Récupération de l'ID
                $id = intval($_POST['id']);
                
                if ($id <= 0) {
                    throw new Exception('ID de catégorie invalide');
                }
                
                // Vérifier si des frais sont associés à cette catégorie
                $stmt = $connexion->prepare("SELECT COUNT(*) as nb_frais FROM frais WHERE categorie_id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['nb_frais'] > 0) {
                    // Option 1: Empêcher la suppression
                    // throw new Exception("Impossible de supprimer cette catégorie car elle est utilisée par {$result['nb_frais']} frais");
                    
                    // Option 2: Supprimer d'abord les frais associés
                    $stmt = $connexion->prepare("DELETE FROM frais WHERE categorie_id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                }
                
                // Suppression de la catégorie
                $stmt = $connexion->prepare("DELETE FROM categories_frais WHERE id = :id");
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'La catégorie de frais a été supprimée avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de la suppression de la catégorie');
                }
                
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
    }
    
    // Redirection vers la page des catégories
    header('Location: ../finance/categories_frais');
    exit();
} else {
    // Si accès direct au script sans POST
    header('Location: ../finance/categories_frais');
    exit();
}