<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idInventaire = intval($_GET['id']);
    $idUser = $_SESSION['id'];
    
    try {
        // Vérifier si l'inventaire existe et s'il est en état "En cours"
        $stmt = $db->prepare("SELECT * FROM inventaire WHERE id_inventaire = :id_inventaire");
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventaire) {
            throw new Exception("Inventaire non trouvé.");
        }
        
        if ($inventaire['etat'] != 'En cours') {
            throw new Exception("Seuls les inventaires en état 'En cours' peuvent être annulés.");
        }
        
        // Mettre à jour l'état de l'inventaire
        $stmt = $db->prepare("UPDATE inventaire 
                             SET etat = 'Annulé', id_user_validation = :id_user_validation, 
                             date_validation = NOW() 
                             WHERE id_inventaire = :id_inventaire");
        $stmt->bindParam(':id_user_validation', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'inventaire a été annulé avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/inventaire.view&id=" . $idInventaire . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.history.back();
            });
        </script>";
        exit;
    }
} else {
    // Redirection si l'ID n'est pas valide
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Identifiant d\'inventaire invalide.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../stock/inventaire.list';
        });
    </script>";
    exit;
}
