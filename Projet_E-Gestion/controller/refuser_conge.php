<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $idDemande = isset($_POST['idDemande']) ? intval($_POST['idDemande']) : 0;
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
        $idUser = $_SESSION['id'];
        
        // Validation des données
        if ($idDemande <= 0) {
            throw new Exception("Identifiant de demande invalide.");
        }
        
        if (empty($commentaire)) {
            throw new Exception("Veuillez fournir un motif de refus.");
        }
        
        // Vérifier si la demande existe et est en attente
        $stmt = $db->prepare("SELECT * FROM demande_conge WHERE iddemande_conge = :idDemande");
        $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
        $stmt->execute();
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande) {
            throw new Exception("Demande de congé introuvable.");
        }
        
        if ($demande['statut'] !== 'En attente') {
            throw new Exception("Cette demande a déjà été traitée.");
        }
        
        // Refuser la demande de congé
        $stmt = $db->prepare("UPDATE demande_conge 
                             SET statut = 'Refusé', 
                                 commentaire_decision = :commentaire, 
                                 date_decision = NOW(), 
                                 idDecideur = :idUser 
                             WHERE iddemande_conge = :idDemande");
        
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
        $success = $stmt->execute();
        
        if ($success) {
            echo "<script>
                Swal.fire({
                    title: 'Succès',
                    text: 'La demande de congé a été refusée avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../grh/conges.list';
                    }
                });
            </script>";
            exit();
        } else {
            throw new Exception("Une erreur est survenue lors du refus de la demande.");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../grh/conges.view&id=" . $idDemande . "';
            });
        </script>";
        exit();
    }
} else {
    // Redirection si accès direct au fichier
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Accès non autorisé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../grh/conges.list';
        });
    </script>";
    exit();
}
