<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['id']) || !isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    header('Location: ../laboratoire/laboratoire.list');
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $idLabo = isset($_POST['idlabo']) ? intval($_POST['idlabo']) : 0;
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $localisation = isset($_POST['localisation']) ? trim($_POST['localisation']) : '';
        $responsable_id = isset($_POST['responsable_id']) ? intval($_POST['responsable_id']) : 0;
        $annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
        $ref_latitude = isset($_POST['ref_latitude']) && !empty($_POST['ref_latitude']) ? floatval($_POST['ref_latitude']) : null;
        $ref_longitude = isset($_POST['ref_longitude']) && !empty($_POST['ref_longitude']) ? floatval($_POST['ref_longitude']) : null;
        $geo_verification_active = isset($_POST['geo_verification_active']) ? 1 : 0;
        
        // Validation des données
        if (!$idLabo || !$nom || !$localisation || !$responsable_id || !$annee_acad_id) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        // Connexion à la base de données
        $db = Connexion::getInstance()->getPDO();
        
        // Vérifier si le laboratoire existe
        $query = "SELECT * FROM laboratoire WHERE idlabo = :idLabo";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':idLabo', $idLabo);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Laboratoire non trouvé.');
        }
        
        // Mettre à jour le laboratoire
        $query = "UPDATE laboratoire 
                  SET nom = :nom, 
                      description = :description, 
                      localisation = :localisation, 
                      responsable_id = :responsable_id,
                      ref_latitude = :ref_latitude,
                      ref_longitude = :ref_longitude,
                      geo_verification_active = :geo_verification_active
                  WHERE idlabo = :idLabo";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':localisation', $localisation);
        $stmt->bindParam(':responsable_id', $responsable_id);
        $stmt->bindParam(':ref_latitude', $ref_latitude);
        $stmt->bindParam(':ref_longitude', $ref_longitude);
        $stmt->bindParam(':geo_verification_active', $geo_verification_active);
        $stmt->bindParam(':idLabo', $idLabo);
        
        if ($stmt->execute()) {
            // Redirection avec message de succès
            $_SESSION['success_message'] = 'Laboratoire mis à jour avec succès.';
            header('Location: ../laboratoire/laboratoire.list');
            exit();
        } else {
            throw new Exception('Erreur lors de la mise à jour du laboratoire.');
        }
        
    } catch (Exception $e) {
        // Afficher un message d'erreur
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "',
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.history.back();
                });
            });
        </script>";
        exit();
    }
} else {
    // Redirection si accès direct au script
    header('Location: ../laboratoire/laboratoire.list');
    exit();
}
?>
