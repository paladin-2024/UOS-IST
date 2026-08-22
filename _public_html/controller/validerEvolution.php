<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $idSuivi = $_GET['id'] ?? '';
    $action = $_GET['action'] ?? '';
    $commentaire = $_GET['commentaire'] ?? '';
    $userId = $_SESSION['id'];

    // Validation des données
    if (empty($idSuivi) || empty($action) || !in_array($action, ['valider', 'rejeter'])) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres invalides.'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";
        exit();
    }

    if ($action === 'rejeter' && empty($commentaire)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le commentaire est obligatoire pour rejeter une évolution.'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier que l'évolution existe et est en attente
        $queryVerif = "SELECT statut_validation FROM suivi_enseignement_ecue WHERE id_suivi = :idSuivi";
        $stmtVerif = $db->prepare($queryVerif);
        $stmtVerif->bindParam(':idSuivi', $idSuivi, PDO::PARAM_INT);
        $stmtVerif->execute();
        
        $evolution = $stmtVerif->fetch(PDO::FETCH_ASSOC);
        
        if (!$evolution) {
            throw new Exception("Évolution non trouvée.");
        }
        
        if ($evolution['statut_validation'] !== 'En attente') {
            throw new Exception("Cette évolution a déjà été traitée.");
        }

        // Récupérer l'ID de l'agent (appariteur) connecté
        $queryAgent = "SELECT idAgent FROM t_users WHERE idUser = :userId";
        $stmtAgent = $db->prepare($queryAgent);
        $stmtAgent->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmtAgent->execute();
        $userInfo = $stmtAgent->fetch(PDO::FETCH_ASSOC);
        
        $appariteurId = $userInfo['idAgent'] ?? null;

        // Mettre à jour le statut
        $nouveauStatut = ($action === 'valider') ? 'Validé' : 'Rejeté';
        
        $queryUpdate = "UPDATE suivi_enseignement_ecue 
                        SET statut_validation = :statut,
                            date_validation = NOW(),
                            appariteur_id = :appariteurId,
                            commentaire_validation = :commentaire
                        WHERE id_suivi = :idSuivi";
        
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bindParam(':statut', $nouveauStatut);
        $stmtUpdate->bindParam(':appariteurId', $appariteurId, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':commentaire', $commentaire);
        $stmtUpdate->bindParam(':idSuivi', $idSuivi, PDO::PARAM_INT);
        
        $stmtUpdate->execute();

        $message = ($action === 'valider') ? 'Évolution validée avec succès.' : 'Évolution rejetée avec succès.';
        $icon = ($action === 'valider') ? 'success' : 'info';

        echo "<script>
            Swal.fire({
                icon: '$icon',
                title: 'Succès',
                text: '$message'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";

    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/evolution_cours");
    exit();
}
?>