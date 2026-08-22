<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if (isset($_GET['id'])) {
    $visiteId = intval($_GET['id']);
    $userId = $_SESSION['id'];
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Vérifier si la visite existe et appartient à l'utilisateur connecté
        $stmtCheck = $db->prepare("
            SELECT idVisite, statut_visite, nom_visiteur 
            FROM visites 
            WHERE idVisite = ? AND cree_par = ?
        ");
        $stmtCheck->execute([$visiteId, $userId]);
        $visite = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$visite) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Visite non trouvée ou accès non autorisé.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
            exit();
        }
        
        // Vérifier si la visite peut être supprimée (pas en cours ou terminée)
        if (in_array($visite['statut_visite'], ['en_cours', 'terminee'])) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Suppression impossible',
                    text: 'Une visite en cours ou terminée ne peut pas être supprimée.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
            exit();
        }
        
        // Supprimer la visite
        $stmt = $db->prepare("DELETE FROM visites WHERE idVisite = ?");
        $result = $stmt->execute([$visiteId]);
        
        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Visite supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la suppression");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
    }
} else {
    header("Location: ../reception/visites.add");
    exit();
}
?>