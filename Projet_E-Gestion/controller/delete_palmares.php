<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $idPalmares = intval($_GET['id']);
    
    // Récupérer l'ID de l'utilisateur connecté
    $idUser = $_SESSION['id'] ?? 0;
    
    if ($idPalmares <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Identifiant de palmarès invalide.'
            }).then(() => {
                window.location.href = '../?view=academique/palmares';
            });
        </script>";
        exit();
    }
    
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    try {
        // Récupérer le fichier scanné pour suppression
        $stmt = $pdo->prepare("SELECT fichier_scanne, designation FROM palmares_archive WHERE id_palmares = ?");
        $stmt->execute([$idPalmares]);
        $palmares = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$palmares) {
            throw new Exception("Le palmarès spécifié n'existe pas.");
        }
        
        // Supprimer le fichier s'il existe
        if (!empty($palmares['fichier_scanne'])) {
            $cheminFichier = dirname(__DIR__) . '/' . $palmares['fichier_scanne'];
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }
        }
        
        // Enregistrer l'action dans l'historique avant de supprimer
        $query = "INSERT INTO palmares_historique 
                 (id_palmares, action, details, \"idUser\") 
                 VALUES (?, 'Suppression', ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $idPalmares,
            "Suppression du palmarès: " . $palmares['designation'],
            $idUser
        ]);
        
        // Supprimer les étudiants associés
        $stmt = $pdo->prepare("DELETE FROM palmares_etudiant WHERE id_palmares = ?");
        $stmt->execute([$idPalmares]);
        
        // Supprimer le palmarès
        $stmt = $pdo->prepare("DELETE FROM palmares_archive WHERE id_palmares = ?");
        $stmt->execute([$idPalmares]);
        
        $pdo->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le palmarès a été supprimé avec succès.'
            }).then(() => {
                window.location.href = '../?view=academique/palmares';
            });
        </script>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la suppression du palmarès: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../?view=academique/palmares';
            });
        </script>";
    }
    
    exit();
} else {
    // Redirection si accès direct au fichier
    header("Location: ../?view=academique/palmares");
    exit();
}
?>