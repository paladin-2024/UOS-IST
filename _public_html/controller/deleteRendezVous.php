<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $rendezVousId = intval($_GET['id']);
    $userId = $_SESSION['id'];

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier que le rendez-vous appartient à l'utilisateur connecté
        $stmtCheck = $db->prepare("
            SELECT idRendez_vous, objet, date_rendez_vous, heure_debut 
            FROM rendez_vous 
            WHERE idRendez_vous = ? AND cree_par = ?
        ");
        $stmtCheck->execute([$rendezVousId, $userId]);
        $rendezVous = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$rendezVous) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Rendez-vous non trouvé ou accès non autorisé.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
                });
            </script>";
            exit();
        }

        // Supprimer le rendez-vous
        $stmtDelete = $db->prepare("DELETE FROM rendez_vous WHERE idRendez_vous = ?");
        $result = $stmtDelete->execute([$rendezVousId]);

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Rendez-vous supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
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
                window.location.href = '../reception/rendez_vous.add';
            });
        </script>";
    }
} else {
    header("Location: ../reception/rendez_vous.add");
    exit();
}
?>