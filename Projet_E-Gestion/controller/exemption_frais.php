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
    try {
        // Récupération des données du formulaire
        $affectation_id = intval($_POST['affectation_id']);
        $current_exemption = intval($_POST['current_exemption']);
        $remove_exemption = isset($_POST['remove_exemption']) && $_POST['remove_exemption'] == '1';
        $motif_exemption = trim($_POST['motif_exemption'] ?? '');
        $reference_decision = trim($_POST['reference_decision'] ?? '');
        
        if ($affectation_id <= 0) {
            throw new Exception('ID d\'affectation invalide');
        }
        
        // Vérifier si l'affectation existe
        $stmt = $connexion->prepare("
            SELECT * FROM affectation_frais 
            WHERE id = :id AND statut_paiement != 'Complet'
        ");
        $stmt->bindParam(':id', $affectation_id);
        $stmt->execute();
        
        $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affectation) {
            throw new Exception('Affectation non trouvée ou déjà payée');
        }
        
        // Si on supprime l'exemption
        if ($current_exemption == 1 && $remove_exemption) {
            $stmt = $connexion->prepare("
                UPDATE affectation_frais 
                SET est_exempte = 0, 
                    motif_exemption = NULL, 
                    reference_decision = NULL,
                    date_modification = NOW(),
                    idUserModification = :idUser
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $affectation_id);
            $stmt->bindParam(':idUser', $idUser);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = 'L\'exemption a été supprimée avec succès';
                $_SESSION['messageType'] = 'success';
            } else {
                throw new Exception('Erreur lors de la suppression de l\'exemption');
            }
        } 
        // Si on ajoute ou modifie une exemption
        else {
            if (empty($motif_exemption)) {
                throw new Exception('Le motif d\'exemption est requis');
            }
            
            $stmt = $connexion->prepare("
                UPDATE affectation_frais 
                SET est_exempte = 1, 
                    motif_exemption = :motif, 
                    reference_decision = :reference,
                    date_modification = NOW(),
                    idUserModification = :idUser
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $affectation_id);
            $stmt->bindParam(':motif', $motif_exemption);
            $stmt->bindParam(':reference', $reference_decision);
            $stmt->bindParam(':idUser', $idUser);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $current_exemption == 1 ? 
                    'L\'exemption a été modifiée avec succès' : 
                    'L\'exemption a été appliquée avec succès';
                $_SESSION['messageType'] = 'success';
            } else {
                throw new Exception('Erreur lors de la gestion de l\'exemption');
            }
        }
        
        // Redirection après succès
        header('Location: ../?view=finance/configuration_frais');
        exit();
        
    } catch (Exception $e) {
        // Enregistrer l'erreur dans les logs
        error_log('Erreur dans exemption_frais.php: ' . $e->getMessage());
        
        // Stocker le message d'erreur pour l'afficher
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
        
        // Redirection avec le message d'erreur
        header('Location: ../?view=finance/configuration_frais');
        exit();
    }
} else {
    // Accès direct au fichier sans soumission de formulaire
    header('Location: ../?view=finance/configuration_frais');
    exit();
}